<?php

/**
 * Build Generator for VAPTSecure Clean
 */

if (! defined('ABSPATH')) {
    exit;
}

class VAPTSECURE_Build
{
    private static function safe_preg_replace($pattern, $replacement, $subject, $limit = -1)
    {
        $result = preg_replace($pattern, $replacement, $subject, $limit);
        if ($result === null) {
            return $subject;
        }
        return $result;
    }

    private static function filter_data_file_for_release($source_path, $dest_path, $allowed_feature_keys = [])
    {
        $allowed = array();
        if (is_array($allowed_feature_keys)) {
            foreach ($allowed_feature_keys as $k) {
                $k = strtoupper(trim((string) $k));
                if ($k !== '') {
                    $allowed[$k] = true;
                }
            }
        }

        if (empty($allowed) || !file_exists($source_path)) {
            return copy($source_path, $dest_path);
        }

        // Fetch Release status map from DB
        global $wpdb;
        $status_table = $wpdb->prefix . 'vaptsecure_feature_status';
        $release_features = $wpdb->get_col("SELECT feature_key FROM {$status_table} WHERE status = 'Release'");
        $release_map = array();
        if (is_array($release_features)) {
            foreach ($release_features as $rf) {
                $release_map[strtoupper(trim((string) $rf))] = true;
            }
        }

        $raw = file_get_contents($source_path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return copy($source_path, $dest_path);
        }

        // Helper to check if feature is both allowed AND in Release state
        $is_released_and_allowed = function($key) use ($allowed, $release_map) {
            $key = strtoupper(trim((string) $key));
            return isset($allowed[$key]) && isset($release_map[$key]);
        };

        if (isset($data['risk_interfaces']) && is_array($data['risk_interfaces'])) {
            $filtered = array();
            foreach ($data['risk_interfaces'] as $risk_key => $item) {
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $risk_key)));
                if ($candidate !== '' && $is_released_and_allowed($candidate)) {
                    $filtered[$risk_key] = $item;
                }
            }
            $data['risk_interfaces'] = $filtered;
        } elseif (isset($data['risk_catalog']) && is_array($data['risk_catalog'])) {
            $data['risk_catalog'] = array_values(array_filter($data['risk_catalog'], function ($item) use ($is_released_and_allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && $is_released_and_allowed($candidate);
            }));
        } elseif (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = array_values(array_filter($data['features'], function ($item) use ($is_released_and_allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && $is_released_and_allowed($candidate);
            }));
        } elseif (isset($data['wordpress_vapt']) && is_array($data['wordpress_vapt'])) {
            $data['wordpress_vapt'] = array_values(array_filter($data['wordpress_vapt'], function ($item) use ($is_released_and_allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && $is_released_and_allowed($candidate);
            }));
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return copy($source_path, $dest_path);
        }
        return file_put_contents($dest_path, $json) !== false;
    }

    public static function get_feature_meta_snapshot_public($feature_keys = [])
    {
        return self::get_feature_meta_snapshot($feature_keys);
    }

    private static function get_feature_meta_snapshot($feature_keys = [])
    {
        if (!function_exists('sanitize_text_field')) {
            return array();
        }
        if (!is_array($feature_keys) || empty($feature_keys)) {
            return array();
        }

        $normalized = array_values(array_unique(array_filter(array_map(function ($k) {
            $k = strtoupper(trim((string) $k));
            return $k !== '' ? $k : null;
        }, $feature_keys))));

        if (empty($normalized)) {
            return array();
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return array();
        }

        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $placeholders = implode(',', array_fill(0, count($normalized), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE feature_key IN ({$placeholders})", $normalized), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            if (!isset($row['feature_key'])) {
                continue;
            }
            $key = strtoupper(trim((string) $row['feature_key']));
            if ($key === '') {
                continue;
            }

            $out[$key] = array(
                'generated_schema_b64' => isset($row['generated_schema']) && is_string($row['generated_schema']) ? base64_encode($row['generated_schema']) : '',
                'implementation_data_b64' => isset($row['implementation_data']) && is_string($row['implementation_data']) ? base64_encode($row['implementation_data']) : '',
                'override_schema_b64' => isset($row['override_schema']) && is_string($row['override_schema']) ? base64_encode($row['override_schema']) : '',
                'override_implementation_data_b64' => isset($row['override_implementation_data']) && is_string($row['override_implementation_data']) ? base64_encode($row['override_implementation_data']) : '',
                'include_test_method' => isset($row['include_test_method']) ? (int) $row['include_test_method'] : 0,
                'include_verification' => isset($row['include_verification']) ? (int) $row['include_verification'] : 0,
                'include_verification_engine' => isset($row['include_verification_engine']) ? (int) $row['include_verification_engine'] : 0,
                'include_verification_guidance' => isset($row['include_verification_guidance']) ? (int) $row['include_verification_guidance'] : 1,
                'include_manual_protocol' => isset($row['include_manual_protocol']) ? (int) $row['include_manual_protocol'] : 1,
                'include_operational_notes' => isset($row['include_operational_notes']) ? (int) $row['include_operational_notes'] : 1,
                'wireframe_url' => isset($row['wireframe_url']) ? (string) $row['wireframe_url'] : '',
                'dev_instruct' => isset($row['dev_instruct']) ? (string) $row['dev_instruct'] : '',
                'is_adaptive_deployment' => isset($row['is_adaptive_deployment']) ? (int) $row['is_adaptive_deployment'] : 0,
                'active_enforcer' => isset($row['active_enforcer']) ? (string) $row['active_enforcer'] : '',
                'is_enabled' => isset($row['is_enabled']) ? (int) $row['is_enabled'] : 0,
                'is_enforced' => isset($row['is_enforced']) ? (int) $row['is_enforced'] : 0,
            );
        }

        return $out;
    }

    /**
     * Generate a build ZIP for a specific domain
     */
    public static function generate($data)
    {
        $domain = sanitize_text_field($data['domain']);
        $features = isset($data['features']) ? $data['features'] : [];
        $version = sanitize_text_field($data['version']);
        $white_label = $data['white_label'];
        $generate_type = isset($data['generate_type']) ? $data['generate_type'] : 'full_build';
        $license_type = isset($data['license_type']) ? sanitize_text_field($data['license_type']) : 'standard';
        $security_alert_email = isset($data['security_alert_email']) ? sanitize_email((string) $data['security_alert_email']) : '';
        $is_universal_domain = ($domain === '*') || (is_string($domain) && strpos($domain, '__universal__:') === 0);
        $domain_for_files = $is_universal_domain ? 'universal' : $domain;
        
        // [FIX v3.2.3] Get the is_wildcard flag from the build request data
        $is_wildcard_flag = isset($data['is_wildcard']) && ($data['is_wildcard'] === true || $data['is_wildcard'] === 'true' || $data['is_wildcard'] === 1 || $data['is_wildcard'] === '1');

        // 1. Setup Build Paths
        $upload_dir = wp_upload_dir();
        $base_storage_dir = $upload_dir['basedir'] . '/VAPT-Builds'; // Custom Storage Path

        // Ensure storage directory exists
        if (!file_exists($base_storage_dir)) {
            wp_mkdir_p($base_storage_dir);
            // Secure the directory
            file_put_contents($base_storage_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($base_storage_dir . '/.htaccess', 'Options -Indexes');
        }

        $build_slug = sanitize_title($domain_for_files . '-' . $version);
        $build_dir = $base_storage_dir . '/' . $domain_for_files . '/' . $version;
        wp_mkdir_p($build_dir);

        // Temp dir for assembly
        $temp_dir = get_temp_dir() . 'vapt-build-' . time() . '-' . wp_generate_password(8, false);
        wp_mkdir_p($temp_dir);

        $plugin_slug = sanitize_title($white_label['text_domain'] ?: $white_label['name']);
        $plugin_dir = $temp_dir . '/' . $plugin_slug;
        wp_mkdir_p($plugin_dir);

        $include_config = !isset($data['include_config']) || filter_var($data['include_config'], FILTER_VALIDATE_BOOLEAN);
        $include_data = isset($data['include_data']) && filter_var($data['include_data'], FILTER_VALIDATE_BOOLEAN);

        // 2. Output Config Content (Generated)
        // [FIX v2.4.11] Always identify the active data file so the UI works in generated builds
        $active_data_file_name = $include_data ? get_option('vaptsecure_active_feature_file', 'interface_schema_v2.0.json') : null;
        
        $license_scope = isset($data['license_scope']) ? $data['license_scope'] : 'single';
        $domain_limit = isset($data['installation_limit']) ? intval($data['installation_limit']) : 1;
        $restrict_features = isset($data['restrict_features']) ? (bool) $data['restrict_features'] : false;
        $require_wp = isset($data['require_wp']) ? sanitize_text_field($data['require_wp']) : '6.0';
        $require_php = isset($data['require_php']) ? sanitize_text_field($data['require_php']) : '7.4.33';

        // Get snapshot of feature metadata (configs, schemas, etc)
        $feature_meta_snapshot = array();
        if ($include_config) {
            $feature_meta_snapshot = self::get_feature_meta_snapshot($features);
        }

        $config_content = self::generate_config_content(
            $domain, $version, $features, $active_data_file_name, 
            $license_type, $license_scope, $domain_limit, 
            $restrict_features, $security_alert_email, 
            $feature_meta_snapshot, $is_wildcard_flag,
            $require_wp, $require_php
        );  // If Config Only -> Save and ZIP just that
        if ($generate_type === 'config_only') {
            $config_filename = "vapt-{$domain_for_files}-config-{$version}.php";
            file_put_contents($build_dir . '/' . $config_filename, $config_content);
            return $build_dir . '/' . $config_filename; // Return path to file directly
        }

        // 3. Full Build: Copy Plugin Files Recursively
        self::copy_plugin_files(VAPTSECURE_PATH, $plugin_dir, $active_data_file_name, $generate_type, $data);

        $config_filename = "vapt-{$domain_for_files}-config-{$version}.php";
        if ($include_config) {
            file_put_contents($plugin_dir . "/" . $config_filename, $config_content);
        }

        // 5. Rewrite Main Plugin File Headers & Logic
        self::rewrite_main_plugin_file($plugin_dir, $plugin_slug, $white_label, $version, $domain, $config_filename, $include_config, $require_wp, $require_php);

        // 6. Generate uninstall cleanup entry for WordPress deletion flow.
        self::generate_uninstall_php($plugin_dir);

        // 7. Generate Documentation
        self::generate_docs($plugin_dir, $domain, $version, $features);

        // 8. Create ZIP Archive
        $master_plugin_name = sanitize_title((string) self::get_master_plugin_name());
        if ($master_plugin_name === '') {
            $master_plugin_name = 'vaptsecure-clean';
        }
        $white_label_name = sanitize_title((string) ($white_label['name'] ?? ''));
        if ($white_label_name === '') {
            $white_label_name = $plugin_slug;
        }
        $zip_filename = "{$master_plugin_name}-{$white_label_name}-{$version}.zip";
        $zip_path = $build_dir . '/' . $zip_filename;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                self::add_dir_to_zip($plugin_dir, $zip, $plugin_slug);
                $zip->close();
            } else {
                throw new Exception('Failed to create ZIP archive (ZipArchive open failed).');
            }
        } else {
            if (!defined('ABSPATH')) {
                throw new Exception('Failed to create ZIP archive (ZipArchive missing and ABSPATH not defined).');
            }
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new PclZip($zip_path);
            $result = $archive->create(
                $plugin_dir,
                PCLZIP_OPT_REMOVE_PATH,
                $temp_dir
            );
            if ($result === 0) {
                throw new Exception('Failed to create ZIP archive (PclZip): ' . $archive->errorInfo(true));
            }
        }

        // Cleanup Temp
        self::recursive_rmdir($temp_dir);

        // Return URL to the ZIP
        $base_storage_url = $upload_dir['baseurl'] . '/VAPT-Builds';
        return $base_storage_url . '/' . $domain_for_files . '/' . $version . '/' . $zip_filename;
    }

    public static function generate_config_content($domain, $version, $features, $active_data_file = null, $license_type = 'standard', $license_scope = 'single', $domain_limit = 1, $restrict_features = false, $security_alert_email = '', $feature_meta_snapshot = array(), $is_wildcard = false, $require_wp = '6.0', $require_php = '7.4.33')
    {
        $alert_email_b64 = ($security_alert_email && function_exists('is_email') && is_email($security_alert_email)) ? base64_encode($security_alert_email) : '';

        $default_payload = array(
            'build_profile' => 'client',
            'license_type' => (string) $license_type,
            'is_trial' => in_array($license_type, ['7-day-trial', '15-day-demo'], true),
            'domain_locked' => ($license_type !== 'developer_unbound') ? (string) $domain : '',
            'build_version' => (string) $version,
            'build_at' => current_time('mysql'),
            'license_scope' => (string) $license_scope,
            'domain_limit' => intval($domain_limit),
            'restrict_features' => (bool) $restrict_features,
            'is_wildcard' => (bool) $is_wildcard,
            'require_wp' => (string) $require_wp,
            'require_php' => (string) $require_php,
            'features' => array_values(array_map('strval', is_array($features) ? $features : array())),
        );

        if ($license_type === 'developer_unbound') {
            $default_payload['restrict_features'] = false;
        }

        $extended_payload = array(
            'build_at' => current_time('mysql'),
            'active_data_file' => (string) ($active_data_file ?: ''),
            'security_alert_email_b64' => $alert_email_b64,
            'feature_meta' => is_array($feature_meta_snapshot) ? $feature_meta_snapshot : array(),
        );

        $default_b64 = base64_encode(json_encode($default_payload));
        $extended_b64 = base64_encode(json_encode($extended_payload));
        $default_hash = hash('sha256', $default_b64);
        $extended_hash = hash('sha256', $extended_b64);

        $config = "<?php\n";
        $config .= "/**\n * VAPTSecure Clean Configuration for $domain\n * Build Version: $version\n */\n\n";
        $config .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";
        $config .= "define( 'VAPTSECURE_CONFIG_B64', '" . $default_b64 . "' );\n";
        $config .= "define( 'VAPTSECURE_DEFAULT_CONFIG_B64', '" . $default_b64 . "' );\n";
        $config .= "define( 'VAPTSECURE_DEFAULT_CONFIG_HASH', '" . $default_hash . "' );\n";
        $config .= "define( 'VAPTSECURE_EXTENDED_CONFIG_B64', '" . $extended_b64 . "' );\n";
        $config .= "define( 'VAPTSECURE_EXTENDED_CONFIG_HASH', '" . $extended_hash . "' );\n";
        $config .= "if ( ! function_exists( 'vaptsecure_apply_config_payload' ) ) {\n";
        $config .= "    function vaptsecure_apply_config_payload( \$payload, \$section = 'default' ) {\n";
        $config .= "        if ( ! is_array( \$payload ) ) { return false; }\n";
        $config .= "        \$section = (string) \$section;\n";
        $config .= "        \$license_type = isset( \$payload['license_type'] ) ? (string) \$payload['license_type'] : 'standard';\n";
        $config .= "        \$is_default = ( \$section === 'default' );\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_BUILD_PROFILE' ) && isset( \$payload['build_profile'] ) ) { define( 'VAPTSECURE_BUILD_PROFILE', (string) \$payload['build_profile'] ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_LICENSE_TYPE' ) ) { define( 'VAPTSECURE_LICENSE_TYPE', \$license_type ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_IS_TRIAL' ) ) { define( 'VAPTSECURE_IS_TRIAL', ! empty( \$payload['is_trial'] ) ); }\n";
        $config .= "        if ( \$is_default && \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_LOCKED' ) && ! empty( \$payload['domain_locked'] ) ) { define( 'VAPTSECURE_DOMAIN_LOCKED', (string) \$payload['domain_locked'] ); }\n";
        $config .= "        if ( \$is_default && \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_WILDCARD' ) && ! empty( \$payload['is_wildcard'] ) ) { define( 'VAPTSECURE_DOMAIN_WILDCARD', true ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_BUILD_VERSION' ) && isset( \$payload['build_version'] ) ) { define( 'VAPTSECURE_BUILD_VERSION', (string) \$payload['build_version'] ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_LICENSE_SCOPE' ) && isset( \$payload['license_scope'] ) ) { define( 'VAPTSECURE_LICENSE_SCOPE', (string) \$payload['license_scope'] ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_DOMAIN_LIMIT' ) && isset( \$payload['domain_limit'] ) ) { define( 'VAPTSECURE_DOMAIN_LIMIT', intval( \$payload['domain_limit'] ) ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_REQUIRE_WP' ) && isset( \$payload['require_wp'] ) ) { define( 'VAPTSECURE_REQUIRE_WP', (string) \$payload['require_wp'] ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_REQUIRE_PHP' ) && isset( \$payload['require_php'] ) ) { define( 'VAPTSECURE_REQUIRE_PHP', (string) \$payload['require_php'] ); }\n";
        $config .= "        if ( \$is_default ) {\n";
        $config .= "            \$restrict = ! empty( \$payload['restrict_features'] );\n";
        $config .= "            if ( \$license_type === 'developer_unbound' ) { \$restrict = false; }\n";
        $config .= "            if ( ! defined( 'VAPTSECURE_RESTRICT_FEATURES' ) ) { define( 'VAPTSECURE_RESTRICT_FEATURES', (bool) \$restrict ); }\n";
        $config .= "            if ( isset( \$payload['features'] ) && is_array( \$payload['features'] ) ) {\n";
        $config .= "                foreach ( \$payload['features'] as \$key ) {\n";
        $config .= "                    \$key = (string) \$key;\n";
        $config .= "                    if ( \$key === '' ) { continue; }\n";
        $config .= "                    \$const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', \$key ) );\n";
        $config .= "                    if ( ! defined( \$const ) ) { define( \$const, true ); }\n";
        $config .= "                }\n";
        $config .= "            }\n";
        $config .= "        }\n";
        $config .= "        if ( \$section !== 'default' && isset( \$payload['features'] ) && is_array( \$payload['features'] ) ) {\n";
        $config .= "            foreach ( \$payload['features'] as \$key ) {\n";
        $config .= "                \$key = (string) \$key;\n";
        $config .= "                if ( \$key === '' ) { continue; }\n";
        $config .= "                \$const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', \$key ) );\n";
        $config .= "                if ( ! defined( \$const ) ) { define( \$const, true ); }\n";
        $config .= "            }\n";
        $config .= "        }\n";
        $config .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_ACTIVE_DATA_FILE' ) && ! empty( \$payload['active_data_file'] ) ) { define( 'VAPTSECURE_ACTIVE_DATA_FILE', (string) \$payload['active_data_file'] ); }\n";
        $config .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_BUILD_AT' ) && ! empty( \$payload['build_at'] ) ) { define( 'VAPTSECURE_BUILD_AT', (string) \$payload['build_at'] ); }\n";
        $config .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_SECURITY_ALERT_EMAIL' ) && ! empty( \$payload['security_alert_email_b64'] ) ) { define( 'VAPTSECURE_SECURITY_ALERT_EMAIL', base64_decode( (string) \$payload['security_alert_email_b64'] ) ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_CONFIG_LOADED' ) ) { define( 'VAPTSECURE_CONFIG_LOADED', true ); }\n";
        $config .= "        return true;\n";
        $config .= "    }\n";
        $config .= "}\n";
        $config .= "if ( ! function_exists( 'vaptsecure_load_config_sections' ) ) {\n";
        $config .= "    function vaptsecure_load_config_sections() {\n";
        $config .= "        \$default_json = base64_decode( VAPTSECURE_DEFAULT_CONFIG_B64, true );\n";
        $config .= "        \$default_payload = \$default_json ? json_decode( \$default_json, true ) : null;\n";
        $config .= "        if ( is_array( \$default_payload ) ) { vaptsecure_apply_config_payload( \$default_payload, 'default' ); }\n";
        $config .= "        if ( defined( 'VAPTSECURE_EXTENDED_CONFIG_B64' ) && VAPTSECURE_EXTENDED_CONFIG_B64 !== '' ) {\n";
        $config .= "            \$extended_json = base64_decode( VAPTSECURE_EXTENDED_CONFIG_B64, true );\n";
        $config .= "            \$extended_payload = \$extended_json ? json_decode( \$extended_json, true ) : null;\n";
        $config .= "            if ( is_array( \$extended_payload ) ) { vaptsecure_apply_config_payload( \$extended_payload, 'extended' ); }\n";
        $config .= "            unset( \$extended_json, \$extended_payload );\n";
        $config .= "        }\n";
        $config .= "        unset( \$default_json, \$default_payload );\n";
        $config .= "        return true;\n";
        $config .= "    }\n";
        $config .= "}\n";
        $config .= "vaptsecure_load_config_sections();\n";
        $config .= "\n";
        $config .= "/* VAPTSECURE_CONFIG_CUSTOM_START - Add custom PHP code below */\n";
        $config .= "/* VAPTSECURE_CONFIG_CUSTOM_END */\n";
        $config .= "\n";
        return $config;
    }

    private static function copy_plugin_files($source, $dest, $active_data_file = null, $generate_type = 'full_build', $build_data = [])
    {
        $source = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $source), DIRECTORY_SEPARATOR);
        $dest = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $dest), DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $allowed_exact_paths = array(
            'README.md',
            'LICENSE',
            'LICENSE.txt',
            'LICENSE.md',
            'uninstall.php',
            'vapt-functions.php',
            'data/interface_schema_v2.0.json',
            'data/enforcer_pattern_library_v2.0.json',
            'data/ai_agent_instructions_v2.0.json',
            'data/vapt_driver_manifest_v2.0.json',
            'data/VAPT_Driver_Reference_v2.0.php',
            'includes/debug-utils.php',
            'includes/class-vaptsecure-auth.php',
            'includes/interfaces/interface-vaptsecure-driver.php',
            'includes/class-vaptsecure-schema-validator.php',
            'includes/class-vaptsecure-rest.php',
            'includes/class-vaptsecure-db.php',
            'includes/class-vaptsecure-workflow.php',
            'includes/class-vaptsecure-ai-config.php',
            'includes/class-vaptsecure-config-cleaner.php',
            'includes/class-vaptsecure-enforcer.php',
            'includes/class-vaptsecure-admin.php',
            'includes/class-vaptsecure-license-manager.php',
            'includes/class-vaptsecure-environment-detector.php',
            'includes/class-vaptsecure-deployment-orchestrator.php',
            'assets/css/admin.css',
            'assets/js/admin.js',
            'assets/js/client.js',
        );

        $allowed_prefixes = array(
            'includes/enforcers/',
            'includes/interfaces/',
            'includes/rest/',
            'assets/css/',
            'assets/js/admin-modules/',
            'assets/js/modules/',
        );

        if (is_string($active_data_file) && trim($active_data_file) !== '') {
            $allowed_exact_paths[] = 'data/' . ltrim(str_replace('\\', '/', trim($active_data_file)), '/');
        }

        // Determine if config should be included
        $include_config = isset($build_data['include_config']) &&
                          ($build_data['include_config'] === true ||
                           $build_data['include_config'] === 'true' ||
                           $build_data['include_config'] === 1);

        // Core exclusions - development and testing files
        $exclusions = [
            '.git', '.vscode', 'node_modules', 'brain', 'tests', 'vapt-debug.txt',
            '.clinerules', '.rules', 'COMMIT_MESSAGE.md', 'COMMIT_MSG.txt', 'Graphy.md',
            'Graphy', 'null',
            'Implementation Plan', 'plans', 'tools', 'archive', 'Debug', 'backup_debug_cleanup',
            'update-graphy', 'ANALYSIS_REPORT.md', 'CODEBASE_REVIEW.md',
            '.github',
            // AI/Agent configuration directories
            '.ai', '.roo', '.claude', '.cursor', '.gemini', '.kilocode', '.qoder', '.trae',
            '.windsurf', '.opencode', '.agent', '.kilo', '.junie', '.continue',
            // Specific AI subdirectories
            '.ai/workflows', '.ai/skills', '.ai/rules',
            '.claude/skills', '.cursor/skills', '.gemini/antigravity/skills',
            '.kilocode/rules', '.qoder/skills', '.trae/skills', '.windsurf/skills',
            '.roo/rules', '.roo/skills',
            // Deployment directory
            'deployment',
            // Debug and search files
            'debug-field-mapping.js', 'debug-field-structure.js', 'search-enforcer-fields.js'
        ];

        // Documentation files to exclude (keep only README.md and How to User.md)
        $doc_exclusions = [
            'CLAUDE.md', 'DEBUG-MODE.md', 'VERSION_HISTORY.md', 'SOUL.md', 'SOUL_Claude-Notes.md',
            'SOUL_comprehensive.md', 'SOUL_enhanced.md', 'SOUL_with_selfcheck.md', 'SOUL-Claude-Ext.md',
            'SOUL-Claude.md', 'AGENTS.md', 'README-Claude-Ext.md'
        ];

        foreach ($iterator as $item) {
            $subPath = str_replace('\\', '/', (string) $iterator->getSubPathName());
            $filename = basename($subPath);
            $nativeSubPath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $subPath);
            $is_allowed_path = in_array($subPath, $allowed_exact_paths, true);
            if (!$is_allowed_path) {
                foreach ($allowed_prefixes as $prefix) {
                    if (strpos($subPath, $prefix) === 0 || ($item->isDir() && strpos($prefix, $subPath . '/') === 0)) {
                        $is_allowed_path = true;
                        break;
                    }
                }
            }
            if (!$is_allowed_path) {
                continue;
            }

            // Check Exclusions
            foreach ($exclusions as $exclude) {
                if (strpos($subPath, $exclude) === 0) { continue 2;
                }
            }

            // Handle Data Directory
            if (strpos($subPath, 'data') === 0) {
                // When active data file is specified (Include Active Data enabled)
                if ($active_data_file) {
                    $allowed_data_files = array(
                        $active_data_file,
                        'enforcer_pattern_library_v2.0.json',
                        'ai_agent_instructions_v2.0.json',
                        'vapt_driver_manifest_v2.0.json',
                        'VAPT_Driver_Reference_v2.0.php'
                    );

                    // Allow the data directory itself
                    if ($item->isDir() && (strcasecmp($subPath, 'data') === 0 || strcasecmp($subPath, 'data/') === 0 || strcasecmp($subPath, 'data\\') === 0)) {
                        // Allow
                    } 
                    // [FEATURE] Include data/Enforcers folder in build
                    elseif (strpos($subPath, 'data' . DIRECTORY_SEPARATOR . 'Enforcers') === 0) {
                        // Allow Enforcers folder and its contents
                    }
                    else {
                        // Disallow other nested folders under /data
                        if ($item->isDir() && strpos($subPath, 'data' . DIRECTORY_SEPARATOR) === 0) {
                            $parts = explode(DIRECTORY_SEPARATOR, $subPath);
                            if (count($parts) > 2) { // Deeper than data/Enforcers/ is handled by the Enforcers check above
                                continue;
                            }
                            if (count($parts) === 2 && strcasecmp($parts[1], 'Enforcers') !== 0) {
                                continue;
                            }
                        }

                        // Only allow a small set of top-level data files
                        $is_top_level_file = (strpos($subPath, 'data/') === 0 || strpos($subPath, 'data\\') === 0) &&
                            (substr_count($subPath, '/') === 1 || substr_count($subPath, '\\') === 1);
                        if (!$is_top_level_file || !in_array($filename, $allowed_data_files, true)) {
                            // Check if it's inside Enforcers
                            if (strpos($subPath, 'data' . DIRECTORY_SEPARATOR . 'Enforcers') !== 0) {
                                continue;
                            }
                        }
                    }

                    // If this is the active data file, write a filtered version containing only the build's Release features
                    if (!$item->isDir() && (strcasecmp($filename, $active_data_file) === 0)) {
                        $allowed_feature_keys = isset($build_data['features']) && is_array($build_data['features']) ? $build_data['features'] : array();
                        $dest_path = $dest . DIRECTORY_SEPARATOR . $nativeSubPath;
                        $dest_dir = dirname($dest_path);
                        if (!file_exists($dest_dir)) {
                            mkdir($dest_dir, 0755, true);
                        }
                        self::filter_data_file_for_release((string) $item, $dest_path, $allowed_feature_keys);
                        continue;
                    }
                }
                // When no active data file (Include Active Data disabled)
                else {
                    continue; // Skip entire data folder
                }
            }

            // Exclude documentation files (except README.md and How to User.md)
            if (in_array($filename, $doc_exclusions, true)) {
                continue;
            }

            // Special case: exclude .md files in root except README.md and "How to User.md"
            if (strpos($subPath, '.md') !== false && strpos($subPath, '/') === false && strpos($subPath, '\\') === false) {
                if ($filename !== 'README.md' && $filename !== 'How to User.md') {
                    continue;
                }
            }

            // Exclude test files (files starting with "test-")
            if (strpos($filename, 'test-') === 0) {
                continue;
            }

            // Exclude supporting repository files that should never ship in client builds.
            if (
                strcasecmp($filename, 'LICENSE.txt') === 0 ||
                strcasecmp($filename, 'LICENSE.md') === 0 ||
                strcasecmp($filename, 'test.ftp') === 0 ||
                stripos($filename, 'update-graphy') === 0 ||
                stripos($filename, 'graphy') === 0
            ) {
                continue;
            }

            // Exclude domain-specific configuration files (vapt-*-config-*.php)
            // In config-only builds, allow all config files
            if (preg_match('/^vapt-.*-config-.*\.php$/i', $filename)) {
                // In config-only builds, allow config files
                if ($generate_type === 'config_only') {
                    // Allow - config files are purpose of this build
                }
                // When config inclusion is enabled, still exclude existing ones
                // (a new one will be generated in the generate() method)
                else {
                    continue;
                }
            }

            // Exclude the source main plugin file; the generated main file is rewritten later.
            if (strcasecmp($filename, 'vaptsecure.php') === 0) {
                continue;
            }

            // Exclude archive files globally
            if (preg_match('/\.(zip|rar|7z|tar|gz|bz2|xz)$/i', $filename)) {
                continue;
            }

            if ($item->isDir()) {
                $dest_dir_path = $dest . DIRECTORY_SEPARATOR . $nativeSubPath;
                if (!file_exists($dest_dir_path)) {
                    mkdir($dest_dir_path, 0755, true);
                }
            } else {
                copy($item, $dest . DIRECTORY_SEPARATOR . $nativeSubPath);
            }
        }
    }

    private static function rewrite_main_plugin_file($plugin_dir, $plugin_slug, $white_label, $version, $domain, $config_filename, $include_config, $require_wp = '6.0', $require_php = '7.4.33')
    {
        $source_main = VAPTSECURE_PATH . 'vaptsecure.php';
        $plugin_file_slug = sanitize_title((string) $white_label['name']);
        if ($plugin_file_slug === '') {
            $plugin_file_slug = sanitize_title((string) ($white_label['text_domain'] ?: $plugin_slug));
        }
        if ($plugin_file_slug === '') {
            $plugin_file_slug = 'vaptsecure';
        }
        $dest_filename = $plugin_file_slug . '.php';
        $dest_main = $plugin_dir . '/' . $dest_filename;

        $content = file_get_contents($source_main);

        // Rewrite Headers
        $headers = "/**\n";
        $headers .= " * Plugin Name: " . $white_label['name'] . "\n";
        $headers .= " * Plugin URI: " . $white_label['plugin_uri'] . "\n";
        $headers .= " * Description: " . $white_label['description'] . "\n";
        $headers .= " * Version: " . $version . "\n";
        $headers .= " * Author: " . $white_label['author'] . "\n";
        $headers .= " * Author URI: " . $white_label['author_uri'] . "\n";
        $headers .= " * Text Domain: " . $white_label['text_domain'] . "\n";
        $headers .= " * Requires at least: " . $require_wp . "\n";
        $headers .= " * Requires PHP: " . $require_php . "\n";
        $headers .= " */\n";

        // Regex replace the existing header block
        $content = self::safe_preg_replace('/\/\*\*.*?\*\//s', $headers, $content, 1);

        // Remove ALL superadmin functionality from generated builds using more precise patterns
        
        // 1. Remove vaptsecure_get_superadmin_identity()
        // Generated builds should not expose any hidden-user identity surface.
        $content = self::safe_preg_replace('/function vaptsecure_get_superadmin_identity\s*\(\)\s*\{[\s\S]*?\n\}/s', '', $content);

        // 2. Remove VAPTSECURE_SUPERADMIN_USER and VAPTSECURE_SUPERADMIN_EMAIL constants definition
        // Match the entire block that sets identity and defines constants
        $content = self::safe_preg_replace('/\/\/ Set Superadmin Constants[\s\S]*?if \(! defined\(\'VAPTSECURE_SUPERADMIN_EMAIL\'\)\) \{[\s\S]*?\}/s', '', $content);
        
        // 3. Stub is_vaptsecure_superadmin()
        // Robust stubbing: replace function body to always return false
        $content = self::safe_preg_replace('/function is_vaptsecure_superadmin\s*\([^)]*\)\s*\{[^{}]*\{(?:[^{}]*\{[^{}]*\}[^{}]*|[^{}]*)*\}[\s\S]*?\n\}/s', 'function is_vaptsecure_superadmin($require_auth = false) { return false; }', $content);
        
        // 4. Remove superadmin menu logic
        // Replaces the conditional superadmin menu with a static one for all admins
        $content = self::safe_preg_replace('/\$is_superadmin_identity = is_vaptsecure_superadmin\(false\);[\s\S]*?remove_submenu_page\(\'vaptsecure\', \'vaptsecure\'\);/s', '// 1. Parent Menu (Visible to all admins)
        add_menu_page(
            __(\'VAPTSecure Clean\', \'vaptsecure\'),
            __(\'VAPTSecure Clean\', \'vaptsecure\'),
            \'manage_options\',
            \'vaptsecure\',
            \'vaptsecure_render_client_status_page\',
            \'dashicons-shield\',
            80
        );
        remove_submenu_page(\'vaptsecure\', \'vaptsecure\');', $content);
        
        // 5. Remove superadmin page rendering functions
        // Ensure entire function bodies are removed
        $content = self::safe_preg_replace('/function vaptsecure_render_workbench_page\s*\([^)]*\)\s*\{[^{}]*\{(?:[^{}]*\{[^{}]*\}[^{}]*|[^{}]*)*\}[\s\S]*?\n\}/s', '', $content);
        $content = self::safe_preg_replace('/function vaptsecure_render_admin_page\s*\([^)]*\)\s*\{[^{}]*\{(?:[^{}]*\{[^{}]*\}[^{}]*|[^{}]*)*\}[\s\S]*?\n\}/s', '', $content);
        $content = self::safe_preg_replace('/function vaptsecure_master_dashboard_page\s*\([^)]*\)\s*\{[^{}]*\{(?:[^{}]*\{[^{}]*\}[^{}]*|[^{}]*)*\}[\s\S]*?\n\}/s', '', $content);

        // 6. Synchronize VAPTSECURE_VERSION definition in the content
        // [v2.4.11] Ultra-robust version synchronization
        $version_sync = "if (defined('VAPTSECURE_BUILD_VERSION')) {\n    define('VAPTSECURE_VERSION', VAPTSECURE_BUILD_VERSION);\n} else {\n    define('VAPTSECURE_VERSION', '{$version}');\n}";
        
        // Match the entire if/else block for VAPTSECURE_VERSION
        $content = self::safe_preg_replace('/if\s*\(\s*defined\s*\(\s*\'VAPTSECURE_BUILD_VERSION\'\s*\)\s*\)\s*\{[\s\S]*?\}\s*else\s*\{[\s\S]*?\}/s', $version_sync, $content);
        
        // Also ensure simple define is replaced if if/else was missing (fallback)
        $content = self::safe_preg_replace('/define\(\s*\'VAPTSECURE_VERSION\'\s*,\s*\'[^\']+\'\s*\);/', $version_sync, $content);

        // Remove builder-only and self-check includes from generated client builds.
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/class-vaptsecure-build\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-check-item\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-self-check-result\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-audit-log\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-auto-correct\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-self-check\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-cron\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/self-check\/class-vapt-lifecycle\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/^require_once VAPTSECURE_PATH \. "includes\/admin\/class-vapt-diagnostics-page\.php";\s*$/m', '', $content);
        $content = self::safe_preg_replace('/add_action\("vapt_license_expired", function \(\) \{\s*if \(class_exists\("VAPT_Self_Check"\)\) \{\s*VAPT_Self_Check::run\("license_expire"\);\s*\}\s*\}\);\s*/s', '', $content);

        $cleanup_block = "if (!function_exists('vaptsecure_client_clear_transient_state')) {\n"
            . "    function vaptsecure_client_clear_transient_state() {\n"
            . "        global \$wpdb;\n"
            . "        \$option_patterns = array(\n"
            . "            '_transient_vaptsecure_%',\n"
            . "            '_transient_vapt_%',\n"
            . "            '_transient_timeout_vaptsecure_%',\n"
            . "            '_transient_timeout_vapt_%',\n"
            . "        );\n"
            . "        foreach (\$option_patterns as \$pattern) {\n"
            . "            \$options = \$wpdb->get_col(\$wpdb->prepare(\"SELECT option_name FROM {\$wpdb->options} WHERE option_name LIKE %s\", \$pattern));\n"
            . "            if (!is_array(\$options)) {\n"
            . "                continue;\n"
            . "            }\n"
            . "            foreach (\$options as \$option) {\n"
            . "                delete_option(\$option);\n"
            . "            }\n"
            . "        }\n"
            . "        if (is_multisite() && !empty(\$wpdb->sitemeta)) {\n"
            . "            \$site_patterns = array(\n"
            . "                '_site_transient_vaptsecure_%',\n"
            . "                '_site_transient_vapt_%',\n"
            . "                '_site_transient_timeout_vaptsecure_%',\n"
            . "                '_site_transient_timeout_vapt_%',\n"
            . "            );\n"
            . "            foreach (\$site_patterns as \$pattern) {\n"
            . "                \$site_options = \$wpdb->get_col(\$wpdb->prepare(\"SELECT meta_key FROM {\$wpdb->sitemeta} WHERE meta_key LIKE %s\", \$pattern));\n"
            . "                if (!is_array(\$site_options)) {\n"
            . "                    continue;\n"
            . "                }\n"
            . "                foreach (\$site_options as \$site_option) {\n"
            . "                    delete_site_option(\$site_option);\n"
            . "                }\n"
            . "            }\n"
            . "        }\n"
            . "    }\n"
            . "}\n"
            . "if (!function_exists('vaptsecure_client_deactivate')) {\n"
            . "    function vaptsecure_client_deactivate() {\n"
            . "        if (function_exists('vaptsecure_client_clear_transient_state')) {\n"
            . "            vaptsecure_client_clear_transient_state();\n"
            . "        }\n"
            . "        if (class_exists('VAPTSECURE_Config_Cleaner')) {\n"
            . "            VAPTSECURE_Config_Cleaner::clean_all();\n"
            . "        } elseif (class_exists('VAPTSECURE_Enforcer')) {\n"
            . "            VAPTSECURE_Enforcer::clean_all_config_files();\n"
            . "        }\n"
            . "        delete_transient('vaptsecure_active_enforcements');\n"
            . "        update_option('vaptsecure_global_protection', 0);\n"
            . "        update_option('vapt_plugin_status', 'deactivated');\n"
            . "        update_option('vapt_active_features', array());\n"
            . "    }\n"
            . "}\n";
        $content = self::safe_preg_replace(
            '/register_deactivation_hook\(__FILE__, \["VAPT_Lifecycle", "on_deactivate"\]\);\s*register_uninstall_hook\(__FILE__, \["VAPT_Lifecycle", "on_uninstall"\]\);/s',
            $cleanup_block . "register_deactivation_hook(__FILE__, 'vaptsecure_client_deactivate');\n",
            $content
        );

        $activation_email_rewrite = "function vaptsecure_send_activation_email() {\n"
            . "    if (!function_exists('wp_mail')) { return; }\n"
            . "    \$to = '';\n"
            . "    if (defined('VAPTSECURE_SECURITY_ALERT_EMAIL') && VAPTSECURE_SECURITY_ALERT_EMAIL) { \$to = (string) VAPTSECURE_SECURITY_ALERT_EMAIL; }\n"
            . "    if (!\$to) { \$to = (string) get_option('admin_email'); }\n"
            . "    if (!\$to) { return; }\n"
            . "    if (function_exists('is_email') && !is_email(\$to)) { return; }\n"
            . "    \$site_name = get_bloginfo('name');\n"
            . "    \$site_url = get_site_url();\n"
            . "    \$admin_url = admin_url('admin.php?page=vaptsecure');\n"
            . "    \$subject = sprintf('[VAPT Alert] Plugin Activated on %s', \$site_name);\n"
            . "    \$message = \"VAPTSecure Clean has been activated on a new site.\\n\\n\";\n"
            . "    \$message .= \"Site Name: {\$site_name}\\n\";\n"
            . "    \$message .= \"Site URL: {\$site_url}\\n\";\n"
            . "    \$message .= \"Activation Date: \" . current_time('mysql') . \"\\n\";\n"
            . "    \$message .= \"Access Dashboard: {\$admin_url}\\n\\n\";\n"
            . "    \$message .= 'This is an automated security notification.';\n"
            . "    \$headers = array('Content-Type: text/plain; charset=UTF-8');\n"
            . "    wp_mail(\$to, \$subject, \$message, \$headers);\n"
            . "}\n";

        $content = self::safe_preg_replace(
            '/function\s+vaptsecure_send_activation_email\s*\(\)\s*\{[\s\S]*?\n\}/s',
            $activation_email_rewrite,
            $content,
            1
        );

        $guard_code = "\n";
        $guard_code .= "define('VAPTSECURE_EXPECTS_CONFIG', true);\n";
        $guard_code .= "\$__vaptsecure_config_path = plugin_dir_path(__FILE__) . '" . $config_filename . "';\n";
        $guard_code .= "\$__vaptsecure_config_files = array();\n";
        $guard_code .= "if (file_exists(\$__vaptsecure_config_path)) {\n";
        $guard_code .= "    \$__vaptsecure_config_files[] = \$__vaptsecure_config_path;\n";
        $guard_code .= "} else {\n";
        $guard_code .= "    \$__vaptsecure_config_files = glob(plugin_dir_path(__FILE__) . 'vapt-*-config-*.php');\n";
        $guard_code .= "}\n";
        $guard_code .= "if (!empty(\$__vaptsecure_config_files)) {\n";
        $guard_code .= "    require_once \$__vaptsecure_config_files[0];\n";
        $guard_code .= "} else {\n";
        $guard_code .= "    define('VAPTSECURE_CONFIG_MISSING', true);\n";
        $guard_code .= "    if (function_exists('update_option')) { update_option('vaptsecure_global_protection', 0); }\n";
        $guard_code .= "    \$__vaptsecure_to = (string) get_option('admin_email');\n";
        $guard_code .= "    if (\$__vaptsecure_to && function_exists('wp_mail')) { wp_mail(\$__vaptsecure_to, '[VAPTSecure Clean] Configuration file missing', 'VAPTSecure Clean is disabled because its configuration file is missing.'); }\n";
        $guard_code .= "    add_action('admin_notices', function () {\n";
        $guard_code .= "        if (!current_user_can('manage_options')) { return; }\n";
        $guard_code .= "        echo '<div class=\"notice notice-error\"><p><strong>VAPTSecure Clean:</strong> Required configuration file is missing. This build is disabled.</p></div>';\n";
        $guard_code .= "    });\n";
        $guard_code .= "    add_action('init', function () {\n";
        $guard_code .= "        if (!is_admin()) {\n";
        $guard_code .= "            \$is_api = (defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');\n";
        $guard_code .= "            if (\$is_api) {\n";
        $guard_code .= "                if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }\n";
        $guard_code .= "                echo json_encode(array('success' => false, 'error' => 'config_missing', 'message' => 'VAPTSecure Clean configuration file is missing.'));\n";
        $guard_code .= "                exit;\n";
        $guard_code .= "            }\n";
        $guard_code .= "            wp_die('<h1>VAPTSecure Clean</h1><p>This build is disabled because its configuration file is missing.</p>');\n";
        $guard_code .= "        }\n";
        $guard_code .= "    }, 0);\n";
        $guard_code .= "    return;\n";
        $guard_code .= "}\n\n";

        // [FIX v3.1.1] Support wildcard domain matching - uses is_wildcard_flag set earlier
        // is_wildcard_flag is defined at the start of the generate() function
        
        $guard_code .= "if (defined('VAPTSECURE_DOMAIN_LOCKED') && VAPTSECURE_DOMAIN_LOCKED) {\n";
        $guard_code .= "    \$current_host = isset(\$_SERVER['HTTP_HOST']) ? \$_SERVER['HTTP_HOST'] : '';\n";
        $guard_code .= "    \$current_host = strtolower(preg_replace('/:\\d+$/', '', \$current_host));\n";
        $guard_code .= "    \$current_host = preg_replace('/^www\\./', '', \$current_host);\n";
        $guard_code .= "    \$locked_host = strtolower(trim((string) VAPTSECURE_DOMAIN_LOCKED));\n";
        $guard_code .= "    \$locked_host = preg_replace('/^www\\./', '', \$locked_host);\n";
        $guard_code .= "    \$is_wildcard_build = defined('VAPTSECURE_DOMAIN_WILDCARD') && VAPTSECURE_DOMAIN_WILDCARD;\n";
        $guard_code .= "    \$domain_match = (\$current_host === \$locked_host);\n";
        $guard_code .= "    if (!\$domain_match && \$is_wildcard_build && \$locked_host !== '') {\n";
        $guard_code .= "        if (substr(\$current_host, -strlen(\$locked_host)) === \$locked_host) {\n";
        $guard_code .= "            \$prefix = substr(\$current_host, 0, strlen(\$current_host) - strlen(\$locked_host));\n";
        $guard_code .= "            if (\$prefix === '' || substr(\$prefix, -1) === '.') { \$domain_match = true; }\n";
        $guard_code .= "        }\n";
        $guard_code .= "    }\n";
        $guard_code .= "    if (!\$domain_match) {\n";
        $guard_code .= "        \$to = (defined('VAPTSECURE_SECURITY_ALERT_EMAIL') && VAPTSECURE_SECURITY_ALERT_EMAIL) ? VAPTSECURE_SECURITY_ALERT_EMAIL : get_option('admin_email');\n";
        $guard_code .= "        if (\$to && function_exists('wp_mail')) {\n";
        $guard_code .= "            \$transient_key = 'vapt_alert_' . md5(\$current_host);\n";
        $guard_code .= "            if (!get_transient(\$transient_key)) {\n";
        $guard_code .= "                \$subject = '[VAPTSecure Clean] Unauthorized Domain Usage Alert';\n";
        $guard_code .= "                \$msg = \"Security build license validation failed.\\n\\n\";\n";
        $guard_code .= "                \$msg .= \"Locked to Domain: \" . \$locked_host . \"\\n\";\n";
        $guard_code .= "                \$msg .= \"Detected on Domain: \" . \$current_host . \"\\n\";\n";
        $guard_code .= "                \$msg .= \"Action: Plugin features have been disabled for this request.\\n\";\n";
        $guard_code .= "                wp_mail(\$to, \$subject, \$msg);\n";
        $guard_code .= "                set_transient(\$transient_key, 1, HOUR_IN_SECONDS);\n";
        $guard_code .= "            }\n";
        $guard_code .= "        }\n";
        $guard_code .= "        if (!is_admin()) {\n";
        $guard_code .= "            \$is_api_request = (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_REST_API') && WP_REST_API) || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset(\$_SERVER['CONTENT_TYPE']) && strpos(\$_SERVER['CONTENT_TYPE'], 'application/json') !== false) || (isset(\$_GET['rest_route']) && strpos(\$_GET['rest_route'], '/vaptsecure/v1') !== false) || (isset(\$_SERVER['REQUEST_URI']) && strpos(\$_SERVER['REQUEST_URI'], '/wp-json/vaptsecure/v1') !== false);\n";
        $guard_code .= "            if (\$is_api_request) {\n";
        $guard_code .= "                if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }\n";
        $guard_code .= "                echo json_encode(array('success' => false, 'error' => 'domain_mismatch', 'message' => 'This security plugin is not licensed for this domain.', 'domain' => \$current_host, 'locked_domain' => \$locked_host));\n";
        $guard_code .= "                exit;\n";
        $guard_code .= "            }\n";
        $guard_code .= "            \$html = '<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Security Alert</title><style>*{box-sizing:border-box;}body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px;}.alert-box{background:#fff;border-left:5px solid #dc3232;border-radius:8px;padding:40px;max-width:500px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideIn 0.5s ease-out;}@keyframes slideIn{from{opacity:0;transform:translateY(-30px);}to{opacity:1;transform:translateY(0);}}h1{color:#dc3232;margin:0 0 20px;font-size:32px;font-weight:600;}p{color:#555;margin:0 0 20px;font-size:18px;line-height:1.6;}.domain-info{background:#f0f0f0;border-radius:4px;padding:12px;margin-top:20px;font-family:monospace;font-size:14px;color:#333;}</style></head><body><div class=\"alert-box\"><h1>&#9888; Security Alert</h1><p>This security plugin is not licensed for this domain.</p><div class=\"domain-info\">Detected: ' . esc_html(\$current_host) . '</div></div></body></html>';\n";
        $guard_code .= "            if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }\n";
        $guard_code .= "            echo \$html;\n";
        $guard_code .= "            exit;\n";
        $guard_code .= "        }\n";
        $guard_code .= "    }\n";
        $guard_code .= "}\n";

        // Insert after first defined('ABSPATH') check block
        $content = self::safe_preg_replace('/if\s*\(\s*!\s*defined\s*\(\s*\'ABSPATH\'\s*\)\s*\)\s*\{[\s\S]*?\}/i', "$0\n" . $guard_code, $content, 1);

        // Remove the original file from the copy if it was copied by the recursive copier
        // [FIX v2.4.11] We are now using vaptsecure.php as the main filename, so no unlinking needed
        // unless the slug somehow created a different file during copy.
        if (file_exists($plugin_dir . '/vapt-copilot.php')) {
            unlink($plugin_dir . '/vapt-copilot.php');
        }

        file_put_contents($dest_main, $content);
    }

    private static function get_feature_title_map()
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = array();
        $paths = array(
            VAPTSECURE_PATH . 'data/interface_schema_v2.0.json',
            VAPTSECURE_PATH . 'data/Updated_Feature_List_159_Adaptive_V3_1.json',
            VAPTSECURE_PATH . 'data/Updated_Feature_List_159_Adaptive_V3_1_Lite.json',
        );

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }

            $candidate_sets = array();
            foreach (array('risk_interfaces', 'risk_catalog', 'features', 'wordpress_vapt') as $section) {
                if (isset($data[$section]) && is_array($data[$section])) {
                    $candidate_sets[] = $data[$section];
                }
            }

            foreach ($candidate_sets as $candidate_set) {
                foreach ($candidate_set as $key => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $title = '';
                    if (isset($item['title'])) {
                        $title = trim((string) $item['title']);
                    } elseif (isset($item['name'])) {
                        $title = trim((string) $item['name']);
                    }
                    if ($title === '') {
                        continue;
                    }

                    $keys = array(
                        isset($item['risk_id']) ? (string) $item['risk_id'] : '',
                        isset($item['id']) ? (string) $item['id'] : '',
                        is_string($key) ? (string) $key : '',
                    );

                    foreach ($keys as $candidate_key) {
                        $candidate_key = strtoupper(trim((string) $candidate_key));
                        if ($candidate_key !== '') {
                            $map[$candidate_key] = $title;
                        }
                    }
                }
            }
        }

        return $map;
    }

    private static function feature_title_from_key($feature_key, $title_map)
    {
        $feature_key = strtoupper(trim((string) $feature_key));
        if ($feature_key !== '' && isset($title_map[$feature_key])) {
            return $title_map[$feature_key];
        }

        $fallback = trim((string) $feature_key);
        $fallback = str_replace(array('-', '_'), ' ', strtolower($fallback));
        $fallback = trim(preg_replace('/\s+/', ' ', $fallback));
        return $fallback !== '' ? ucwords($fallback) : '';
    }

    private static function get_master_plugin_name()
    {
        $source = VAPTSECURE_PATH . 'vaptsecure.php';
        if (file_exists($source)) {
            $headers = @get_file_data($source, array('Name' => 'Plugin Name'), 'plugin');
            if (is_array($headers) && !empty($headers['Name'])) {
                $name = trim((string) $headers['Name']);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'VAPTSecure Clean';
    }

    private static function generate_docs($dir, $domain, $version, $features)
    {
        $readme = "# VAPTSecure Clean Security Build for $domain\n\n";
        $readme .= "Version: $version\n";
        $readme .= "Generated: " . date('Y-m-d') . "\n\n";
        $readme .= "## Active Protection Modules\n";
        $title_map = self::get_feature_title_map();
        $lines = array();
        foreach ($features as $f) {
            $title = self::feature_title_from_key($f, $title_map);
            if ($title === '') {
                continue;
            }
            $lines[] = $title;
        }
        $lines = array_values(array_unique($lines));
        foreach ($lines as $index => $title) {
            $readme .= ($index + 1) . '. ' . $title . "\n";
        }
        file_put_contents($dir . '/README.md', $readme);
    }

    private static function generate_uninstall_php($dir)
    {
        $uninstall = <<<'PHP'
<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$plugin_root = dirname(__FILE__);

$cleanup_paths = array(
    $plugin_root . '/vapt-functions.php',
    $plugin_root . '/data/generated',
);

foreach ($cleanup_paths as $path) {
    if (is_dir($path)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $target = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($target);
            } else {
                @unlink($target);
            }
        }
        @rmdir($path);
    } elseif (file_exists($path)) {
        @unlink($path);
    }
}

foreach (glob($plugin_root . '/vapt-*-config-*.php') ?: array() as $config_file) {
    @unlink($config_file);
}

$table_patterns = array(
    $wpdb->prefix . 'vaptsecure_%',
    $wpdb->prefix . 'vapt_%',
);

foreach ($table_patterns as $pattern) {
    $tables = $wpdb->get_results($wpdb->prepare('SHOW TABLES LIKE %s', $pattern), ARRAY_N);
    if (!is_array($tables)) {
        continue;
    }
    foreach ($tables as $row) {
        if (!empty($row[0])) {
            $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($row[0]) . '`');
        }
    }
}

$options = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vaptsecure_%' OR option_name LIKE 'vapt_%'");
if (is_array($options)) {
    foreach ($options as $option) {
        delete_option($option);
    }
}

$transient_options = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_vaptsecure_%' OR option_name LIKE '_transient_vapt_%' OR option_name LIKE '_transient_timeout_vaptsecure_%' OR option_name LIKE '_transient_timeout_vapt_%'");
if (is_array($transient_options)) {
    foreach ($transient_options as $transient_option) {
        delete_option($transient_option);
    }
}

if (is_multisite() && !empty($wpdb->sitemeta)) {
    $site_transient_options = $wpdb->get_col("SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_vaptsecure_%' OR meta_key LIKE '_site_transient_vapt_%' OR meta_key LIKE '_site_transient_timeout_vaptsecure_%' OR meta_key LIKE '_site_transient_timeout_vapt_%'");
    if (is_array($site_transient_options)) {
        foreach ($site_transient_options as $site_transient_option) {
            delete_site_option($site_transient_option);
        }
    }
}

delete_option('vaptsecure_global_protection');
delete_option('vapt_plugin_status');
delete_option('vapt_active_features');
delete_option('vaptsecure_config_current_b64');
delete_option('vaptsecure_config_current_extended_b64');
delete_option('vaptsecure_config_original_b64');
delete_option('vaptsecure_config_original_hash');
delete_option('vaptsecure_config_original_path');

?>
PHP;

        file_put_contents($dir . '/uninstall.php', $uninstall);
    }

    private static function add_dir_to_zip($dir, $zip, $zip_path)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $file_path = $file->getRealPath();
                if (preg_match('/\.(zip|rar|7z|tar|gz|bz2|xz)$/i', $file_path)) {
                    continue;
                }
                $relative_path = $zip_path . '/' . substr($file_path, strlen($dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }

    private static function recursive_rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                        self::recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
