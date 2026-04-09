<?php

/**
 * Plugin Name: VAPTSecure Clean
 * Description: Ultimate VAPT and OWASP Security Plugin Builder.
 * Version: 3.0.2
 * Author: Tanveer H. Malik
 * Author URI: https://vapt.copilot.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: vaptsecure
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the Composer autoloader is included if it exists.
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    include_once dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Linter Stubs (Satisfies IDEs without WP symbols)
 */
if (false) {
    function home_url($path = '', $scheme = null)
    {
        return '';
    }
    function remove_submenu_page($menu_slug, $submenu_slug)
    {
    }
    function wp_add_inline_script($handle, $data, $position = 'after')
    {
    }
    function admin_url($path = '', $scheme = 'admin')
    {
        return '';
    }
    function rest_url($path = '', $scheme = 'rest')
    {
        return '';
    }
    function wp_create_nonce($action = -1)
    {
        return '';
    }
}

/**
 * Define Paths & Constants
 */
if (defined('VAPTSECURE_BUILD_VERSION')) {
    define('VAPTSECURE_VERSION', VAPTSECURE_BUILD_VERSION);
} else {
    define('VAPTSECURE_VERSION', '3.0.2');
}
if (! defined('VAPTSECURE_DATA_VERSION')) {
    define('VAPTSECURE_DATA_VERSION', '2.5.0');
}
if (! defined('VAPTSECURE_PATH')) {
    define('VAPTSECURE_PATH', plugin_dir_path(__FILE__));
}
if (! defined('VAPTSECURE_URL')) {
    define('VAPTSECURE_URL', plugin_dir_url(__FILE__));
}

if (! defined('VAPTSECURE_ACTIVE_DATA_FILE')) {
    define('VAPTSECURE_ACTIVE_DATA_FILE', get_option('vaptsecure_active_feature_file', 'interface_schema_v2.0.json'));
}

// v2.0 Schema Architecture Links
if (! defined('VAPTSECURE_PATTERN_LIBRARY')) {
    define('VAPTSECURE_PATTERN_LIBRARY', 'enforcer_pattern_library_v2.0.json');
}
if (! defined('VAPTSECURE_AI_INSTRUCTIONS')) {
    define('VAPTSECURE_AI_INSTRUCTIONS', 'ai_agent_instructions_v2.0.json');
}

// Backward Compatibility Aliases
if (! defined('VAPTC_VERSION')) {
    define('VAPTC_VERSION', VAPTSECURE_VERSION);
}
if (! defined('VAPTC_PATH')) {
    define('VAPTC_PATH', VAPTSECURE_PATH);
}
if (! defined('VAPTC_URL')) {
    define('VAPTC_URL', VAPTSECURE_URL);
}

/**
 * ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ Obfuscated Superadmin Identity
 * Returns decoded credentials for strict access control.
 *
 * User: tanmalik786 (Base64: dGFubWFsaWs3ODY=)
 * Email: tanmalik786@gmail.com (Base64: dGFubWFsaWs3ODZAZ21haWwuY29t)
 *
 * @return array Decoded identity credentials.
 */
function vaptsecure_get_superadmin_identity()
{
    return array(
    'user' => base64_decode('dGFubWFsaWs3ODY='),
    'email' => base64_decode('dGFubWFsaWs3ODZAZ21haWwuY29t')
    );
}

// Set Superadmin Constants
$vaptsecure_identity = vaptsecure_get_superadmin_identity();
if (! defined('VAPTSECURE_SUPERADMIN_USER')) {
    define('VAPTSECURE_SUPERADMIN_USER', $vaptsecure_identity['user']);
}
if (! defined('VAPTSECURE_SUPERADMIN_EMAIL')) {
    define('VAPTSECURE_SUPERADMIN_EMAIL', $vaptsecure_identity['email']);
}

/**
 * ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ Strict Superadmin Check
 * Verifies if current user matches the hidden identity.
 *
 * @return bool True if the current user is a superadmin.
 */
function is_vaptsecure_superadmin($require_auth = false)
{
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) { return false;
    }

    $identity = vaptsecure_get_superadmin_identity();
    $login = strtolower($current_user->user_login);
    $email = strtolower($current_user->user_email);

    // 1. ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Identity Check (Primary Firewall)
    // MUST match the hardcoded superadmin identity login or email.
    $is_super_identity = ($login === strtolower($identity['user']) || $email === strtolower($identity['email']));

    if (!$is_super_identity) {
        return false;
    }

    // 2. ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Authentication Check (Secondary Layer)
    // If require_auth is true, also check if the user has a valid OTP session.
    if ($require_auth && class_exists('VAPTSECURE_Auth')) {
        if (!VAPTSECURE_Auth::is_authenticated()) {
            return false;
        }
    }

    return true;
}

/**
 * Check if a feature is allowed to be used/enforced.
 * Supports Restricted Mode (defined via VAPTSECURE_RESTRICT_FEATURES).
 *
 * @param string $feature_key The feature ID/key to check.
 * @return bool True if allowed, false otherwise.
 */
function vaptsecure_is_feature_allowed($feature_key)
{
    // If not in restricted mode, all features are allowed
    if (!defined('VAPTSECURE_RESTRICT_FEATURES') || !VAPTSECURE_RESTRICT_FEATURES) {
        return true;
    }

    // Check if the specific feature constant is defined (set in generated config)
    $const_name = 'VAPTSECURE_FEATURE_' . strtoupper(str_replace('-', '_', $feature_key));
    return defined($const_name) && constant($const_name) === true;
}

// Include core classes (new Builder includes)
require_once VAPTSECURE_PATH . 'includes/debug-utils.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-auth.php';

// P4.1: Driver Interface Contract - Load interface before drivers
require_once VAPTSECURE_PATH . 'includes/interfaces/interface-vaptsecure-driver.php';

// P4.2: Schema Validation Pipeline - Load validator before REST
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-schema-validator.php';

require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-rest.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-db.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-workflow.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-ai-config.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-build.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-config-cleaner.php';  // Shared utility for config cleaning
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-enforcer.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-admin.php';
require_once VAPTSECURE_PATH . 'includes/class-vaptsecure-license-manager.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-check-item.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-self-check-result.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-audit-log.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-auto-correct.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-self-check.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-cron.php';
require_once VAPTSECURE_PATH . 'includes/self-check/class-vapt-lifecycle.php';
require_once VAPTSECURE_PATH . 'includes/admin/class-vapt-diagnostics-page.php';

/**
 * Initialize Global Services
 * Deferred to plugins_loaded to avoid DB access during activation.
 */
add_action('plugins_loaded', array('VAPTSECURE_Enforcer', 'init'));

/**
 * Instantiate service objects on plugins_loaded so their constructors can hook into WP.
 */
add_action('plugins_loaded', 'vaptsecure_initialize_services');

/**
 * Service initialization callback.
 */
function vaptsecure_initialize_services()
{
    if (class_exists('VAPTSECURE_REST')) {
        new VAPTSECURE_REST();
    }
    if (class_exists('VAPTSECURE_Auth')) {
        // Auth may provide static helpers but instantiate to register hooks if needed
        new VAPTSECURE_Auth();
    }
    if (class_exists('VAPTSECURE_Admin')) {
        new VAPTSECURE_Admin();
    }
}


/**
 * Activation Hook: Initialize Database Tables
 */
register_activation_hook(__FILE__, 'vaptsecure_activate_plugin');
register_deactivation_hook(__FILE__, ['VAPT_Lifecycle', 'on_deactivate']);
register_uninstall_hook(__FILE__, ['VAPT_Lifecycle', 'on_uninstall']);

add_action('vapt_feature_enabled',  function( $id ) {
    if(class_exists('VAPT_Self_Check')) VAPT_Self_Check::run('feature_enable',  ['feature_id' => $id]);
});
add_action('vapt_feature_disabled', function( $id ) {
    if(class_exists('VAPT_Self_Check')) VAPT_Self_Check::run('feature_disable', ['feature_id' => $id]);
});
add_action('vapt_license_expired',  function() {
    if(class_exists('VAPT_Self_Check')) VAPT_Self_Check::run('license_expire');
});

function vaptsecure_activate_plugin()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    include_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // Domains Table
    $table_domains = "CREATE TABLE {$wpdb->prefix}vaptsecure_domains (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        domain VARCHAR(255) NOT NULL,
        is_wildcard TINYINT(1) DEFAULT 0,
        license_id VARCHAR(100),
        license_type VARCHAR(50) DEFAULT 'standard',
        first_activated_at DATETIME DEFAULT NULL,
        manual_expiry_date DATETIME DEFAULT NULL,
        auto_renew TINYINT(1) DEFAULT 0,
        renewals_count INT DEFAULT 0,
        renewal_history TEXT DEFAULT NULL,
        is_enabled TINYINT(1) DEFAULT 1,
        license_scope VARCHAR(50) DEFAULT 'single',
        installation_limit INT DEFAULT 1,
        PRIMARY KEY  (id),
        UNIQUE KEY domain (domain)
    ) $charset_collate;";
    // Domain Features Table
    $table_features = "CREATE TABLE {$wpdb->prefix}vaptsecure_domain_features (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_id BIGINT(20) UNSIGNED NOT NULL,
        feature_key VARCHAR(100) NOT NULL,
        enabled TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id),
        KEY domain_id (domain_id)
    ) $charset_collate;";
    // Feature Status Table
    $table_status = "CREATE TABLE {$wpdb->prefix}vaptsecure_feature_status (
        feature_key VARCHAR(100) NOT NULL,
        status ENUM('Draft', 'Develop', 'Release') DEFAULT 'Draft',
        implemented_at DATETIME DEFAULT NULL,
        assigned_to BIGINT(20) UNSIGNED DEFAULT NULL,
        PRIMARY KEY  (feature_key)
    ) $charset_collate;";
    // Feature Meta Table
    $table_meta = "CREATE TABLE {$wpdb->prefix}vaptsecure_feature_meta (
        feature_key VARCHAR(100) NOT NULL,
        category VARCHAR(100),
        test_method TEXT,
        verification_steps TEXT,
        include_test_method TINYINT(1) DEFAULT 0,
        include_verification TINYINT(1) DEFAULT 0,
        include_verification_engine TINYINT(1) DEFAULT 0,
        include_verification_guidance TINYINT(1) DEFAULT 1,
        include_manual_protocol TINYINT(1) DEFAULT 1,
        include_operational_notes TINYINT(1) DEFAULT 1,
        wireframe_url TEXT DEFAULT NULL,
        generated_schema LONGTEXT DEFAULT NULL,
        implementation_data LONGTEXT DEFAULT NULL,
        dev_instruct LONGTEXT DEFAULT NULL,
        is_adaptive_deployment TINYINT(1) DEFAULT 0,
        override_schema LONGTEXT DEFAULT NULL,
        override_implementation_data LONGTEXT DEFAULT NULL,
        is_enabled TINYINT(1) DEFAULT 0,
        is_enforced TINYINT(1) DEFAULT 0,
        active_enforcer VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY  (feature_key)
    ) $charset_collate;";
    // Feature History/Audit Table
    $table_history = "CREATE TABLE {$wpdb->prefix}vaptsecure_feature_history (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        feature_key VARCHAR(100) NOT NULL,
        old_status VARCHAR(50),
        new_status VARCHAR(50),
        user_id BIGINT(20) UNSIGNED,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY feature_key (feature_key)
    ) $charset_collate;";
    // Build History Table
    $table_builds = "CREATE TABLE {$wpdb->prefix}vaptsecure_domain_builds (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        domain VARCHAR(255) NOT NULL,
        version VARCHAR(50) NOT NULL,
        features TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY domain (domain)
    ) $charset_collate;";
    // Security Events Table
    $table_security_events = "CREATE TABLE {$wpdb->prefix}vaptsecure_security_events (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        feature_key VARCHAR(100) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        request_uri TEXT,
        details LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY feature_key (feature_key),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($table_domains);
    dbDelta($table_features);
    dbDelta($table_status);
    dbDelta($table_meta);
    dbDelta($table_history);
    dbDelta($table_builds);
    dbDelta($table_security_events);
    // Ensure data directory exists
    if (! file_exists(VAPTSECURE_PATH . 'data')) {
        wp_mkdir_p(VAPTSECURE_PATH . 'data');
    }

    // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Send Activation Email to Superadmin (Only on fresh activation)
    $existing_version = get_option('vaptsecure_version');
    if (empty($existing_version)) {
        vaptsecure_send_activation_email();
    }

    // Run manual DB fix to add missing columns
    vaptsecure_manual_db_fix();

    // Run Self-Check lifecycle instantiation
    if(class_exists('VAPT_Lifecycle')) {
        VAPT_Lifecycle::on_activate();
    }
}

/**
 * Manual Database Fix / Migrations
 * Ensures new columns are added to existing tables.
 */
function vaptsecure_manual_db_fix()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'vaptsecure_feature_meta';

    // Check and add is_enabled if missing
    $column = $wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s", DB_NAME, $table_name, 'is_enabled'));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_enabled TINYINT(1) DEFAULT 0");
    }

    // Check and add is_enforced if missing
    $column = $wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s", DB_NAME, $table_name, 'is_enforced'));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_enforced TINYINT(1) DEFAULT 0");
    }

    // Check and add active_enforcer if missing
    $column = $wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s", DB_NAME, $table_name, 'active_enforcer'));
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN active_enforcer VARCHAR(100) DEFAULT NULL");
    }
}

/**
 * Send Activation Email
 * Notifies the superadmin when the plugin is activated on a new site.
 */
function vaptsecure_send_activation_email()
{
    $identity = vaptsecure_get_superadmin_identity();
    $to = $identity['email'];
    $site_name = get_bloginfo('name');
    $site_url = get_site_url();
    $admin_url = admin_url('admin.php?page=vaptsecure-domain-admin');

    $subject = sprintf("[VAPT Alert] Plugin Activated on %s", $site_name);
    $message = "VAPT Secure has been activated on a new site.\n\n";
    $message .= "Site Name: $site_name\n";
    $message .= "Site URL: $site_url\n";
    $message .= "Activation Date: " . current_time('mysql') . "\n";
    $message .= "Access Dashboard: $admin_url\n\n";
    $message .= "This is an automated security notification.";

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    wp_mail($to, $subject, $message, $headers);
}

/**
 * Manual DB Fix Trigger (Force Run)
 */
add_action('init', 'vaptsecure_manual_db_fix');

/**
 * Auto-update DB on version change
 */
add_action('init', 'vaptsecure_auto_update_db');

/**
 * Logic to run database updates if version mismatch.
 */
function vaptsecure_auto_update_db()
{
    $saved_version = get_option('vaptsecure_version');
    if ($saved_version !== VAPTSECURE_VERSION) {
        vaptsecure_activate_plugin();
        update_option('vaptsecure_version', VAPTSECURE_VERSION);
    }
}

/**
 * Manual database schema fix.
 * Can be triggered via ?vaptsecure_fix_db=1.
 */
if (! function_exists('vaptsecure_manual_db_fix')) {
    function vaptsecure_manual_db_fix()
    {
        if (isset($_GET['vaptsecure_fix_db']) && current_user_can('manage_options')) {
            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            global $wpdb;
            // 1. Run standard dbDelta
            vaptsecure_activate_plugin();
            // 2. Force add column just in case dbDelta missed it
            $table = $wpdb->prefix . 'vaptsecure_domains';
            $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'manual_expiry_date'));
            if (empty($col)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN manual_expiry_date DATETIME DEFAULT NULL");
            }
            // 3. Migrate Status ENUM to Title Case
            $status_table = $wpdb->prefix . 'vaptsecure_feature_status';
            $wpdb->query("ALTER TABLE {$status_table} MODIFY COLUMN status ENUM('Draft', 'Develop', 'Release') DEFAULT 'Draft'");
            // 4. Update existing lowercase statuses to Title Case
            $wpdb->query("UPDATE {$status_table} SET status = 'Draft' WHERE status IN ('draft', 'available')");
            $wpdb->query("UPDATE {$status_table} SET status = 'Develop' WHERE status IN ('develop', 'in_progress', 'test', 'Test')");
            $wpdb->query("UPDATE {$status_table} SET status = 'Release' WHERE status IN ('release', 'implemented')");
            // 5. Ensure wireframe_url column exists
            $meta_table = $wpdb->prefix . 'vaptsecure_feature_meta';
            $meta_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'wireframe_url'));
            if (empty($meta_col)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN wireframe_url TEXT DEFAULT NULL");
            }
            echo '<div class="notice notice-success"><p>Database migration complete. Statuses normalized to Draft, Develop, Release.</p></div>';
            // 4. Force drop is_enforced column (deprecated)
            $table_meta = $wpdb->prefix . 'vaptsecure_feature_meta';
            $col_enforced = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_meta} LIKE %s", 'is_enforced'));
            if (!empty($col_enforced)) {
                $wpdb->query("ALTER TABLE {$table_meta} DROP COLUMN is_enforced");
            }
            // 5. Force add assigned_to column
            $col_assigned = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$status_table} LIKE %s", 'assigned_to'));
            if (empty($col_assigned)) {
                $wpdb->query("ALTER TABLE {$status_table} ADD COLUMN assigned_to BIGINT(20) UNSIGNED DEFAULT NULL");
            }
            // 3. Force add generated_schema column
            $col_schema = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'generated_schema'));
            if (empty($col_schema)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN generated_schema LONGTEXT DEFAULT NULL");
            }
            $col_data = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'implementation_data'));
            if (empty($col_data)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN implementation_data LONGTEXT DEFAULT NULL");
            }
            $col_verif = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'include_verification_engine'));
            if (empty($col_verif)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN include_verification_engine TINYINT(1) DEFAULT 0");
            }
            $col_guidance = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'include_verification_guidance'));
            if (empty($col_guidance)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN include_verification_guidance TINYINT(1) DEFAULT 1");
            }
            $col_proto = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'include_manual_protocol'));
            if (empty($col_proto)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN include_manual_protocol TINYINT(1) DEFAULT 1");
            }
            $col_notes = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'include_operational_notes'));
            if (empty($col_notes)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN include_operational_notes TINYINT(1) DEFAULT 1");
            }
            if (empty($col_dev)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN dev_instruct LONGTEXT DEFAULT NULL");
            }
            $col_adaptive = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'is_adaptive_deployment'));
            if (empty($col_adaptive)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN is_adaptive_deployment TINYINT(1) DEFAULT 0");
            }
            $col_ov_schema = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'override_schema'));
            if (empty($col_ov_schema)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN override_schema LONGTEXT DEFAULT NULL");
            }
            $col_ov_impl = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$meta_table} LIKE %s", 'override_implementation_data'));
            if (empty($col_ov_impl)) {
                $wpdb->query("ALTER TABLE {$meta_table} ADD COLUMN override_implementation_data LONGTEXT DEFAULT NULL");
            }
            $col_enabled = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'is_enabled'));
            if (empty($col_enabled)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_enabled TINYINT(1) DEFAULT 1");
            }
            $col_id = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'id'));
            if (empty($col_id)) {
                $wpdb->query("ALTER TABLE {$table} DROP PRIMARY KEY");
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)");
            } else {
                $pk_check = $wpdb->get_row($wpdb->prepare("SHOW KEYS FROM {$table} WHERE Key_name = %s", 'PRIMARY'));
                if (!$pk_check || $pk_check->Column_name !== 'id') {
                    $wpdb->query("ALTER TABLE {$table} DROP PRIMARY KEY");
                    $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
                }
            }
            $col_scope = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'license_scope'));
            if (empty($col_scope)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN license_scope VARCHAR(50) DEFAULT 'single'");
            }
            $col_limit = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'installation_limit'));
            if (empty($col_limit)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN installation_limit INT DEFAULT 1");
            }

            // Force create Security Events table
            $table_security_events = "CREATE TABLE {$wpdb->prefix}vaptsecure_security_events (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feature_key VARCHAR(100) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            request_uri TEXT,
            details LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY feature_key (feature_key),
            KEY created_at (created_at)
        ) $charset_collate;";
            dbDelta($table_security_events);

            // 6. Clean up domain-feature relationships: remove features not in Release state
            $domain_features_table = $wpdb->prefix . 'vaptsecure_domain_features';
            $feature_status_table = $wpdb->prefix . 'vaptsecure_feature_status';
            
            // Get all features that are NOT in Release state
            $non_release_features = $wpdb->get_col(
                "SELECT feature_key FROM {$feature_status_table} WHERE status != 'Release'"
            );
            
            if (!empty($non_release_features)) {
                $placeholders = array_fill(0, count($non_release_features), '%s');
                $placeholders_string = implode(', ', $placeholders);
                
                // Delete domain-feature relationships for non-release features
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$domain_features_table} WHERE feature_key IN ({$placeholders_string})",
                        $non_release_features
                    )
                );
                
                echo '<div class="notice notice-success"><p>Cleaned up domain-feature relationships: removed ' . intval($deleted) . ' entries for features not in Release state.</p></div>';
            }

            $msg = "Database schema updated (History Table + Security Events + assigned_to + Status Enum + Manual Expiry + Generated Schema + Implementation Data + Domain Enabled + Robust ID column + License Scope + Inst. Limit + Removed is_enforced + Cleaned non-Release domain-feature relationships).";
            wp_die(sprintf("<h1>VAPT Secure Database Updated</h1><p>Schema refresh run. %s</p><p>Please go back to the dashboard.</p>", esc_html($msg)));
        }
    }
}

/**
 * Workbench Action Handler (Ajax-Alternative via GET)
 */
add_action(
    'init', function () {
        if (isset($_GET['vaptsecure_action']) && current_user_can('manage_options')) {
            $action = sanitize_text_field($_GET['vaptsecure_action']);
            if ($action === 'reset_rate_limits') {
                include_once VAPTSECURE_PATH . 'includes/enforcers/class-vaptsecure-hook-driver.php';
                VAPTSECURE_Hook_Driver::reset_limit();
                wp_die("Rate limits reset successfully.", "VAPT Secure Reset", array('response' => 200, 'back_link' => true));
            }
        }
    }
);

/**
 * Detect Localhost Environment
 * Verified against standard localhost IP and hostnames.
 *
 * @return bool True if on localhost.
 */
if (! function_exists('is_vaptsecure_localhost')) {
    function is_vaptsecure_localhost()
    {
        $whitelist = array('127.0.0.1', '::1', 'localhost');
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if (in_array($addr, $whitelist) || in_array($host, $whitelist)) {
            return true;
        }
        $dev_suffixes = array('.local', '.test', '.dev', '.wp', '.site');
        foreach ($dev_suffixes as $suffix) {
            if (strpos($host, $suffix) !== false) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Admin Menu Setup
 */
add_action('admin_menu', 'vaptsecure_add_admin_menu');

/**
 * Check Strict Permissions
 * Terminates execution if the current user is not a superadmin.
 */
if (! function_exists('vaptsecure_check_permissions')) {
    function vaptsecure_check_permissions($require_auth = false)
    {
        if (! is_vaptsecure_superadmin($require_auth)) {
            wp_die(__('You do not have permission to access the VAPT Secure Dashboard.', 'vaptsecure'));
        }
    }
}

/**
 * Registers the VAPT Secure and VAPT Domain Admin menu pages.
 */
if (! function_exists('vaptsecure_add_admin_menu')) {
    function vaptsecure_add_admin_menu()
    {
        $is_superadmin_identity = is_vaptsecure_superadmin(false);

        // 1. Parent Menu (Visible to all admins with manage_options)
        add_menu_page(
            __('VAPT Secure', 'vaptsecure'),
            __('VAPT Secure', 'vaptsecure'),
            'manage_options',
            'vaptsecure',
            'vaptsecure_render_client_status_page',
            'dashicons-shield',
            80
        );

        // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Superadmin Only Sub-menus
        if ($is_superadmin_identity) {
            // Sub-menu 1: Workbench
            add_submenu_page(
                'vaptsecure',
                __('VAPTSecure Workbench', 'vaptsecure'),
                __('VAPTSecure Workbench', 'vaptsecure'),
                'manage_options',
                'vaptsecure-workbench',
                'vaptsecure_render_workbench_page'
            );

            // Sub-menu 2: Domain Admin
            add_submenu_page(
                'vaptsecure',
                __('VAPTSecure Domain Admin', 'vaptsecure'),
                __('VAPTSecure Domain Admin', 'vaptsecure'),
                'manage_options',
                'vaptsecure-domain-admin',
                'vaptsecure_render_admin_page'
            );
        }

        // Remove the default duplicate submenu item created by WordPress
        remove_submenu_page('vaptsecure', 'vaptsecure');
    }
}

/**
 * Handle Legacy Slug Redirects
 */
add_action('admin_init', 'vaptsecure_handle_legacy_redirects');
if (! function_exists('vaptsecure_handle_legacy_redirects')) {
    function vaptsecure_handle_legacy_redirects()
    {
        if (!isset($_GET['page'])) { return;
        }
        $legacy_slugs = array('vapt-secure', 'vapt-domain-admin', 'vapt-copilot', 'vapt-copilot-main', 'vapt-copilot-status', 'vapt-copilot-domain-build', 'vapt-client');
        if (in_array($_GET['page'], $legacy_slugs)) {
            $target = ($_GET['page'] === 'vapt-domain-admin') ? 'vaptsecure-domain-admin' : 'vaptsecure';
            wp_safe_redirect(admin_url('admin.php?page=' . $target));
            exit;
        }
    }
}

/**
 * Localhost Admin Notice
 */


/**
 * Render Client Status Page
 */
if (! function_exists('vaptsecure_render_client_status_page')) {
    function vaptsecure_render_client_status_page()
    {
        ?>
    <div class="wrap">
      <h1 class="wp-heading-inline"><?php _e('VAPT Secure', 'vaptsecure'); ?></h1>
      <hr class="wp-header-end" />
      <div id="vapt-client-root">
        <div style="padding: 40px; text-align: center; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
          <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
          <p><?php _e('Loading Implementation Workbench...', 'vaptsecure'); ?></p>
        </div>
      </div>
    </div>
        <?php
    }
}

/**
 * Render Superadmin Workbench Page
 */
if (! function_exists('vaptsecure_render_workbench_page')) {
    function vaptsecure_render_workbench_page()
    {
        if (! is_vaptsecure_superadmin(true)) {
            if (is_vaptsecure_superadmin(false)) {
                $identity = vaptsecure_get_superadmin_identity();
                if (! get_transient('vaptsecure_otp_email_' . $identity['user'])) {
                    VAPTSECURE_Auth::send_otp();
                }
                VAPTSECURE_Auth::render_otp_form();
            } else {
                wp_die(__('You do not have permission to access the VAPT Secure Dashboard.', 'vaptsecure'));
            }
            return;
        }
        ?>
    <div class="wrap">
      <h1 class="wp-heading-inline"><?php _e('VAPT Secure Workbench', 'vaptsecure'); ?></h1>
      <hr class="wp-header-end" />
      <div id="vapt-workbench-root">
        <div style="padding: 40px; text-align: center; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
          <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
          <p><?php _e('Loading Superadmin Workbench...', 'vaptsecure'); ?></p>
        </div>
      </div>
    </div>
        <?php
    }
}

if (! function_exists('vaptsecure_render_admin_page')) {
    function vaptsecure_render_admin_page()
    {
        vaptsecure_master_dashboard_page();
    }
}

if (! function_exists('vaptsecure_master_dashboard_page')) {
    function vaptsecure_master_dashboard_page()
    {
        // Verify Strict Identity AND Session
        if (! is_vaptsecure_superadmin(true)) {
            // If they match identity but lack auth, show OTP.
            // If they don't match identity, they already failed is_vaptsecure_superadmin() and was blocked by parent layer?
            // Actually vaptsecure_add_admin_menu blocks them from seeing it.
            // But if they access via direct URL, this check will trigger.
      
            if (is_vaptsecure_superadmin(false)) {
                // Identity matches, but needs auth.
                $identity = vaptsecure_get_superadmin_identity();
                if (! get_transient('vaptsecure_otp_email_' . $identity['user'])) {
                    VAPTSECURE_Auth::send_otp();
                }
                VAPTSECURE_Auth::render_otp_form();
            } else {
                // Identity DOES NOT match. Hard block.
                wp_die(__('You do not have permission to access the VAPT Secure Dashboard.', 'vaptsecure'));
            }
            return;
        }
        ?>
    <div id="vapt-admin-root" class="wrap">
      <h1><?php _e('VAPTSecure Domain Admin', 'vaptsecure'); ?></h1>
      <div style="padding: 20px; text-align: center;">
        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
        <p><?php _e('Loading VAPT Secure...', 'vaptsecure'); ?></p>
      </div>
    </div>
        <?php
    }
}

/**
 * Enqueue Admin Assets
 */
add_action('admin_enqueue_scripts', 'vaptsecure_enqueue_admin_assets');

/**
 * Enqueue Assets for React App
 */
function vaptsecure_enqueue_admin_assets($hook)
{
    global $vaptsecure_hooks;
    $GLOBALS['vaptsecure_current_hook'] = $hook;
    $screen = get_current_screen();
    $current_user = wp_get_current_user();
    $is_superadmin = is_vaptsecure_superadmin();
    if (!$screen) { return;
    }
    // Enqueue Shared Styles
    wp_enqueue_style('vapt-admin-css', VAPTSECURE_URL . 'assets/css/admin.css', array('wp-components'), VAPTSECURE_VERSION);
    // 1. Superadmin Dashboard (admin.js)
    if ($screen->id === 'toplevel_page_vaptsecure-domain-admin' || $screen->id === 'vaptsecure_page_vaptsecure-domain-admin' || strpos($screen->id, 'vaptsecure-domain-admin') !== false) {
        if (!VAPTSECURE_Auth::is_authenticated()) {
            return; // Do not enqueue heavy React apps if OTP is pending
        }

        error_log('VAPT Admin Assets Enqueued for: ' . $screen->id);
        wp_enqueue_script(
            'vapt-admin-logger',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/logger.js',
            array(),
            VAPTSECURE_VERSION,
            true
        );
        wp_enqueue_script(
            'vapt-admin-api-fetch-hotpatch',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/api-fetch-hotpatch.js',
            array('wp-api-fetch', 'vapt-admin-logger'),
            VAPTSECURE_VERSION,
            true
        );
        wp_enqueue_script(
            'vapt-admin-modals',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/modals.js',
            array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'),
            VAPTSECURE_VERSION,
            true
        );
        wp_enqueue_script(
            'vapt-admin-field-mapping',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/field-mapping.js',
            array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'),
            VAPTSECURE_VERSION,
            true
        );
        wp_enqueue_script(
            'vapt-admin-design-modal',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/design-modal.js',
            array('wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'vapt-admin-logger', 'vapt-admin-api-fetch-hotpatch', 'vapt-admin-modals', 'vapt-admin-field-mapping'),
            VAPTSECURE_VERSION,
            true
        );
        wp_enqueue_script(
            'vapt-admin-domains',
            plugin_dir_url(__FILE__) . 'assets/js/admin-modules/domains.js',
            array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'),
            VAPTSECURE_VERSION,
            true
        );
        // Enqueue Auto-Interface Generator (Module)
        wp_enqueue_script(
            'vapt-interface-generator',
            plugin_dir_url(__FILE__) . 'assets/js/modules/interface-generator.js',
            array(), // No deps, but strictly before admin.js
            VAPTSECURE_VERSION,
            true
        );
        // Enqueue A+ Adaptive Generator (Module)
        wp_enqueue_script(
            'vapt-aplus-generator',
            plugin_dir_url(__FILE__) . 'assets/js/modules/aplus-generator.js',
            array(),
            VAPTSECURE_VERSION,
            true
        );
        // Enqueue Generated Interface UI Component
        wp_enqueue_script(
            'vapt-generated-interface-ui',
            plugin_dir_url(__FILE__) . 'assets/js/modules/generated-interface.js',
            array('wp-element', 'wp-components', 'wp-i18n'),
            VAPTSECURE_VERSION,
            true
        );
        // Enqueue Admin Dashboard Script
        wp_enqueue_script(
            'vapt-admin-js',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'vapt-admin-logger', 'vapt-admin-api-fetch-hotpatch', 'vapt-admin-modals', 'vapt-admin-field-mapping', 'vapt-admin-design-modal', 'vapt-admin-domains', 'vapt-interface-generator', 'vapt-aplus-generator', 'vapt-generated-interface-ui'),
            VAPTSECURE_VERSION,
            true
        );
    }


    // Common Settings Localization
    $localized_settings = array(
            'root'          => esc_url_raw(rest_url()),
            'nonce'         => wp_create_nonce('wp_rest'),
            'domainLocked'  => defined('VAPTSECURE_DOMAIN_LOCKED') ? VAPTSECURE_DOMAIN_LOCKED : false,
            'buildVersion'  => VAPTSECURE_VERSION,
            'activeData'    => VAPTSECURE_ACTIVE_DATA_FILE
        );
    $vapt_settings = array(
        'isSuper' => $is_superadmin,
        'pluginVersion' => VAPTSECURE_VERSION,
        'pluginName' => 'VAPT Secure',
        'currentDomain' => parse_url(home_url(), PHP_URL_HOST),
        'abspath' => ABSPATH,
        'pluginPath' => VAPTSECURE_PATH,
        'uploadPath' => wp_upload_dir()['basedir'],
    'uploadPath' => wp_upload_dir()['basedir'],
    );

    // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â GLOBAL REST HOTPATCH (v3.8.17) - Inline for maximum priority
    $home_url = esc_url_raw(home_url());
    $inline_patch = "
    (function() {
      if (typeof wp === 'undefined' || !wp.apiFetch) return;
      if (wp.apiFetch.__vaptsecure_patched) return;
      
      try {
        // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â RECOVERY: If the browser was permanently stuck in silent mode, free it (v2.2.9 Fix)
        localStorage.removeItem('vaptsecure_rest_broken');
      } catch (e) { }

      const originalApiFetch = wp.apiFetch;

      const patchedApiFetch = (args) => {
        // Get nonce from WordPress standard wpApiSettings or custom vaptSecureSettings
        const wpNonce = (window.wpApiSettings && window.wpApiSettings.nonce) || '';
        const vaptNonce = (window.vaptSecureSettings && window.vaptSecureSettings.nonce) || '';
        const effectiveNonce = wpNonce || vaptNonce;
        
        const home = '{$home_url}';
        
        // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â AUTH PERI-FIX: Ensure Nonce is present for non-GET requests
        const method = (args.method || 'GET').toUpperCase();
        if (effectiveNonce && method !== 'GET') {
          if (!args.headers) args.headers = {};
          // Handle both plain objects and Headers objects
          if (typeof args.headers.set === 'function') {
            if (!args.headers.has('X-WP-Nonce')) args.headers.set('X-WP-Nonce', effectiveNonce);
          } else {
            args.headers['X-WP-Nonce'] = effectiveNonce;
          }
        }
        
        const getFallbackUrl = (pathOrUrl) => {
          if (!pathOrUrl) return null;
          const path = typeof pathOrUrl === 'string' && pathOrUrl.includes('/wp-json/')
            ? pathOrUrl.split('/wp-json/')[1]
            : pathOrUrl;
          const cleanHome = home.replace(/\/$/, '');
          const cleanPath = path.replace(/^\//, '').split('?')[0];
          const queryParams = path.includes('?') ? '&' + path.split('?')[1] : '';
          const nonceParam = effectiveNonce ? '&_wpnonce=' + effectiveNonce : '';
          return cleanHome + '/?rest_route=/' + cleanPath + queryParams + nonceParam;
        };

        // ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂºÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â REMOVED THE INSTANT FALLBACK LOGIC to prevent permanently spamming 403s
        // on all endpoints when only one endpoint was broken.
        // Fallbacks will only occur per-request dynamically.

        return originalApiFetch(args).catch(err => {
          const status = err.status || (err.data && err.data.status);
          const isFallbackTrigger = status === 404 || status === 403 || err.code === 'rest_no_route' || err.code === 'invalid_json';

          if (isFallbackTrigger && (args.path || args.url) && home) {
            const fallbackUrl = getFallbackUrl(args.path || args.url);
            if (!fallbackUrl) throw err;

            // Notice: We are trying fallback dynamically, but NOT saving it to localStorage
            console.warn('VAPT Secure: Original API request failed, attempting fallback (?rest_route=...).');
            
            const fallbackArgs = Object.assign({}, args, { url: fallbackUrl });
            delete fallbackArgs.path;
            
            // Note: If the fallback also fails, the promise will reject normally back to the caller
            return originalApiFetch(fallbackArgs);
          }
          throw err;
        });
      };

      Object.keys(originalApiFetch).forEach(key => { patchedApiFetch[key] = originalApiFetch[key]; });
      patchedApiFetch.__vaptsecure_patched = true;
      wp.apiFetch = patchedApiFetch;
      console.log('VAPT Secure: Persistent Global REST Hotpatch Active (v3.8.17)');
    })();
  ";
    wp_add_inline_script('wp-api-fetch', $inline_patch);

    if ($screen->id === 'toplevel_page_vaptsecure-domain-admin' || $screen->id === 'vaptsecure_page_vaptsecure-domain-admin' || strpos($screen->id, 'vaptsecure-domain-admin') !== false) {
        wp_localize_script('vapt-admin-js', 'vaptSecureSettings', $vapt_settings);
    }
    // 2. Shared: Generated Interface UI Component
    if ($screen->id === 'toplevel_page_vaptsecure' || strpos($screen->id, 'vaptsecure-workbench') !== false) {
        wp_enqueue_script(
            'vapt-generated-interface-ui',
            plugin_dir_url(__FILE__) . 'assets/js/modules/generated-interface.js',
            array('wp-element', 'wp-components', 'wp-i18n'),
            VAPTSECURE_VERSION,
            true
        );
    }

    // 2a. Client Dashboard (client.js) - WordPress Admin view - "VAPT Secure" page (Release features only)
    if ($screen->id === 'toplevel_page_vaptsecure') {
        wp_enqueue_script(
            'vapt-client-js',
            plugin_dir_url(__FILE__) . 'assets/js/client.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'vapt-generated-interface-ui'),
            VAPTSECURE_VERSION,
            true
        );
        wp_localize_script('vapt-client-js', 'vaptSecureSettings', $vapt_settings);
    }

    // 2b. Superadmin Workbench (workbench.js) - "VAPT Secure Workbench" page (All features, unscoped)
    if (strpos($screen->id, 'vaptsecure-workbench') !== false) {
        wp_enqueue_script(
            'vapt-workbench-js',
            plugin_dir_url(__FILE__) . 'assets/js/workbench.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'vapt-generated-interface-ui'),
            VAPTSECURE_VERSION,
            true
        );
        wp_localize_script('vapt-workbench-js', 'vaptSecureSettings', $vapt_settings);
    }
}
?>
