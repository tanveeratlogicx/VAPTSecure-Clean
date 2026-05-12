<?php

/**
 * Build Generator for VAPTSecure Clean
 */

if (! defined('ABSPATH')) {
    exit;
}

class VAPTSECURE_Build
{
    private const PACKAGE_POLICY_MINIMUM_RUNTIME = 'minimum_runtime';
    private const PACKAGE_POLICY_AI_SUPPORT = 'ai_support';

    private static function safe_preg_replace($pattern, $replacement, $subject, $limit = -1)
    {
        $result = preg_replace($pattern, $replacement, $subject, $limit);
        if ($result === null) {
            return $subject;
        }
        return $result;
    }

    private static function filter_data_file_for_release($source_path, $dest_path, $allowed_feature_keys = [], $package_policy = self::PACKAGE_POLICY_MINIMUM_RUNTIME)
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

        if (!file_exists($source_path)) {
            return false;
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
                    self::hydrate_platform_implementations($item);
                    $filtered[$risk_key] = $item;
                }
            }
            $data['risk_interfaces'] = $filtered;
        } elseif (isset($data['risks']) && is_array($data['risks'])) {
            $filtered = array();
            foreach ($data['risks'] as $risk_key => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? $risk_key)));
                if ($candidate !== '' && $is_released_and_allowed($candidate)) {
                    self::hydrate_platform_implementations($item);
                    $filtered[$risk_key] = $item;
                }
            }
            $data['risks'] = $filtered;
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

        self::apply_custom_bundle_header($data, $source_path, $package_policy);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return copy($source_path, $dest_path);
        }
        return file_put_contents($dest_path, $json) !== false;
    }

    private static function count_filtered_features($data)
    {
        foreach (array('risk_interfaces', 'risks', 'risk_catalog', 'features', 'wordpress_vapt') as $section) {
            if (isset($data[$section]) && is_array($data[$section])) {
                return count($data[$section]);
            }
        }

        return 0;
    }

    private static function apply_custom_bundle_header(array &$data, $source_path, $package_policy = self::PACKAGE_POLICY_MINIMUM_RUNTIME)
    {
        $filename = basename((string) $source_path);
        if (!in_array($filename, array('interface_schema_v2.0.json', 'vapt_driver_manifest_v2.0.json'), true)) {
            return;
        }

        $feature_count = self::count_filtered_features($data);
        $package_policy = in_array($package_policy, array(self::PACKAGE_POLICY_MINIMUM_RUNTIME, self::PACKAGE_POLICY_AI_SUPPORT), true)
            ? $package_policy
            : self::PACKAGE_POLICY_MINIMUM_RUNTIME;

        $bundle_files = array(
            'pattern_library' => 'enforcer_pattern_library_v2.0.json',
            'interface_schema' => 'interface_schema_v2.0.json',
        );

        if ($package_policy === self::PACKAGE_POLICY_AI_SUPPORT) {
            $bundle_files['ai_agent_instructions'] = 'ai_agent_instructions_v2.0.json';
        }

        if ($filename === 'vapt_driver_manifest_v2.0.json') {
            $bundle_files['driver_manifest'] = 'vapt_driver_manifest_v2.0.json';
        }

        $data['schema_version'] = isset($data['schema_version']) ? (string) $data['schema_version'] : '2.0.0';
        $data['bundle_date'] = current_time('mysql');
        $data['file_role'] = $filename === 'vapt_driver_manifest_v2.0.json' ? 'client_driver_manifest' : 'client_interface_schema';
        $data['bundle_policy'] = $package_policy;
        $data['feature_count'] = $feature_count;
        $data['bundle_files'] = $bundle_files;

        if ($filename === 'interface_schema_v2.0.json') {
            $data['description'] = sprintf(
                'VAPT client interface schema bundle for %d enabled risk(s). platform_implementations.code_ref points to enforcer_pattern_library_v2.0 using consistent lib_key names.',
                $feature_count
            );
        } else {
            $data['description'] = sprintf(
                'VAPT client driver manifest bundle for %d enabled risk(s). Every field is directly usable for client delivery.',
                $feature_count
            );
        }
    }

    public static function get_feature_meta_snapshot_public($feature_keys = [])
    {
        return self::get_feature_meta_snapshot($feature_keys);
    }

    private static function load_catalog_data()
    {
        static $catalog = null;
        if (is_array($catalog)) {
            return $catalog;
        }

        $catalog = array();
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
            if (is_array($data) && !empty($data)) {
                $catalog = $data;
                break;
            }
        }

        return $catalog;
    }

    private static function load_pattern_library_data()
    {
        static $pattern_library = null;
        if (is_array($pattern_library)) {
            return $pattern_library;
        }

        $pattern_library = array();
        $path = VAPTSECURE_PATH . 'data/enforcer_pattern_library_v2.0.json';
        if (!file_exists($path)) {
            return $pattern_library;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data) && !empty($data)) {
            $pattern_library = $data;
        }

        return $pattern_library;
    }

    private static function hydrate_platform_implementations(array &$item)
    {
        if (empty($item['platform_implementations']) || !is_array($item['platform_implementations'])) {
            return;
        }

        $pattern_lib = self::load_pattern_library_data();
        if (empty($pattern_lib)) {
            return;
        }

        foreach ($item['platform_implementations'] as $plat_key => &$plat_data) {
            if (!is_array($plat_data) || empty($plat_data['code_ref'])) {
                continue;
            }

            $code_ref_clean = preg_replace('/^.*?\.patterns\./', 'patterns.', (string) $plat_data['code_ref']);
            $ref_path = explode('.', $code_ref_clean);
            $current_node = $pattern_lib;

            foreach ($ref_path as $node) {
                if (is_array($current_node) && isset($current_node[$node])) {
                    $current_node = $current_node[$node];
                } else {
                    $current_node = null;
                    break;
                }
            }

            if (is_string($current_node)) {
                $plat_data['code'] = $current_node;
            } elseif (is_array($current_node) && isset($current_node['code'])) {
                $plat_data['code'] = $current_node['code'];
                if (isset($current_node['wrapped_code'])) {
                    $plat_data['wrapped_code'] = $current_node['wrapped_code'];
                }
            }
        }
        unset($plat_data);
    }

    private static function find_catalog_feature_item($feature_key)
    {
        $feature_key = strtoupper(trim((string) $feature_key));
        if ($feature_key === '') {
            return array();
        }

        $catalog = self::load_catalog_data();
        if (!is_array($catalog) || empty($catalog)) {
            return array();
        }

        foreach (array('risk_interfaces', 'risks', 'risk_catalog', 'features', 'wordpress_vapt') as $section) {
            if (empty($catalog[$section]) || !is_array($catalog[$section])) {
                continue;
            }

            foreach ($catalog[$section] as $item_key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? $item_key)));
                if ($candidate === $feature_key) {
                    if (!isset($item['feature_key'])) {
                        $item['feature_key'] = $feature_key;
                    }
                    return $item;
                }
            }
        }

        if (isset($catalog[$feature_key]) && is_array($catalog[$feature_key])) {
            $item = $catalog[$feature_key];
            if (!isset($item['feature_key'])) {
                $item['feature_key'] = $feature_key;
            }
            return $item;
        }

        return array();
    }

    private static function normalize_catalog_enforcer_label($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim((string) $value, '_');

        $aliases = array(
            'apache' => 'htaccess',
            'apache_htaccess' => 'htaccess',
            'apache2' => 'htaccess',
            'apache2_htaccess' => 'htaccess',
            'htaccess' => 'htaccess',
            'dot_htaccess' => 'htaccess',
            'wp_config' => 'wp-config',
            'wpconfig' => 'wp-config',
            'wp_config_php' => 'wp-config',
            'wpconfigphp' => 'wp-config',
            'config' => 'wp-config',
            'nginx' => 'nginx',
            'cloudflare' => 'cloudflare',
            'fail2ban' => 'fail2ban',
            'php_functions' => 'php-headers',
            'phpfunctions' => 'php-headers',
            'php_headers' => 'php-headers',
            'phpheaders' => 'php-headers',
            'hook' => 'php-headers',
            'wordpress' => 'php-headers',
            'wordpress_core' => 'php-headers',
            'wordpresscore' => 'php-headers',
            'server_cron' => 'php-cron',
            'servercron' => 'php-cron',
            'php_cron' => 'php-cron',
            'phpcron' => 'php-cron',
            'cron' => 'php-cron',
        );

        return isset($aliases[$value]) ? $aliases[$value] : str_replace('_', '-', $value);
    }

    private static function detect_catalog_primary_enforcer(array $item)
    {
        if (empty($item['platform_implementations']) || !is_array($item['platform_implementations'])) {
            return '';
        }

        foreach ($item['platform_implementations'] as $platform_name => $platform_impl) {
            $candidates = array();
            if (is_array($platform_impl)) {
                foreach (array('lib_key', 'enforcer', 'driver', 'implementation_type', 'operation', 'target_file') as $field) {
                    if (!empty($platform_impl[$field])) {
                        $candidates[] = $platform_impl[$field];
                    }
                }
            } elseif (!empty($platform_impl)) {
                $candidates[] = $platform_impl;
            }

            $candidates[] = $platform_name;

            foreach ($candidates as $candidate) {
                $normalized = self::normalize_catalog_enforcer_label($candidate);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return '';
    }

    private static function detect_catalog_surface_family(array $item, array $feature_meta = array())
    {
        $parts = array(
            isset($item['risk_id']) ? $item['risk_id'] : '',
            isset($item['title']) ? $item['title'] : '',
            isset($item['label']) ? $item['label'] : '',
            isset($item['name']) ? $item['name'] : '',
            isset($item['summary']) ? $item['summary'] : '',
            isset($item['description']) ? $item['description'] : '',
            isset($item['remediation']) ? $item['remediation'] : '',
            !empty($item['platform_implementations']) ? wp_json_encode($item['platform_implementations']) : '',
            !empty($item['available_platforms']) ? wp_json_encode($item['available_platforms']) : '',
            !empty($feature_meta) ? wp_json_encode($feature_meta) : '',
        );
        $blob = strtolower(implode(' ', array_filter(array_map('strval', $parts))));

        if (strpos($blob, '/wp-json/wp/v2/users') !== false || strpos($blob, 'rest api') !== false || strpos($blob, 'wordpress rest api') !== false) {
            return 'rest_users';
        }

        if (strpos($blob, '/?author=1') !== false || strpos($blob, 'author query') !== false || strpos($blob, 'author archives') !== false || strpos($blob, 'author enumeration') !== false) {
            return 'author_query';
        }

        if (strpos($blob, '/wp-login.php') !== false || strpos($blob, 'login_errors') !== false || strpos($blob, 'invalid credentials') !== false) {
            return 'login_error';
        }

        if (strpos($blob, 'xmlrpc') !== false || strpos($blob, 'xml-rpc') !== false || strpos($blob, 'pingback') !== false) {
            return 'xmlrpc';
        }

        if (strpos($blob, 'cron') !== false || strpos($blob, 'wp-cron') !== false) {
            return 'cron';
        }

        return '';
    }

    public static function normalize_client_schema_controls($feature_key, array $schema = array(), array $feature_meta = array())
    {
        if (empty($schema) || !is_array($schema) || empty($schema['controls']) || !is_array($schema['controls'])) {
            return $schema;
        }

        $risk_id = strtoupper(trim((string) ($schema['risk_id'] ?? $feature_key)));
        $surface_family = self::detect_catalog_surface_family($schema, $feature_meta);
        $is_rest_users_risk = ($surface_family === 'rest_users');
        $is_author_query_risk = ($surface_family === 'author_query');
        $is_login_error_risk = ($surface_family === 'login_error');

        $blob_parts = array(
            $feature_key,
            $risk_id,
            isset($schema['title']) ? $schema['title'] : '',
            isset($schema['label']) ? $schema['label'] : '',
            isset($schema['name']) ? $schema['name'] : '',
            isset($schema['summary']) ? $schema['summary'] : '',
            isset($schema['description']) ? $schema['description'] : '',
            isset($schema['remediation']) ? $schema['remediation'] : '',
            !empty($schema['platform_implementations']) ? wp_json_encode($schema['platform_implementations']) : '',
            !empty($schema['available_platforms']) ? wp_json_encode($schema['available_platforms']) : '',
            !empty($feature_meta) ? wp_json_encode($feature_meta) : '',
        );
        $feature_blob = strtolower(implode(' ', array_filter(array_map('strval', $blob_parts))));
        $is_rest_users_surface = $is_rest_users_risk || (bool) preg_match('/rest api|wp-json\/wp\/v2\/users|wordpress rest api/i', $feature_blob);
        $is_author_query_surface = $is_author_query_risk || (bool) preg_match('/author query|author archives|author enumeration|\?author=1/i', $feature_blob);
        $is_login_error_surface = $is_login_error_risk || (bool) preg_match('/wp-login\.php|login_errors|invalid credentials/i', $feature_blob);
        $is_pingback_surface = (bool) preg_match('/pingback|xmlrpc|xml-rpc/i', $feature_blob);
        $is_cron_surface = (bool) preg_match('/cron|wp-cron/i', $feature_blob);

        $normalize_login_control = function (array $control) use ($feature_key) {
            $control['label'] = 'Login Error Consistency Check';
            $control['key'] = 'verify_login_error_disclosure';
            $control['test_logic'] = 'universal_probe';
            $control['test_config'] = array(
                'method' => 'POST',
                'path' => '/wp-login.php',
                'params' => array(
                    'log' => 'vaptsecure_nonexistent_user',
                    'pwd' => 'invalid-password',
                    'wp-submit' => 'Log In',
                    'redirect_to' => home_url('/wp-admin/'),
                    'testcookie' => '1',
                ),
                'expected_status' => array(200),
                'expected_text' => 'Invalid credentials. Please try again.',
                'expected_enforcer' => !empty($control['test_config']['expected_enforcer'])
                    ? $control['test_config']['expected_enforcer']
                    : 'php-headers',
            );
            $control['help'] = 'Verifies wp-login.php returns a generic login error message.';
            return $control;
        };

        $normalize_rest_users_control = function (array $control) {
            $control['label'] = 'REST API Protection Check';
            $control['key'] = 'verify_rest_lockdown';
            $control['test_logic'] = 'universal_probe';
            $control['test_config'] = array(
                'method' => 'GET',
                'path' => '/wp-json/wp/v2/users',
                'expected_status' => array(401, 403, 404, 405),
                'expected_enforcer' => 'php-author-enum',
            );
            $control['help'] = 'Verifies the WordPress Users REST endpoint is protected.';
            return $control;
        };

        $normalize_author_query_control = function (array $control) {
            $control['label'] = 'Author Enumeration Check';
            $control['key'] = 'verify_author_protection';
            $control['test_logic'] = 'universal_probe';
            $control['test_config'] = array(
                'method' => 'GET',
                'path' => '/?author=1',
                'expected_status' => array(403, 404),
                'expected_enforcer' => 'php-author-enum',
            );
            $control['help'] = 'Verifies author enumeration via query string is blocked.';
            return $control;
        };

        $normalize_pingback_control = function (array $control) {
            $control['label'] = 'Test: XML-RPC Pingback Block';
            $control['test_logic'] = 'disable_xmlrpc_pingback';
            return $control;
        };

        $normalize_cron_control = function (array $control) {
            $control['label'] = 'Verify Cron Protection';
            $control['test_logic'] = 'spam_requests';
            $control['test_config'] = array(
                'method' => 'GET',
                'path' => '/wp-cron.php?doing_wp_cron=1',
                'expected_status' => array(403, 429),
                'expected_enforcer' => 'php-cron',
            );
            $control['help'] = 'Verifies that wp-cron.php abuse is blocked.';
            return $control;
        };

        $normalized_controls = array();
        foreach ($schema['controls'] as $control) {
            if (!is_array($control)) {
                continue;
            }

            $label = strtolower((string) ($control['label'] ?? ''));
            $test_logic = strtolower((string) ($control['test_logic'] ?? ''));
            $test_path = strtolower((string) ($control['test_config']['path'] ?? ''));
            $is_test = isset($control['type']) && $control['type'] === 'test_action';

            // [FIX v4.1.5] Skip normalization if an explicit non-root path is already defined
            $has_explicit_path = !empty($control['test_config']['path']) && $control['test_config']['path'] !== '/' && $control['test_config']['path'] !== '/index.php';

            $is_login_candidate = !$has_explicit_path && $is_login_error_surface && $is_test && (
                strpos($label, 'a+ header verification') !== false ||
                strpos($label, 'rest api protection check') !== false ||
                strpos($label, 'author enumeration check') !== false ||
                strpos($label, 'rest user enumeration') !== false ||
                $test_logic === 'check_headers' ||
                $test_logic === 'block_author_enumeration' ||
                $test_logic === 'verify_rest_lockdown' ||
                strpos($test_path, '/wp-json/wp/v2/users') !== false ||
                strpos($test_path, '/?author=1') !== false
            );
            $is_rest_candidate = !$has_explicit_path && $is_rest_users_surface && $is_test && (
                strpos($label, 'a+ header verification') !== false ||
                strpos($label, 'author enumeration check') !== false ||
                strpos($label, 'rest api protection check') !== false ||
                strpos($label, 'rest user enumeration') !== false ||
                $test_logic === 'check_headers' ||
                $test_logic === 'block_author_enumeration' ||
                $test_logic === 'verify_rest_lockdown' ||
                strpos($test_path, '/wp-login.php') !== false ||
                strpos($test_path, '/?author=1') !== false
            );
            $is_author_candidate = !$has_explicit_path && $is_author_query_surface && $is_test && (
                strpos($label, 'a+ header verification') !== false ||
                strpos($label, 'rest api protection check') !== false ||
                strpos($label, 'author enumeration check') !== false ||
                $test_logic === 'check_headers' ||
                $test_logic === 'block_author_enumeration' ||
                strpos($test_path, '/wp-json/wp/v2/users') !== false ||
                strpos($test_path, '/wp-login.php') !== false
            );
            $is_pingback_candidate = !$has_explicit_path && $is_pingback_surface && $is_test && (
                $test_logic === 'check_headers' ||
                $test_logic === 'block_xmlrpc' ||
                strpos($label, 'xml-rpc') !== false ||
                strpos($test_path, 'xmlrpc.php') !== false
            );
            $is_cron_candidate = !$has_explicit_path && $is_cron_surface && $is_test && (
                $test_logic === 'check_headers' ||
                $test_logic === 'spam_requests' ||
                strpos($label, 'cron') !== false ||
                strpos($test_path, 'wp-cron.php') !== false
            );

            if ($is_login_candidate) {
                $control = $normalize_login_control($control);
            } elseif ($is_rest_candidate) {
                $control = $normalize_rest_users_control($control);
            } elseif ($is_author_candidate) {
                $control = $normalize_author_query_control($control);
            } elseif ($is_pingback_candidate) {
                $control = $normalize_pingback_control($control);
            } elseif ($is_cron_candidate) {
                $control = $normalize_cron_control($control);
            }

            $dedupe_key = strtolower(
                (string) ($control['type'] ?? '') . ':' .
                (string) ($control['key'] ?? '') . ':' .
                (string) ($control['label'] ?? '') . ':' .
                (string) ($control['test_logic'] ?? '')
            );
            if (isset($normalized_controls[$dedupe_key])) {
                continue;
            }
            $normalized_controls[$dedupe_key] = $control;
        }

        $schema['controls'] = array_values($normalized_controls);
        return $schema;
    }

    private static function synthesize_feature_meta_from_catalog($feature_key)
    {
        $feature_key = strtoupper(trim((string) $feature_key));
        if ($feature_key === '') {
            return array();
        }

        $catalog_item = self::find_catalog_feature_item($feature_key);
        if (empty($catalog_item)) {
            return array();
        }

        $catalog_item['feature_key'] = $feature_key;
        if (!isset($catalog_item['risk_id']) || trim((string) $catalog_item['risk_id']) === '') {
            $catalog_item['risk_id'] = $feature_key;
        }
        self::hydrate_platform_implementations($catalog_item);
        $catalog_item = self::normalize_client_schema_controls($feature_key, $catalog_item, array());

        $risk_suffix = str_replace('-', '_', strtolower($feature_key));
        $implementation_data = array(
            'enabled' => 1,
            'feat_enabled' => 1,
            'prot_enabled' => 1,
        );
        if ($risk_suffix !== '') {
            $implementation_data['vapt_risk_' . $risk_suffix . '_enabled'] = 1;
        }

        $generated_schema_json = json_encode($catalog_item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $implementation_data_json = json_encode($implementation_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return array(
            'generated_schema_b64' => is_string($generated_schema_json) ? base64_encode($generated_schema_json) : '',
            'implementation_data_b64' => is_string($implementation_data_json) ? base64_encode($implementation_data_json) : '',
            'override_schema_b64' => '',
            'override_implementation_data_b64' => '',
            'include_test_method' => !empty($catalog_item['detection']) ? 1 : 0,
            'include_verification' => 1,
            'include_verification_engine' => !empty($catalog_item['platform_implementations']) ? 1 : 0,
            'include_verification_guidance' => 1,
            'include_manual_protocol' => 1,
            'include_operational_notes' => 1,
            'wireframe_url' => '',
            'dev_instruct' => '',
            'is_adaptive_deployment' => !empty($catalog_item['platform_implementations']) ? 1 : 0,
            'active_enforcer' => self::detect_catalog_primary_enforcer($catalog_item),
            'is_enabled' => 1,
            'is_enforced' => 1,
        );
    }

    /**
     * Trim feature_meta to only runtime-essential fields.
     * Removes: override_schema, override_implementation_data, dev_instruct, wireframe_url, generated_schema blob.
     */
    private static function trim_feature_meta(array $meta)
    {
        $allowed = array(
            'feature_key' => true,
            'active_enforcer' => true,
            'is_enabled' => true,
            'is_enforced' => true,
            'platform_implementations' => true,
            'test_config' => true,
        );
        $out = array();
        foreach ($meta as $key => $value) {
            if (isset($allowed[$key])) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Build-time validation: verify active_enforcer and hydrated code integrity.
     * Throws Exception on failure so the build aborts.
     */
    private static function validate_feature_build(array $feature_meta, array $features)
    {
        $canonical_platforms = array(
            'htaccess' => true,
            'nginx' => true,
            'php_functions' => true,
            'wp_config' => true,
            'apache' => true,
            'cloudflare' => true,
            'fail2ban' => true,
            'server_cron' => true,
            'wordpress_core' => true,
        );

        foreach ($features as $feature_key) {
            $key = strtoupper(trim((string) $feature_key));
            if ($key === '') {
                continue;
            }
            $meta = isset($feature_meta[$key]) ? $feature_meta[$key] : array();
            $active_enforcer = isset($meta['active_enforcer']) ? (string) $meta['active_enforcer'] : '';

            if ($active_enforcer === '') {
                throw new Exception("Build validation failed: feature {$key} has empty active_enforcer.");
            }

            $enforcer_normalized = strtolower(str_replace(array('-', ' '), '_', $active_enforcer));
            if (!isset($canonical_platforms[$enforcer_normalized])) {
                throw new Exception("Build validation failed: feature {$key} has unknown active_enforcer '{$active_enforcer}'.");
            }

            $platform_impl = isset($meta['platform_implementations']) && is_array($meta['platform_implementations']) ? $meta['platform_implementations'] : array();
            if (empty($platform_impl)) {
                $code = isset($meta['code']) ? (string) $meta['code'] : '';
                if ($code === '') {
                    // Allow scan-only features (no file enforcer) to pass with a warning-level check
                    continue;
                }
            }
        }

        return true;
    }

    /**
     * Generate a per-feature build manifest for tamper-evident integrity.
     */
    private static function generate_build_manifest($domain, $version, array $feature_meta, array $features)
    {
        $manifest = array(
            'build_domain' => (string) $domain,
            'build_version' => (string) $version,
            'build_at' => current_time('mysql'),
            'feature_count' => count($features),
            'features' => array(),
        );

        foreach ($features as $feature_key) {
            $key = strtoupper(trim((string) $feature_key));
            if ($key === '') {
                continue;
            }
            $meta = isset($feature_meta[$key]) ? $feature_meta[$key] : array();
            $active_enforcer = isset($meta['active_enforcer']) ? (string) $meta['active_enforcer'] : '';
            $platform_impl = isset($meta['platform_implementations']) && is_array($meta['platform_implementations']) ? $meta['platform_implementations'] : array();
            $code = isset($meta['code']) ? (string) $meta['code'] : '';
            $code_hash = $code !== '' ? hash('sha256', $code) : '';

            $manifest['features'][] = array(
                'feature_key' => $key,
                'active_enforcer' => $active_enforcer,
                'code_hash' => $code_hash,
                'platform_count' => count($platform_impl),
            );
        }

        return $manifest;
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
            $rows = array();
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

            $generated_schema = array();
            if (isset($row['generated_schema']) && is_string($row['generated_schema']) && trim($row['generated_schema']) !== '') {
                $decoded_schema = json_decode($row['generated_schema'], true);
                if (is_array($decoded_schema)) {
                    self::hydrate_platform_implementations($decoded_schema);
                    $decoded_schema = self::normalize_client_schema_controls($key, $decoded_schema, array());
                    $generated_schema = $decoded_schema;
                }
            }

            $out[$key] = array(
                'generated_schema_b64' => !empty($generated_schema) ? base64_encode(json_encode($generated_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : (isset($row['generated_schema']) && is_string($row['generated_schema']) ? base64_encode($row['generated_schema']) : ''),
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

        foreach ($normalized as $feature_key) {
            $existing = isset($out[$feature_key]) ? $out[$feature_key] : array();
            if (empty($existing) || empty($existing['generated_schema_b64']) || empty($existing['implementation_data_b64'])) {
                $synth = self::synthesize_feature_meta_from_catalog($feature_key);
                if (!empty($synth)) {
                    $out[$feature_key] = $synth;
                }
            }
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
        $master_plugin_name = self::get_master_plugin_name();
        $client_plugin_name = trim((string) ($white_label['name'] ?? ''));
        if ($client_plugin_name === '' || (trim((string) $master_plugin_name) !== '' && strcasecmp($client_plugin_name, $master_plugin_name) === 0)) {
            $client_plugin_name = 'VAPTSecure';
        }
        $white_label['name'] = $client_plugin_name;
        $white_label['text_domain'] = trim((string) ($white_label['text_domain'] ?? ''));
        if ($white_label['text_domain'] === '' || strcasecmp(sanitize_title($white_label['text_domain']), sanitize_title((string) $master_plugin_name)) === 0) {
            $white_label['text_domain'] = sanitize_title($client_plugin_name) . '-client';
        }
        $generate_type = isset($data['generate_type']) ? $data['generate_type'] : 'full_build';
        $license_type = isset($data['license_type']) ? sanitize_text_field($data['license_type']) : 'standard';
        $security_alert_email = isset($data['security_alert_email']) ? sanitize_email((string) $data['security_alert_email']) : '';
        $is_universal_domain = ($domain === '*') || (is_string($domain) && strpos($domain, '__universal__:') === 0);
        $domain_for_files = $is_universal_domain ? 'universal' : $domain;
        $package_policy = self::normalize_package_policy($data);
        
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
        $include_data = $generate_type !== 'config_only';

        // 2. Output Config Content (Generated)
        // [FIX v2.4.11] Always identify the active data file so the UI works in generated builds
        $active_data_file_name = $include_data ? get_option('vaptsecure_active_feature_file', 'interface_schema_v2.0.json') : null;
        if ($include_data && !$active_data_file_name) {
            $active_data_file_name = 'interface_schema_v2.0.json';
        }
        
        $license_scope = isset($data['license_scope']) ? $data['license_scope'] : 'single';
        $domain_limit = isset($data['installation_limit']) ? intval($data['installation_limit']) : 1;
        $restrict_features = isset($data['restrict_features']) ? (bool) $data['restrict_features'] : false;
        $require_wp = isset($data['require_wp']) ? sanitize_text_field($data['require_wp']) : '6.0';
        $require_php = isset($data['require_php']) ? sanitize_text_field($data['require_php']) : '7.4.33';

        // Get snapshot of feature metadata (configs, schemas, etc)
        $feature_meta_snapshot = array();
        if ($include_config) {
            $raw_snapshot = self::get_feature_meta_snapshot($features);
            // Trim to runtime-essential fields only (Phase 4: reduce sensitive payload)
            foreach ($raw_snapshot as $fk => $fm) {
                $feature_meta_snapshot[$fk] = self::trim_feature_meta($fm);
            }
            // Build-time validation: abort if active_enforcer is missing or invalid (Phase 2: build integrity)
            self::validate_feature_build($feature_meta_snapshot, $features);
        }

        $config_content = self::generate_config_content(
            $domain, $version, $features, $active_data_file_name, 
            $license_type, $license_scope, $domain_limit, 
            $restrict_features, $security_alert_email, 
            $feature_meta_snapshot, $is_wildcard_flag,
            $require_wp, $require_php, (string) ($white_label['name'] ?? '')
        );  // If Config Only -> Save and ZIP just that
        if ($generate_type === 'config_only') {
            $config_filename = "vapt-{$domain_for_files}-config-{$version}.php";
            file_put_contents($build_dir . '/' . $config_filename, $config_content);
            return $build_dir . '/' . $config_filename; // Return path to file directly
        }

        // 3. Full Build: Copy Plugin Files Recursively
        self::copy_plugin_files(VAPTSECURE_PATH, $plugin_dir, $active_data_file_name, $generate_type, $data, $package_policy);

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

        // 8. Generate Build Manifest (Phase 2: per-feature integrity)
        if ($include_config) {
            $manifest = self::generate_build_manifest($domain, $version, $feature_meta_snapshot, $features);
            $manifest_json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            file_put_contents($plugin_dir . '/build-manifest.json', $manifest_json);
        }

        // 9. Create ZIP Archive
        $master_plugin_name = sanitize_title((string) self::get_master_plugin_name());
        if ($master_plugin_name === '') {
            $master_plugin_name = 'vaptsecure-clean';
        }
        $zip_domain = sanitize_title((string) $domain_for_files);
        if ($zip_domain === '') {
            $zip_domain = $plugin_slug;
        }
        $zip_filename = "{$master_plugin_name}-{$zip_domain}_v{$version}.zip";
        $zip_path = $build_dir . '/' . $zip_filename;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->setArchiveComment(self::build_archive_comment($domain, $version, $white_label['name'], $features));
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
                $temp_dir,
                PCLZIP_OPT_COMMENT,
                self::build_archive_comment($domain, $version, $white_label['name'], $features)
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

    public static function generate_config_content($domain, $version, $features, $active_data_file = null, $license_type = 'standard', $license_scope = 'single', $domain_limit = 1, $restrict_features = false, $security_alert_email = '', $feature_meta_snapshot = array(), $is_wildcard = false, $require_wp = '6.0', $require_php = '7.4.33', $plugin_name = '')
    {
        $alert_email_b64 = ($security_alert_email && function_exists('is_email') && is_email($security_alert_email)) ? base64_encode($security_alert_email) : '';
        $plugin_name = trim((string) $plugin_name);
        $menu_slug = sanitize_title($plugin_name);
        if ($menu_slug !== '') {
            $menu_slug .= '-client';
        }

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
            'plugin_name' => $plugin_name,
            'menu_slug' => $menu_slug,
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
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_PLUGIN_NAME' ) && isset( \$payload['plugin_name'] ) && \$payload['plugin_name'] !== '' ) { define( 'VAPTSECURE_PLUGIN_NAME', (string) \$payload['plugin_name'] ); }\n";
        $config .= "        if ( \$is_default && ! defined( 'VAPTSECURE_MENU_SLUG' ) && isset( \$payload['menu_slug'] ) && \$payload['menu_slug'] !== '' ) { define( 'VAPTSECURE_MENU_SLUG', sanitize_title( (string) \$payload['menu_slug'] ) ); }\n";
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

    private static function normalize_package_policy($build_data = array())
    {
        if (!is_array($build_data)) {
            return self::PACKAGE_POLICY_MINIMUM_RUNTIME;
        }

        $policy = isset($build_data['package_policy']) ? strtolower(trim((string) $build_data['package_policy'])) : '';
        if ($policy === self::PACKAGE_POLICY_AI_SUPPORT) {
            return self::PACKAGE_POLICY_AI_SUPPORT;
        }

        if (!empty($build_data['include_ai_support']) || !empty($build_data['include_ai_instructions'])) {
            return self::PACKAGE_POLICY_AI_SUPPORT;
        }

        return self::PACKAGE_POLICY_MINIMUM_RUNTIME;
    }

    private static function get_package_policy_data_files($package_policy, $active_data_file = null)
    {
        $data_files = array(
            'enforcer_pattern_library_v2.0.json',
        );

        if (is_string($active_data_file) && trim($active_data_file) !== '') {
            $data_files[] = ltrim(str_replace('\\', '/', trim($active_data_file)), '/');
        } else {
            $data_files[] = 'interface_schema_v2.0.json';
        }

        if ($package_policy === self::PACKAGE_POLICY_AI_SUPPORT) {
            $data_files[] = 'ai_agent_instructions_v2.0.json';
        }

        return array_values(array_unique($data_files));
    }

    private static function copy_plugin_files($source, $dest, $active_data_file = null, $generate_type = 'full_build', $build_data = [], $package_policy = self::PACKAGE_POLICY_MINIMUM_RUNTIME)
    {
        $source = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $source), DIRECTORY_SEPARATOR);
        $dest = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $dest), DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $package_policy = self::normalize_package_policy($build_data) ?: $package_policy;
        $data_policy_files = self::get_package_policy_data_files($package_policy, $active_data_file);

        $allowed_exact_paths = array(
            'README.md',
            'LICENSE',
            'LICENSE.txt',
            'LICENSE.md',
            'uninstall.php',
            'vapt-functions.php',
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

        foreach ($data_policy_files as $data_file) {
            $allowed_exact_paths[] = 'data/' . ltrim(str_replace('\\', '/', (string) $data_file), '/');
        }
        $allowed_exact_paths = array_values(array_unique($allowed_exact_paths));

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
                    $allowed_data_files = $data_policy_files;

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
                    if (
                        !$item->isDir() &&
                        strcasecmp($filename, $active_data_file) === 0
                    ) {
                        $allowed_feature_keys = isset($build_data['features']) && is_array($build_data['features']) ? $build_data['features'] : array();
                        $dest_path = $dest . DIRECTORY_SEPARATOR . $nativeSubPath;
                        $dest_dir = dirname($dest_path);
                        if (!file_exists($dest_dir)) {
                            mkdir($dest_dir, 0755, true);
                        }
                        self::filter_data_file_for_release((string) $item, $dest_path, $allowed_feature_keys, $package_policy);
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
                $dest_file_path = $dest . DIRECTORY_SEPARATOR . $nativeSubPath;
                $dest_file_dir = dirname($dest_file_path);
                if (!file_exists($dest_file_dir)) {
                    mkdir($dest_file_dir, 0755, true);
                }
                copy($item, $dest_file_path);
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
        $menu_label = trim((string) ($white_label['name'] ?? ''));
        if ($menu_label === '') {
            $menu_label = self::get_master_plugin_name();
        }
        if ($menu_label === '') {
            $menu_label = 'VAPTSecure Clean';
        }
        $menu_label_escaped = addslashes($menu_label);

        $menu_slug = trim((string) $plugin_slug);
        if ($menu_slug === '') {
            $menu_slug = sanitize_title((string) ($white_label['text_domain'] ?: $menu_label));
        }
        if ($menu_slug === '') {
            $menu_slug = 'vaptsecure';
        }
        $menu_slug = sanitize_title($menu_slug);
        if ($menu_slug !== '' && !preg_match('/-client$/', $menu_slug)) {
            $menu_slug .= '-client';
        }
        $menu_slug_escaped = addslashes($menu_slug);

        $content = self::safe_preg_replace('/\$is_superadmin_identity = is_vaptsecure_superadmin\(false\);[\s\S]*?remove_submenu_page\(\s*["\']vaptsecure["\']\s*,\s*["\']vaptsecure["\']\s*\);\s*/s', '// 1. Parent Menu (Visible to all admins)
        add_menu_page(
            __(\'' . $menu_label_escaped . '\', \'vaptsecure\'),
            __(\'' . $menu_label_escaped . '\', \'vaptsecure\'),
            \'manage_options\',
            \'' . $menu_slug_escaped . '\',
            \'vaptsecure_render_client_status_page\',
            \'dashicons-shield\',
            80
        );
        remove_submenu_page(\'' . $menu_slug_escaped . '\', \'' . $menu_slug_escaped . '\');', $content);
        
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
        $content = self::safe_preg_replace('/^\s*require_once\s+VAPTSECURE_PATH\s*\.\s*"includes\/self-check\/[^"]+";\s*$/m', '', $content);
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
        $content = self::safe_preg_replace('/^\s*if\s*\(\s*!\s*defined\s*\(\s*\'ABSPATH\'\s*\)\s*\)\s*\{\s*exit;\s*\}\s*$/m', "$0\n" . $guard_code, $content, 1);

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
            foreach (array('risk_interfaces', 'risks', 'risk_catalog', 'features', 'wordpress_vapt') as $section) {
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

    private static function feature_titles_from_keys($features, $title_map)
    {
        $lines = array();
        if (!is_array($features)) {
            return $lines;
        }

        foreach ($features as $feature_key) {
            $title = self::feature_title_from_key($feature_key, $title_map);
            if ($title === '') {
                continue;
            }
            $lines[] = $title;
        }

        return array_values(array_unique($lines));
    }

    private static function get_master_plugin_name()
    {
        $source = VAPTSECURE_PATH . 'vaptsecure.php';
        if (file_exists($source)) {
            $contents = file_get_contents($source);
            if ($contents !== false && preg_match('/^\s*\*\s*Plugin Name:\s*(.+)$/mi', $contents, $matches)) {
                $name = trim((string) $matches[1]);
                if ($name !== '') {
                    return $name;
                }
            }

            if (function_exists('get_file_data')) {
                $headers = @get_file_data($source, array('Name' => 'Plugin Name'), 'plugin');
                if (is_array($headers) && !empty($headers['Name'])) {
                    $name = trim((string) $headers['Name']);
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        }

        return 'VAPTSecure Clean';
    }

    private static function get_builder_version()
    {
        if (defined('VAPTSECURE_VERSION') && is_string(VAPTSECURE_VERSION) && VAPTSECURE_VERSION !== '') {
            return (string) VAPTSECURE_VERSION;
        }

        $source = VAPTSECURE_PATH . 'vaptsecure.php';
        if (file_exists($source)) {
            $contents = file_get_contents($source);
            if ($contents !== false && preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $matches)) {
                $version = trim((string) $matches[1]);
                if ($version !== '') {
                    return $version;
                }
            }

            if (function_exists('get_file_data')) {
                $headers = @get_file_data($source, array('Version' => 'Version'), 'plugin');
                if (is_array($headers) && !empty($headers['Version'])) {
                    $version = trim((string) $headers['Version']);
                    if ($version !== '') {
                        return $version;
                    }
                }
            }
        }

        return 'unknown';
    }

    private static function get_builder_commit_id()
    {
        $repo_root = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, VAPTSECURE_PATH), DIRECTORY_SEPARATOR);
        $git_dir = $repo_root . DIRECTORY_SEPARATOR . '.git';
        if (!is_dir($git_dir) || !function_exists('shell_exec')) {
            return 'unknown';
        }

        $command = 'git -C ' . escapeshellarg($repo_root) . ' rev-parse --short HEAD 2>NUL';
        $commit = trim((string) shell_exec($command));
        return $commit !== '' ? $commit : 'unknown';
    }

    private static function build_archive_comment($domain, $version, $plugin_name, $features)
    {
        $title_map = self::get_feature_title_map();
        $feature_lines = self::feature_titles_from_keys($features, $title_map);
        $feature_block = array();
        if (!empty($feature_lines)) {
            foreach ($feature_lines as $index => $title) {
                $feature_block[] = ($index + 1) . '. ' . $title;
            }
        }

        $comment_lines = array(
            self::get_master_plugin_name(),
            'Build Time: ' . current_time('mysql'),
            'Builder Version: ' . self::get_builder_version(),
            'Builder Commit: ' . self::get_builder_commit_id(),
            '',
            'Generated Build Version: ' . (string) $version,
            'Target Domain: ' . (string) $domain,
            'Plugin Name: ' . (string) $plugin_name,
            '',
        );

        if (!empty($feature_block)) {
            $comment_lines = array_merge($comment_lines, $feature_block);
        }

        return implode("\n", $comment_lines);
    }

    private static function generate_docs($dir, $domain, $version, $features)
    {
        $readme = "# VAPTSecure Clean Security Build for $domain\n\n";
        $readme .= "Version: $version\n";
        $readme .= "Generated: " . date('Y-m-d') . "\n\n";
        $readme .= "## Active Protection Modules\n";
        $title_map = self::get_feature_title_map();
        $lines = self::feature_titles_from_keys($features, $title_map);
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
