<?php

/**
 * VAPTSECURE_Config_Deployer: Adaptive wp-config.php Deployment
 */

if (!defined('ABSPATH')) { exit;
}

class VAPTSECURE_Config_Deployer implements VAPTSECURE_Driver_Interface
{
    /**
     * Static wrapper for generate_rules - delegates to config driver
     */
    public static function generate_rules($impl_data, $schema)
    {
        return VAPTSECURE_Config_Driver::generate_rules($impl_data, $schema);
    }

    /**
     * Static wrapper for write_batch - delegates to config driver
     */
    public static function write_batch($rules, $target = 'root')
    {
        return VAPTSECURE_Config_Driver::write_batch($rules);
    }

    /**
     * Static wrapper for clean - delegates to config driver
     */
    public static function clean($target = 'root')
    {
        return VAPTSECURE_Config_Driver::clean();
    }

    // Instance methods below...
    public function can_deploy()
    {
        $paths = [];
        if (defined('ABSPATH')) {
            $base = rtrim(ABSPATH, DIRECTORY_SEPARATOR);
            
            // Standard location
            $paths[] = $base . DIRECTORY_SEPARATOR . 'wp-config.php';
            
            // One level above ABSPATH (WP standard for security)
            $paths[] = dirname($base) . DIRECTORY_SEPARATOR . 'wp-config.php';
            
            // [v3.13.31] Special: Home URL detection for subdirectory installs
            if (function_exists('get_home_path')) {
                $home = rtrim(get_home_path(), DIRECTORY_SEPARATOR);
                if (!empty($home) && !in_array($home . DIRECTORY_SEPARATOR . 'wp-config.php', $paths)) {
                    $paths[] = $home . DIRECTORY_SEPARATOR . 'wp-config.php';
                    $paths[] = dirname($home) . DIRECTORY_SEPARATOR . 'wp-config.php';
                }
            }
        }

        foreach (array_unique($paths) as $path) {
            if (@file_exists($path) && @is_writable($path)) {
                return true;
            }
        }

        return false;
    }

    public function deploy($feature_key, $implementation, $is_enabled = true)
    {
        $wp_config_path = $this->resolve_wp_config_path();
        if (!$wp_config_path) {
            return new WP_Error('vapt_deploy_failed', 'wp-config.php not found or not writable.');
        }

        $rules = $this->normalize_rules($implementation);

        $content = file_get_contents($wp_config_path);
        $start_marker = "// BEGIN VAPT FEATURE: {$feature_key}";
        $end_marker = "// END VAPT FEATURE: {$feature_key}";

        // Remove old block for this feature
        $pattern = "/" . preg_quote($start_marker, '/') . ".*?" . preg_quote($end_marker, '/') . "/s";
        $content = preg_replace($pattern, '', $content);

        if ($is_enabled && !empty($rules)) {
            $new_block = "\n" . $start_marker . "\n" . $rules . "\n" . $end_marker . "\n";

            $insert_marker = "That's all, stop editing";
            if (stripos($content, $insert_marker) !== false) {
                $content = str_ireplace($insert_marker, $new_block . $insert_marker, $content);
            } else {
                $content .= $new_block;
            }
        }

        // Clean up extra blank lines
        $content = preg_replace("/\n\s*\n(\s*\n)+/", "\n\n", $content);

        $original = file_get_contents($wp_config_path);
        if (trim($content) !== trim($original)) {
            @copy($wp_config_path, $wp_config_path . '.bak');
            file_put_contents($wp_config_path, trim($content) . "\n");
        }

        return ['status' => 'deployed', 'platform' => 'wp_config'];
    }

    public function undeploy($feature_key)
    {
        $wp_config_path = $this->resolve_wp_config_path();
        if (!$wp_config_path) {
            return true;
        }

        $content = file_get_contents($wp_config_path);
        $start_marker = "// BEGIN VAPT FEATURE: {$feature_key}";
        $end_marker   = "// END VAPT FEATURE: {$feature_key}";

        $pattern = "/" . preg_quote($start_marker, '/') . ".*?" . preg_quote($end_marker, '/') . "/s";
        $new_content = preg_replace($pattern, '', $content);

        if ($new_content !== $content) {
            $new_content = preg_replace("/\n\s*\n(\s*\n)+/", "\n\n", $new_content);
            @copy($wp_config_path, $wp_config_path . '.bak');
            file_put_contents($wp_config_path, trim($new_content) . "\n");
            // error_log("VAPT CONFIG DEPLOYER: Removed block for {$feature_key} from wp-config.php");
            return true;
        }

        // error_log("VAPT CONFIG DEPLOYER: No block found for {$feature_key} in wp-config.php");
        return true;
    }

    private function resolve_wp_config_path()
    {
        $paths = [];
        if (defined('ABSPATH')) {
            $base = rtrim(ABSPATH, DIRECTORY_SEPARATOR);
            $paths[] = $base . DIRECTORY_SEPARATOR . 'wp-config.php';
            $paths[] = dirname($base) . DIRECTORY_SEPARATOR . 'wp-config.php';
            if (function_exists('get_home_path')) {
                $home = rtrim(get_home_path(), DIRECTORY_SEPARATOR);
                if (!empty($home) && !in_array($home . DIRECTORY_SEPARATOR . 'wp-config.php', $paths)) {
                    $paths[] = $home . DIRECTORY_SEPARATOR . 'wp-config.php';
                    $paths[] = dirname($home) . DIRECTORY_SEPARATOR . 'wp-config.php';
                }
            }
        }
        foreach (array_unique($paths) as $path) {
            if (@is_file($path) && @is_readable($path) && @is_writable($path)) {
                return $path;
            }
        }
        return null;
    }

    private function normalize_rules($input)
    {
        if (is_string($input)) {
            return $input;
        }
        if (is_array($input)) {
            if (isset($input['code'])) {
                $code = is_array($input['code']) ? implode("\n", $input['code']) : $input['code'];
                return $code;
            }
            if (isset($input['rules'])) {
                $rules = is_array($input['rules']) ? implode("\n", $input['rules']) : $input['rules'];
                return $rules;
            }
            return implode("\n", $input);
        }
        return '';
    }
}
