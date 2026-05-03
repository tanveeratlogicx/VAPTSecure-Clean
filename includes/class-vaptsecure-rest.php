<?php

/**
 * REST API Handler for VAPTSecure Clean
 */

if (! defined('ABSPATH')) {
    exit;
}

class VAPTSECURE_REST
{
    private static $cached_pattern_library = null;

    private static function bump_semver_patch($version)
    {
        $version = trim((string) $version);
        if ($version === '') {
            return '1.0.0';
        }
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) {
            return '1.0.0';
        }
        $major = (int) $m[1];
        $minor = (int) $m[2];
        $patch = (int) $m[3];
        $patch++;
        return $major . '.' . $minor . '.' . $patch;
    }

    private static function domain_version_key($domain)
    {
        $domain = strtolower(trim((string) $domain));
        return 'vaptsecure_last_build_version_' . md5($domain);
    }

    private static function get_cached_pattern_library()
    {
        if (self::$cached_pattern_library === null) {
            $pattern_lib_path = VAPTSECURE_PATH . 'data/enforcer_pattern_library_v2.0.json';
            if (file_exists($pattern_lib_path)) {
                self::$cached_pattern_library = json_decode(file_get_contents($pattern_lib_path), true);
            } else {
                self::$cached_pattern_library = array();
            }
        }
        return self::$cached_pattern_library;
    }

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route(
            'vaptsecure/v1', '/features', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_features'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/data-files/all', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_data_files'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/data-files', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_data_files'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/update-hidden-files', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_hidden_files'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/data-files/remove', array(
            'methods' => 'POST',
            'callback' => array($this, 'remove_data_file'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/reset-limit', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_rate_limit'),
            'permission_callback' => '__return_true', // Public endpoint for testing (limited to user IP)
            )
        );


        register_rest_route(
            'vaptsecure/v1', '/features/update', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_feature'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/transition', array(
            'methods'  => 'POST',
            'callback' => array($this, 'transition_feature'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/(?P<key>[a-zA-Z0-9_-]+)/history', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_feature_history'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/(?P<key>[a-zA-Z0-9_-]+)/stats', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_feature_stats'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/(?P<key>[a-zA-Z0-9_-]+)/verify', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'verify_implementation'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/(?P<key>[a-zA-Z0-9_-]+)/reset', array(
            'methods'  => 'POST',
            'callback' => array($this, 'reset_feature_stats'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/assignees', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_assignees'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/assign', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_assignment'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/upload-json', array(
            'methods'  => 'POST',
            'callback' => array($this, 'upload_json'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/domains', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_domains'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/domains/update', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_domain'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/domains/features', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_domain_features'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/domains/delete', array(
            'methods'  => 'DELETE',
            'callback' => array($this, 'delete_domain'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/domains/batch-delete', array(
                'methods' => 'POST',
                'callback' => array($this, 'batch_delete_domains'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );

        // License status check endpoint
        register_rest_route(
            'vaptsecure/v1', '/license/status', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_license_status'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );

        // Manual restore from cache endpoint
        register_rest_route(
            'vaptsecure/v1', '/license/restore', array(
                'methods' => 'POST',
                'callback' => array($this, 'restore_license_cache'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );

        // Force license check endpoint
        register_rest_route(
            'vaptsecure/v1', '/license/check', array(
                'methods' => 'POST',
                'callback' => array($this, 'force_license_check'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/build/generate', array(
            'methods'  => 'POST',
            'callback' => array($this, 'generate_build'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'installation_limit' => array(
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'license_scope' => array(
                    'type' => 'string',
                    'default' => 'single',
                ),
                'include_config' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
                'include_data' => array(
                    'type' => 'boolean',
                    'default' => false,
                ),
            ),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/build/save-config', array(
            'methods'  => 'POST',
            'callback' => array($this, 'save_config_to_root'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/settings/enforcement', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_global_enforcement'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/settings/enforcement', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_global_enforcement'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/upload-media', array(
            'methods'  => 'POST',
            'callback' => array($this, 'upload_media'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/data-files/meta', array(
            'methods'  => 'POST',
            'callback' => array($this, 'update_file_meta'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/active-file', array(
            'methods'  => array('GET', 'POST'),
            'callback' => array($this, 'handle_active_file'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/build/sync-config', array(
            'methods'  => 'POST',
            'callback' => array($this, 'sync_config_from_file'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );


        register_rest_route(
            'vaptsecure/v1', '/ping', array(
            'methods'  => 'GET',
            'callback' => function () {
                return new WP_REST_Response(['pong' => true], 200);
            },
            'permission_callback' => '__return_true',
            )
        );

        // v1.9.2 – Batch Revert Develop → Draft (Preview & Execute)
        register_rest_route(
            'vaptsecure/v1', '/features/preview-revert', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'preview_revert_to_draft'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/features/batch-revert', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'batch_revert_to_draft'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/clear-cache', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'clear_enforcement_cache'),
            'permission_callback' => array($this, 'check_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/security/stats', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_security_stats'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );

        register_rest_route(
            'vaptsecure/v1', '/security/logs', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_security_logs'),
            'permission_callback' => array($this, 'check_read_permission'),
            )
        );
    }

    public function check_permission()
    {
        $is_super = is_vaptsecure_superadmin();
        if (!$is_super) {
            $uid = get_current_user_id();
            $user = get_userdata($uid);
            $login = $user ? $user->user_login : 'unknown';
            error_log("VAPTSECURE_REST: check_permission FAILED for user ID $uid ($login). Superadmin status required.");
        }
        return $is_super;
    }

    public function check_read_permission()
    {
        $is_super = is_vaptsecure_superadmin();
        $can_manage = current_user_can('manage_options');
        $uid = get_current_user_id();
        $user = get_userdata($uid);
        $login = $user ? $user->user_login : 'unknown';

        // Debug logging
        error_log("VAPTSECURE_REST: check_read_permission - User ID: $uid ($login), is_super: " . ($is_super ? 'true' : 'false') . ", can_manage: " . ($can_manage ? 'true' : 'false'));

        if (!$is_super && !$can_manage) {
            error_log("VAPTSECURE_REST: check_read_permission FAILED for user ID $uid ($login). 'manage_options' capability required.");
        }
        return $is_super || $can_manage;
    }

    public function get_global_enforcement($request)
    {
        if (!class_exists('VAPTSECURE_DB')) {
            return new WP_REST_Response(array('error' => 'DB helper not loaded'), 500);
        }

        $enabled = VAPTSECURE_DB::get_global_enforcement();
        return new WP_REST_Response(array('enabled' => (bool) $enabled), 200);
    }

    public function update_global_enforcement($request)
    {
        if (!class_exists('VAPTSECURE_DB')) {
            return new WP_REST_Response(array('error' => 'DB helper not loaded'), 500);
        }

        $enabled_raw = $request->get_param('enabled');
        $enabled = filter_var($enabled_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            return new WP_REST_Response(array('error' => 'Invalid enabled value'), 400);
        }

        $ok = VAPTSECURE_DB::update_global_enforcement($enabled ? 1 : 0);
        delete_transient('vaptsecure_active_enforcements');

        return new WP_REST_Response(array('success' => (bool) $ok, 'enabled' => (bool) $enabled), 200);
    }

    public function get_security_stats($request)
    {
        if (!class_exists('VAPTSECURE_DB')) {
            return new WP_REST_Response(array('error' => 'DB helper not loaded'), 500);
        }

        $stats = VAPTSECURE_DB::get_security_stats_summary();
        return new WP_REST_Response($stats, 200);
    }

    public function get_security_logs($request)
    {
        if (!class_exists('VAPTSECURE_DB')) {
            return new WP_REST_Response(array('error' => 'DB helper not loaded'), 500);
        }

        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $offset = (int) $request->get_param('offset');
        if ($offset < 0) {
            $offset = 0;
        }

        $events = VAPTSECURE_DB::get_security_events($limit, $offset);
        return new WP_REST_Response($events, 200);
    }

    public function verify_implementation($request)
    {
        if (!class_exists('VAPTSECURE_DB')) {
            return new WP_REST_Response(array('error' => 'DB helper not loaded'), 500);
        }

        $key = sanitize_text_field((string) $request->get_param('key'));
        if ($key === '') {
            return new WP_REST_Response(array('error' => 'Missing feature key'), 400);
        }

        $meta = VAPTSECURE_DB::get_feature_meta($key);
        if (!$meta) {
            return new WP_REST_Response(array('error' => 'Feature not found', 'key' => $key), 404);
        }

        $status = isset($meta['status']) ? strtolower((string) $meta['status']) : 'draft';
        $raw_schema = ($status === 'test' && !empty($meta['override_schema'])) ? $meta['override_schema'] : ($meta['generated_schema'] ?? null);
        $schema = $raw_schema ? json_decode($raw_schema, true) : array();
        $raw_impl = ($status === 'test' && !empty($meta['override_implementation_data'])) ? $meta['override_implementation_data'] : ($meta['implementation_data'] ?? null);
        $implementation_data = $raw_impl ? json_decode($raw_impl, true) : array();
        if (!is_array($implementation_data)) {
            $implementation_data = array();
        }

        $runtime_verified = false;
        if (class_exists('VAPTSECURE_Hook_Driver')) {
            $runtime_verified = (bool) VAPTSECURE_Hook_Driver::verify($key, $implementation_data, is_array($schema) ? $schema : array());
        }

        $blob = strtolower(trim(
            (string) $key . ' ' .
            (string) ($schema['title'] ?? '') . ' ' .
            (string) ($schema['summary'] ?? '') . ' ' .
            (string) ($schema['description'] ?? '')
        ));

        $probe = array(
            'path' => '/',
            'method' => 'GET',
            'expected_statuses' => array(200),
            'expected_enforcer' => null,
        );

        if (strpos($blob, 'cron') !== false) {
            $probe['path'] = '/wp-cron.php';
            $probe['expected_statuses'] = array(403);
            $probe['expected_enforcer'] = 'php-cron';
        } elseif (strpos($blob, 'xmlrpc') !== false || strpos($blob, 'xml-rpc') !== false || strpos($blob, 'pingback') !== false) {
            $probe['path'] = '/xmlrpc.php';
            $probe['method'] = 'POST';
            $probe['expected_statuses'] = array(401, 403, 404, 405);
            $probe['expected_enforcer'] = 'php-xmlrpc';
        } elseif (strpos($blob, 'username enumeration') !== false
            || strpos($blob, 'user enumeration') !== false
            || strpos($blob, 'rest api') !== false
            || strpos($blob, '/wp-json/wp/v2/users') !== false
        ) {
            $probe['path'] = '/wp-json/wp/v2/users';
            $probe['expected_statuses'] = array(401, 403, 404, 405);
            $probe['expected_enforcer'] = 'php-author-enum';
        }

        return new WP_REST_Response(array(
            'success' => $runtime_verified,
            'key' => $key,
            'title' => $schema['title'] ?? ($meta['feature_name'] ?? $key),
            'status' => $meta['status'] ?? 'unknown',
            'probe' => $probe,
            'runtime_verified' => $runtime_verified,
            'message' => $runtime_verified
                ? 'Runtime enforcement is registered for this feature.'
                : 'Runtime enforcement is not currently registered for this feature.',
        ), 200);
    }

    public function get_features($request)
    {
        try {
            $default_file = defined('VAPTSECURE_ACTIVE_DATA_FILE') ? VAPTSECURE_ACTIVE_DATA_FILE : 'interface_schema_v2.0.json';
            $requested_file = $request->get_param('file') ?: $default_file;

            // 1. Resolve which files to load
            $files_to_load = [];
            if ($requested_file === '__all__') {
                $data_dir = VAPTSECURE_PATH . 'data';
                if (is_dir($data_dir)) {
                    $all_json = array_filter(
                        scandir($data_dir), function ($f) {
                            return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'json';
                        }
                    );
                    $hidden_files = get_option('vaptsecure_hidden_json_files', array());
                    $removed_files = get_option('vaptsecure_removed_json_files', array());
                    $hidden_normalized = array_map('sanitize_file_name', $hidden_files);
                    $removed_normalized = array_map('sanitize_file_name', $removed_files);

                    foreach ($all_json as $f) {
                            $normalized = sanitize_file_name($f);
                        if (!in_array($normalized, $hidden_normalized) && !in_array($normalized, $removed_normalized)) {
                            $files_to_load[] = $f;
                        }
                    }
                }
            } else {
                $files_to_load = array_filter(explode(',', $requested_file));
            }

            // v3.12.1: Final filter against existence to prevent stale entries
            $files_to_load = array_filter(
                $files_to_load, function ($f) {
                    return file_exists(VAPTSECURE_PATH . 'data/' . sanitize_file_name($f));
                }
            );

            // 2. Pre-fetch global state (Status map and History counts)
            $statuses = VAPTSECURE_DB::get_feature_statuses_full();
            $status_map = [];
            foreach ($statuses as $row) {
                  $status_map[$row['feature_key']] = array(
                    'status' => $row['status'],
                    'implemented_at' => $row['implemented_at'],
                    'assigned_to' => $row['assigned_to']
                  );
            }

            global $wpdb;
            $history_table = $wpdb->prefix . 'vaptsecure_feature_history';
            $history_counts = $wpdb->get_results("SELECT feature_key, COUNT(*) as count FROM $history_table GROUP BY feature_key", OBJECT_K);

            $is_superadmin = is_vaptsecure_superadmin();
            $scope = $request->get_param('scope');
            $severity_param = $request->get_param('severity');

            $features = [];
            $schema = [];
            $merged_features = []; // Track by normalized label
            $design_prompt = null;
            $ai_agent_instructions = null;
            $global_settings = null;

            // [v1.4.2] Detected Environment Profile for dynamic enforcer mapping
            include_once VAPTSECURE_PATH . 'includes/class-vaptsecure-environment-detector.php';
            $detector = new VAPTSECURE_Environment_Detector();

            // Force redetect if requested or one-time for cache clearing after logic update
            if ($request->get_param('redetect')) {
                $environment_profile = $detector->redetect();
            } else {
                $environment_profile = $detector->detect();
            }

            // 3. Load and process each file
            foreach ($files_to_load as $file) {
                $json_path = VAPTSECURE_PATH . 'data/' . sanitize_file_name($file);
                if (! file_exists($json_path)) { continue;
                }

                $content = file_get_contents($json_path);
                $raw_data = json_decode($content, true);
                if (! is_array($raw_data)) { continue;
                }

                // [v2.4.11] Restricted Mode Filtering for Generated Builds
                // If a build is locked to a domain, we only show features that are allowed
                if (defined('VAPTSECURE_DOMAIN_LOCKED')) {
                    if (isset($raw_data['wordpress_vapt']) && is_array($raw_data['wordpress_vapt'])) {
                        $raw_data['wordpress_vapt'] = array_filter($raw_data['wordpress_vapt'], function($f) {
                            return vaptsecure_is_feature_allowed($f['key'] ?? '');
                        });
                    }
                    if (isset($raw_data['risk_interfaces']) && is_array($raw_data['risk_interfaces'])) {
                        $raw_data['risk_interfaces'] = array_filter($raw_data['risk_interfaces'], function($f, $k) {
                            $key = $f['risk_id'] ?? $k;
                            return vaptsecure_is_feature_allowed($key);
                        }, ARRAY_FILTER_USE_BOTH);
                    }
                }

                if (!$design_prompt && isset($raw_data['design_prompt'])) { $design_prompt = $raw_data['design_prompt'];
                }
                if (!$ai_agent_instructions && isset($raw_data['ai_agent_instructions'])) { $ai_agent_instructions = $raw_data['ai_agent_instructions'];
                }
                if (!$global_settings && isset($raw_data['global_settings'])) { $global_settings = $raw_data['global_settings'];
                }

                $current_features = [];
                $current_schema = [];

                if (isset($raw_data['wordpress_vapt']) && is_array($raw_data['wordpress_vapt'])) {
                    $current_features = $raw_data['wordpress_vapt'];
                    $current_schema = isset($raw_data['schema']) ? $raw_data['schema'] : [];
                } elseif (isset($raw_data['features']) && is_array($raw_data['features'])) {
                    $current_features = $raw_data['features'];
                    $current_schema = isset($raw_data['schema']) ? $raw_data['schema'] : [];
                } elseif (isset($raw_data['risk_catalog']) && is_array($raw_data['risk_catalog'])) {
                    foreach ($raw_data['risk_catalog'] as $item) {
                        if (isset($item['risk_id']) && empty($item['id'])) { $item['id'] = $item['risk_id'];
                        }
                        if (isset($item['risk_id']) && empty($item['key'])) { $item['key'] = $item['risk_id'];
                        }
                        if (isset($item['description']) && is_array($item['description'])) {
                            $item['original_description'] = $item['description'];
                            $item['description'] = isset($item['description']['summary']) ? $item['description']['summary'] : '';
                        }
                        if (isset($item['severity']) && is_array($item['severity'])) {
                            $item['original_severity'] = $item['severity'];
                            $item['severity'] = isset($item['severity']['level']) ? $item['severity']['level'] : 'medium';
                        }
                        if (empty($item['test_method']) && isset($item['testing']['test_method'])) { $item['test_method'] = $item['testing']['test_method'];
                        }

                        // Hyper-Personalization: Attach source-specific root nodes to each feature (v3.13.1)
                        $item['root_design_prompt'] = isset($raw_data['design_prompt']) ? $raw_data['design_prompt'] : null;
                        $item['root_ai_agent_instructions'] = isset($raw_data['ai_agent_instructions']) ? $raw_data['ai_agent_instructions'] : null;
                        $item['root_global_settings'] = isset($raw_data['global_settings']) ? $raw_data['global_settings'] : null;
                        $item['source_file'] = $file;

                        if (empty($item['verification_engine']) && isset($item['protection']['automated_protection'])) { $item['verification_engine'] = $item['protection']['automated_protection'];
                        }
                        if (isset($item['testing']) && isset($item['testing']['verification_steps']) && is_array($item['testing']['verification_steps'])) {
                            $steps = [];
                            foreach ($item['testing']['verification_steps'] as $step) {
                                if (is_array($step) && isset($step['action'])) { $steps[] = $step['action'];
                                } elseif (is_string($step)) { $steps[] = $step;
                                }
                            }
                            $item['verification_steps'] = $steps;
                        }
                        if (isset($item['protection']) && is_array($item['protection'])) {
                            if (isset($item['protection']['automated_protection']['implementation_steps'][0]['code'])) {
                                $item['remediation'] = $item['protection']['automated_protection']['implementation_steps'][0]['code'];
                            }
                        }
                        if (isset($item['owasp_mapping']) && isset($item['owasp_mapping']['owasp_top_10_2021'])) { $item['owasp'] = $item['owasp_mapping']['owasp_top_10_2021'];
                        }
                        $current_features[] = $item;
                    }
                    $current_schema = isset($raw_data['schema']) ? $raw_data['schema'] : [];
                } elseif (isset($raw_data['risk_interfaces']) && is_array($raw_data['risk_interfaces'])) {
                    // 🛡️ INTERFACE SCHEMA FORMAT (risk_interfaces node — e.g. interface_schema_full125.json)
                    // Converts the keyed RISK-NNN dictionary into the standard flat feature array.
                    foreach ($raw_data['risk_interfaces'] as $risk_key => $item) {
                        $item['id']          = isset($item['risk_id'])  ? $item['risk_id']  : $risk_key;
                        $item['key']         = $item['id'];
                        $item['name']        = isset($item['title'])    ? $item['title']    : $risk_key;
                        $item['label']       = $item['name'];
                        // Map summary → description so the Feature List "Description" column is populated
                        if (!isset($item['description']) && isset($item['summary'])) {
                            $item['description'] = $item['summary'];
                        }
                        // Flatten severity if it is an object (guard for mixed catalogues)
                        if (isset($item['severity']) && is_array($item['severity'])) {
                            $item['severity'] = isset($item['severity']['level']) ? $item['severity']['level'] : 'medium';
                        }
                        // Attach root-level metadata for Hyper-Personalization compatibility
                        $item['root_design_prompt']          = null;
                        $item['root_ai_agent_instructions']  = null;
                        $item['root_global_settings']        = isset($raw_data['global_ui_config']) ? $raw_data['global_ui_config'] : null;
                        $item['source_file']                 = $file;

                        // [FIX v2.4.11] Extract remediation code from platform_implementations.htaccess
                        if (isset($item['platform_implementations'])) {
                            $pattern_lib = self::get_cached_pattern_library();
                            foreach ($item['platform_implementations'] as $plat_key => &$plat_data) {
                                if (isset($plat_data['code_ref'])) {
                                    $code_ref_clean = preg_replace('/^.*?\.patterns\./', 'patterns.', $plat_data['code_ref']);
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
                            }
                            unset($plat_data);

                            $htaccessImpl = $item['platform_implementations']['.htaccess'] ??
                             $item['platform_implementations']['htaccess'] ??
                             $item['platform_implementations']['apache_htaccess'] ??
                             null;
                            if ($htaccessImpl && isset($htaccessImpl['code'])) {
                                $item['remediation'] = $htaccessImpl['code'];
                            }
                        }

                        $current_features[] = $item;
                    }
                    $current_schema = isset($raw_data['schema']) ? $raw_data['schema'] : array(
                    'item_fields' => array('id', 'category', 'title', 'severity', 'description')
                    );
                } else {
                    $current_features = $raw_data;
                }

                if (empty($schema) && !empty($current_schema)) { $schema = $current_schema;
                }

                foreach ($current_features as &$feature) {
                    $label = isset($feature['name']) ? $feature['name'] : (isset($feature['title']) ? $feature['title'] : (isset($feature['label']) ? $feature['label'] : __('Unnamed Feature', 'vaptsecure')));
                    $feature['label'] = $label;

                    // Hyper-Personalization: Attach source-specific root nodes to each feature (v3.13.1)
                    $feature['root_design_prompt'] = isset($raw_data['design_prompt']) ? $raw_data['design_prompt'] : null;
                    $feature['root_ai_agent_instructions'] = isset($raw_data['ai_agent_instructions']) ? $raw_data['ai_agent_instructions'] : null;
                    $feature['root_global_settings'] = isset($raw_data['global_settings']) ? $raw_data['global_settings'] : null;
                    // The source_file is already set below, but for consistency with the risk_catalog block, we can add it here too.
                    // However, the existing line `feature['source_file'] = $file;` is sufficient.

                    $key = isset($feature['id']) ? $feature['id'] : (isset($feature['key']) ? $feature['key'] : sanitize_title($label));
                    $feature['key'] = $key;

                    $dedupe_key = strtolower(trim($label));

                    if (isset($merged_features[$dedupe_key])) {
                        $merged_features[$dedupe_key]['exists_in_multiple_files'] = true;
                        continue;
                    }

                    $st = isset($status_map[$key]) ? $status_map[$key] : array('status' => 'Draft', 'implemented_at' => null, 'assigned_to' => null);
                    $norm_status = strtolower($st['status']);
                    if ($norm_status === 'implemented') { $norm_status = 'release';
                    }
                    if ($norm_status === 'in_progress') { $norm_status = 'develop';
                    }
                    if ($norm_status === 'testing') {     $norm_status = 'test';
                    }
                    if ($norm_status === 'available') {   $norm_status = 'draft';
                    }
                    $feature['normalized_status'] = $norm_status;
                    $feature['status'] = ucfirst($norm_status);
                    $feature['implemented_at'] = $st['implemented_at'];
                    $feature['assigned_to'] = $st['assigned_to'];
                    $feature['has_history'] = isset($history_counts[$key]) && $history_counts[$key]->count > 0;
                    $feature['source_file'] = $file;
                    $feature['exists_in_multiple_files'] = false;

                    $meta = VAPTSECURE_DB::get_feature_meta($key);
                    if ($meta) {
                        $feature['include_test_method'] = (bool) $meta['include_test_method'];
                        $feature['include_verification'] = (bool) $meta['include_verification'];
                        $feature['include_verification_engine'] = isset($meta['include_verification_engine']) ? (bool) $meta['include_verification_engine'] : false;
                        $feature['include_verification_guidance'] = isset($meta['include_verification_guidance']) ? (bool) $meta['include_verification_guidance'] : true;
                        $feature['is_enforced'] = (bool) $meta['is_enforced'];
                        $feature['is_adaptive_deployment'] = isset($meta['is_adaptive_deployment']) ? (bool) $meta['is_adaptive_deployment'] : false;
                        $feature['wireframe_url'] = $meta['wireframe_url'];
                        $feature['dev_instruct'] = isset($meta['dev_instruct']) ? $meta['dev_instruct'] : '';

                        $schema_data = array();
                        $use_override_schema = in_array($norm_status, ['test', 'release']) && !empty($meta['override_schema']);
                        $source_schema_json = $use_override_schema ? $meta['override_schema'] : $meta['generated_schema'];
                        if (!empty($source_schema_json)) {
                            $decoded = json_decode($source_schema_json, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $schema_data = $decoded;
                                // [v3.12.17] Translate URL placeholders when returning schema to UI
                                $schema_data = VAPTSECURE_Schema_Validator::translate_url_placeholders($schema_data);
                                if ($use_override_schema) { $feature['is_overridden'] = true;
                                }
                            }
                        }
                        $feature['generated_schema'] = $schema_data;
                        $source_impl_json = (in_array($norm_status, ['test', 'release']) && !empty($meta['override_implementation_data'])) ? $meta['override_implementation_data'] : $meta['implementation_data'];
                        $feature['implementation_data'] = $source_impl_json ? json_decode($source_impl_json, true) : array();

                        // [v3.12.17] Include manual_protocol and operational_notes in response
                        if (!empty($meta['manual_protocol_content'])) {
                            $feature['manual_protocol'] = $meta['manual_protocol_content'];
                        }
                        if (!empty($meta['operational_notes_content'])) {
                            $feature['operational_notes'] = $meta['operational_notes_content'];
                        }
                        $feature['is_enabled'] = isset($meta['is_enabled']) ? (bool)$meta['is_enabled'] : false;
                        $feature['is_enforced'] = isset($meta['is_enforced']) ? (bool)$meta['is_enforced'] : false;
                        $feature['active_enforcer'] = isset($meta['active_enforcer']) ? $meta['active_enforcer'] : null;
                    }

                    $merged_features[$dedupe_key] = $feature;
                }
            }

            $features = array_values($merged_features);

            if (empty($schema)) { $schema = array('item_fields' => array('id', 'category', 'title', 'severity', 'description'));
            }

            if ($scope === 'client') {
                $domain = $request->get_param('domain');
                $enabled_features = [];
                if ($domain) {
                    $dom_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s", $domain));
                    if (!$dom_row && strpos($domain, '.') !== false) {
                          $domain_base = explode('.', $domain)[0];
                          $dom_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s", $domain_base));
                    }
                    if ($dom_row) {
                        $feat_rows = $wpdb->get_results($wpdb->prepare("SELECT feature_key FROM {$wpdb->prefix}vaptsecure_domain_features WHERE domain_id = %d AND enabled = 1", $dom_row->id), ARRAY_N);
                        $enabled_features = array_column($feat_rows, 0);
                    }
                }
                $is_config_build = defined('VAPTSECURE_BUILD_PROFILE') && VAPTSECURE_BUILD_PROFILE === 'client';
                $is_builder_context = function_exists('vaptsecure_is_builder_context') && vaptsecure_is_builder_context();
                $features = array_filter(
                    $features, function ($f) use ($enabled_features, $is_superadmin, $is_config_build, $is_builder_context) {
                        $s = $f['normalized_status'];
                        if ($s === 'release') {
                            // On a config build, also restrict to features allowed by the config
                            if ($is_config_build && !$is_builder_context && !vaptsecure_is_feature_allowed($f['key'] ?? '')) {
                                return false;
                            }
                            return true;
                        }
                        return $is_superadmin && in_array($s, ['draft', 'develop', 'test']);
                    }
                );
                $features = array_values($features);
            }

            // Apply severity filtering if severity parameter is provided
            if (!empty($severity_param)) {
                $severity_values = array_map('trim', explode(',', $severity_param));
                $severity_values = array_map('strtolower', $severity_values);

                $features = array_filter(
                    $features,
                    function ($f) use ($severity_values) {
                        $feature_severity = isset($f['severity']) ? strtolower($f['severity']) : 'medium';
                        return in_array($feature_severity, $severity_values);
                    }
                );
                $features = array_values($features);
            }

            // 🛡️ v1.1 FALLBACK: Ensure AI Agent Instructions and Global Settings are loaded (v3.13.8)
            if (!$ai_agent_instructions && defined('VAPTSECURE_AI_INSTRUCTIONS')) {
                $instr_path = VAPTSECURE_PATH . 'data/' . VAPTSECURE_AI_INSTRUCTIONS;
                if (file_exists($instr_path)) {
                    $instr_data = json_decode(file_get_contents($instr_path), true);
                    if (isset($instr_data['ai_agent_instructions'])) {
                          $ai_agent_instructions = $instr_data['ai_agent_instructions'];
                    }
                }
            }

            if (!$global_settings) {
                // Try to find global_ui_config in the same file as instructions or active file
                if (isset($instr_data) && isset($instr_data['global_ui_config'])) {
                    $global_settings = $instr_data['global_ui_config'];
                }
            }

            $response_data = array(
            'features' => $features,
            'schema' => $schema,
            'design_prompt' => $design_prompt,
            'ai_agent_instructions' => $ai_agent_instructions,
            'global_settings' => $global_settings,
            'environment_profile' => $environment_profile
            );
            if ($is_superadmin) {
                $response_data['active_catalog'] = $requested_file;
                $response_data['total_features'] = count($features);
            }
            return new WP_REST_Response($response_data, 200);
        } catch (\Throwable $e) {
            error_log('[VAPT REST Error] get_features: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function get_data_files()
    {
        $data_dir = VAPTSECURE_PATH . 'data';
        if (!is_dir($data_dir)) { return new WP_REST_Response([], 200);
        }

        $files = array_diff(scandir($data_dir), array('..', '.'));
        $json_files = [];

        $hidden_files  = get_option('vaptsecure_hidden_json_files', array());
        $removed_files = get_option('vaptsecure_removed_json_files', array());
        $active_option = get_option('vaptsecure_active_feature_file');
        $current_active = $active_option ? explode(',', $active_option) : array();

        $hidden_normalized  = array_map('sanitize_file_name', $hidden_files);
        $removed_normalized = array_map('sanitize_file_name', $removed_files);
        $active_normalized  = array_map('sanitize_file_name', $current_active);

        foreach ($files as $file) {
            if (is_dir($data_dir . '/' . $file)) { continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext !== 'json') { continue;
            }

            $normalized = sanitize_file_name($file);
            if (in_array($normalized, $removed_normalized)) { continue;
            }

            $is_hidden = in_array($normalized, $hidden_normalized);
            $is_active = in_array($normalized, $active_normalized);

            if ($is_active || !$is_hidden) {
                $json_files[] = array(
                'label' => $file,
                'value' => $file
                );
            }
        }

        return new WP_REST_Response($json_files, 200);
    }

    /**
     * Handle active file endpoint (GET/POST)
     * GET: Returns the current active file
     * POST: Sets the active file
     */
    public function handle_active_file($request)
    {
        $method = $request->get_method();

        if ($method === 'GET') {
            // Get current active file
            $active_file = defined('VAPTSECURE_ACTIVE_DATA_FILE') ? VAPTSECURE_ACTIVE_DATA_FILE : get_option('vaptsecure_active_feature_file', '');
            return new WP_REST_Response([
                'active_file' => $active_file,
                'success' => true
            ], 200);
        } elseif ($method === 'POST') {
            // Set active file
            $filename = $request->get_param('filename');
            if (!$filename) {
                return new WP_REST_Response(['error' => 'Missing filename parameter'], 400);
            }

            // Validate file exists
            $data_dir = VAPTSECURE_PATH . 'data';
            $file_path = $data_dir . '/' . $filename;
            if (!file_exists($file_path)) {
                return new WP_REST_Response(['error' => 'File does not exist'], 404);
            }

            // Update the active file option
            update_option('vaptsecure_active_feature_file', $filename);

            error_log("VAPT REST: Active file set to '{$filename}'");
            return new WP_REST_Response([
                'active_file' => $filename,
                'success' => true
            ], 200);
        }

        return new WP_REST_Response(['error' => 'Method not allowed'], 405);
    }

    // Scanner methods
    public function start_scan($request)
    {
        $target_url = $request->get_param('target_url');
        if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response(['error' => 'Invalid target URL'], 400);
        }

        $scanner = new VAPTSECURE_Scanner();
        $scan_id = $scanner->start_scan($target_url);

        if ($scan_id === false) {
            return new WP_REST_Response(['error' => 'Failed to start scan'], 500);
        }

        return new WP_REST_Response(['scan_id' => $scan_id, 'status' => 'started'], 200);
    }

    public function get_scan_report($request)
    {
        $scan_id = $request->get_param('id');
        $scanner = new VAPTSECURE_Scanner();
        $report = $scanner->generate_report($scan_id);

        if (!$report) {
            return new WP_REST_Response(['error' => 'Scan not found'], 404);
        }

        return new WP_REST_Response($report, 200);
    }

    public function get_scans($request)
    {
        global $wpdb;
        $scans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vaptsecure_scans ORDER BY created_at DESC", ARRAY_A);
        return new WP_REST_Response($scans, 200);
    }

    public function update_feature($request)
    {
        $key = $request->get_param('key');
        $status = $request->get_param('status');
        $include_test = $request->get_param('include_test_method');
        $include_verification = $request->get_param('include_verification');
        $wireframe_url = $request->get_param('wireframe_url');
        $generated_schema = $request->get_param('generated_schema');
        $implementation_data = $request->get_param('implementation_data');
        $reset_history = $request->get_param('reset_history');

        // FIX: Lifecycle race condition. Capture initial status before transition runs.
        $current_feat_db = VAPTSECURE_DB::get_feature($key);
        $initial_status = 'draft';
        if ($current_feat_db) {
            if (is_array($current_feat_db) && isset($current_feat_db['status'])) {
                $initial_status = strtolower($current_feat_db['status']);
            } elseif (is_object($current_feat_db) && isset($current_feat_db->status)) {
                $initial_status = strtolower($current_feat_db->status);
            }
        }

        if ($status) {
            $note = $request->get_param('history_note') ?: ($request->get_param('transition_note') ?: '');
            $result = VAPTSECURE_Workflow::transition_feature($key, $status, $note);
            if (is_wp_error($result)) {
                return new WP_REST_Response($result, 400);
            }
        }

        $meta_updates = array();
        if ($include_test !== null) { $meta_updates['include_test_method'] = $include_test ? 1 : 0;
        }
        if ($include_verification !== null) { $meta_updates['include_verification'] = $include_verification ? 1 : 0;
        }

        $include_verification_engine = $request->get_param('include_verification_engine');
        if ($include_verification_engine !== null) { $meta_updates['include_verification_engine'] = $include_verification_engine ? 1 : 0;
        }

        $include_verification_guidance = $request->get_param('include_verification_guidance');
        if ($include_verification_guidance !== null) { $meta_updates['include_verification_guidance'] = $include_verification_guidance ? 1 : 0;
        }

        $include_operational_notes = $request->get_param('include_operational_notes');
        if ($include_operational_notes !== null) { $meta_updates['include_operational_notes'] = $include_operational_notes ? 1 : 0;
        }

        $is_enabled_param = $request->get_param('is_enabled');
        if ($is_enabled_param !== null) { $meta_updates['is_enabled'] = $is_enabled_param ? 1 : 0;
        }

        $is_enforced_param = $request->get_param('is_enforced');
        if ($is_enforced_param !== null) { $meta_updates['is_enforced'] = $is_enforced_param ? 1 : 0;
        }

        $force_inject_impl = false;
        $force_sync_val = ($is_enforced_param !== null) ? filter_var($is_enforced_param, FILTER_VALIDATE_BOOLEAN) : (($is_enabled_param !== null) ? filter_var($is_enabled_param, FILTER_VALIDATE_BOOLEAN) : null);

        if ($force_sync_val !== null) {
            $risk_suffix = str_replace('-', '_', strtolower($key));
            $auto_key = "vapt_risk_{$risk_suffix}_enabled";

            if ($request->has_param('implementation_data')) {
                $impl_temp = $request->get_param('implementation_data');
                if (is_string($impl_temp)) { $impl_temp = json_decode($impl_temp, true); }
                if (is_array($impl_temp)) {
                    $impl_temp[$auto_key] = $force_sync_val;
                    $impl_temp['enabled'] = $force_sync_val;
                    $request->set_param('implementation_data', $impl_temp);
                }
            } else {
                $existing_meta = VAPTSECURE_DB::get_feature_meta($key);
                $existing_impl = ($existing_meta && !empty($existing_meta['implementation_data'])) ? json_decode($existing_meta['implementation_data'], true) : [];
                if (!is_array($existing_impl)) { $existing_impl = []; }
                $existing_impl[$auto_key] = $force_sync_val;
                $existing_impl['enabled'] = $force_sync_val;
                $request->set_param('implementation_data', $existing_impl);
                $force_inject_impl = true;
            }
        }

        $is_adaptive = $request->get_param('is_adaptive_deployment');
        if ($is_adaptive !== null) { $meta_updates['is_adaptive_deployment'] = $is_adaptive ? 1 : 0;
        }

        if ($wireframe_url !== null) { $meta_updates['wireframe_url'] = $wireframe_url;
        }

        $active_enforcer = $request->get_param('active_enforcer');
        if ($active_enforcer !== null) { $meta_updates['active_enforcer'] = $active_enforcer;
        }

        $dev_instruct = $request->get_param('dev_instruct');
        if ($dev_instruct !== null) { $meta_updates['dev_instruct'] = $dev_instruct;
        }

        if ($request->has_param('generated_schema')) {
            $generated_schema = $request->get_param('generated_schema');
            if ($generated_schema === null) {
                $meta_updates['generated_schema'] = null;
            } else {
                $schema = (is_array($generated_schema) || is_object($generated_schema))
                ? json_decode(json_encode($generated_schema), true)
                : json_decode($generated_schema, true);

                // 🛡️ LIFECYCLE ENFORCEMENT: Schema updates allowed only in Draft/Develop stages
                // Update: 'Test' stage allows updates but saves to OVERRIDE meta (Local customization)
                $current_status = $initial_status; // Use captured status to prevent locking during transition

                if (!in_array($current_status, ['draft', 'develop', 'test'])) {
                    return new WP_REST_Response(
                        array(
                        'error' => 'Lifecycle Restriction',
                        'message' => 'Design/Schema changes are strictly locked in Release stage. Current status: ' . ucfirst($current_status),
                        'code' => 'lifecycle_locked'
                        ), 403
                    );
                }

                $is_legacy_format = isset($schema['type']) && in_array($schema['type'], ['wp_config', 'htaccess', 'manual', 'complex_input']);

                if (!$is_legacy_format) {
                    $schema['is_adaptive_deployment'] = $is_adaptive ? 1 : 0;
                    $schema = VAPTSECURE_Schema_Validator::sanitize_and_fix_schema($schema);
                    $validation = VAPTSECURE_Schema_Validator::validate_schema($schema);
                    if (is_wp_error($validation)) {
                        return new WP_REST_Response(
                            array(
                            'error' => 'Schema validation failed',
                            'message' => $validation->get_error_message(),
                            'code' => $validation->get_error_code(),
                            'schema_received' => $schema
                            ), 400
                        );
                    }

                    // 🛡️ DATA EXTRACTION (v3.13.0)
                    // Extract rich context for specific UI tabs
                    if (isset($schema['manual_protocol'])) {
                        $meta_updates['manual_protocol_content'] = is_string($schema['manual_protocol'])
                        ? $schema['manual_protocol']
                        : json_encode($schema['manual_protocol']);
                    }
                    if (isset($schema['operational_notes'])) {
                        $meta_updates['operational_notes_content'] = is_string($schema['operational_notes'])
                        ? $schema['operational_notes']
                        : json_encode($schema['operational_notes']);
                    }

                    // 🛡️ URL TRANSLATION (v3.12.17)
                    // Translate {{site_url}} and other placeholders to fully qualified URLs
                    $schema = VAPTSECURE_Schema_Validator::translate_url_placeholders($schema);

                    // 🛡️ INTELLIGENT ENFORCEMENT (v3.3.9)
                    $schema = VAPTSECURE_Schema_Validator::analyze_enforcement_strategy($schema, $key);

                    // [FIX] Self-Healing for XML-RPC (v3.12.13)
                    // Detected missing enforcement in legacy schema, patching from catalog.
                    $is_xml_rpc = (stripos($key, 'xml-rpc') !== false) || (stripos($key, 'xmlrpc') !== false) || $key === 'RISK-016-001';

                    if ($is_xml_rpc && empty($schema['enforcement'])) {
                        $schema['enforcement'] = [
                        'driver' => 'htaccess',
                        'target' => 'root',
                        'mappings' => [
                        'UI-xml-rpc-api-security-001' => "<Files xmlrpc.php>\n  Order Deny,Allow\n  Deny from all\n</Files>"
                        ]
                        ];
                        // Auto-update the generated schema variable
                        $generated_schema = $schema;
                    }
                }

                if ($current_status === 'test') {
                    $meta_updates['override_schema'] = json_encode($schema);
                } else {
                    $meta_updates['generated_schema'] = json_encode($schema);
                }
            }
        }

        if ($request->has_param('implementation_data') || (isset($force_inject_impl) && $force_inject_impl)) {
            $current_status = $initial_status; // Use captured status

            // 🛡️ VALIDATION: Check implementation data against schema (v3.6.19)
            // Get the effective schema for validation
            $schema_for_val = null;
            if ($request->has_param('generated_schema')) {
                $schema_for_val = isset($schema) ? $schema : null; // Already decoded above
            } else {
                $meta = VAPTSECURE_DB::get_feature_meta($key);
                $raw_schema = ($current_status === 'test') ? ($meta['override_schema'] ?? $meta['generated_schema']) : ($meta['generated_schema'] ?? null);
                $schema_for_val = $raw_schema ? json_decode($raw_schema, true) : null;
            }

            $implementation_data = $request->get_param('implementation_data');

            // 🛡️ TYPE SANITIZATION: Handle stringified JSON from client (v3.6.19 Fix)
            if (is_string($implementation_data)) {
                $decoded = json_decode($implementation_data, true);
                if (is_array($decoded)) {
                    $implementation_data = $decoded;
                }
            }

            // Reverse sync: Ensure implementations reflect UI toggle enforce switch (Fix for deployment overrides)
            if (isset($force_sync_val) && $force_sync_val !== null) {
                if (!is_array($implementation_data)) {
                    $existing_meta = VAPTSECURE_DB::get_feature_meta($key);
                    $implementation_data = ($existing_meta && !empty($existing_meta['implementation_data'])) ? json_decode($existing_meta['implementation_data'], true) : [];
                    if (!is_array($implementation_data)) { $implementation_data = []; }
                }
                $risk_suffix = str_replace('-', '_', strtolower($key));
                $auto_key = "vapt_risk_{$risk_suffix}_enabled";
                $implementation_data[$auto_key] = $force_sync_val;
                $implementation_data['enabled'] = $force_sync_val;
                $implementation_data['feat_enabled'] = $force_sync_val;
                $implementation_data['prot_enabled'] = $force_sync_val;
            }

            if ($schema_for_val) {
                $val_result = VAPTSECURE_Schema_Validator::validate_implementation_data($implementation_data, $schema_for_val);
                if (is_wp_error($val_result)) {
                    // [FIX] Proactive Error Reporting
                    return new WP_REST_Response(
                        array(
                        'error' => 'Implementation validation failed',
                        'message' => $val_result->get_error_message(),
                        'code' => $val_result->get_error_code()
                        ), 400
                    );
                }
            }

            $val = ($implementation_data === null) ? null : (is_array($implementation_data) ? json_encode($implementation_data) : $implementation_data);

            if ($current_status === 'test') {
                $meta_updates['override_implementation_data'] = $val;
            } else {
                $meta_updates['implementation_data'] = $val;
            }

            // [v3.13.30] BI-DIRECTIONAL SYNC: Auto-detect Master Toggle in implementation_data
            // [FIX v4.0.x] Also sync is_enforced so toggle affects file enforcement
            // [FIX] Only sync from implementation_data if client didn't explicitly set is_enabled/is_enforced
            $client_set_enabled = $request->has_param('is_enabled') || $request->has_param('is_enforced');
            if (is_array($implementation_data) && !$client_set_enabled) {
                $is_enabled = null;
                $risk_suffix = str_replace('-', '_', strtolower($key));
                $auto_key = "vapt_risk_{$risk_suffix}_enabled";

                // Robust toggle check - check multiple possible toggle keys
                $v = null;
                if (isset($implementation_data['enabled'])) $v = $implementation_data['enabled'];
                elseif (isset($implementation_data['feat_enabled'])) $v = $implementation_data['feat_enabled'];
                elseif (isset($implementation_data['prot_enabled'])) $v = $implementation_data['prot_enabled'];
                elseif (isset($implementation_data[$auto_key])) $v = $implementation_data[$auto_key];

                if ($v !== null) {
                    $is_enabled = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                    // Sync both is_enabled AND is_enforced for consistent enforcement
                    $meta_updates['is_enabled'] = $is_enabled ? 1 : 0;
                    $meta_updates['is_enforced'] = $is_enabled ? 1 : 0;
                    error_log("VAPT: Toggled enforcement for $key to " . ($is_enabled ? 'ENABLED' : 'DISABLED') . " (synced from implementation_data to both is_enabled and is_enforced)");
                }
            }
        }

        if (! empty($meta_updates) || ! empty($status)) {
            global $wpdb;
            if (! empty($meta_updates)) {
                // error_log("VAPT: Updating meta for $key: " . json_encode($meta_updates));
                VAPTSECURE_DB::update_feature_meta($key, $meta_updates);
                if ($wpdb->last_error) {
                    error_log("[VAPT Error] DB Update Failed for $key: " . $wpdb->last_error);
                }
            }
            error_log("VAPT REST: Triggering vaptsecure_feature_saved hook for feature '{$key}'");
            do_action('vaptsecure_feature_saved', $key, $meta_updates);
        } else {
             // error_log("VAPT: No meta updates identified for $key");
        }

        if ($reset_history) {
            global $wpdb;
            $history_table = $wpdb->prefix . 'vaptsecure_feature_history';
            $wpdb->delete($history_table, array('feature_key' => $key), array('%s'));
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    // =========================================================================
    // v1.8.0 – HINT BACKFILL: enrich existing generated_schemas with `help`
    // =========================================================================
    /**
     * Preview what would be affected by a batch revert to Draft.
     * GET /vaptsecure/v1/features/preview-revert
     */
    public function preview_revert_to_draft($request)
    {
        $include_broken = (bool) $request->get_param('include_broken');
        $include_release = (bool) $request->get_param('include_release');
        $result = VAPTSECURE_Workflow::preview_revert_to_draft($include_broken, $include_release);
        return new WP_REST_Response($result, 200);
    }

    /**
     * Execute batch revert all Develop features to Draft.
     * POST /vaptsecure/v1/features/batch-revert
     */
    public function batch_revert_to_draft($request)
    {
        $note = $request->get_param('note') ?: 'Batch revert to Draft via Workbench';
        $include_broken = (bool) $request->get_param('include_broken');
        $include_release = (bool) $request->get_param('include_release');

        $result = VAPTSECURE_Workflow::batch_revert_to_draft($note, $include_broken, $include_release);

        if (!$result['success']) {
            return new WP_REST_Response($result, 207); // Multi-status (partial success)
        }

        return new WP_REST_Response($result, 200);
    }

    public function update_file_meta($request)
    {
        $file = $request->get_param('file');
        $key = $request->get_param('key');
        $value = $request->get_param('value');

        if (!$file || !$key) {
            return new WP_REST_Response(array('error' => 'Missing file or key param'), 400);
        }

        $json_path = VAPTSECURE_PATH . 'data/' . sanitize_file_name($file);

        if (!file_exists($json_path)) {
            return new WP_REST_Response(array('error' => 'File not found'), 404);
        }

        $content = file_get_contents($json_path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return new WP_REST_Response(array('error' => 'Invalid JSON in file'), 500);
        }

        if ($value === null) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }

        $saved = file_put_contents($json_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($saved === false) {
            return new WP_REST_Response(array('error' => 'Failed to write to file'), 500);
        }

        return new WP_REST_Response(array('success' => true, 'updated_key' => $key), 200);
    }

    public function transition_feature($request)
    {
        $key = $request->get_param('key');
        $status = $request->get_param('status');
        $note = $request->get_param('note') ?: '';

        error_log("VAPT REST: Transition request for feature '{$key}' to status '{$status}' with note: '{$note}'");

        $result = VAPTSECURE_Workflow::transition_feature($key, $status, $note);

        if (is_wp_error($result)) {
            error_log("VAPT REST: Transition failed for '{$key}': " . $result->get_error_message());
            return new WP_REST_Response($result, 400);
        }

        error_log("VAPT REST: Transition successful for feature '{$key}'");
        return new WP_REST_Response(array('success' => true), 200);
    }

    public function get_feature_history($request)
    {
        $key = $request['key'];
        $history = VAPTSECURE_Workflow::get_history($key);

        return new WP_REST_Response($history, 200);
    }

    public function get_feature_stats($request)
    {
        $key = $request['key'];
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-hook-driver.php';
        if (method_exists('VAPTSECURE_Hook_Driver', 'get_feature_stats')) {
            $stats = VAPTSECURE_Hook_Driver::get_feature_stats($key);
            return new WP_REST_Response($stats, 200);
        }
        return new WP_REST_Response(['error' => 'Method not supported'], 500);
    }

    public function reset_feature_stats($request)
    {
        $key = $request['key'];
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-hook-driver.php';
        if (method_exists('VAPTSECURE_Hook_Driver', 'reset_feature_stats')) {
            $count = VAPTSECURE_Hook_Driver::reset_feature_stats($key);
            return new WP_REST_Response(['success' => true, 'deleted_locks' => $count], 200);
        }
        return new WP_REST_Response(['error' => 'Method not supported'], 500);
    }

    public function upload_json($request)
    {
        error_log('VAPTSecure Clean: Starting JSON upload...');

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            error_log('VAPTSecure Clean: No file param found.');
            return new WP_REST_Response(array('error' => 'No file uploaded'), 400);
        }

        $file = $files['file'];
        error_log('VAPTSecure Clean: Received file ' . $file['name'] . ' size ' . $file['size']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log('VAPTSecure Clean: PHP Upload Error ' . $file['error']);
            return new WP_REST_Response(array('error' => 'PHP Upload Error: ' . $file['error']), 500);
        }

        $filename = sanitize_file_name($file['name']);
        $content = file_get_contents($file['tmp_name']);

        if ($content === false) {
            error_log('VAPTSecure Clean: Could not read temp file.');
            return new WP_REST_Response(array('error' => 'Failed to read uploaded file.'), 500);
        }

        $data = json_decode($content, true);
        if (is_null($data)) {
            error_log('VAPTSecure Clean: Invalid JSON content.');
            return new WP_REST_Response(array('error' => 'Invalid JSON'), 400);
        }

        $json_path = VAPTSECURE_PATH . 'data/' . $filename;
        $data_dir = VAPTSECURE_PATH . 'data/';

        include_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        if (!$wp_filesystem->is_dir($data_dir)) {
            if (!$wp_filesystem->mkdir($data_dir)) {
                return new WP_REST_Response(array('error' => 'Data directory missing and could not be created: ' . $data_dir), 500);
            }
        }

        // Proactively attempt to fix permissions if not writable
        if (!$wp_filesystem->is_writable($data_dir)) {
            $wp_filesystem->chmod($data_dir, 0755);
        }

        if (!$wp_filesystem->is_writable($data_dir)) {
            $method = $wp_filesystem->method;
            return new WP_REST_Response(
                array(
                'error' => "Data directory is not writable via standard WordPress API ($method method). Please check folder permissions for: " . $data_dir
                ), 500
            );
        }

        $saved = $wp_filesystem->put_contents($json_path, $content, FS_CHMOD_FILE);

        if (!$saved) {
            return new WP_REST_Response(array('error' => 'WP_Filesystem failed to write file to: ' . $json_path), 500);
        }

        error_log('VAPTSecure Clean: Upload successful to ' . $json_path);

        // ... rest of the logic remains same ...

        // Auto-unhide if it was hidden
        $hidden_files = get_option('vaptsecure_hidden_json_files', array());
        $normalized_hidden = array_map('sanitize_file_name', $hidden_files);

        if (in_array($filename, $normalized_hidden) || in_array($files['file']['name'], $hidden_files)) {
            $new_hidden = array_filter(
                $hidden_files, function ($f) use ($filename, $files) {
                    return sanitize_file_name($f) !== $filename && $f !== $files['file']['name'];
                }
            );
            update_option('vaptsecure_hidden_json_files', array_values($new_hidden));
        }

        // Auto-restore if it was removed
        $removed_files = get_option('vaptsecure_removed_json_files', array());
        $normalized_removed = array_map('sanitize_file_name', $removed_files);

        if (in_array($filename, $normalized_removed) || in_array($files['file']['name'], $removed_files)) {
            $new_removed = array_filter(
                $removed_files, function ($f) use ($filename, $files) {
                    return sanitize_file_name($f) !== $filename && $f !== $files['file']['name'];
                }
            );
            update_option('vaptsecure_removed_json_files', array_values($new_removed));
        }

        return new WP_REST_Response(array('success' => true, 'filename' => $filename), 200);
    }

    public function update_hidden_files($request)
    {
        $hidden_files = $request->get_param('hidden_files');
        if (!is_array($hidden_files)) {
            $hidden_files = array();
        }

        $hidden_files = array_map('sanitize_file_name', $hidden_files);

        update_option('vaptsecure_hidden_json_files', $hidden_files);
        $this->sanitize_active_file();

        return new WP_REST_Response(array('success' => true, 'hidden_files' => $hidden_files), 200);
    }

    public function remove_data_file($request)
    {
        $filename = $request->get_param('filename');
        if (!$filename) {
            return new WP_REST_Response(array('error' => 'Missing filename'), 400);
        }

        $active_file = defined('VAPTSECURE_ACTIVE_DATA_FILE') ? VAPTSECURE_ACTIVE_DATA_FILE : 'VAPT-SixTee-Risk-Catalogue-12-EntReady_v3.4.json';
        if ($filename === $active_file || sanitize_file_name($filename) === sanitize_file_name($active_file)) {
            return new WP_REST_Response(array('error' => 'Cannot remove the active file.'), 400);
        }

        $removed_files = get_option('vaptsecure_removed_json_files', array());
        if (!in_array($filename, $removed_files)) {
            $removed_files[] = $filename;
            update_option('vaptsecure_removed_json_files', $removed_files);
            $this->sanitize_active_file();
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    public function reset_rate_limit($request)
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-hook-driver.php';
        if (class_exists('VAPTSECURE_Hook_Driver')) {
            $result = VAPTSECURE_Hook_Driver::reset_limit();
            return new WP_REST_Response(array('success' => true, 'debug' => $result), 200);
        }
        return new WP_REST_Response(array('error' => 'Hook driver not found'), 500);
    }

    /**
     * Clear the enforcement cache transient.
     * POST /vaptsecure/v1/clear-cache
     */
    public function clear_enforcement_cache($request)
    {
        delete_transient('vaptsecure_active_enforcements');

        // Also clear any other VAPT transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vaptsecure_%' OR option_name LIKE '_site_transient_vaptsecure_%'");

        return new WP_REST_Response(
            array(
            'success' => true,
            'message' => 'Enforcement cache cleared successfully. Refresh the page to see updated features.'
            ), 200
        );
    }

    /**
     * Debug enforcement state - shows what's in the database and cache.
     * GET /vaptsecure/v1/debug-enforcement
     */
    public function debug_enforcement_state($request)
    {
        global $wpdb;

        // Check transient cache
        $cached = get_transient('vaptsecure_active_enforcements');

        // Check database directly
        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $db_results = $wpdb->get_results(
            "
      SELECT m.feature_key, m.implementation_data, s.status
      FROM $table m
      LEFT JOIN {$wpdb->prefix}vaptsecure_feature_status s ON m.feature_key = s.feature_key
      WHERE m.implementation_data IS NOT NULL
        AND m.implementation_data != ''
        AND m.implementation_data != '{}'
        AND m.implementation_data != 'null'
    ", ARRAY_A
        );

        return new WP_REST_Response(
            array(
            'cache_exists' => $cached !== false,
            'cache_count' => is_array($cached) ? count($cached) : 0,
            'cache_keys' => is_array($cached) ? array_column($cached, 'feature_key') : [],
            'db_count' => count($db_results),
            'db_keys' => array_column($db_results, 'feature_key'),
            'db_results' => $db_results,
            ), 200
        );
    }

    public function get_all_data_files()
    {
        $data_dir = VAPTSECURE_PATH . 'data';
        if (!is_dir($data_dir)) { return new WP_REST_Response([], 200);
        }

        $files = array_diff(scandir($data_dir), array('..', '.'));
        $json_files = [];
        $hidden_files  = get_option('vaptsecure_hidden_json_files', array());
        $removed_files = get_option('vaptsecure_removed_json_files', array());

        $hidden_normalized  = array_map('sanitize_file_name', $hidden_files);
        $removed_normalized = array_map('sanitize_file_name', $removed_files);

        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'json') {
                $normalized_current = sanitize_file_name($file);

                // Skip removed files
                if (in_array($normalized_current, $removed_normalized) || in_array($file, $removed_files)) {
                    continue;
                }

                $json_files[] = array(
                'filename' => $file,
                'isHidden' => in_array($normalized_current, $hidden_normalized) || in_array($file, $hidden_files)
                );
            }
        }

        return new WP_REST_Response($json_files, 200);
    }

    public function get_domains()
    {
        global $wpdb;
        $domains = VAPTSECURE_DB::get_domains();

        foreach ($domains as &$domain) {
            $domain_id = $domain['id'];
            // Only get features that are in Release state
            $feat_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT df.feature_key
                 FROM {$wpdb->prefix}vaptsecure_domain_features df
                 INNER JOIN {$wpdb->prefix}vaptsecure_feature_status fs
                 ON df.feature_key = fs.feature_key
                 WHERE df.domain_id = %d
                 AND df.enabled = 1
                 AND fs.status = 'Release'",
                $domain_id
            ), ARRAY_N);
            $domain['features'] = array_column($feat_rows, 0);
            $domain['imported_at'] = get_option('vaptsecure_imported_at_' . $domain['domain'], null);
            $domain['version'] = get_option(self::domain_version_key($domain['domain']), null);
        }

        return new WP_REST_Response($domains, 200);
    }

    public function update_domain($request)
    {
        global $wpdb;
        $domain = $request->get_param('domain');
        $is_wildcard = $request->get_param('is_wildcard');
        $license_id = $request->get_param('license_id');
        $license_type = $request->get_param('license_type') ?: 'standard';
        $manual_expiry_date = $request->get_param('manual_expiry_date');
        $auto_renew = $request->get_param('auto_renew') !== null ? ($request->get_param('auto_renew') ? 1 : 0) : null;
        $action = $request->get_param('action');
        $license_scope = $request->get_param('license_scope');
        $installation_limit = $request->get_param('installation_limit');
        $user_notes = $request->get_param('user_notes');

        $id = $request->get_param('id');
        if ($id) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vaptsecure_domains WHERE id = %d", $id), ARRAY_A);
            if ($current && !$domain) { $domain = $current['domain'];
            }
        } else {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s", $domain), ARRAY_A);
        }

        $history = $current && !empty($current['renewal_history']) ? json_decode($current['renewal_history'], true) : array();

        $renewals_count = $request->has_param('renewals_count') ? (int) $request->get_param('renewals_count') : ($current ? (int)$current['renewals_count'] : 0);
        if ($auto_renew === null && $current) { $auto_renew = (int)$current['auto_renew'];
        }

        if ($request->has_param('is_wildcard')) {
            $val = $request->get_param('is_wildcard');
            $is_wildcard = (is_string($val)) ? ($val === 'true' || $val === '1') : (bool)$val;
        } else if ($current) {
            $is_wildcard = (int)$current['is_wildcard'];
        }

        if ($request->has_param('is_enabled')) {
            $val = $request->get_param('is_enabled');
            $is_enabled = (is_string($val)) ? ($val === 'true' || $val === '1') : (bool)$val;
        } else if ($current) {
            $is_enabled = (int)$current['is_enabled'];
        } else {
            $is_enabled = 1;
        }
        if ($current && ($license_id === null || $license_id === '')) {
            if (!empty($current['license_id'])) {
                $license_id = $current['license_id'];
            } else {
                $prefix = 'STD-';
                if ($license_type === 'pro') { $prefix = 'PRO-';
                }
                if ($license_type === 'developer') { $prefix = 'DEV-';
                }
                if ($license_type === 'developer_unbound') { $prefix = 'DEVU-';
                }
                $license_id = $prefix . strtoupper(substr(md5(uniqid()), 0, 9));
            }
        }
        if ($manual_expiry_date === null && $current) { $manual_expiry_date = $current['manual_expiry_date'];
        }
        if ($license_scope === null && $current) { $license_scope = $current['license_scope'] ?: 'single';
        }
        if ($installation_limit === null && $current) { $installation_limit = $current['installation_limit'] ?: 1;
        }
        if ($user_notes === null && $current) { $user_notes = $current['user_notes'] ?: '';
        }

        // Auto-generate license ID for new domains if missing (Glitch Fix)
        if (!$current && empty($license_id)) {
            $prefix = 'STD-';
            if ($license_type === 'pro') { $prefix = 'PRO-';
            }
            if ($license_type === 'developer') { $prefix = 'DEV-';
            }
            if ($license_type === 'developer_unbound') { $prefix = 'DEVU-';
            }
            $license_id = $prefix . strtoupper(substr(md5(uniqid()), 0, 9));
        }

        if ($license_type === 'developer_unbound') {
            $manual_expiry_date = null;
            $auto_renew = 0;
        }

        if ($manual_expiry_date) {
            $manual_expiry_date = date('Y-m-d 00:00:00', strtotime($manual_expiry_date));
        }

        $today_ts = strtotime(date('Y-m-d 00:00:00'));
        $current_exp_ts = ($current && !empty($current['manual_expiry_date'])) ? strtotime(date('Y-m-d', strtotime($current['manual_expiry_date']))) : 0;
        $new_exp_ts = $manual_expiry_date ? strtotime(date('Y-m-d', strtotime($manual_expiry_date))) : 0;

        if ($action === 'invalidate') {
            $manual_expiry_date = '1970-01-01 00:00:00';
        } else if ($action === 'undo' && !empty($history)) {
            $last = array_pop($history);
            $days = (int) $last['duration_days'];
            $manual_expiry_date = date('Y-m-d 00:00:00', strtotime($current['manual_expiry_date'] . " -$days days"));
            $renewals_count = max(0, (int)$current['renewals_count'] - 1);
        } else if ($action === 'reset' && !empty($history)) {
            $temp_expiry_ts = $current_exp_ts;
            $temp_count = $renewals_count;

            while (!empty($history)) {
                $entry = end($history);
                if ($entry['source'] === 'auto') { break;
                }

                $days = (int) $entry['duration_days'];
                $potential_expiry_ts = strtotime(date('Y-m-d 00:00:00', $temp_expiry_ts) . " -$days days");

                if ($potential_expiry_ts < $today_ts) { break;
                }

                array_pop($history);
                $temp_expiry_ts = $potential_expiry_ts;
                $temp_count = max(0, $temp_count - 1);
            }
            $manual_expiry_date = date('Y-m-d 00:00:00', $temp_expiry_ts);
            $renewals_count = $temp_count;
        } else {
            if ($current && $new_exp_ts > $current_exp_ts) {
                $diff = $new_exp_ts - $current_exp_ts;
                $days = round($diff / 86400);

                if ($days > 0) {
                    $source = $request->get_param('renew_source') ?: 'manual';
                    $history[] = array(
                    'date_added' => current_time('mysql'),
                    'duration_days' => $days,
                    'license_type' => $license_type,
                    'source' => $source
                    );
                    $renewals_count++;
                }
            }

            if ($auto_renew && $new_exp_ts < $today_ts) {
                $duration = '+30 days';
                $days = 30;
                if ($license_type === 'pro') {
                    $duration = '+1 year';
                    $days = 365;
                }
                if ($license_type === 'developer') {
                    $duration = '+100 years';
                    $days = 36500;
                }
                if ($license_type === 'developer_unbound') {
                    $duration = '+100 years';
                    $days = 36500;
                }

                $manual_expiry_date = date('Y-m-d 00:00:00', strtotime($manual_expiry_date . ' ' . $duration));
                $renewals_count++;

                $history[] = array(
                'date_added' => current_time('mysql'),
                'duration_days' => $days,
                'license_type' => $license_type,
                'source' => 'auto'
                );
            }
        }

        $result_id = VAPTSECURE_DB::update_domain($domain, $is_wildcard ? 1 : 0, $is_enabled ? 1 : 0, $id, $license_id, $license_type, $manual_expiry_date, $auto_renew, $renewals_count, $history, $license_scope, $installation_limit, $user_notes);

        if ($result_id === false) {
            return new WP_REST_Response(array('error' => 'Database update failed'), 500);
        }

        // Check if license was previously expired and is now valid (restoration scenario)
        $was_expired = get_transient('vaptsecure_license_cache_' . $domain . '_expired_handled');
        if ($was_expired && $new_exp_ts > $today_ts && $current_exp_ts < $today_ts) {
            // License was expired, now renewed - restore settings from cache
            if (class_exists('VAPTSECURE_License_Manager')) {
                VAPTSECURE_License_Manager::restore_from_cache($domain);
            }
        }

        $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vaptsecure_domains WHERE id = %d", $result_id), ARRAY_A);

        return new WP_REST_Response(array('success' => true, 'domain' => $fresh), 200);
        }

    public function delete_domain($request)
    {
        $domain_id = $request->get_param('id');
        if (!$domain_id) {
            return new WP_REST_Response(array('error' => 'Missing domain ID'), 400);
        }

        VAPTSECURE_DB::delete_domain($domain_id);
        return new WP_REST_Response(array('success' => true), 200);
    }

    public function batch_delete_domains($request)
    {
        $ids = $request->get_param('ids');
        if (!$ids || !is_array($ids)) {
            return new WP_REST_Response(array('error' => 'Missing or invalid domain IDs'), 400);
        }

        VAPTSECURE_DB::batch_delete_domains($ids);
        return new WP_REST_Response(array('success' => true), 200);
    }

    public function update_domain_features($request)
    {
        global $wpdb;
        $domain_id = $request->get_param('domain_id');
        $features = $request->get_param('features');

        if (! is_array($features)) {
            return new WP_REST_Response(array('error' => 'Invalid features format'), 400);
        }

        $table = $wpdb->prefix . 'vaptsecure_domain_features';

        $wpdb->delete($table, array('domain_id' => $domain_id), array('%d'));

        foreach ($features as $key) {
            $wpdb->insert(
                $table, array(
                'domain_id'   => $domain_id,
                'feature_key' => $key,
                'enabled'     => 1
                ), array('%d', '%s', '%d')
            );
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    public function generate_build($request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }

        // Merge other parameters
        $data['include_config'] = $request->get_param('include_config');
        $data['include_data'] = $request->get_param('include_data');
        $data['license_scope'] = $request->get_param('license_scope');
        $data['installation_limit'] = $request->get_param('installation_limit');
        $data['restrict_features'] = $request->get_param('restrict_features');
        $data['is_wildcard'] = $request->get_param('is_wildcard');
        $data['require_wp'] = $request->get_param('require_wp');
        $data['require_php'] = $request->get_param('require_php');

        // Delegate to Build Class
        include_once VAPTSECURE_PATH . 'includes/class-vaptsecure-build.php';
        try {
            $domain = isset($data['domain']) ? sanitize_text_field($data['domain']) : '';
            $version_key = self::domain_version_key($domain);
            $last_version = (string) get_option($version_key, '');
            $requested_version = isset($data['version']) ? sanitize_text_field($data['version']) : '';

            if ($requested_version === '') {
                $data['version'] = ($last_version !== '') ? self::bump_semver_patch($last_version) : '1.0.0';
            } elseif ($last_version !== '' && $requested_version === $last_version) {
                $data['version'] = self::bump_semver_patch($last_version);
            }

            $download_url = VAPTSECURE_Build::generate($data);
            $built_version = isset($data['version']) ? (string) $data['version'] : '';
            if ($domain && $built_version) {
                update_option($version_key, $built_version);
                update_option('vaptsecure_last_build_at_' . md5(strtolower(trim((string) $domain))), current_time('mysql'));
                global $wpdb;
                $table = $wpdb->prefix . 'vaptsecure_domain_builds';
                $wpdb->insert(
                    $table,
                    array(
                        'domain' => $domain,
                        'version' => $built_version,
                        'features' => json_encode(isset($data['features']) ? $data['features'] : array())
                    ),
                    array('%s', '%s', '%s')
                );
            }

            $next_version = self::bump_semver_patch($built_version);
            return new WP_REST_Response(array('success' => true, 'download_url' => $download_url, 'built_version' => $built_version, 'next_version' => $next_version), 200);
        } catch (Throwable $e) {
            return new WP_REST_Response(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function save_config_to_root($request)
    {
        $domain = $request->get_param('domain');
        $version = $request->get_param('version');
        $features = $request->get_param('features');
        $license_type = $request->get_param('license_type') ?: 'standard';
        $license_scope = $request->get_param('license_scope') ?: 'single';
        $installation_limit = $request->get_param('installation_limit') ?: 1;
        $restrict_features = $request->get_param('restrict_features');

        if (!$domain || !$version) {
            return new WP_REST_Response(array('error' => 'Missing domain or version'), 400);
        }

        $domain_for_files = ($domain === '*' || (is_string($domain) && strpos($domain, '__universal__:') === 0)) ? 'universal' : sanitize_file_name($domain);

        include_once VAPTSECURE_PATH . 'includes/class-vaptsecure-build.php';
        $feature_meta_snapshot = is_array($features) && !empty($features) ? VAPTSECURE_Build::get_feature_meta_snapshot_public($features) : array();

        // Generate candidate config at the CURRENT version to detect changes
        $candidate_content = VAPTSECURE_Build::generate_config_content($domain, $version, $features, null, $license_type, $license_scope, $installation_limit, $restrict_features, '', $feature_meta_snapshot);

        // Extract the B64 payload from the candidate
        $has_changed = true;
        if (preg_match("/VAPTSECURE_CONFIG_B64', '([A-Za-z0-9+\/=]+)'/", $candidate_content, $new_m)) {
            $new_payload_b64 = $new_m[1];
            // Find the existing config file for this domain at current version
            $existing_file = VAPTSECURE_PATH . "vapt-{$domain_for_files}-config-" . sanitize_file_name($version) . ".php";
            if (file_exists($existing_file)) {
                $existing_content = file_get_contents($existing_file);
                if (preg_match("/VAPTSECURE_CONFIG_B64', '([A-Za-z0-9+\/=]+)'/", $existing_content, $old_m)) {
                    // Compare decoded payloads excluding build_at timestamp
                    $new_decoded = json_decode(base64_decode($new_payload_b64), true);
                    $old_decoded = json_decode(base64_decode($old_m[1]), true);
                    if (is_array($new_decoded) && is_array($old_decoded)) {
                        unset($new_decoded['build_at'], $old_decoded['build_at']);
                        $has_changed = (json_encode($new_decoded) !== json_encode($old_decoded));
                    }
                }
            }
        }

        // Bump version only when content actually changed
        $write_version = $has_changed ? self::bump_semver_patch($version) : $version;
        $version_for_files = sanitize_file_name($write_version);
        $config_content = $has_changed
            ? VAPTSECURE_Build::generate_config_content($domain, $write_version, $features, null, $license_type, $license_scope, $installation_limit, $restrict_features, '', $feature_meta_snapshot)
            : $candidate_content;

        $filename = "vapt-{$domain_for_files}-config-{$version_for_files}.php";
        $filepath = VAPTSECURE_PATH . $filename;

        $saved = file_put_contents($filepath, $config_content);

        if ($saved !== false) {
            if ($has_changed) {
                update_option(self::domain_version_key($domain), $write_version);
            }
            $next_version = $has_changed ? self::bump_semver_patch($write_version) : $write_version;
            return new WP_REST_Response(array('success' => true, 'path' => $filepath, 'filename' => $filename, 'built_version' => $write_version, 'next_version' => $next_version, 'changed' => $has_changed), 200);
        } else {
            return new WP_REST_Response(array('error' => 'Failed to write config file to plugin root', 'path' => $filepath), 500);
        }
    }

    public function sync_config_from_file($request)
    {
        $domain = $request->get_param('domain');
        if (!$domain) {
            return new WP_REST_Response(array('error' => 'Missing domain'), 400);
        }

        $domain_for_files = ($domain === '*' || (is_string($domain) && strpos($domain, '__universal__:') === 0)) ? 'universal' : sanitize_file_name($domain);

        $files = glob(VAPTSECURE_PATH . "vapt-*-config-*.php");
        $matched_file = null;

        if ($files) {
            foreach ($files as $file) {
                if (strpos(basename($file), "vapt-{$domain_for_files}-config-") !== false) {
                    $matched_file = $file;
                    break;
                }
            }
        }

        if (!$matched_file && file_exists(VAPTSECURE_PATH . 'vapt-locked-config.php')) {
            $matched_file = VAPTSECURE_PATH . 'vapt-locked-config.php';
        }

        if (!$matched_file) {
            return new WP_REST_Response(array('error' => 'No config file found for domain: ' . $domain), 404);
        }

        $content = file_get_contents($matched_file);
        preg_match_all("/define\( 'VAPTSECURE_FEATURE_(.*?)', true \);/", $content, $matches);

        $features = array();
        if (!empty($matches[1])) {
            foreach ($matches[1] as $key_upper) {
                $features[] = strtolower($key_upper);
            }
        }

        $version = 'Unknown';
        if (preg_match("/Build Version: (.*?)[\r\n]/", $content, $v_match)) {
            $version = trim($v_match[1]);
        }

        update_option('vaptsecure_imported_at_' . $domain, current_time('mysql'));
        update_option('vaptsecure_imported_version_' . $domain, $version);

        return new WP_REST_Response(
            array(
            'success' => true,
            'imported_at' => current_time('mysql'),
            'version' => $version,
            'features_count' => count($features),
            'features' => $features
            ), 200
        );
    }

    public function get_assignees()
    {
        $users = get_users(array('role' => 'administrator'));
        $assignees = array_map(
            function ($u) {
                return array('id' => $u->ID, 'name' => $u->display_name);
            }, $users
        );

        return new WP_REST_Response($assignees, 200);
    }

    public function update_assignment($request)
    {
        global $wpdb;
        $key = $request->get_param('key');
        $user_id = $request->get_param('user_id');
        $table_status = $wpdb->prefix . 'vaptsecure_feature_status';
        $wpdb->update($table_status, array('assigned_to' => $user_id ? $user_id : null), array('feature_key' => $key));

        return new WP_REST_Response(array('success' => true), 200);
    }

    public function upload_media($request)
    {
        if (empty($_FILES['file'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }

        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/media.php';
        include_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_dir_filter = function ($uploads) {
            $subdir = '/vapt-wireframes';
            $uploads['subdir'] = $subdir;
            $uploads['path']   = $uploads['basedir'] . $subdir;
            $uploads['url']    = $uploads['baseurl'] . $subdir;

            if (! file_exists($uploads['path'])) {
                wp_mkdir_p($uploads['path']);
            }
            return $uploads;
        };

        add_filter('upload_dir', $upload_dir_filter);

        $file = $_FILES['file'];
        $upload_overrides = array('test_form' => false);

        $movefile = wp_handle_upload($file, $upload_overrides);

        remove_filter('upload_dir', $upload_dir_filter);

        if ($movefile && ! isset($movefile['error'])) {
            $filename = $movefile['file'];
            $attachment = array(
            'guid'           => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content'   => '',
            'post_status'    => 'inherit'
            );

            $attach_id = wp_insert_attachment($attachment, $filename);
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            wp_update_attachment_metadata($attach_id, $attach_data);

            return new WP_REST_Response(
                array(
                'success' => true,
                'url'     => $movefile['url'],
                'id'      => $attach_id
                ), 200
            );
        } else {
            return new WP_Error('upload_error', $movefile['error'], array('status' => 500));
        }
    }

    /**
     * ========================================================================
     * SCHEMA VALIDATION METHODS
     * ========================================================================
     *
     * The following validation methods have been extracted to
     * class-vaptsecure-schema-validator.php for shared usage:
     *
     * - analyze_enforcement_strategy() -> VAPTSECURE_Schema_Validator::analyze_enforcement_strategy()
     * - sanitize_and_fix_schema() -> VAPTSECURE_Schema_Validator::sanitize_and_fix_schema()
     * - validate_schema() -> VAPTSECURE_Schema_Validator::validate_schema()
     * - validate_implementation_data() -> VAPTSECURE_Schema_Validator::validate_implementation_data()
     * - translate_url_placeholders() -> VAPTSECURE_Schema_Validator::translate_url_placeholders()
     *
     * @deprecated Use VAPTSECURE_Schema_Validator methods directly
     */

    /**
     * 🛡️ INTELLIGENT ENFORCEMENT STRATEGY (v3.3.9)
     * Analyzes the schema and automatically corrects driver selection
     * if it detects physical file targets being handled by PHP hooks.
     *
     * @deprecated Use VAPTSECURE_Schema_Validator::analyze_enforcement_strategy()
     */
    private static function analyze_enforcement_strategy($schema, $feature_key)
    {
        return VAPTSECURE_Schema_Validator::analyze_enforcement_strategy($schema, $feature_key);
    }

    /**
     * Auto-fix common schema issues before validation.
     *
     * @deprecated Use VAPTSECURE_Schema_Validator::sanitize_and_fix_schema()
     */
    private static function sanitize_and_fix_schema($schema)
    {
        return VAPTSECURE_Schema_Validator::sanitize_and_fix_schema($schema);
    }

    /**
     * Validates the feature schema structure.
     *
     * @deprecated Use VAPTSECURE_Schema_Validator::validate_schema()
     */
    private static function validate_schema($schema)
    {
        return VAPTSECURE_Schema_Validator::validate_schema($schema);
    }

    /**
     * 🛡️ IMPLEMENTATION VALIDATOR (v3.6.19)
     * Validates user-provided implementation settings against the feature's JSON schema.
     *
     * @deprecated Use VAPTSECURE_Schema_Validator::validate_implementation_data()
     */
    private static function validate_implementation_data($data, $schema)
    {
        return VAPTSECURE_Schema_Validator::validate_implementation_data($data, $schema);
    }

    /**
     * Translate URL placeholders in schema to fully qualified URLs (v3.12.17)
     *
     * @deprecated Use VAPTSECURE_Schema_Validator::translate_url_placeholders()
     */
    private static function translate_url_placeholders($schema)
    {
        return VAPTSECURE_Schema_Validator::translate_url_placeholders($schema);
    }

    // ========================================================================
    // ORIGINAL METHOD IMPLEMENTATIONS REMOVED

}
