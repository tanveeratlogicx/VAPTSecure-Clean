<?php

/**
 * VAPTSECURE_PHP_Deployer: Universal Hook-based Fallback
 */

if (!defined('ABSPATH')) { exit;
}

class VAPTSECURE_PHP_Deployer implements VAPTSECURE_Driver_Interface
{
    /**
     * Static wrapper for generate_rules - delegates to php driver
     */
    public static function generate_rules($impl_data, $schema)
    {
        return VAPTSECURE_PHP_Driver::generate_rules($impl_data, $schema);
    }

    /**
     * Static wrapper for write_batch - delegates to php driver
     */
    public static function write_batch($rules, $target = 'root')
    {
        return VAPTSECURE_PHP_Driver::write_batch($rules);
    }

    /**
     * Static wrapper for clean - delegates to php driver
     */
    public static function clean($target = 'root')
    {
        return VAPTSECURE_PHP_Driver::clean();
    }

    // Instance methods below...

    public function can_deploy()
    {
        return true; // Universal fallback
    }

    public function deploy($feature_key, $implementation, $is_enabled = true)
    {
        $path = VAPTSECURE_PATH . 'vapt-functions.php';

        $rules = $this->normalize_rules($implementation);

        $start_marker = "// BEGIN VAPT FEATURE: {$feature_key}";
        $end_marker = "// END VAPT FEATURE: {$feature_key}";

        if (!file_exists($path)) {
            if (empty($rules) || !$is_enabled) {
                return true;
            }
            $dir = dirname($path);
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            @file_put_contents($path, "<?php\n\n/**\n * VAPTSecure Clean: Centralized PHP Protections\n */\n\nif (!defined('ABSPATH')) exit;\n\n");
        }

        $content = file_get_contents($path);

        // Remove old block for this feature
        $pattern = "/" . preg_quote($start_marker, '/') . ".*?" . preg_quote($end_marker, '/') . "/s";
        $content = preg_replace($pattern, '', $content);

        if ($is_enabled && !empty($rules)) {
            $new_block = "\n" . $start_marker . "\n" . $rules . "\n" . $end_marker . "\n";
            $content = rtrim($content) . $new_block;
        }

        $content = preg_replace("/(\r?\n){3,}/", "$1$1", $content);
        @file_put_contents($path, $content);

        return ['status' => 'deployed', 'platform' => 'php_functions'];
    }

    public function undeploy($feature_key)
    {
        return $this->deploy($feature_key, '', false);
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
            return implode("\n\n", $input);
        }
        return '';
    }
}
