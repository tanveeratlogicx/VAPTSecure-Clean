<?php

/**
 * VAPTSECURE_IIS_Driver
 * Handles enforcement of rules for IIS via web.config XML injection.
 */

if (!defined('ABSPATH')) { exit;
}

class VAPTSECURE_IIS_Driver implements VAPTSECURE_Driver_Interface
{
    /**
     * Generates a list of valid IIS XML nodes based on the provided data and schema.
     */
    public static function generate_rules($data, $schema)
    {
        // 🛡️ TWO-WAY DEACTIVATION (v3.6.19)
        $is_enabled = true;
        if (isset($data['feat_enabled'])) {
            $is_enabled = (bool) filter_var($data['feat_enabled'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($data['enabled'])) {
            $is_enabled = (bool) filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($data['prot_enabled'])) {
            $is_enabled = (bool) filter_var($data['prot_enabled'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $risk_key = $schema['risk_id'] ?? $schema['id'] ?? $schema['feature_key'] ?? '';
            $risk_suffix = str_replace('-', '_', strtolower($risk_key));
            $auto_key = "vapt_risk_{$risk_suffix}_enabled";
            if (isset($data[$auto_key])) {
                $is_enabled = (bool) filter_var($data[$auto_key], FILTER_VALIDATE_BOOLEAN);
            }
        }
        if (!$is_enabled) {
            return array();
        }

        $enf_config = isset($schema['enforcement']) ? $schema['enforcement'] : array();
        $rules = array();
        $mappings = isset($enf_config['mappings']) ? $enf_config['mappings'] : array();

        foreach ($mappings as $key => $directive) {
            if (!empty($data[$key])) {
                // [v1.4.1] Support for v1.1/v2.0 rich mappings (Platform Objects)
                $directive = VAPTSECURE_Enforcer::extract_code_from_mapping($directive, 'iis');
                if (empty($directive)) { continue;
                }

                $iis_rule = self::translate_to_iis($key, $directive);
                if ($iis_rule) {
                    $rules[] = $iis_rule;
                }
            }
        }

        if (!empty($rules)) {
            $feature_key = isset($schema['feature_key']) ? $schema['feature_key'] : 'unknown';
            $title = isset($schema['title']) ? $schema['title'] : '';

            $wrapped_rules = array();
            $wrapped_rules[] = "<!-- BEGIN VAPT $feature_key" . ($title ? ": $title" : "") . " -->";
            $wrapped_rules = array_merge($wrapped_rules, $rules);
            $wrapped_rules[] = "<!-- VAPT-Feature: $feature_key -->"; // Marker for verify
            $wrapped_rules[] = "<!-- END VAPT $feature_key -->";

            return $wrapped_rules;
        }

        return array();
    }

    /**
     * 🔍 VERIFICATION LOGIC (v3.6.19)
     */
    public static function verify($key, $impl_data, $schema)
    {
        $config_path = ABSPATH . 'web.config';
        if (!file_exists($config_path)) {
            return false;
        }

        $content = file_get_contents($config_path);
        return (strpos($content, "VAPT-Feature: $key") !== false);
    }

    private static function translate_to_iis($key, $directive)
    {
        // 1. Headers -> <customHeaders>
        if (strpos($directive, 'Header set') !== false) {
            $clean = str_replace(['Header set ', '"'], ['', ''], $directive);
            $parts = explode(' ', $clean, 2);
            if (count($parts) == 2) {
                return '<add name="' . $parts[0] . '" value="' . $parts[1] . '" />';
            }
        }

        // 2. Directory Browsing -> <directoryBrowse enabled="false" />
        if (strpos($directive, 'Options -Indexes') !== false) {
            return '<directoryBrowse enabled="false" />';
        }

        // 3. Block XMLRPC -> <requestFiltering><hiddenSegments>...
        if ($key === 'block_xmlrpc') {
            return '<hiddenSegments><add segment="xmlrpc.php" /></hiddenSegments>';
        }

        return null;
    }

    /**
     * Writes batch to web.config
     * WARNING: XML manipulation is fragile. We use simple regex/string replacements for safety.
     *
     * @param array  $rules  Flat array of all IIS rules to write
     * @param string $target Target location identifier (not used for IIS driver)
     * @return bool Success status
     */
    public static function write_batch($rules, $target = 'root')
    {
        $all_rules_array = $rules;
        $config_path = ABSPATH . 'web.config';

        // Structure:
        // <configuration>
        //   <system.webServer>
        //      <httpProtocol><customHeaders>...
        //      <security><requestFiltering>...

        // For MVP, we will simplify: We will only support Custom Headers injection for now to demonstrate capability.
        // Full XML parsing is risky without DOMDocument validation.

        if (!file_exists($config_path)) {
            // Create basic web.config?
            // Skipping auto-creation to avoid breaking existing IIS setups.
            return false;
        }

        $content = file_get_contents($config_path);
        if ($content === false) {
            return false;
        }

        $start_marker = '<!-- BEGIN VAPT SECURITY RULES';
        $end_marker = '<!-- END VAPT SECURITY RULES -->';
        $new_content = $content;

        while (($start_pos = strpos($new_content, $start_marker)) !== false &&
               ($end_pos = strpos($new_content, $end_marker)) !== false &&
               $end_pos > $start_pos) {
            $before = substr($new_content, 0, $start_pos);
            $after = substr($new_content, $end_pos + strlen($end_marker));
            $new_content = $before . $after;
        }

        if (!empty($all_rules_array)) {
            $feature_key = 'unknown';
            foreach ($all_rules_array as $rule) {
                if (preg_match('/VAPT\s+([A-Z0-9\-_]+)/i', (string) $rule, $m)) {
                    $feature_key = $m[1];
                    break;
                }
            }

            $block = array();
            $block[] = $start_marker . ' -->';
            foreach ($all_rules_array as $rule) {
                $block[] = $rule;
            }
            $block[] = "<!-- VAPT-Feature: $feature_key -->";
            $block[] = $end_marker;

            $insert_pos = strripos($new_content, '</system.webServer>');
            if ($insert_pos !== false) {
                $new_content = substr($new_content, 0, $insert_pos) . implode("\n", $block) . "\n" . substr($new_content, $insert_pos);
            } else {
                $insert_pos = strripos($new_content, '</configuration>');
                if ($insert_pos !== false) {
                    $new_content = substr($new_content, 0, $insert_pos) . implode("\n", $block) . "\n" . substr($new_content, $insert_pos);
                } else {
                    $new_content = trim($new_content) . "\n" . implode("\n", $block) . "\n";
                }
            }
        }

        $new_content = preg_replace("/\n{3,}/", "\n\n", $new_content);
        if ($new_content !== $content) {
            return @file_put_contents($config_path, $new_content) !== false;
        }

        return true;
    }

    /**
     * Cleans/removes all VAPT rules from web.config.
     *
     * @param string $target Target location (unused for IIS driver, kept for interface compatibility)
     * @return bool Success status
     */
    public static function clean($target = 'root')
    {
        $config_path = ABSPATH . 'web.config';
        if (!file_exists($config_path)) {
            return true;
        }
        if (!is_writable($config_path)) {
            return false;
        }

        $content = file_get_contents($config_path);
        if ($content === false) {
            return false;
        }

        $content = preg_replace('/<!-- BEGIN VAPT SECURITY RULES -->.*?<!-- END VAPT SECURITY RULES -->/s', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return file_put_contents($config_path, trim($content) . "\n") !== false;
    }
}
