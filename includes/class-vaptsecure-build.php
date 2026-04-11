<?php

/**
 * Build Generator for VAPT Secure
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

        $raw = file_get_contents($source_path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return copy($source_path, $dest_path);
        }

        if (isset($data['risk_interfaces']) && is_array($data['risk_interfaces'])) {
            $filtered = array();
            foreach ($data['risk_interfaces'] as $risk_key => $item) {
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $risk_key)));
                if ($candidate !== '' && isset($allowed[$candidate])) {
                    $filtered[$risk_key] = $item;
                }
            }
            $data['risk_interfaces'] = $filtered;
        } elseif (isset($data['risk_catalog']) && is_array($data['risk_catalog'])) {
            $data['risk_catalog'] = array_values(array_filter($data['risk_catalog'], function ($item) use ($allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && isset($allowed[$candidate]);
            }));
        } elseif (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = array_values(array_filter($data['features'], function ($item) use ($allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && isset($allowed[$candidate]);
            }));
        } elseif (isset($data['wordpress_vapt']) && is_array($data['wordpress_vapt'])) {
            $data['wordpress_vapt'] = array_values(array_filter($data['wordpress_vapt'], function ($item) use ($allowed) {
                if (!is_array($item)) {
                    return false;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? '')));
                return $candidate !== '' && isset($allowed[$candidate]);
            }));
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return copy($source_path, $dest_path);
        }
        return file_put_contents($dest_path, $json) !== false;
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
        $restrict_features = isset($data['restrict_features']) ? filter_var($data['restrict_features'], FILTER_VALIDATE_BOOLEAN) : false;

        $feature_meta_snapshot = self::get_feature_meta_snapshot($features);
        $is_wildcard = isset($data['is_wildcard']) && ($data['is_wildcard'] === true || $data['is_wildcard'] === 'true' || $data['is_wildcard'] === 1 || $data['is_wildcard'] === '1');
        $config_content = self::generate_config_content($domain, $version, $features, $active_data_file_name, $license_type, $license_scope, $domain_limit, $restrict_features, $security_alert_email, $feature_meta_snapshot, $is_wildcard);

        // If Config Only -> Save and ZIP just that
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
        self::rewrite_main_plugin_file($plugin_dir, $plugin_slug, $white_label, $version, $domain, $config_filename, $include_config);

        // 6. Generate Documentation
        self::generate_docs($plugin_dir, $domain, $version, $features);

        // 7. Create ZIP Archive
        $zip_filename = "{$plugin_slug}-{$domain_for_files}-{$version}.zip";
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

    public static function generate_config_content($domain, $version, $features, $active_data_file = null, $license_type = 'standard', $license_scope = 'single', $domain_limit = 1, $restrict_features = false, $security_alert_email = '', $feature_meta_snapshot = array(), $is_wildcard = false)
    {
        $alert_email_b64 = ($security_alert_email && function_exists('is_email') && is_email($security_alert_email)) ? base64_encode($security_alert_email) : '';

        $payload = array(
            'build_profile' => 'client',
            'license_type' => (string) $license_type,
            'domain_locked' => ($license_type !== 'developer_unbound') ? (string) $domain : '',
            'build_version' => (string) $version,
            'license_scope' => (string) $license_scope,
            'domain_limit' => intval($domain_limit),
            'security_alert_email_b64' => $alert_email_b64,
            'active_data_file' => (string) ($active_data_file ?: ''),
            'restrict_features' => (bool) $restrict_features,
            'is_wildcard' => (bool) $is_wildcard,
            'features' => array_values(array_map('strval', is_array($features) ? $features : array())),
            'feature_meta' => is_array($feature_meta_snapshot) ? $feature_meta_snapshot : array()
        );

        if ($license_type === 'developer_unbound') {
            $payload['restrict_features'] = false;
        }

        $payload_b64 = base64_encode(json_encode($payload));

        $config = "<?php\n";
        $config .= "/**\n * VAPT Secure Configuration for $domain\n * Build Version: $version\n */\n\n";
        $config .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";
        $config .= "define( 'VAPTSECURE_CONFIG_B64', '" . $payload_b64 . "' );\n";
        $config .= "if ( ! function_exists( 'vaptsecure_apply_config_payload' ) ) {\n";
        $config .= "    function vaptsecure_apply_config_payload( \$payload ) {\n";
        $config .= "        if ( ! is_array( \$payload ) ) { return false; }\n";
        $config .= "        \$license_type = isset( \$payload['license_type'] ) ? (string) \$payload['license_type'] : 'standard';\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_BUILD_PROFILE' ) && isset( \$payload['build_profile'] ) ) { define( 'VAPTSECURE_BUILD_PROFILE', (string) \$payload['build_profile'] ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_LICENSE_TYPE' ) ) { define( 'VAPTSECURE_LICENSE_TYPE', \$license_type ); }\n";
        $config .= "        if ( \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_LOCKED' ) && ! empty( \$payload['domain_locked'] ) ) { define( 'VAPTSECURE_DOMAIN_LOCKED', (string) \$payload['domain_locked'] ); }\n";
        $config .= "        if ( \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_WILDCARD' ) && isset( \$payload['is_wildcard'] ) && \$payload['is_wildcard'] ) { define( 'VAPTSECURE_DOMAIN_WILDCARD', true ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_BUILD_VERSION' ) && isset( \$payload['build_version'] ) ) { define( 'VAPTSECURE_BUILD_VERSION', (string) \$payload['build_version'] ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_LICENSE_SCOPE' ) && isset( \$payload['license_scope'] ) ) { define( 'VAPTSECURE_LICENSE_SCOPE', (string) \$payload['license_scope'] ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_DOMAIN_LIMIT' ) && isset( \$payload['domain_limit'] ) ) { define( 'VAPTSECURE_DOMAIN_LIMIT', intval( \$payload['domain_limit'] ) ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_SECURITY_ALERT_EMAIL' ) && ! empty( \$payload['security_alert_email_b64'] ) ) { define( 'VAPTSECURE_SECURITY_ALERT_EMAIL', base64_decode( (string) \$payload['security_alert_email_b64'] ) ); }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_ACTIVE_DATA_FILE' ) && ! empty( \$payload['active_data_file'] ) ) { define( 'VAPTSECURE_ACTIVE_DATA_FILE', (string) \$payload['active_data_file'] ); }\n";
        $config .= "        \$restrict = ! empty( \$payload['restrict_features'] );\n";
        $config .= "        if ( \$license_type === 'developer_unbound' ) { \$restrict = false; }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_RESTRICT_FEATURES' ) ) { define( 'VAPTSECURE_RESTRICT_FEATURES', (bool) \$restrict ); }\n";
        $config .= "        if ( isset( \$payload['features'] ) && is_array( \$payload['features'] ) ) {\n";
        $config .= "            foreach ( \$payload['features'] as \$key ) {\n";
        $config .= "                \$key = (string) \$key;\n";
        $config .= "                if ( \$key === '' ) { continue; }\n";
        $config .= "                \$const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', \$key ) );\n";
        $config .= "                if ( ! defined( \$const ) ) { define( \$const, true ); }\n";
        $config .= "            }\n";
        $config .= "        }\n";
        $config .= "        if ( ! defined( 'VAPTSECURE_CONFIG_LOADED' ) ) { define( 'VAPTSECURE_CONFIG_LOADED', true ); }\n";
        $config .= "        return true;\n";
        $config .= "    }\n";
        $config .= "}\n";
        $config .= "\$__vaptsecure_payload_json = base64_decode( VAPTSECURE_CONFIG_B64, true );\n";
        $config .= "\$__vaptsecure_payload = \$__vaptsecure_payload_json ? json_decode( \$__vaptsecure_payload_json, true ) : null;\n";
        $config .= "if ( \$__vaptsecure_payload ) { vaptsecure_apply_config_payload( \$__vaptsecure_payload ); }\n";
        $config .= "unset( \$__vaptsecure_payload_json, \$__vaptsecure_payload );\n";
        $config .= "\n";
        $config .= "/* VAPTSECURE_CONFIG_CUSTOM_START - Add custom PHP code below */\n";
        $config .= "/* VAPTSECURE_CONFIG_CUSTOM_END */\n";
        $config .= "\n";
        return $config;
    }

    private static function copy_plugin_files($source, $dest, $active_data_file = null, $generate_type = 'full_build', $build_data = [])
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Determine if config should be included
        $include_config = isset($build_data['include_config']) &&
                          ($build_data['include_config'] === true ||
                           $build_data['include_config'] === 'true' ||
                           $build_data['include_config'] === 1);

        // Core exclusions - development and testing files
        $exclusions = [
            '.git', '.vscode', 'node_modules', 'brain', 'tests', 'vapt-debug.txt',
            '.clinerules', 'null',
            'Implementation Plan', 'plans', 'tools', 'archive', 'Debug', 'backup_debug_cleanup',
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
            $subPath = $iterator->getSubPathName();
            $filename = basename($subPath);

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
                    } else {
                        // Disallow nested folders under /data (keeps the build lean and avoids shipping non-release catalogs)
                        if ($item->isDir()) {
                            continue;
                        }

                        // Only allow a small set of top-level data files
                        $is_top_level_file = (strpos($subPath, 'data/') === 0 || strpos($subPath, 'data\\') === 0) &&
                            (substr_count($subPath, '/') === 1 || substr_count($subPath, '\\') === 1);
                        if (!$is_top_level_file || !in_array($filename, $allowed_data_files, true)) {
                            continue;
                        }
                    }

                    // If this is the active data file, write a filtered version containing only the build's Release features
                    if (!$item->isDir() && (strcasecmp($filename, $active_data_file) === 0)) {
                        $allowed_feature_keys = isset($build_data['features']) && is_array($build_data['features']) ? $build_data['features'] : array();
                        $dest_path = $dest . DIRECTORY_SEPARATOR . $subPath;
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

            // Exclude ZIP files globally
            if (preg_match('/\.zip$/i', $filename)) {
                continue;
            }

            if ($item->isDir()) {
                if (!file_exists($dest . DIRECTORY_SEPARATOR . $subPath)) {
                    mkdir($dest . DIRECTORY_SEPARATOR . $subPath, 0755, true);
                }
            } else {
                copy($item, $dest . DIRECTORY_SEPARATOR . $subPath);
            }
        }
    }

    private static function rewrite_main_plugin_file($plugin_dir, $plugin_slug, $white_label, $version, $domain, $config_filename, $include_config)
    {
        // We need to copy vaptsecure.php to the target filename and modify headers
        // [v2.4.11] Keeping vaptsecure.php as the main plugin file to prevent breaking standard WP expectations
        $source_main = VAPTSECURE_PATH . 'vaptsecure.php';
        $dest_filename = 'vaptsecure.php'; // FORCE vaptsecure.php instead of $plugin_slug . '.php'
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
        $headers .= " */\n";

        // Regex replace the existing header block
        $content = self::safe_preg_replace('/\/\*\*.*?\*\//s', $headers, $content, 1);

        // Remove ALL superadmin functionality from generated builds using more precise patterns
        
        // 1. Stub vaptsecure_get_superadmin_identity()
        // [v2.4.11] Robust stubbing: replace function body with empty identity
        $content = self::safe_preg_replace('/function vaptsecure_get_superadmin_identity\s*\(\)\s*\{[^{}]*\{(?:[^{}]*\{[^{}]*\}[^{}]*|[^{}]*)*\}[\s\S]*?\n\}/s', 'function vaptsecure_get_superadmin_identity() { return array("user" => "none", "email" => "none"); }', $content);

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
            __(\'VAPT Secure\', \'vaptsecure\'),
            __(\'VAPT Secure\', \'vaptsecure\'),
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
            . "    \$message = \"VAPT Secure has been activated on a new site.\\n\\n\";\n"
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
        $guard_code .= "    if (\$__vaptsecure_to && function_exists('wp_mail')) { wp_mail(\$__vaptsecure_to, '[VAPT Secure] Configuration file missing', 'VAPT Secure is disabled because its configuration file is missing.'); }\n";
        $guard_code .= "    add_action('admin_notices', function () {\n";
        $guard_code .= "        if (!current_user_can('manage_options')) { return; }\n";
        $guard_code .= "        echo '<div class=\"notice notice-error\"><p><strong>VAPT Secure:</strong> Required configuration file is missing. This build is disabled.</p></div>';\n";
        $guard_code .= "    });\n";
        $guard_code .= "    add_action('init', function () {\n";
        $guard_code .= "        if (!is_admin()) {\n";
        $guard_code .= "            \$is_api = (defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');\n";
        $guard_code .= "            if (\$is_api) {\n";
        $guard_code .= "                if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }\n";
        $guard_code .= "                echo json_encode(array('success' => false, 'error' => 'config_missing', 'message' => 'VAPT Secure configuration file is missing.'));\n";
        $guard_code .= "                exit;\n";
        $guard_code .= "            }\n";
        $guard_code .= "            wp_die('<h1>VAPT Secure</h1><p>This build is disabled because its configuration file is missing.</p>');\n";
        $guard_code .= "        }\n";
        $guard_code .= "    }, 0);\n";
        $guard_code .= "    return;\n";
        $guard_code .= "}\n\n";

        // [FIX v3.1.1] Support wildcard domain matching - check if is_wildcard flag is passed
        $is_wildcard = isset($data['is_wildcard']) && ($data['is_wildcard'] === true || $data['is_wildcard'] === 'true' || $data['is_wildcard'] === 1 || $data['is_wildcard'] === '1');
        $wildcard_check = $is_wildcard ? "strpos(\$current_host, \$locked_host) === false" : "\$current_host !== \$locked_host";
        
        $guard_code .= "if (defined('VAPTSECURE_DOMAIN_LOCKED') && VAPTSECURE_DOMAIN_LOCKED) {\n";
        $guard_code .= "    \$current_host = isset(\$_SERVER['HTTP_HOST']) ? \$_SERVER['HTTP_HOST'] : '';\n";
        $guard_code .= "    \$current_host = strtolower(preg_replace('/:\\d+$/', '', \$current_host));\n";
        $guard_code .= "    \$locked_host = strtolower(VAPTSECURE_DOMAIN_LOCKED);\n";
        $guard_code .= "    \$is_wildcard_build = defined('VAPTSECURE_DOMAIN_WILDCARD') && VAPTSECURE_DOMAIN_WILDCARD;\n";
        $guard_code .= "    if (strpos(\$current_host, 'www.') === 0) { \$current_host = substr(\$current_host, 4); }\n";
        $guard_code .= "    if (!\$is_wildcard_build && strpos(\$locked_host, 'www.') === 0) { \$locked_host = substr(\$locked_host, 4); }\n";
        $guard_code .= "    \$wildcard_check_result = \$is_wildcard_build ? (strpos(\$current_host, \$locked_host) === false) : (\$current_host !== \$locked_host);\n";
        $guard_code .= "    error_log('VAPT Domain Check: current=' . \$current_host . ' locked=' . \$locked_host . ' wildcard=' . (\$is_wildcard_build ? 'yes' : 'no') . ' result=' . (\$wildcard_check_result ? 'FAIL' : 'PASS'));\n";
        $guard_code .= "    if (\$wildcard_check_result) {\n";
        $guard_code .= "        \$to = (defined('VAPTSECURE_SECURITY_ALERT_EMAIL') && VAPTSECURE_SECURITY_ALERT_EMAIL) ? VAPTSECURE_SECURITY_ALERT_EMAIL : get_option('admin_email');\n";
        $guard_code .= "        if (\$to && function_exists('wp_mail')) {\n";
        $guard_code .= "            \$transient_key = 'vapt_alert_' . md5(\$current_host);\n";
        $guard_code .= "            if (!get_transient(\$transient_key)) {\n";
        $guard_code .= "                \$subject = '[VAPT Secure] Unauthorized Domain Usage Alert';\n";
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

    private static function generate_docs($dir, $domain, $version, $features)
    {
        $readme = "# VAPT Secure Security Build for $domain\n\n";
        $readme .= "Version: $version\n";
        $readme .= "Generated: " . date('Y-m-d') . "\n\n";
        $readme .= "## Active Protection Modules\n";
        foreach ($features as $f) {
            $readme .= "- " . strtoupper(str_replace('-', ' ', $f)) . "\n";
        }
        file_put_contents($dir . '/README.md', $readme);
    }

    private static function add_dir_to_zip($dir, $zip, $zip_path)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $file_path = $file->getRealPath();
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
