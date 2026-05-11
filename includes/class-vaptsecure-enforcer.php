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
     * Always triggers a rebuild so toggling OFF also removes rules from config files.
     */
    public static function dispatch_enforcement($key, $data)
    {
        // Clear runtime cache so changes apply instantly
        delete_transient('vaptsecure_active_enforcements');

        $toggle_off = false;
        foreach (array('is_enabled', 'is_enforced', 'enabled', 'feat_enabled', 'prot_enabled') as $toggle_key) {
            if (!array_key_exists($toggle_key, $data)) {
                continue;
            }
            $toggle_value = filter_var($data[$toggle_key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($toggle_value === false) {
                $toggle_off = true;
                break;
            }
        }

        if ($toggle_off) {
            error_log("VAPT ENFORCER: Toggle OFF detected for {$key}; rebuilding active files to remove plugin-owned changes.");
            self::rebuild_all();
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

        // [FIX v1.4.0] Always rebuild even if this feature has no enforcement block.
        // This ensures that toggling OFF removes previously written rules from config files.
        if (empty($schema['enforcement'])) {
            error_log("VAPT ENFORCER: No enforcement block, rebuilding all config files for {$key}");
            $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';
            if (strpos($server, 'nginx') !== false) {
                self::rebuild_nginx();
            } else {
                self::rebuild_htaccess();
            }
            self::rebuild_config();
            self::rebuild_php_functions();
            return;
        }

        // [v4.0.0] Adaptive Deployment Orchestration
        // [FIX v4.0.x] Use !== '0' instead of !empty() to handle string/int comparison properly
        $is_adaptive = $meta['is_adaptive_deployment'] ?? null;
        if ($is_adaptive !== null && $is_adaptive !== '0' && $is_adaptive !== 0 && $is_adaptive !== false) {
            include_once VAPTSECURE_PATH . 'includes/class-vaptsecure-deployment-orchestrator.php';
            $orchestrator = new VAPTSECURE_Deployment_Orchestrator();

            // Resolve implementation data for toggle intelligence
            $impl_data = self::resolve_impl($meta);

            // Use profile from settings if available, else default to auto_detect
            $profile = get_option('vaptsecure_deployment_profile', 'auto_detect');
            $results = $orchestrator->orchestrate($key, $schema, $profile, $impl_data);

            error_log("VAPT: Adaptive Deployment for {$key} results: " . json_encode($results));

            // Keep the post-orchestration rebuild scoped to the selected driver.
            $driver_name = $schema['enforcement']['driver'] ?? 'htaccess';
            switch ($driver_name) {
                case 'nginx':
                    self::rebuild_nginx();
                    break;
                case 'config':
                case 'wp_config':
                case 'wp-config':
                    self::rebuild_config();
                    break;
                case 'hook':
                case 'php_functions':
                    self::rebuild_php_functions();
                    break;
                case 'htaccess':
                case 'universal':
                default:
                    self::rebuild_htaccess();
                    break;
            }
            return;
        }

        $driver_name = $schema['enforcement']['driver'];

        // [FIX v4.0.x] Enhanced driver dispatch with comprehensive file coverage
        // Dispatch to the correct driver based on enforcement type
        switch ($driver_name) {
            case 'htaccess':
                // UNIVERSAL FIX: Rebuild based on Server Type
                $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';

                if (strpos($server, 'nginx') !== false) {
                    self::rebuild_nginx();
                } else {
                    // Default to Apache/.htaccess
                    self::rebuild_htaccess();
                }
                self::rebuild_config();
                break;
                
            case 'nginx':
                self::rebuild_nginx();
                self::rebuild_htaccess(); // Also write PHP fallback
                break;
                
            case 'cloudflare':
                self::rebuild_cloudflare();
                break;
                
            case 'config':
            case 'wp_config':
            case 'wp-config':
                self::rebuild_config();
                self::rebuild_htaccess(); // Also write header fallbacks
                break;
                
            case 'hook':
            case 'php_functions':
            case 'universal':
            default:
                // [FIX v4.0.x] Hook/PHP functions should also trigger htaccess/wp-config
                // for header-based protection as fallback
                error_log("VAPT ENFORCER: Driver is {$driver_name}, triggering rebuild_php_functions, rebuild_htaccess, rebuild_config");
                self::rebuild_php_functions();
                self::rebuild_htaccess();
                self::rebuild_config();
                break;
        }
        
        error_log("VAPT ENFORCER: Dispatch complete for {$key}");
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
    private static function resolve_pattern_code_ref($ref, $platform)
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
     * Rebuilds .htaccess files by aggregating rules from ALL enabled features.
     */
    private static function rebuild_htaccess()
    {
        error_log('VAPT: rebuild_htaccess called');
        
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-htaccess-driver.php';
        if (!class_exists('VAPTSECURE_Htaccess_Driver')) { 
            error_log('VAPT: Htaccess Driver not found, skipping rebuild');
            return;
        }

        $enforced_features = self::get_enforced_features();

        // [ENHANCEMENT] Filter by Active Data Files (v3.12.0)
        // [FIX v1.4.0] Only apply key filter when we actually have active keys - prevents
        // silently dropping all features when the active file resolves to an empty key list.
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    // [FIX] Always allow XML-RPC regardless of key mismatch (v3.12.13)
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        // Group rules by target
        $targets_rules = array(
        'root' => array(),
        'uploads' => array()
        );

        foreach ($enforced_features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            $driver = isset($schema['enforcement']['driver']) ? $schema['enforcement']['driver'] : '';
            $target = isset($schema['enforcement']['target']) ? $schema['enforcement']['target'] : 'root';

            // 🛡️ Map common alias ".htaccess" to standard "root" target (v3.13.15)
            if ($target === '.htaccess') {
                $target = 'root';
            }

            if ($driver === 'htaccess' || $driver === 'universal') {
                $feature_rules = VAPTSECURE_Htaccess_Driver::generate_rules($impl_data, $schema);
                if (!empty($feature_rules)) {
                    if (!isset($targets_rules[$target])) {
                        $targets_rules[$target] = array();
                    }
                    if (isset($targets_rules[$target]) && is_array($feature_rules)) {
                        $targets_rules[$target] = array_merge($targets_rules[$target], $feature_rules);
                    }
                }
            }
        }

        // Write batch for each target
        foreach ($targets_rules as $target => $rules) {
            VAPTSECURE_Htaccess_Driver::write_batch($rules, $target);
        }
    }

    /**
     * Rebuilds all wp-config.php rules across active features
     */
    public static function rebuild_config()
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-config-driver.php';
        if (!class_exists('VAPTSECURE_Config_Driver')) { return;
        }

        $enforced_features = self::get_enforced_features();

        // [ENHANCEMENT] Filter by Active Data Files (v3.12.0)
        // [FIX v1.4.0] Only apply key filter when we actually have active keys.
        $active_keys = self::get_active_file_keys();
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    // [FIX] Always allow XML-RPC regardless of key mismatch (v3.12.13)
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        $all_rules = array();

        if (!empty($enforced_features)) {
            foreach ($enforced_features as $meta) {
                $schema = self::resolve_schema($meta);
                $impl_data = self::resolve_impl($meta);
                $driver = $schema['enforcement']['driver'] ?? '';

                if ($driver === 'config' || $driver === 'wp-config' || $driver === 'wp_config' || $driver === 'universal') {
                    $feature_rules = VAPTSECURE_Config_Driver::generate_rules($impl_data, $schema);
                    if (!empty($feature_rules)) {
                        $all_rules[] = "// Rule for: " . ($meta['feature_key']);
                        if (is_array($feature_rules)) {
                            $all_rules = array_merge($all_rules, $feature_rules);
                        }
                    }
                }
            }
        }

        $write_res = VAPTSECURE_Config_Driver::write_batch($all_rules);
        if ($write_res) {
            error_log("VAPT: Rebuilt wp-config.php with " . count($all_rules) . " rules.");
        } else {
            error_log("VAPT: Failed to rebuild wp-config.php. Check permissions.");
        }
        return $write_res;
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
     * Build a file-target audit for a feature.
     * Reports the status of the feature's primary enforcement target only.
     */
    public static function audit_feature_cleanup($feature_key)
    {
        $feature_key = trim((string) $feature_key);
        if ($feature_key === '') {
            return array();
        }

        $meta = VAPTSECURE_DB::get_feature_meta($feature_key);
        if (!$meta) {
            return array();
        }

        $schema = self::resolve_schema($meta);
        $driver = strtolower((string) ($schema['enforcement']['driver'] ?? ''));
        $target = strtolower((string) ($schema['enforcement']['target'] ?? 'root'));

        $audit = array();

        $resolve_wp_config_path = function () {
            $paths = array();
            if (defined('ABSPATH')) {
                $base = rtrim(ABSPATH, DIRECTORY_SEPARATOR);
                $paths[] = $base . DIRECTORY_SEPARATOR . 'wp-config.php';
                $paths[] = dirname($base) . DIRECTORY_SEPARATOR . 'wp-config.php';
                if (function_exists('get_home_path')) {
                    $home = rtrim(get_home_path(), DIRECTORY_SEPARATOR);
                    if (!empty($home)) {
                        $paths[] = $home . DIRECTORY_SEPARATOR . 'wp-config.php';
                        $paths[] = dirname($home) . DIRECTORY_SEPARATOR . 'wp-config.php';
                    }
                }
            }

            foreach (array_unique($paths) as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            return isset($paths[0]) ? $paths[0] : (ABSPATH . 'wp-config.php');
        };

        $path = '';
        $label = '';

        if ($driver === 'htaccess' || $driver === 'universal' || $driver === '') {
            $path = ($target === 'uploads') ? (wp_upload_dir()['basedir'] . '/.htaccess') : ((function_exists('get_home_path') ? get_home_path() : ABSPATH) . '.htaccess');
            $label = ($target === 'uploads') ? 'uploads/.htaccess' : './.htaccess';
        } elseif (in_array($driver, array('config', 'wp-config', 'wp_config'), true)) {
            $path = $resolve_wp_config_path();
            $label = './wp-config.php';
        } elseif (in_array($driver, array('hook', 'php_functions'), true)) {
            $path = VAPTSECURE_PATH . 'vapt-functions.php';
            $label = 'vapt-functions.php';
        } elseif ($driver === 'nginx') {
            $path = wp_upload_dir()['basedir'] . '/vapt-nginx-rules.conf';
            $label = 'vapt-nginx-rules.conf';
        } else {
            $path = ($target === 'uploads') ? (wp_upload_dir()['basedir'] . '/.htaccess') : ((function_exists('get_home_path') ? get_home_path() : ABSPATH) . '.htaccess');
            $label = ($target === 'uploads') ? 'uploads/.htaccess' : './.htaccess';
        }

        $exists = file_exists($path);
        $content = $exists ? @file_get_contents($path) : '';
        $has_marker = false;

        if ($content !== false && $content !== '') {
            if ($driver === 'htaccess' || $driver === 'universal' || $driver === '') {
                $has_marker =
                    (
                        strpos($content, '# BEGIN VAPT SECURITY RULES') !== false ||
                        strpos($content, '# ' . $feature_key) !== false ||
                        strpos($content, '# BEGIN VAPT ' . $feature_key) !== false
                    ) &&
                    (strpos($content, $feature_key) !== false);
            } elseif (in_array($driver, array('config', 'wp-config', 'wp_config'), true)) {
                $has_marker =
                    (strpos($content, 'BEGIN VAPT CONFIG RULES') !== false) ||
                    (strpos($content, 'BEGIN VAPT SECURITY RULES') !== false) ||
                    (strpos($content, $feature_key) !== false);
            } elseif (in_array($driver, array('hook', 'php_functions'), true)) {
                $has_marker =
                    (strpos($content, '// BEGIN VAPT ' . $feature_key) !== false) ||
                    (strpos($content, '# BEGIN VAPT ' . $feature_key) !== false) ||
                    (strpos($content, $feature_key) !== false);
            } elseif ($driver === 'nginx') {
                $has_marker = (strpos($content, 'X-VAPT-Feature "' . $feature_key . '"') !== false);
            }
        }

        $audit[] = array(
            'target' => $driver ?: 'htaccess',
            'label' => $label,
            'path' => $path,
            'exists' => $exists,
            'status' => $has_marker ? 'present' : 'removed',
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
     * [v4.0.1] Rebuilds vapt-functions.php via VAPTSECURE_PHP_Driver
     */
    public static function rebuild_php_functions()
    {
        include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-php-driver.php';
        if (!class_exists('VAPTSECURE_PHP_Driver')) { return;
        }

        $enforced_features = self::get_enforced_features();
        $active_keys = self::get_active_file_keys();
    
        if (!empty($active_keys)) {
            $enforced_features = array_filter(
                $enforced_features, function ($feat) use ($active_keys) {
                    // [FIX] Always allow XML-RPC regardless of key mismatch (v3.12.13)
                    if (strpos($feat['feature_key'], 'xml-rpc') !== false || strpos($feat['feature_key'], 'xmlrpc') !== false || $feat['feature_key'] === 'RISK-016-001') {
                        return true;
                    }
                    return self::feature_matches_active_keys($feat, $active_keys);
                }
            );
        }

        $all_rules = array();
        foreach ($enforced_features as $meta) {
            $schema = self::resolve_schema($meta);
            $impl_data = self::resolve_impl($meta);
            $driver = $schema['enforcement']['driver'] ?? '';

            if ($driver === 'php_functions' || $driver === 'hook' || $driver === 'universal') {
                $feature_rules = VAPTSECURE_PHP_Driver::generate_rules($impl_data, $schema);
                if (!empty($feature_rules) && is_array($feature_rules)) {
                    $all_rules = array_merge($all_rules, $feature_rules);
                }
            }
        }

        return VAPTSECURE_PHP_Driver::write_batch($all_rules);
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
