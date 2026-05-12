<?php

/**
 * VAPTSECURE_Enforcer: The Global Security Hammer
 * 
 * Acts as a generic dispatcher that routes enforcement requests to specific drivers
 * (Htaccess, Hooks, etc.) based on the feature's generated_schema.
 */

if (!defined('ABSPATH')) { exit;
}

class VAPTSECURE_Enforcer
{
    private static $runtime_applied = false;

    public static function init()
    {
        // Listen for workbench saves
        add_action('vaptsecure_feature_saved', array(__CLASS__, 'dispatch_enforcement'), 10, 2);

        // Apply PHP-based hooks at runtime
        self::runtime_enforcement();
    }

    /**
     * Applies all active 'hook' based enforcements on every request
     */
    public static function runtime_enforcement()
    {
        if (self::$runtime_applied) {
            return;
        }
        self::$runtime_applied = true;

        $cache_key = 'vaptsecure_active_enforcements';
        $enforced = get_transient($cache_key);

        if (false === $enforced) {
            global $wpdb;
            $table = $wpdb->prefix . 'vaptsecure_feature_meta';
            $is_global = VAPTSECURE_DB::get_global_enforcement();

            if ($is_global) {
                $enforced = $wpdb->get_results(
                    "
          SELECT m.*, s.status 
          FROM $table m
          LEFT JOIN {$wpdb->prefix}vaptsecure_feature_status s ON m.feature_key = s.feature_key
          WHERE s.status IN ('develop', 'release', 'test')
          AND (m.is_enforced = 1 OR m.is_enabled = 1)
        ", 'ARRAY_A'
                );
            } else {
                // [v3.13.20] Global is OFF: Total Kill Switch (Return Nothing)
                $enforced = array();
            }
            set_transient($cache_key, $enforced, HOUR_IN_SECONDS);
        }
        
        vapt_debug("Found " . count($enforced) . " enforced features in runtime enforcement");

        if (empty($enforced)) { return;
        }

        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-hook-driver.php';

        // [v3.13.27] Load external PHP functions files for php_functions enforcer
        self::load_php_functions_file();

        foreach ($enforced as $meta) {
            $feature_key = $meta['feature_key'];

            // [v2.7.0] Restricted Mode Validation
            if (!vaptsecure_is_feature_allowed($feature_key)) {
                vapt_debug("Feature $feature_key skipped: Not allowed in Restricted Mode");
                continue;
            }

            $status = isset($meta['status']) ? strtolower($meta['status']) : 'draft';

            // Override Logic
            $use_override_schema = in_array($status, ['test', 'release']) && !empty($meta['override_schema']);
            $raw_schema = $use_override_schema ? $meta['override_schema'] : $meta['generated_schema'];
            $schema = !empty($raw_schema) ? json_decode($raw_schema, true) : array();

            $use_override_impl = in_array($status, ['test', 'release']) && !empty($meta['override_implementation_data']);
            $raw_impl = $use_override_impl ? $meta['override_implementation_data'] : $meta['implementation_data'];
            $impl_data = !empty($raw_impl) ? json_decode($raw_impl, true) : array();

            $driver = isset($schema['enforcement']['driver']) ? $schema['enforcement']['driver'] : '';

            // [v3.13.27] Skip htaccess for PHP Functions features (use hook instead)
            if ($driver === 'htaccess' && !empty($schema['enforcement']['mappings'])) {
                $mappings = $schema['enforcement']['mappings'];
                foreach ($mappings as $key => $value) {
                    $val_to_test = is_string($value) ? $value : '';
                    if (strpos($val_to_test, 'add_action(') !== false 
                        || strpos($val_to_test, 'add_filter(') !== false 
                        || strpos($val_to_test, 'function ') !== false
                    ) {
                        vapt_debug("Skipping htaccess for $feature_key - using hook driver instead");
                        $driver = 'hook';
                        $schema['enforcement']['driver'] = 'hook';
                        break;
                    }
                }
            }

            // Runtime hooks should only run for PHP-backed features. Static file drivers
            // are handled by their generated config files and should not re-apply on every request.
            if (self::driver_needs_runtime_hooks($driver, $schema)) {
                if (class_exists('VAPTSECURE_Hook_Driver')) {
                    VAPTSECURE_Hook_Driver::apply($impl_data, $schema, $meta['feature_key']);
                }
            }
        }
    }

    /**
     * [v3.13.27] Load external PHP functions file if it exists
     * Handles php_functions enforcer which writes to external files
     */
    private static function load_php_functions_file()
    {
        // Check for local bundled version
        $bundled_path = VAPTSECURE_PATH . 'vapt-functions.php';
        if (file_exists($bundled_path)) {
            include_once $bundled_path;
            vapt_debug("Loaded bundled vapt-functions.php");
        }
    }

    private static function driver_needs_runtime_hooks($driver, $schema)
    {
        $driver = strtolower((string) $driver);
        if (in_array($driver, array('hook', 'php_functions', 'universal'), true)) {
            return true;
        }

        $mappings = isset($schema['enforcement']['mappings']) && is_array($schema['enforcement']['mappings'])
            ? $schema['enforcement']['mappings']
            : array();

        foreach ($mappings as $value) {
            $code = is_string($value) ? $value : json_encode($value);
            if (is_string($code) && preg_match('/\b(add_action|add_filter|remove_action|remove_filter|function)\b/i', $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Entry point for enforcement after a feature is saved.
     * Uses per-feature deployment so only the current feature is updated.
     */
    public static function dispatch_enforcement($key, $data)
    {
        // Clear runtime cache so changes apply instantly
        delete_transient('vaptsecure_active_enforcements');

        $toggle_off = false;
        foreach (array('is_enabled', 'is_enforced', 'enabled', 'feat_enabled', 'prot_enabled') as $toggle_key) {
            if (!array_key_exists($toggle_key, $data)) { continue; }
            $toggle_value = filter_var($data[$toggle_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($toggle_value === false) {
                $toggle_off = true;
                break;
            }
        }

        if ($toggle_off) {
            error_log("VAPT ENFORCER: Toggle OFF detected for {$key}; removing feature from all config files.");
            self::undeploy_feature($key);
            return;
        }

        $meta = VAPTSECURE_DB::get_feature_meta($key);
        if (!$meta) { 
            error_log("VAPT ENFORCER: No meta found for {$key}, skipping dispatch");
            return;
        }
        
        error_log("VAPT ENFORCER: Dispatching enforcement for {$key}, is_enabled={$meta['is_enabled']}, is_enforced={$meta['is_enforced']}, is_adaptive={$meta['is_adaptive_deployment']}");

        // Fetch Status for Context
        global $wpdb;
        $status_row = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$wpdb->prefix}vaptsecure_feature_status WHERE feature_key = %s", $key));
        $status = $status_row ? strtolower($status_row->status) : 'draft';
        $meta['status'] = $status;

        // Override Logic
        $use_override_schema = in_array($status, ['test', 'release']) && !empty($meta['override_schema']);
        $raw_schema = $use_override_schema ? $meta['override_schema'] : $meta['generated_schema'];
        $schema = !empty($raw_schema) ? json_decode($raw_schema, true) : array();
        
        error_log("VAPT ENFORCER: Schema has enforcement=" . (isset($schema['enforcement']) ? 'YES' : 'NO') . ", driver=" . ($schema['enforcement']['driver'] ?? 'none'));

        $impl_data = self::resolve_impl($meta);

        // [FIX v1.4.0] Always deploy even if this feature has no enforcement block.
        // This ensures that toggling OFF removes previously written rules from config files.
        if (empty($schema['enforcement'])) {
            error_log("VAPT ENFORCER: No enforcement block for {$key}, deploying feature to all platforms");
            self::deploy_feature($key, $schema, $impl_data);
            return;
        }

        // [v4.0.0] Adaptive Deployment Orchestration
        $is_adaptive = $meta['is_adaptive_deployment'] ?? null;
        if ($is_adaptive !== null && $is_adaptive !== '0' && $is_adaptive !== 0 && $is_adaptive !== false) {
            include_once VAPTSECURE_PATH . 'includes/class-vaptsecure-deployment-orchestrator.php';
            $orchestrator = new VAPTSECURE_Deployment_Orchestrator();

            // Use profile from settings if available, else default to auto_detect
            $profile = get_option('vaptsecure_deployment_profile', 'auto_detect');
            $results = $orchestrator->orchestrate($key, $schema, $profile, $impl_data);

            error_log("VAPT: Adaptive Deployment for {$key} results: " . json_encode($results));
            return;
        }

        // Non-adaptive: deploy to all relevant platforms for this feature only
        self::deploy_feature($key, $schema, $impl_data);
        error_log("VAPT ENFORCER: Per-feature dispatch complete for {$key}");
    }

    /**
     * Deploy a single feature to all platforms it has rules for.
     * Does NOT loop through other features.
     */
    private static function deploy_feature($key, $schema, $impl_data)
    {
        // First, clean up any existing blocks for this feature across all platforms
        self::undeploy_feature($key);

        // .htaccess
        $htaccess_rules = VAPTSECURE_Htaccess_Driver::generate_rules($impl_data, $schema);
        if (!empty($htaccess_rules)) {
            $deployer = new VAPTSECURE_Apache_Deployer();
            $result = $deployer->deploy($key, array('rules' => $htaccess_rules), true);
            if (is_wp_error($result)) {
                error_log("VAPT ENFORCER: Apache deploy failed for {$key}: " . $result->get_error_message());
            } else {
                error_log("VAPT ENFORCER: Apache deploy success for {$key}: " . json_encode($result));
            }
        } else {
            error_log("VAPT ENFORCER: No htaccess rules generated for {$key}");
        }

        // wp-config.php
        $config_rules = VAPTSECURE_Config_Driver::generate_rules($impl_data, $schema);
        if (!empty($config_rules)) {
            $deployer = new VAPTSECURE_Config_Deployer();
            $result = $deployer->deploy($key, $config_rules, true);
            if (is_wp_error($result)) {
                error_log("VAPT ENFORCER: Config deploy failed for {$key}: " . $result->get_error_message());
            } else {
                error_log("VAPT ENFORCER: Config deploy success for {$key}");
            }
        }

        // vapt-functions.php
        $php_rules = VAPTSECURE_PHP_Driver::generate_rules($impl_data, $schema);
        if (!empty($php_rules)) {
            $deployer = new VAPTSECURE_PHP_Deployer();
            $result = $deployer->deploy($key, $php_rules, true);
            if (is_wp_error($result)) {
                error_log("VAPT ENFORCER: PHP deploy failed for {$key}: " . $result->get_error_message());
            } else {
                error_log("VAPT ENFORCER: PHP deploy success for {$key}");
            }
        }
    }

    /**
     * Remove a single feature from all platforms.
     * Does NOT loop through other features.
     */
    private static function undeploy_feature($key)
    {
        $deployer = new VAPTSECURE_Apache_Deployer();
        $deployer->undeploy($key);

        $deployer = new VAPTSECURE_Config_Deployer();
        $deployer->undeploy($key);

        $deployer = new VAPTSECURE_PHP_Deployer();
        $deployer->undeploy($key);
    }

    /**
     * Rebuilds Nginx Rules File
     */
    private static function rebuild_nginx()
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-nginx-driver.php';
        if (!class_exists('VAPTSECURE_Nginx_Driver')) { return;
        }

        $features = self::get_enforced_features();

        // [ENHANCEMENT] Filter by Active Data Files (v3.12.0)
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $features = array_filter(
                $features, function ($feat) use ($active_keys) {
                    // [FIX] Always allow XML-RPC regardless of key mismatch (v3.12.13)
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        $all_rules = [];

        foreach ($features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl = self::resolve_impl($meta);
            $driver = $schema['enforcement']['driver'] ?? '';

            if ($driver === 'nginx' || $driver === 'htaccess' || $driver === 'universal') {
                $rules = VAPTSECURE_Nginx_Driver::generate_rules($impl, $schema);
                if ($rules && is_array($rules)) { $all_rules = array_merge($all_rules, $rules);
                }
            }
        }

        VAPTSECURE_Nginx_Driver::write_batch($all_rules);
    }

    /**
     * Rebuilds Cloudflare (Interface/API Meta)
     */
    private static function rebuild_cloudflare()
    {
        // Cloudflare enforcement is currently informational/manual via the dashboard instructions.
        // In future versions, this would trigger an API sync.
        error_log('VAPT: Cloudflare rebuild triggered (Informational - Manual Action required in Dashboard)');
    }

    // Helper to fetch enforced features (DRY)
    private static function get_enforced_features()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $is_global = VAPTSECURE_DB::get_global_enforcement();

        if ($is_global) {
            // [FIX v4.0.x] Check both is_enabled and is_enforced for toggle compatibility
            // is_enforced is the traditional flag, is_enabled is synced from the toggle
            // [v4.0.3] Status values match DB ENUM (Title Case)
            return $wpdb->get_results(
                "
        SELECT m.*, s.status 
        FROM $table m 
        LEFT JOIN {$wpdb->prefix}vaptsecure_feature_status s ON m.feature_key = s.feature_key 
        WHERE s.status IN ('Develop', 'Release', 'Test', 'develop', 'release', 'test')
        AND (m.is_enforced = 1 OR m.is_enabled = 1)
      ", ARRAY_A
            );
        } else {
            // [v3.13.20] Global is OFF: Total Kill Switch
            return array();
        }
    }

    // Helpers for Schema/Impl Resolution
    private static function resolve_schema($meta)
    {
        $status = $meta['status'] ?? 'draft';
        $raw = (in_array($status, ['test', 'release']) && !empty($meta['override_schema'])) ? $meta['override_schema'] : $meta['generated_schema'];
        $schema = $raw ? json_decode($raw, true) : [];

        // [v4.0.0] Adaptive Schema Resolution
        // Prefer the catalog enforcement definition whenever the saved schema is missing
        // or has an incomplete enforcement block (common after older generated payloads).
        if (!isset($schema['enforcement']) || empty($schema['enforcement']) || empty($schema['enforcement']['mappings'])) {
            if (isset($schema['client_deployment']['enforcement']) && is_array($schema['client_deployment']['enforcement'])) {
                $schema['enforcement'] = $schema['client_deployment']['enforcement'];
            }
        }

        // [v3.12.5] Inject feature key if missing
        if (!isset($schema['feature_key']) && isset($meta['feature_key'])) {
            $schema['feature_key'] = $meta['feature_key'];
        }

        $catalog_schema = self::load_catalog_feature_schema($schema['feature_key'] ?? ($meta['feature_key'] ?? ''));
        if (is_array($catalog_schema)) {
            $schema_key = $schema['feature_key'] ?? ($meta['feature_key'] ?? '');
            if (empty($schema['platform_implementations']) && !empty($catalog_schema['platform_implementations'])) {
                $schema['platform_implementations'] = $catalog_schema['platform_implementations'];
            }
            if (empty($schema['client_deployment']) && !empty($catalog_schema['client_deployment'])) {
                $schema['client_deployment'] = $catalog_schema['client_deployment'];
            }
            if ((empty($schema['enforcement']) || empty($schema['enforcement']['mappings'])) && !empty($catalog_schema['client_deployment']['enforcement'])) {
                $schema['enforcement'] = $catalog_schema['client_deployment']['enforcement'];
            }
            if ((empty($schema['enforcement']) || empty($schema['enforcement']['mappings'])) && !empty($catalog_schema['platform_implementations']) && is_array($catalog_schema['platform_implementations'])) {
                $platform_code = self::resolve_pattern_code_ref(
                    $catalog_schema['platform_implementations']['.htaccess']['code_ref'] ?? ($catalog_schema['platform_implementations']['htaccess']['code_ref'] ?? ''),
                    'htaccess'
                );
                $schema['enforcement'] = array(
                'driver' => 'htaccess',
                'target' => 'root',
                'mappings' => self::build_toggle_alias_mappings($schema_key, $platform_code)
                );
            }
        }

        // [v4.0.2] Schema self-heal: derive an enforcement block from platform implementations
        // when older saved schemas only contain the UI catalog data.
        if ((empty($schema['enforcement']) || empty($schema['enforcement']['mappings'])) && !empty($schema['platform_implementations']) && is_array($schema['platform_implementations'])) {
            $risk_key = $schema['risk_id'] ?? $schema['feature_key'] ?? ($meta['feature_key'] ?? '');
            $risk_suffix = str_replace('-', '_', strtolower((string) $risk_key));
            $auto_key = "vapt_risk_{$risk_suffix}_enabled";

            $platform_order = array('.htaccess', 'htaccess', 'cloudflare');
            $selected_platform = null;
            foreach ($platform_order as $candidate) {
                foreach ($schema['platform_implementations'] as $platform_name => $platform_impl) {
                    $normalized = strtolower(str_replace(array(' ', '-'), '', (string) $platform_name));
                    $candidate_norm = strtolower(str_replace(array(' ', '-', '.'), '', (string) $candidate));
                    if ($normalized === $candidate_norm || strpos($normalized, $candidate_norm) !== false || strpos($candidate_norm, $normalized) !== false) {
                        $selected_platform = $platform_name;
                        break 2;
                    }
                }
            }

            if ($selected_platform !== null) {
                $platform_impl = $schema['platform_implementations'][$selected_platform];
                $platform_code = '';
                if (is_array($platform_impl)) {
                    if (!empty($platform_impl['code'])) {
                        $platform_code = $platform_impl['code'];
                    } elseif (!empty($platform_impl['wrapped_code'])) {
                        $platform_code = $platform_impl['wrapped_code'];
                    } elseif (!empty($platform_impl['code_ref'])) {
                        $platform_code = self::resolve_pattern_code_ref($platform_impl['code_ref'], 'htaccess');
                    }
                } else {
                    $platform_code = (string) $platform_impl;
                }

                if (!empty($platform_code)) {
                    $schema['enforcement'] = array(
                    'driver' => ((stripos($selected_platform, 'cloudflare') !== false) ? 'cloudflare' : 'htaccess'),
                    'target' => (stripos($selected_platform, 'uploads') !== false) ? 'uploads' : 'root',
                    'mappings' => self::build_toggle_alias_mappings($schema['feature_key'] ?? ($meta['feature_key'] ?? ''), $platform_code)
                    );
                }
            }
        }

        // [v4.1.x] Multi-platform auto-detection: if a feature has 2+ real platform
        // implementations, force driver to 'universal' so rebuild_htaccess,
        // rebuild_config, and rebuild_php_functions all process it.
        if (!empty($schema['platform_implementations']) && is_array($schema['platform_implementations'])) {
            $real_platforms = 0;
            foreach ($schema['platform_implementations'] as $pname => $pimpl) {
                if (is_array($pimpl)) {
                    if (!empty($pimpl['code']) || !empty($pimpl['wrapped_code']) || !empty($pimpl['code_ref'])) {
                        $real_platforms++;
                    }
                } elseif (is_string($pimpl) && strlen(trim($pimpl)) > 0) {
                    $real_platforms++;
                }
            }
            if ($real_platforms > 1) {
                if (empty($schema['enforcement']) || !is_array($schema['enforcement'])) {
                    $schema['enforcement'] = array();
                }
                $schema['enforcement']['driver'] = 'universal';
            }
        }

        return $schema;
    }

    /**
     * Load the canonical feature definition from the active catalog bundle.
     */
    private static function load_catalog_feature_schema($feature_key)
    {
        $feature_key = strtoupper(trim((string) $feature_key));
        if ($feature_key === '') {
            return array();
        }

        $active_file = defined('VAPTSECURE_ACTIVE_DATA_FILE')
            ? VAPTSECURE_ACTIVE_DATA_FILE
            : get_option('vaptsecure_active_feature_file', 'interface_schema_v2.0.json');

        $path = VAPTSECURE_PATH . 'data/' . sanitize_file_name((string) $active_file);
        if (!file_exists($path)) {
            $fallback = VAPTSECURE_PATH . 'data/interface_schema_v2.0.json';
            if (file_exists($fallback)) {
                $path = $fallback;
            } else {
                return array();
            }
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return array();
        }

        if (isset($data['risk_interfaces'][$feature_key]) && is_array($data['risk_interfaces'][$feature_key])) {
            return $data['risk_interfaces'][$feature_key];
        }

        if (isset($data[$feature_key]) && is_array($data[$feature_key])) {
            return $data[$feature_key];
        }

        if (!empty($data['risk_interfaces']) && is_array($data['risk_interfaces'])) {
            foreach ($data['risk_interfaces'] as $item_key => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $candidate = strtoupper(trim((string) ($item['risk_id'] ?? $item['id'] ?? $item['key'] ?? $item_key)));
                if ($candidate === $feature_key) {
                    return $item;
                }
            }
        }

        return array();
    }

    /**
     * Resolve a code_ref into concrete code from the bundled pattern library.
     */
    public static function resolve_pattern_code_ref($ref, $platform)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return '';
        }

        $pattern_lib_path = VAPTSECURE_PATH . 'data/enforcer_pattern_library_v2.0.json';
        if (!file_exists($pattern_lib_path)) {
            return '';
        }

        static $pattern_lib = null;
        if ($pattern_lib === null) {
            $pattern_lib = json_decode(file_get_contents($pattern_lib_path), true);
            if (!is_array($pattern_lib)) {
                $pattern_lib = array();
            }
        }

        $code_ref_clean = preg_replace('/^.*?\.patterns\./', 'patterns.', $ref);
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
            return $current_node;
        }

        if (is_array($current_node)) {
            if (isset($current_node[$platform])) {
                $inner = $current_node[$platform];
                if (is_array($inner)) {
                    return $inner['code'] ?? $inner['wrapped_code'] ?? '';
                }
                return (string) $inner;
            }

            if (isset($current_node['code'])) {
                return $current_node['code'];
            }
            if (isset($current_node['wrapped_code'])) {
                return $current_node['wrapped_code'];
            }
        }

        return '';
    }

    /**
     * Build a tolerant toggle mapping set for the synthesized enforcement block.
     */
    private static function build_toggle_alias_mappings($feature_key, $platform_code)
    {
        $feature_key = (string) $feature_key;
        $risk_suffix = str_replace('-', '_', strtolower($feature_key));
        $auto_key = $risk_suffix !== '' ? "vapt_risk_{$risk_suffix}_enabled" : 'feat_enabled';

        return array(
            'feat_enabled' => $platform_code,
            'enabled' => $platform_code,
            'prot_enabled' => $platform_code,
            $auto_key => $platform_code,
        );
    }

    private static function resolve_impl($meta)
    {
        $status = $meta['status'] ?? 'draft';
        $raw = (in_array($status, ['test', 'release']) && !empty($meta['override_implementation_data'])) ? $meta['override_implementation_data'] : $meta['implementation_data'];
        $resolved = $raw ? json_decode($raw, true) : [];
        if (!is_array($resolved)) {
            $resolved = array();
        }

        // Prefer the explicit UI toggle aliases first so OFF can override stale legacy flags.
        $toggle_state = null;
        $toggle_priority = array('feat_enabled', 'enabled', 'prot_enabled', 'is_enforced', 'is_enabled');
        foreach ($toggle_priority as $toggle_key) {
            if (array_key_exists($toggle_key, $resolved)) {
                $toggle_state = filter_var($resolved[$toggle_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                break;
            }
            if (array_key_exists($toggle_key, $meta)) {
                $toggle_state = filter_var($meta[$toggle_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                break;
            }
        }

        if ($toggle_state === null) {
            $auto_key = 'vapt_risk_' . str_replace('-', '_', strtolower((string)($meta['feature_key'] ?? $meta['risk_id'] ?? $meta['id'] ?? ''))) . '_enabled';
            if (array_key_exists($auto_key, $resolved)) {
                $toggle_state = filter_var($resolved[$auto_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } elseif (array_key_exists($auto_key, $meta)) {
                $toggle_state = filter_var($meta[$auto_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($toggle_state !== null) {
            $resolved['enabled'] = $toggle_state;
            $resolved['feat_enabled'] = $toggle_state;
            $resolved['prot_enabled'] = $toggle_state;
        }

        return $resolved;
    }

    /**
     * Rebuilds .htaccess using per-feature blocks (no consolidated block).
     * Only loops through features for full rebuilds; single-feature toggles bypass this.
     */
    private static function rebuild_htaccess()
    {
        error_log('VAPT: rebuild_htaccess called');
        
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-htaccess-driver.php';
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-apache-deployer.php';
        if (!class_exists('VAPTSECURE_Htaccess_Driver') || !class_exists('VAPTSECURE_Apache_Deployer')) { 
            error_log('VAPT: Htaccess Driver or Apache Deployer not found, skipping rebuild');
            return;
        }

        // 1. Remove all existing blocks (both consolidated and per-feature)
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $all_features = $wpdb->get_results("SELECT feature_key FROM $table", ARRAY_A);
        $deployer = new VAPTSECURE_Apache_Deployer();
        foreach ($all_features as $feat) {
            $deployer->undeploy($feat['feature_key'], 'root');
            $deployer->undeploy($feat['feature_key'], 'uploads');
        }

        // 2. Get enabled features and filter by active keys
        $enforced_features = self::get_enforced_features();
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        // 3. Deploy each enabled feature individually
        foreach ($enforced_features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            $driver = isset($schema['enforcement']['driver']) ? $schema['enforcement']['driver'] : '';
            $target = isset($schema['enforcement']['target']) ? $schema['enforcement']['target'] : 'root';
            if ($target === '.htaccess') { $target = 'root'; }

            if ($driver === 'htaccess' || $driver === 'universal') {
                $feature_rules = VAPTSECURE_Htaccess_Driver::generate_rules($impl_data, $schema);
                if (!empty($feature_rules)) {
                    $deployer->deploy($meta['feature_key'], array('rules' => $feature_rules, 'target' => $target), true);
                }
            }
        }
    }

    /**
     * Rebuilds wp-config.php using per-feature blocks (no consolidated block).
     * Only loops through features for full rebuilds; single-feature toggles bypass this.
     */
    public static function rebuild_config()
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-config-driver.php';
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-config-deployer.php';
        if (!class_exists('VAPTSECURE_Config_Driver') || !class_exists('VAPTSECURE_Config_Deployer')) { return;
        }

        // 1. Remove all existing per-feature blocks
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $all_features = $wpdb->get_results("SELECT feature_key FROM $table", ARRAY_A);
        $deployer = new VAPTSECURE_Config_Deployer();
        foreach ($all_features as $feat) {
            $deployer->undeploy($feat['feature_key']);
        }

        // 2. Get enabled features and filter by active keys
        $enforced_features = self::get_enforced_features();
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        // 3. Deploy each enabled feature individually
        foreach ($enforced_features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            $driver = $schema['enforcement']['driver'] ?? '';

            if ($driver === 'config' || $driver === 'wp-config' || $driver === 'wp_config' || $driver === 'universal') {
                $feature_rules = VAPTSECURE_Config_Driver::generate_rules($impl_data, $schema);
                if (!empty($feature_rules)) {
                    $deployer->deploy($meta['feature_key'], $feature_rules, true);
                }
            }
        }
    }

    /**
     * Rebuilds all enforcements across all active drivers
     *
     * @param bool $remove_only If true, removes all VAPT rules instead of rebuilding
     */
    public static function rebuild_all($remove_only = false)
    {
        // [v4.0.1] Always purge the enforcement cache FIRST so get_enforced_features()
        // reads fresh DB data — especially critical when called from transition_feature on reset.
        delete_transient('vaptsecure_active_enforcements');
    
        if ($remove_only) {
            // Remove all VAPT rules from configuration files
            self::clean_all_config_files();
            return;
        }
    
        self::rebuild_htaccess();
        self::rebuild_config();
        self::rebuild_nginx();
        self::rebuild_php_functions();
        delete_transient('vaptsecure_active_enforcements');
    }

    /**
     * Self-Healing: Verify and re-inject rules for a single enabled feature if missing.
     * Returns the audit result with a 'self_healed' flag if re-injection occurred.
     *
     * @param string $feature_key
     * @return array Audit summary item with 'self_healed' flag
     */
    public static function self_heal_feature($feature_key)
    {
        $feature_key = trim((string) $feature_key);
        if ($feature_key === '') {
            return array();
        }

        $meta = VAPTSECURE_DB::get_feature_meta($feature_key);
        if (!$meta) {
            return array();
        }

        $is_enabled = !empty($meta['is_enabled']) || !empty($meta['is_enforced']);
        if (!$is_enabled) {
            return array();
        }

        $audit = self::audit_feature_cleanup($feature_key);
        $needs_heal = true;
        foreach ($audit as $item) {
            if (isset($item['status']) && strtolower((string) $item['status']) === 'present') {
                $needs_heal = false;
                break;
            }
        }

        if ($needs_heal) {
            error_log("VAPT SELF-HEAL: Rules missing for enabled feature {$feature_key}. Re-injecting...");
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            self::deploy_feature($feature_key, $schema, $impl_data);

            // Re-audit after healing
            $audit = self::audit_feature_cleanup($feature_key);
            foreach ($audit as &$item) {
                $item['self_healed'] = true;
                $item['live_state'] = 'recovered';
            }
            error_log("VAPT SELF-HEAL: Re-injection complete for {$feature_key}");
        }

        return $audit;
    }

    /**
     * Background Verification: Run on admin page load.
     * Checks all enabled features and re-injects missing rules.
     * Stores results in a transient for frontend consumption.
     *
     * @return array Healed feature keys
     */
    public static function run_background_verification()
    {
        $features = self::get_enforced_features();
        $healed = array();

        foreach ($features as $feat) {
            $key = $feat['feature_key'];
            $audit = self::self_heal_feature($key);
            foreach ($audit as $item) {
                if (!empty($item['self_healed'])) {
                    $healed[] = $key;
                    break;
                }
            }
        }

        if (!empty($healed)) {
            set_transient('vaptsecure_self_healed_features', $healed, HOUR_IN_SECONDS);
        }

        return $healed;
    }

    /**
     * [v4.0.x] Build a file-target audit for a feature.
     * Checks ALL three active deployment targets and returns the one(s)
     * that actually contain the feature marker. Fixes stale schema driver
     * drift where deploy_feature writes to .htaccess but schema says wp_config.
     */
    public static function audit_feature_cleanup($feature_key)
    {
        $feature_key = trim((string) $feature_key);
        if ($feature_key === '') {
            return array();
        }

        $meta = VAPTSECURE_DB::get_feature_meta($feature_key);
        $is_enabled = !empty($meta['is_enabled']) || !empty($meta['is_enforced']);

        // Resolve wp-config.php path robustly
        $wp_config_path = ABSPATH . 'wp-config.php';
        if (!file_exists($wp_config_path) && function_exists('get_home_path')) {
            $alt = get_home_path() . 'wp-config.php';
            if (file_exists($alt)) {
                $wp_config_path = $alt;
            }
        }

        $htaccess_path = (function_exists('get_home_path') ? get_home_path() : ABSPATH) . '.htaccess';
        $php_path      = VAPTSECURE_PATH . 'vapt-functions.php';

        $targets = array(
            array('key' => 'htaccess', 'label' => './.htaccess', 'path' => $htaccess_path, 'type' => 'htaccess'),
            array('key' => 'wp-config', 'label' => './wp-config.php', 'path' => $wp_config_path, 'type' => 'config'),
            array('key' => 'php_functions', 'label' => 'vapt-functions.php', 'path' => $php_path, 'type' => 'php'),
        );

        $audit = array();
        $found_in_any = false;

        foreach ($targets as $t) {
            $exists = file_exists($t['path']);
            $content = $exists ? @file_get_contents($t['path']) : '';
            $has_marker = false;

            if ($content !== false && $content !== '') {
                if ($t['type'] === 'htaccess') {
                    $has_marker = (
                        strpos($content, '# BEGIN VAPT ' . $feature_key) !== false ||
                        strpos($content, '# ' . $feature_key) !== false ||
                        (strpos($content, '# BEGIN VAPT SECURITY RULES') !== false && strpos($content, $feature_key) !== false)
                    );
                } elseif ($t['type'] === 'config') {
                    $has_marker = (
                        strpos($content, 'BEGIN VAPT CONFIG RULES') !== false ||
                        strpos($content, 'BEGIN VAPT SECURITY RULES') !== false ||
                        strpos($content, $feature_key) !== false
                    );
                } elseif ($t['type'] === 'php') {
                    $has_marker = (
                        strpos($content, '// BEGIN VAPT ' . $feature_key) !== false ||
                        strpos($content, '# BEGIN VAPT ' . $feature_key) !== false ||
                        strpos($content, $feature_key) !== false
                    );
                }
            }

            if ($has_marker) {
                $found_in_any = true;
            }

            $live_state = $has_marker ? 'present' : 'missing';
            if (!$is_enabled && !$has_marker) {
                $live_state = 'cleaned';
            }

            $audit[] = array(
                'target'  => $t['key'],
                'label'   => $t['label'],
                'path'    => $t['path'],
                'exists'  => $exists,
                'status'  => $has_marker ? 'present' : 'removed',
                'live_state' => $live_state,
                'feature_enabled' => $is_enabled,
            );
        }

        // Re-order: put the file that actually HAS the marker first (source of truth)
        // so frontend tooltip can reliably use audit_summary[0]
        usort(
            $audit, function ($a, $b) {
                $a_score = ($a['status'] === 'present') ? 2 : ($a['feature_enabled'] ? 1 : 0);
                $b_score = ($b['status'] === 'present') ? 2 : ($b['feature_enabled'] ? 1 : 0);
                return $b_score - $a_score;
            }
        );

        return $audit;
    }

    /**
     * Clean all configuration files of VAPT rules
     * Used when license expires or when removing protections
     * 
     * Delegates to the shared Config Cleaner utility to avoid code duplication
     * with the License Manager.
     * 
     * @return array Results of cleaning operations
     */
    public static function clean_all_config_files()
    {
        // Use shared Config Cleaner if available
        if (class_exists('VAPTSECURE_Config_Cleaner')) {
            return VAPTSECURE_Config_Cleaner::clean_all();
        }
        
        // Fallback: perform basic cleaning inline
        return self::legacy_clean_all_config_files();
    }
    
    /**
     * Legacy config cleaning implementation (fallback)
     * Kept for backward compatibility if Config Cleaner is not loaded
     * 
     * @return array Results of cleaning operations
     */
    private static function legacy_clean_all_config_files()
    {
        $results = array();
        
        // Clean .htaccess
        $htaccess = ABSPATH . '.htaccess';
        if (file_exists($htaccess) && is_writable($htaccess)) {
            $content = file_get_contents($htaccess);
            $content = preg_replace('/# BEGIN VAPT[^\n]*\n.*?# END VAPT[^\n]*/s', '', $content);
            $content = preg_replace('/# BEGIN VAPT-RISK[^\n]*\n.*?# END VAPT-RISK[^\n]*/s', '', $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            $results['htaccess'] = (bool) file_put_contents($htaccess, $content);
        } else {
            $results['htaccess'] = !file_exists($htaccess);
        }
    
        // Clean wp-config.php
        $wp_config = ABSPATH . 'wp-config.php';
        if (file_exists($wp_config) && is_writable($wp_config)) {
            $content = file_get_contents($wp_config);
            $content = preg_replace('/\/\/ BEGIN VAPT[^\n]*\n.*?\/\/ END VAPT[^\n]*/s', '', $content);
            $content = preg_replace('/\/\* BEGIN VAPT[^\n]*\*\/.*?\/\* END VAPT[^\n]*\*\//s', '', $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            $results['wp_config'] = (bool) file_put_contents($wp_config, $content);
        } else {
            $results['wp_config'] = !file_exists($wp_config);
        }
    
        // Clean vapt-functions.php
        $vapt_func = VAPTSECURE_PATH . 'vapt-functions.php';
        if (file_exists($vapt_func) && is_writable($vapt_func)) {
            $content = "<?php\n\n/**\n * VAPTSecure Clean Functions\n * License Expired - Functions Disabled\n */\n\nif (!defined('ABSPATH')) { exit; }\n\n";
            $results['php_functions'] = (bool) file_put_contents($vapt_func, $content);
        } else {
            $results['php_functions'] = !file_exists($vapt_func);
        }
    
        // Clean nginx.conf if exists
        $nginx_conf = ABSPATH . 'nginx.conf';
        if (file_exists($nginx_conf) && is_writable($nginx_conf)) {
            $content = file_get_contents($nginx_conf);
            $content = preg_replace('/# BEGIN VAPT[^\n]*\n.*?# END VAPT[^\n]*/s', '', $content);
            $results['nginx'] = (bool) file_put_contents($nginx_conf, $content);
        } else {
            $results['nginx'] = true;
        }
    
        return $results;
    }

    /**
     * [v4.1.x] Rebuilds vapt-functions.php using per-feature blocks (no consolidated block).
     * Only loops through features for full rebuilds; single-feature toggles bypass this.
     */
    public static function rebuild_php_functions()
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-php-driver.php';
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-php-deployer.php';
        if (!class_exists('VAPTSECURE_PHP_Driver') || !class_exists('VAPTSECURE_PHP_Deployer')) { return;
        }

        // 1. Remove all existing per-feature blocks
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $all_features = $wpdb->get_results("SELECT feature_key FROM $table", ARRAY_A);
        $deployer = new VAPTSECURE_PHP_Deployer();
        foreach ($all_features as $feat) {
            $deployer->undeploy($feat['feature_key']);
        }

        // 2. Get enabled features and filter by active keys
        $enforced_features = self::get_enforced_features();
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        // 3. Deploy each enabled feature individually
        foreach ($enforced_features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            $driver = $schema['enforcement']['driver'] ?? '';

            if ($driver === 'php_functions' || $driver === 'hook' || $driver === 'universal') {
                $feature_rules = VAPTSECURE_PHP_Driver::generate_rules($impl_data, $schema);
                if (!empty($feature_rules) && is_array($feature_rules)) {
                    $deployer->deploy($meta['feature_key'], $feature_rules, true);
                }
            }
        }
    }

    /**
     * Helper to fetch all feature keys present in the currently active data files.
     */
    private static function get_active_file_keys()
    {
        $active_files_raw = defined('VAPTSECURE_ACTIVE_DATA_FILE') ? VAPTSECURE_ACTIVE_DATA_FILE : get_option('vaptsecure_active_feature_file', '');
        $files = array_filter(explode(',', $active_files_raw));
        $keys = [];

        foreach ($files as $file) {
            $path = VAPTSECURE_PATH . 'data/' . sanitize_file_name(trim($file));
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $data = json_decode($content, true);
                if ($data) {
                    $features = $data['risk_catalog'] ?? $data['features'] ?? $data['wordpress_vapt'] ?? $data['risk_interfaces'] ?? null;

                    if ($features && (is_array($features) || is_object($features))) {
                        foreach ($features as $k => $v) {
                            if (is_array($v) || is_object($v)) {
                                  $keys[] = $v['risk_id'] ?? $v['id'] ?? $v['key'] ?? (is_string($k) ? $k : '');
                            }
                        }
                    } else {
                        // Flat Object structure (v1.1)
                        foreach ($data as $k => $v) {
                            if (is_array($v) && (isset($v['risk_id']) || isset($v['id']) || isset($v['key']))) {
                                $keys[] = $v['risk_id'] ?? $v['id'] ?? $v['key'] ?? $k;
                            } else if (preg_match('/^RISK-\d+/', $k)) {
                                // Heuristic: If key looks like RISK-NNN, it's a feature
                                $keys[] = $k;
                            }
                        }
                    }
                }
            }
        }
        return array_unique(array_filter($keys));
    }

    /**
     * Flexible active-file matcher that tolerates risk IDs, slugs, titles and slugified aliases.
     */
    private static function feature_matches_active_keys($feat, $active_keys)
    {
        $normalize = static function ($value) {
            $value = strtolower(trim((string) $value));
            return preg_replace('/[^a-z0-9]+/', '', $value);
        };

        $candidates = array();
        foreach (array('feature_key', 'risk_id', 'id', 'slug', 'name', 'title') as $field) {
            if (!empty($feat[$field])) {
                $candidates[] = (string) $feat[$field];
            }
        }

        foreach (array('generated_schema', 'override_schema') as $schema_key) {
            if (!empty($feat[$schema_key])) {
                $decoded = json_decode((string) $feat[$schema_key], true);
                if (is_array($decoded)) {
                    foreach (array('feature_key', 'risk_id', 'id', 'slug', 'name', 'title') as $field) {
                        if (!empty($decoded[$field])) {
                            $candidates[] = (string) $decoded[$field];
                        }
                    }
                }
            }
        }

        $candidate_tokens = array();
        foreach ($candidates as $candidate) {
            $token = $normalize($candidate);
            if ($token !== '') {
                $candidate_tokens[] = $token;
            }
        }

        foreach ($active_keys as $active_key) {
            $active_token = $normalize($active_key);
            if ($active_token === '') {
                continue;
            }

            foreach ($candidate_tokens as $candidate_token) {
                if ($candidate_token === $active_token) {
                    return true;
                }

                if (strpos($candidate_token, $active_token) !== false || strpos($active_token, $candidate_token) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Robustly extract implementation code from a mapping.
     * Handles strings, arrays, and JSON-encoded platform objects.
     * 
     * [v1.4.0] Support for v1.2/v2.0 Schema-First Architecture Platform Objects.
     */
    public static function extract_code_from_mapping($directive, $platform = 'htaccess', $data = [])
    {
        if (empty($directive)) { return '';
        }

        $code = '';

        // If it's a JSON string, decode it first
        if (is_string($directive) && strpos(trim($directive), '{') === 0) {
            $decoded = json_decode($directive, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $directive = $decoded;
            }
        }

        if (is_array($directive)) {
            // 1. Check for specific platform keys
            $platform_keys = [
            $platform,
            '.' . ltrim($platform, '.'), // .htaccess
            str_replace('-', '_', $platform), // wp_config
            str_replace('_', '-', $platform), // wp-config
            ];

            foreach ($platform_keys as $pK) {
                if (isset($directive[$pK])) {
                    $inner = $directive[$pK];
                    $code = is_array($inner) ? ($inner['code'] ?? '') : $inner;
                    break;
                }
            }

            if (empty($code)) {
                // 1b. Robust Iteration (Handle leading/trailing whitespace in keys)
                foreach ($directive as $k => $v) {
                    $tk = trim((string)$k);
                    foreach ($platform_keys as $pK) {
                        if ($tk === $pK) {
                            $code = is_array($v) ? ($v['code'] ?? '') : $v;
                            break 2;
                        }
                    }
                }
            }

            // 2. Fallback to generic 'code' field
            if (empty($code) && isset($directive['code'])) {
                $code = $directive['code'];
            }

            // 3. Fallback to first non-array element (v3.12.5 legacy)
            if (empty($code)) {
                foreach ($directive as $v) {
                    if (is_string($v) && strlen($v) > 0) { 
                        $code = $v;
                        break;
                    }
                }
            }
        } else {
            $code = is_string($directive) ? $directive : '';
        }

        if (!empty($code) && !empty($data)) {
            $code = self::replace_placeholders($code, $data);
        }

        return $code;
    }

    /**
     * [v4.1.1] Replace placeholders like YOUR_SITE_KEY with values from implementation data.
     * Enhanced with logging and generic substitution.
     */
    public static function replace_placeholders($code, $data)
    {
        if (empty($code) || empty($data)) {
            return $code;
        }

        $placeholders = [
            'YOUR_SITE_KEY' => 'vapt_risk_009_site_key',
            '6LelVOQsAAAAAAucOFHcAsC0H8CWHRGa8e17whwY' => 'vapt_risk_009_site_key',
            'unique-key-here' => 'vapt_risk_061_key'
        ];

        $replaced = false;
        foreach ($placeholders as $placeholder => $meta_key) {
            if (strpos($code, $placeholder) !== false) {
                $value = isset($data[$meta_key]) ? (string)$data[$meta_key] : '';
                if ($value !== '') {
                    $code = str_replace($placeholder, $value, $code);
                    $replaced = true;
                    error_log("VAPT: Replaced placeholder '{$placeholder}' with value from '{$meta_key}'");
                } else {
                    error_log("VAPT Warning: Placeholder '{$placeholder}' found in code but '{$meta_key}' is missing or empty in data.");
                }
            }
        }

        // Generic substitution for any key in data (v4.1.1)
        foreach ($data as $key => $value) {
            if (is_scalar($value) && $value !== '' && strpos($code, (string)$key) !== false) {
                $code = str_replace((string)$key, (string)$value, $code);
                $replaced = true;
            }
        }

        if (!$replaced && (strpos($code, 'YOUR_SITE_KEY') !== false || strpos($code, 'unique-key-here') !== false)) {
             error_log("VAPT: No replacements made in code containing placeholders. Data keys: " . implode(', ', array_keys($data)));
        }

        return $code;
    }
}
