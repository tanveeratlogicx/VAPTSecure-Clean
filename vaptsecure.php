<?php

/**
 * Plugin Name: VAPTSecure Clean
 * Description: Ultimate VAPT and OWASP Security Plugin Builder.
 * Version: 3.7.18
 * Author: Tanveer Hayat Malik
 * Author URI: https://vapt.copilot.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: vaptsecure
 * Domain Path: /languages
 */

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

// Ensure the Composer autoloader is included if it exists.
if (file_exists(dirname(__FILE__) . "/vendor/autoload.php")) {
    include_once dirname(__FILE__) . "/vendor/autoload.php";
}

/**
 * Define Paths & Constants
 */
if (defined("VAPTSECURE_BUILD_VERSION")) {
    define("VAPTSECURE_VERSION", VAPTSECURE_BUILD_VERSION);
} else {
    define("VAPTSECURE_VERSION", "3.7.18"); // v3.7.18 - Fixed suffix-agnostic marker removal in .htaccess
}
if (!defined("VAPTSECURE_DATA_VERSION")) {
    define("VAPTSECURE_DATA_VERSION", "2.5.0");
}
if (!defined("VAPTSECURE_PATH")) {
    define("VAPTSECURE_PATH", plugin_dir_path(__FILE__));
}
if (!defined("VAPTSECURE_URL")) {
    define("VAPTSECURE_URL", plugin_dir_url(__FILE__));
}

if (!defined("VAPTSECURE_ACTIVE_DATA_FILE")) {
    define(
        "VAPTSECURE_ACTIVE_DATA_FILE",
        get_option(
            "vaptsecure_active_feature_file",
            "interface_schema_v2.0.json",
        ),
    );
}

// v2.0 Schema Architecture Links
if (!defined("VAPTSECURE_PATTERN_LIBRARY")) {
    define("VAPTSECURE_PATTERN_LIBRARY", "enforcer_pattern_library_v2.0.json");
}
if (!defined("VAPTSECURE_AI_INSTRUCTIONS")) {
    define("VAPTSECURE_AI_INSTRUCTIONS", "ai_agent_instructions_v2.0.json");
}

// Backward Compatibility Aliases
if (!defined("VAPTC_VERSION")) {
    define("VAPTC_VERSION", VAPTSECURE_VERSION);
}
if (!defined("VAPTC_PATH")) {
    define("VAPTC_PATH", VAPTSECURE_PATH);
}
if (!defined("VAPTC_URL")) {
    define("VAPTC_URL", VAPTSECURE_URL);
}

function vaptsecure_get_superadmin_identity()
{
    return [
        "user_hash" =>
            "284c2d5aae9b0e54965ef0ad7fe37fd4b6b31191a270b64cd570e24638d4a22e",
        "email_hash" =>
            "9c8d49887d7dc82af17c7fd9af177142d96da847c9206a44be005face0e04d05",
    ];
}

/**
 * Verifies if current user matches the hidden identity.
 *
 * @return bool True if the current user is a superadmin.
 */
function is_vaptsecure_superadmin($require_auth = false)
{
    if (!function_exists("wp_get_current_user")) {
        return false;
    }

    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false;
    }

    $identity = vaptsecure_get_superadmin_identity();
    $login = strtolower((string) $current_user->user_login);
    $email = strtolower((string) $current_user->user_email);

    $login_hash = hash("sha256", $login);
    $email_hash = hash("sha256", $email);
    $user_ok =
        isset($identity["user_hash"]) &&
        is_string($identity["user_hash"]) &&
        (function_exists("hash_equals")
            ? hash_equals($identity["user_hash"], $login_hash)
            : $identity["user_hash"] === $login_hash);
    $email_ok =
        isset($identity["email_hash"]) &&
        is_string($identity["email_hash"]) &&
        (function_exists("hash_equals")
            ? hash_equals($identity["email_hash"], $email_hash)
            : $identity["email_hash"] === $email_hash);
    $is_super_identity = $user_ok || $email_ok;

    if (!$is_super_identity) {
        return false;
    }

    if ($require_auth && class_exists("VAPTSECURE_Auth")) {
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
    if (
        function_exists("vaptsecure_is_builder_context") &&
        vaptsecure_is_builder_context() &&
        function_exists("is_vaptsecure_superadmin") &&
        is_vaptsecure_superadmin(false)
    ) {
        return true;
    }

    // On a client build, always enforce the feature constant check.
    // VAPTSECURE_FEATURE_* constants are set only for features in the config.
    // This applies regardless of license type — developer_unbound means domain-unlocked, not feature-unlocked.
    if (
        defined("VAPTSECURE_BUILD_PROFILE") &&
        VAPTSECURE_BUILD_PROFILE === "client"
    ) {
        $const_name =
            "VAPTSECURE_FEATURE_" .
            strtoupper(str_replace("-", "_", $feature_key));
        return defined($const_name) && constant($const_name) === true;
    }

    if (
        defined("VAPTSECURE_LICENSE_TYPE") &&
        VAPTSECURE_LICENSE_TYPE === "developer_unbound"
    ) {
        return true;
    }

    // If not in restricted mode, all features are allowed
    if (
        !defined("VAPTSECURE_RESTRICT_FEATURES") ||
        !VAPTSECURE_RESTRICT_FEATURES
    ) {
        return true;
    }

    // Check if the specific feature constant is defined (set in generated config)
    $const_name =
        "VAPTSECURE_FEATURE_" . strtoupper(str_replace("-", "_", $feature_key));
    return defined($const_name) && constant($const_name) === true;
}

/**
 * Check if the current site domain matches the build's locked domain.
 * Supports wildcard matching if VAPTSECURE_DOMAIN_WILDCARD is defined.
 */
function vaptsecure_is_domain_match()
{
    if (
        !defined("VAPTSECURE_DOMAIN_LOCKED") ||
        empty(VAPTSECURE_DOMAIN_LOCKED)
    ) {
        return true;
    }

    $current_host = isset($_SERVER["HTTP_HOST"])
        ? strtolower(
            preg_replace('/:\d+$/', "", (string) $_SERVER["HTTP_HOST"]),
        )
        : "";
    $locked_domain = strtolower(trim((string) VAPTSECURE_DOMAIN_LOCKED));
    $current_host = preg_replace('/^www\./', '', $current_host);
    $locked_domain = preg_replace('/^www\./', '', $locked_domain);

    if ($current_host === $locked_domain) {
        return true;
    }

    if ($locked_domain === "") {
        return false;
    }

    // Wildcard matching: strict dot-delimited label comparison.
    // Prevents substring lookalikes such as hermasnet.com.evil.com.
    if (defined("VAPTSECURE_DOMAIN_WILDCARD") && VAPTSECURE_DOMAIN_WILDCARD) {
        $current_labels = explode('.', $current_host);
        $locked_labels  = explode('.', $locked_domain);
        if (count($current_labels) > count($locked_labels)) {
            $suffix = array_slice($current_labels, -count($locked_labels));
            if ($suffix === $locked_labels) {
                return true;
            }
        }
        if ($current_host === $locked_domain) {
            return true;
        }
    }

    return false;
}

/**
 * Detects when the builder admin UI is available.
 * Generated client builds strip the workbench/admin renderers, so their
 * presence indicates the plugin is still running in builder mode.
 */
function vaptsecure_is_builder_context()
{
    return function_exists("vaptsecure_render_workbench_page") ||
        function_exists("vaptsecure_render_admin_page") ||
        function_exists("vaptsecure_master_dashboard_page");
}

function vaptsecure_is_workbench_request()
{
    $page = "";
    if (isset($_GET["page"])) {
        $page = (string) $_GET["page"];
    } elseif (isset($_REQUEST["page"])) {
        $page = (string) $_REQUEST["page"];
    }

    return in_array(
        $page,
        [
            "vaptsecure",
            "vaptsecure-workbench",
            "vaptsecure-master-dashboard",
            "vaptsecure-admin",
            "vaptsecure-domain-admin",
        ],
        true,
    );
}

if (!function_exists("vaptsecure_get_admin_menu_slug")) {
    function vaptsecure_get_admin_menu_slug()
    {
        if (
            defined("VAPTSECURE_MENU_SLUG") &&
            is_string(VAPTSECURE_MENU_SLUG) &&
            trim(VAPTSECURE_MENU_SLUG) !== ""
        ) {
            return sanitize_title((string) VAPTSECURE_MENU_SLUG);
        }

        return "vaptsecure";
    }
}

if (!function_exists("vaptsecure_is_client_dashboard_screen")) {
    function vaptsecure_is_client_dashboard_screen($screen_id = "")
    {
        $screen_id = (string) $screen_id;
        $slug = vaptsecure_get_admin_menu_slug();

        $candidates = array(
            "toplevel_page_" . $slug,
            "vaptsecure_page_" . $slug,
            $slug,
        );

        return in_array($screen_id, $candidates, true) ||
            strpos($screen_id, $slug) !== false;
    }
}

function vaptsecure_current_user_can_safe($capability)
{
    if (
        !function_exists("current_user_can") ||
        !function_exists("wp_get_current_user")
    ) {
        return false;
    }

    return current_user_can($capability);
}

function vaptsecure_load_required_config()
{
    $is_already_loaded =
        defined("VAPTSECURE_CONFIG_LOADED") && VAPTSECURE_CONFIG_LOADED;
    $host = isset($_SERVER["HTTP_HOST"])
        ? strtolower(
            preg_replace('/:\d+$/', "", (string) $_SERVER["HTTP_HOST"]),
        )
        : "";
    $is_local_host =
        in_array($host, ["localhost", "127.0.0.1", "::1"], true) ||
        preg_match('/\.(local|test|dev)$/', $host);
    if (function_exists("wp_get_environment_type")) {
        $is_local_host =
            $is_local_host || wp_get_environment_type() === "local";
    }

    $should_bypass_blocking = function () use ($is_local_host) {
        $is_builder_admin_request =
            function_exists("vaptsecure_is_workbench_request") &&
            vaptsecure_is_workbench_request();

        if (
            $is_local_host ||
            $is_builder_admin_request
        ) {
            return true;
        }
        if (
            function_exists("vaptsecure_current_user_can_safe") &&
            vaptsecure_current_user_can_safe("manage_options")
        ) {
            return true;
        }
        if (
            function_exists("is_vaptsecure_superadmin") &&
            is_vaptsecure_superadmin(false)
        ) {
            return true;
        }
        return false;
    };

    if (
        function_exists("vaptsecure_is_builder_context") &&
        vaptsecure_is_builder_context() &&
        function_exists("is_vaptsecure_superadmin") &&
        is_vaptsecure_superadmin(false)
    ) {
        if (!defined("VAPTSECURE_LICENSE_TYPE")) {
            define("VAPTSECURE_LICENSE_TYPE", "standard");
        }
        if (!defined("VAPTSECURE_LICENSE_SCOPE")) {
            define("VAPTSECURE_LICENSE_SCOPE", "single");
        }
        if (!defined("VAPTSECURE_DOMAIN_LIMIT")) {
            define("VAPTSECURE_DOMAIN_LIMIT", 1);
        }
        if (!defined("VAPTSECURE_RESTRICT_FEATURES")) {
            define("VAPTSECURE_RESTRICT_FEATURES", false);
        }
        if (!defined("VAPTSECURE_CONFIG_LOADED")) {
            define("VAPTSECURE_CONFIG_LOADED", true);
        }
        if (function_exists("update_option")) {
            update_option("vaptsecure_global_protection", 1);
            delete_transient("vaptsecure_active_enforcements");
        }
        return true;
    }

    $root = VAPTSECURE_PATH;
    $candidates = [];

    $locked = $root . "vapt-locked-config.php";
    if (file_exists($locked)) {
        $candidates[] = $locked;
    }

    $globbed = glob($root . "vapt-*-config-*.php");
    if (is_array($globbed) && !empty($globbed)) {
        usort($globbed, function ($a, $b) {
            $ta = @filemtime($a);
            $tb = @filemtime($b);
            if ($ta === $tb) {
                return 0;
            }
            return $ta > $tb ? -1 : 1;
        });
        $candidates = array_merge($candidates, $globbed);
    }

    $candidates = array_values(
        array_unique(array_filter($candidates, "file_exists")),
    );

    if (empty($candidates)) {
        $is_owner_workbench =
            function_exists("vaptsecure_is_workbench_request") &&
            vaptsecure_is_workbench_request() &&
            function_exists("is_vaptsecure_superadmin") &&
            is_vaptsecure_superadmin(false);
        $is_owner_context = $should_bypass_blocking();

        if ($is_owner_workbench || $is_owner_context) {
            if (!defined("VAPTSECURE_CONFIG_LOADED")) {
                define("VAPTSECURE_CONFIG_LOADED", true);
            }
            if (function_exists("update_option")) {
                update_option("vaptsecure_global_protection", 1);
                delete_transient("vaptsecure_active_enforcements");
            }
            add_action("admin_notices", function () {
                if (
                    !function_exists("vaptsecure_current_user_can_safe") ||
                    !vaptsecure_current_user_can_safe("manage_options")
                ) {
                    return;
                }
                echo '<div class="notice notice-warning"><p><strong>VAPTSecure Clean:</strong> Required configuration file is missing. Owner/builder mode is continuing without disabling the plugin.</p></div>';
            });
            add_action("network_admin_notices", function () {
                if (
                    !function_exists("vaptsecure_current_user_can_safe") ||
                    !vaptsecure_current_user_can_safe("manage_network_options")
                ) {
                    return;
                }
                echo '<div class="notice notice-warning"><p><strong>VAPTSecure Clean:</strong> Required configuration file is missing. Owner/builder mode is continuing without disabling the plugin.</p></div>';
            });
            return true;
        }

        if (!defined("VAPTSECURE_CONFIG_MISSING")) {
            define("VAPTSECURE_CONFIG_MISSING", true);
        }

        if (function_exists("update_option")) {
            update_option("vaptsecure_global_protection", 0);
            delete_transient("vaptsecure_active_enforcements");
        }

        add_action("admin_notices", function () {
            if (
                !function_exists("vaptsecure_current_user_can_safe") ||
                !vaptsecure_current_user_can_safe("manage_options")
            ) {
                return;
            }
            echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> Required configuration file is missing. The plugin is disabled.</p></div>';
        });

        add_action("network_admin_notices", function () {
            if (
                !function_exists("vaptsecure_current_user_can_safe") ||
                !vaptsecure_current_user_can_safe("manage_network_options")
            ) {
                return;
            }
            echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> Required configuration file is missing. The plugin is disabled.</p></div>';
        });

        add_action("admin_init", function () use ($should_bypass_blocking) {
            if ($should_bypass_blocking()) {
                return;
            }
            if (get_transient("vaptsecure_missing_config_notified")) {
                return;
            }
            set_transient(
                "vaptsecure_missing_config_notified",
                1,
                DAY_IN_SECONDS,
            );
            $to = (string) get_option("admin_email");
            if ($to) {
                $site_url = function_exists("get_site_url")
                    ? get_site_url()
                    : "";
                wp_mail(
                    $to,
                    "[VAPTSecure Clean] Configuration file missing",
                    "VAPTSecure Clean is disabled because its configuration file is missing.\n\nSite: {$site_url}\n",
                );
            }
        });

        add_action(
            "init",
            function () use ($should_bypass_blocking) {
                $uri = isset($_SERVER["REQUEST_URI"])
                    ? (string) $_SERVER["REQUEST_URI"]
                    : "";
                if (
                    strpos($uri, "/wp-admin/") !== false ||
                    strpos($uri, "/wp-login.php") !== false
                ) {
                    return;
                }
                if (function_exists("wp_doing_ajax") && wp_doing_ajax()) {
                    return;
                }
                if (defined("REST_REQUEST") && REST_REQUEST) {
                    return;
                }
                if (defined("DOING_CRON") && DOING_CRON) {
                    return;
                }
                if ($should_bypass_blocking()) {
                    return;
                }
                wp_die(
                    "<h1>VAPTSecure Clean</h1><p>Required configuration file is missing. The plugin is disabled.</p>",
                );
            },
            0,
        );

        if (!defined("VAPTSECURE_LICENSE_TYPE")) {
            define("VAPTSECURE_LICENSE_TYPE", "standard");
        }
        if (!defined("VAPTSECURE_LICENSE_SCOPE")) {
            define("VAPTSECURE_LICENSE_SCOPE", "single");
        }
        if (!defined("VAPTSECURE_DOMAIN_LIMIT")) {
            define("VAPTSECURE_DOMAIN_LIMIT", 1);
        }
        if (!defined("VAPTSECURE_RESTRICT_FEATURES")) {
            define("VAPTSECURE_RESTRICT_FEATURES", false);
        }
        if (!defined("VAPTSECURE_CONFIG_LOADED")) {
            define("VAPTSECURE_CONFIG_LOADED", true);
        }
        return true;
    }

    $config_path = $candidates[0];

    $extract_b64 = function ($content) {
        if (!is_string($content) || $content === "") {
            return "";
        }
        if (
            preg_match(
                '/define\\(\\s*[\\\'\\"]VAPTSECURE_DEFAULT_CONFIG_B64[\\\'\\"]\\s*,\\s*[\\\'\\"]([^\\\'\\"]+)[\\\'\\"]\\s*\\)\\s*;/',
                $content,
                $m,
            )
        ) {
            return (string) $m[1];
        }
        if (
            preg_match(
                '/define\\(\\s*[\\\'\\"]VAPTSECURE_CONFIG_B64[\\\'\\"]\\s*,\\s*[\\\'\\"]([^\\\'\\"]+)[\\\'\\"]\\s*\\)\\s*;/',
                $content,
                $m,
            )
        ) {
            return (string) $m[1];
        }
        return "";
    };

    $extract_extended_b64 = function ($content) {
        if (!is_string($content) || $content === "") {
            return "";
        }
        if (
            preg_match(
                '/define\\(\\s*[\\\'\\"]VAPTSECURE_EXTENDED_CONFIG_B64[\\\'\\"]\\s*,\\s*[\\\'\\"]([^\\\'\\"]*)[\\\'\\"]\\s*\\)\\s*;/',
                $content,
                $m,
            )
        ) {
            return (string) $m[1];
        }
        return "";
    };

    $extract_custom = function ($content) {
        if (!is_string($content) || $content === "") {
            return "";
        }
        if (
            preg_match(
                "/\\/\\*\\s*VAPTSECURE_CONFIG_CUSTOM_START\\s*\\*\\/(.*?)\\/\\*\\s*VAPTSECURE_CONFIG_CUSTOM_END\\s*\\*\\//s",
                $content,
                $m,
            )
        ) {
            return (string) $m[1];
        }
        if (
            preg_match(
                '/unset\\s*\\(\\s*\\$__vaptsecure_payload_json\\s*,\\s*\\$__vaptsecure_payload\\s*\\)\\s*;\\s*(.*)$/s',
                $content,
                $m,
            )
        ) {
            return (string) $m[1];
        }
        return "";
    };

    $decode_b64 = function ($b64) {
        if (!is_string($b64) || $b64 === "") {
            return false;
        }
        $json = base64_decode($b64, true);
        if (!is_string($json) || $json === "") {
            return false;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : false;
    };

    $encode_payload = function ($payload) {
        return base64_encode(json_encode($payload));
    };

    $render_config_file = function ($default_b64, $extended_b64 = "", $custom_block = "") {
        $default_b64 = (string) $default_b64;
        $extended_b64 = (string) $extended_b64;
        $custom_block = is_string($custom_block) ? $custom_block : "";
        $default_hash = hash("sha256", $default_b64);
        $extended_hash = hash("sha256", $extended_b64);

        $out = "<?php\n";
        $out .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";
        $out .= "define( 'VAPTSECURE_CONFIG_B64', '" . $default_b64 . "' );\n";
        $out .= "define( 'VAPTSECURE_DEFAULT_CONFIG_B64', '" . $default_b64 . "' );\n";
        $out .= "define( 'VAPTSECURE_DEFAULT_CONFIG_HASH', '" . $default_hash . "' );\n";
        $out .= "define( 'VAPTSECURE_EXTENDED_CONFIG_B64', '" . $extended_b64 . "' );\n";
        $out .= "define( 'VAPTSECURE_EXTENDED_CONFIG_HASH', '" . $extended_hash . "' );\n";
        $out .= "if ( ! function_exists( 'vaptsecure_apply_config_payload' ) ) {\n";
        $out .= "    function vaptsecure_apply_config_payload( \$payload, \$section = 'default' ) {\n";
        $out .= "        if ( ! is_array( \$payload ) ) { return false; }\n";
        $out .= "        \$section = (string) \$section;\n";
        $out .= "        \$is_default = ( \$section === 'default' );\n";
        $out .= "        \$license_type = isset( \$payload['license_type'] ) ? (string) \$payload['license_type'] : 'standard';\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_BUILD_PROFILE' ) && isset( \$payload['build_profile'] ) ) { define( 'VAPTSECURE_BUILD_PROFILE', (string) \$payload['build_profile'] ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_LICENSE_TYPE' ) ) { define( 'VAPTSECURE_LICENSE_TYPE', \$license_type ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_IS_TRIAL' ) ) { define( 'VAPTSECURE_IS_TRIAL', ! empty( \$payload['is_trial'] ) ); }\n";
        $out .= "        if ( \$is_default && \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_LOCKED' ) && ! empty( \$payload['domain_locked'] ) ) { define( 'VAPTSECURE_DOMAIN_LOCKED', (string) \$payload['domain_locked'] ); }\n";
        $out .= "        if ( \$is_default && \$license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_WILDCARD' ) && ! empty( \$payload['is_wildcard'] ) ) { define( 'VAPTSECURE_DOMAIN_WILDCARD', true ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_BUILD_VERSION' ) && isset( \$payload['build_version'] ) ) { define( 'VAPTSECURE_BUILD_VERSION', (string) \$payload['build_version'] ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_LICENSE_SCOPE' ) && isset( \$payload['license_scope'] ) ) { define( 'VAPTSECURE_LICENSE_SCOPE', (string) \$payload['license_scope'] ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_DOMAIN_LIMIT' ) && isset( \$payload['domain_limit'] ) ) { define( 'VAPTSECURE_DOMAIN_LIMIT', intval( \$payload['domain_limit'] ) ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_REQUIRE_WP' ) && isset( \$payload['require_wp'] ) ) { define( 'VAPTSECURE_REQUIRE_WP', (string) \$payload['require_wp'] ); }\n";
        $out .= "        if ( \$is_default && ! defined( 'VAPTSECURE_REQUIRE_PHP' ) && isset( \$payload['require_php'] ) ) { define( 'VAPTSECURE_REQUIRE_PHP', (string) \$payload['require_php'] ); }\n";
        $out .= "        if ( \$is_default ) {\n";
        $out .= "            \$restrict = ! empty( \$payload['restrict_features'] );\n";
        $out .= "            if ( \$license_type === 'developer_unbound' ) { \$restrict = false; }\n";
        $out .= "            if ( ! defined( 'VAPTSECURE_RESTRICT_FEATURES' ) ) { define( 'VAPTSECURE_RESTRICT_FEATURES', (bool) \$restrict ); }\n";
        $out .= "            if ( isset( \$payload['features'] ) && is_array( \$payload['features'] ) ) {\n";
        $out .= "                foreach ( \$payload['features'] as \$key ) {\n";
        $out .= "                    \$key = (string) \$key;\n";
        $out .= "                    if ( \$key === '' ) { continue; }\n";
        $out .= "                    \$const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', \$key ) );\n";
        $out .= "                    if ( ! defined( \$const ) ) { define( \$const, true ); }\n";
        $out .= "                }\n";
        $out .= "            }\n";
        $out .= "        }\n";
        $out .= "        if ( \$section !== 'default' && isset( \$payload['features'] ) && is_array( \$payload['features'] ) ) {\n";
        $out .= "            foreach ( \$payload['features'] as \$key ) {\n";
        $out .= "                \$key = (string) \$key;\n";
        $out .= "                if ( \$key === '' ) { continue; }\n";
        $out .= "                \$const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', \$key ) );\n";
        $out .= "                if ( ! defined( \$const ) ) { define( \$const, true ); }\n";
        $out .= "            }\n";
        $out .= "        }\n";
        $out .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_ACTIVE_DATA_FILE' ) && ! empty( \$payload['active_data_file'] ) ) { define( 'VAPTSECURE_ACTIVE_DATA_FILE', (string) \$payload['active_data_file'] ); }\n";
        $out .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_BUILD_AT' ) && ! empty( \$payload['build_at'] ) ) { define( 'VAPTSECURE_BUILD_AT', (string) \$payload['build_at'] ); }\n";
        $out .= "        if ( \$section !== 'default' && ! defined( 'VAPTSECURE_SECURITY_ALERT_EMAIL' ) && ! empty( \$payload['security_alert_email_b64'] ) ) { define( 'VAPTSECURE_SECURITY_ALERT_EMAIL', base64_decode( (string) \$payload['security_alert_email_b64'] ) ); }\n";
        $out .= "        if ( ! defined( 'VAPTSECURE_CONFIG_LOADED' ) ) { define( 'VAPTSECURE_CONFIG_LOADED', true ); }\n";
        $out .= "        return true;\n";
        $out .= "    }\n";
        $out .= "}\n";
        $out .= "if ( ! function_exists( 'vaptsecure_load_config_sections' ) ) {\n";
        $out .= "    function vaptsecure_load_config_sections() {\n";
        $out .= "        \$__default_json = base64_decode( VAPTSECURE_DEFAULT_CONFIG_B64, true );\n";
        $out .= "        \$__default_payload = \$__default_json ? json_decode( \$__default_json, true ) : null;\n";
        $out .= "        if ( is_array( \$__default_payload ) ) { vaptsecure_apply_config_payload( \$__default_payload, 'default' ); }\n";
        $out .= "        if ( defined( 'VAPTSECURE_EXTENDED_CONFIG_B64' ) && VAPTSECURE_EXTENDED_CONFIG_B64 !== '' ) {\n";
        $out .= "            \$__extended_json = base64_decode( VAPTSECURE_EXTENDED_CONFIG_B64, true );\n";
        $out .= "            \$__extended_payload = \$__extended_json ? json_decode( \$__extended_json, true ) : null;\n";
        $out .= "            if ( is_array( \$__extended_payload ) ) { vaptsecure_apply_config_payload( \$__extended_payload, 'extended' ); }\n";
        $out .= "            unset( \$__extended_json, \$__extended_payload );\n";
        $out .= "        }\n";
        $out .= "        unset( \$__default_json, \$__default_payload );\n";
        $out .= "        return true;\n";
        $out .= "    }\n";
        $out .= "}\n";
        $out .= "vaptsecure_load_config_sections();\n";
        $out .= "/* VAPTSECURE_CONFIG_CUSTOM_START */\n";
        $out .= $custom_block;
        if ($custom_block !== "" && substr($custom_block, -1) !== "\n") {
            $out .= "\n";
        }
        $out .= "/* VAPTSECURE_CONFIG_CUSTOM_END */\n";
        $out .= "\n";
        return $out;
    };

    $notify = function ($subject, $message, $transient_key) {
        if (get_transient($transient_key)) {
            return;
        }
        set_transient($transient_key, 1, DAY_IN_SECONDS);

        $to = "";
        if (
            defined("VAPTSECURE_SECURITY_ALERT_EMAIL") &&
            VAPTSECURE_SECURITY_ALERT_EMAIL
        ) {
            $to = VAPTSECURE_SECURITY_ALERT_EMAIL;
        } else {
            $to = (string) get_option("admin_email");
        }

        if ($to) {
            wp_mail($to, $subject, $message);
        }
    };

    $content = file_get_contents($config_path);
    $current_b64 = $extract_b64($content);
    $current_extended_b64 = $extract_extended_b64($content);
    $custom_block = $extract_custom($content);

    if ($current_b64 !== "") {
        $current_payload = $decode_b64($current_b64);
        if (!is_array($current_payload)) {
            if (!defined("VAPTSECURE_CONFIG_MISSING")) {
                define("VAPTSECURE_CONFIG_MISSING", true);
            }
            if (function_exists("update_option")) {
                update_option("vaptsecure_global_protection", 0);
                delete_transient("vaptsecure_active_enforcements");
            }
            add_action("admin_notices", function () {
                if (
                    !function_exists("vaptsecure_current_user_can_safe") ||
                    !vaptsecure_current_user_can_safe("manage_options")
                ) {
                    return;
                }
                echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> Configuration file is invalid. The plugin is disabled.</p></div>';
            });
            add_action(
                "init",
                function () use ($should_bypass_blocking) {
                    $uri = isset($_SERVER["REQUEST_URI"])
                        ? (string) $_SERVER["REQUEST_URI"]
                        : "";
                    if (
                        strpos($uri, "/wp-admin/") !== false ||
                        strpos($uri, "/wp-login.php") !== false
                    ) {
                        return;
                    }
                    if (function_exists("wp_doing_ajax") && wp_doing_ajax()) {
                        return;
                    }
                    if (defined("REST_REQUEST") && REST_REQUEST) {
                        return;
                    }
                    if (defined("DOING_CRON") && DOING_CRON) {
                        return;
                    }
                    if ($should_bypass_blocking()) {
                        return;
                    }
                    wp_die(
                        "<h1>VAPTSecure Clean</h1><p>Configuration file is invalid. The plugin is disabled.</p>",
                    );
                },
                0,
            );

            if (!$should_bypass_blocking()) {
                $site_url = function_exists("get_site_url")
                    ? get_site_url()
                    : "";
                $notify(
                    "[VAPTSecure Clean] Configuration invalid",
                    "VAPTSecure Clean is disabled because its configuration file is invalid.\n\nSite: {$site_url}\n",
                    "vaptsecure_invalid_config_notified",
                );
            }

            if (!defined("VAPTSECURE_LICENSE_TYPE")) {
                define("VAPTSECURE_LICENSE_TYPE", "standard");
            }
            if (!defined("VAPTSECURE_LICENSE_SCOPE")) {
                define("VAPTSECURE_LICENSE_SCOPE", "single");
            }
            if (!defined("VAPTSECURE_DOMAIN_LIMIT")) {
                define("VAPTSECURE_DOMAIN_LIMIT", 1);
            }
            if (!defined("VAPTSECURE_RESTRICT_FEATURES")) {
                define("VAPTSECURE_RESTRICT_FEATURES", false);
            }
            if (!defined("VAPTSECURE_CONFIG_LOADED")) {
                define("VAPTSECURE_CONFIG_LOADED", true);
            }
            return true;
        }

        $stored_original_path = get_option(
            "vaptsecure_config_original_path",
            "",
        );
        if (!is_string($stored_original_path)) {
            $stored_original_path = "";
        }

        $original_b64 = get_option("vaptsecure_config_original_b64", "");
        if (!is_string($original_b64)) {
            $original_b64 = "";
        }

        if (
            $stored_original_path !== "" &&
            $stored_original_path !== $config_path
        ) {
            update_option("vaptsecure_config_original_b64", $current_b64);
            update_option(
                "vaptsecure_config_original_hash",
                hash("sha256", $current_b64),
            );
            update_option("vaptsecure_config_original_path", $config_path);
            update_option("vaptsecure_config_current_b64", $current_b64);
            update_option(
                "vaptsecure_config_current_extended_b64",
                $current_extended_b64,
            );
            update_option("vaptsecure_config_last_sync", current_time("mysql"));
            $original_b64 = $current_b64;
        }

        if ($original_b64 === "") {
            update_option("vaptsecure_config_original_b64", $current_b64);
            update_option(
                "vaptsecure_config_original_hash",
                hash("sha256", $current_b64),
            );
            update_option("vaptsecure_config_original_path", $config_path);
            update_option("vaptsecure_config_current_b64", $current_b64);
            update_option(
                "vaptsecure_config_current_extended_b64",
                $current_extended_b64,
            );
        } else {
            $baseline_b64 = get_option("vaptsecure_config_current_b64", "");
            if (!is_string($baseline_b64)) {
                $baseline_b64 = "";
            }
            if ($baseline_b64 === "") {
                $baseline_b64 = $original_b64;
                update_option("vaptsecure_config_current_b64", $baseline_b64);
            }

            if ($baseline_b64 !== $current_b64) {
                $original_payload = $decode_b64($original_b64);
                $original_type =
                    is_array($original_payload) &&
                    isset($original_payload["license_type"])
                        ? (string) $original_payload["license_type"]
                        : "";
                $current_type = isset($current_payload["license_type"])
                    ? (string) $current_payload["license_type"]
                    : "";
                $is_dev_unbound =
                    $original_type === "developer_unbound" ||
                    $current_type === "developer_unbound";

                if ($is_dev_unbound && is_array($original_payload)) {
                    $merged = $original_payload;
                    foreach ($current_payload as $k => $v) {
                        if (!array_key_exists($k, $merged)) {
                            $merged[$k] = $v;
                        }
                    }
                    $orig_features =
                        isset($original_payload["features"]) &&
                        is_array($original_payload["features"])
                            ? $original_payload["features"]
                            : [];
                    $cur_features =
                        isset($current_payload["features"]) &&
                        is_array($current_payload["features"])
                            ? $current_payload["features"]
                            : [];
                    $merged["features"] = array_values(
                        array_unique(
                            array_merge($orig_features, $cur_features),
                        ),
                    );
                    $merged["license_type"] = "developer_unbound";
                    $merged["restrict_features"] = false;
                    $new_b64 = $encode_payload($merged);
                } else {
                    $new_b64 = $original_b64;
                }

                if (is_writable($config_path)) {
                    file_put_contents(
                        $config_path,
                        $render_config_file(
                            $new_b64,
                            $current_extended_b64,
                            $custom_block,
                        ),
                    );
                    update_option(
                        "vaptsecure_config_last_sync",
                        current_time("mysql"),
                    );
                    update_option("vaptsecure_config_current_b64", $new_b64);
                    update_option(
                        "vaptsecure_config_current_extended_b64",
                        $current_extended_b64,
                    );
                    $site_url = function_exists("get_site_url")
                        ? get_site_url()
                        : "";
                    $notify(
                        "[VAPTSecure Clean] Configuration restored",
                        "VAPTSecure Clean restored its configuration file to the saved baseline.\n\nSite: {$site_url}\n",
                        "vaptsecure_config_restore_notified",
                    );
                    $current_b64 = $new_b64;
                }
            }
        }

        if (!$is_already_loaded) {
            require_once $config_path;
        }

        return true;
    }

    require_once $config_path;

    $payload = [
        "build_profile" => defined("VAPTSECURE_BUILD_PROFILE")
            ? (string) VAPTSECURE_BUILD_PROFILE
            : "client",
        "license_type" => defined("VAPTSECURE_LICENSE_TYPE")
            ? (string) VAPTSECURE_LICENSE_TYPE
            : "standard",
        "is_trial" => defined("VAPTSECURE_IS_TRIAL")
            ? (bool) VAPTSECURE_IS_TRIAL
            : false,
        "domain_locked" => defined("VAPTSECURE_DOMAIN_LOCKED")
            ? (string) VAPTSECURE_DOMAIN_LOCKED
            : "",
        "build_version" => defined("VAPTSECURE_BUILD_VERSION")
            ? (string) VAPTSECURE_BUILD_VERSION
            : "",
        "license_scope" => defined("VAPTSECURE_LICENSE_SCOPE")
            ? (string) VAPTSECURE_LICENSE_SCOPE
            : "single",
        "domain_limit" => defined("VAPTSECURE_DOMAIN_LIMIT")
            ? intval(VAPTSECURE_DOMAIN_LIMIT)
            : 1,
        "restrict_features" => defined("VAPTSECURE_RESTRICT_FEATURES")
            ? (bool) VAPTSECURE_RESTRICT_FEATURES
            : false,
        "is_wildcard" => defined("VAPTSECURE_DOMAIN_WILDCARD")
            ? (bool) VAPTSECURE_DOMAIN_WILDCARD
            : false,
        "require_wp" => defined("VAPTSECURE_REQUIRE_WP")
            ? (string) VAPTSECURE_REQUIRE_WP
            : "6.0",
        "require_php" => defined("VAPTSECURE_REQUIRE_PHP")
            ? (string) VAPTSECURE_REQUIRE_PHP
            : "7.4.33",
        "features" => [],
    ];

    $consts = get_defined_constants(true);
    $user_consts =
        isset($consts["user"]) && is_array($consts["user"])
            ? $consts["user"]
            : [];
    foreach ($user_consts as $k => $v) {
        if (strpos($k, "VAPTSECURE_FEATURE_") === 0 && $v === true) {
            $suffix = substr($k, strlen("VAPTSECURE_FEATURE_"));
            $payload["features"][] = strtolower(str_replace("_", "-", $suffix));
        }
    }

    $new_b64 = $encode_payload($payload);
    if (is_writable($config_path)) {
        file_put_contents(
            $config_path,
            $render_config_file($new_b64, $current_extended_b64, $custom_block),
        );
        if (get_option("vaptsecure_config_original_b64", "") === "") {
            update_option("vaptsecure_config_original_b64", $new_b64);
            update_option(
                "vaptsecure_config_original_hash",
                hash("sha256", $new_b64),
            );
            update_option("vaptsecure_config_original_path", $config_path);
            update_option("vaptsecure_config_current_b64", $new_b64);
            update_option(
                "vaptsecure_config_current_extended_b64",
                $current_extended_b64,
            );
        }
    }

    return true;
}

if (!vaptsecure_load_required_config()) {
    return;
}

// Include core classes (new Builder includes)
require_once VAPTSECURE_PATH . "includes/debug-utils.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-auth.php";

// P4.1: Driver Interface Contract - Load interface before drivers
require_once VAPTSECURE_PATH .
    "includes/interfaces/interface-vaptsecure-driver.php";

// P4.2: Schema Validation Pipeline - Load validator before REST
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-schema-validator.php";

require_once VAPTSECURE_PATH . "includes/class-vaptsecure-rest.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-db.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-workflow.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-ai-config.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-build.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-config-cleaner.php"; // Shared utility for config cleaning
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-enforcer.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-admin.php";
require_once VAPTSECURE_PATH . "includes/class-vaptsecure-license-manager.php";
require_once VAPTSECURE_PATH . "includes/self-check/class-vapt-check-item.php";
require_once VAPTSECURE_PATH .
    "includes/self-check/class-vapt-self-check-result.php";
require_once VAPTSECURE_PATH . "includes/self-check/class-vapt-audit-log.php";
require_once VAPTSECURE_PATH .
    "includes/self-check/class-vapt-auto-correct.php";
require_once VAPTSECURE_PATH . "includes/self-check/class-vapt-self-check.php";
require_once VAPTSECURE_PATH . "includes/self-check/class-vapt-cron.php";
require_once VAPTSECURE_PATH . "includes/self-check/class-vapt-lifecycle.php";
require_once VAPTSECURE_PATH . "includes/admin/class-vapt-diagnostics-page.php";

function vaptsecure_get_configured_feature_keys()
{
    $keys = [];
    $consts = get_defined_constants(true);
    $user_consts =
        isset($consts["user"]) && is_array($consts["user"])
            ? $consts["user"]
            : [];
    foreach ($user_consts as $k => $v) {
        if (strpos($k, "VAPTSECURE_FEATURE_") !== 0 || $v !== true) {
            continue;
        }
        $suffix = substr($k, strlen("VAPTSECURE_FEATURE_"));
        if (!is_string($suffix) || $suffix === "") {
            continue;
        }
        $keys[] = strtoupper(str_replace("_", "-", $suffix));
    }
    $keys = array_values(array_unique(array_filter($keys)));
    sort($keys);
    return $keys;
}

function vaptsecure_seed_client_release_features()
{
    if (
        !defined("VAPTSECURE_BUILD_PROFILE") ||
        VAPTSECURE_BUILD_PROFILE !== "client"
    ) {
        return;
    }

    $host = parse_url(home_url(), PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : "";
    if ($host === "") {
        return;
    }

    $default_config_b64 = defined("VAPTSECURE_DEFAULT_CONFIG_B64")
        ? VAPTSECURE_DEFAULT_CONFIG_B64
        : (defined("VAPTSECURE_CONFIG_B64") ? VAPTSECURE_CONFIG_B64 : "");
    $extended_config_b64 = defined("VAPTSECURE_EXTENDED_CONFIG_B64")
        ? VAPTSECURE_EXTENDED_CONFIG_B64
        : "";
    $config_b64_hash = md5($default_config_b64 . "|" . $extended_config_b64);
    $seed_key =
        "vaptsecure_client_seeded_" .
        md5(
            $host .
                "|" .
                (defined("VAPTSECURE_BUILD_VERSION")
                    ? VAPTSECURE_BUILD_VERSION
                    : VAPTSECURE_VERSION) .
                "|" .
                $config_b64_hash,
        );
    if (get_option($seed_key)) {
        return;
    }

    global $wpdb;
    $status_table = $wpdb->prefix . "vaptsecure_feature_status";
    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $status_table))) {
        if (function_exists("vaptsecure_activate_plugin")) {
            vaptsecure_activate_plugin();
        }
    }

    $features = vaptsecure_get_configured_feature_keys();
    if (empty($features)) {
        update_option($seed_key, current_time("mysql"));
        return;
    }

    $payload = null;
    $default_payload = null;
    $extended_payload = null;
    if ($default_config_b64 !== "") {
        $payload_json = base64_decode($default_config_b64, true);
        $default_payload = $payload_json ? json_decode($payload_json, true) : null;
    }
    if ($extended_config_b64 !== "") {
        $extended_json = base64_decode($extended_config_b64, true);
        $extended_payload = $extended_json ? json_decode($extended_json, true) : null;
    }
    if (is_array($default_payload)) {
        $payload = $default_payload;
        if (is_array($extended_payload)) {
            foreach ($extended_payload as $k => $v) {
                if ($k === "features" && is_array($v)) {
                    $payload["features"] = array_values(
                        array_unique(
                            array_merge(
                                isset($payload["features"]) && is_array($payload["features"]) ? $payload["features"] : [],
                                $v,
                            ),
                        ),
                    );
                    continue;
                }
                if (!array_key_exists($k, $payload)) {
                    $payload[$k] = $v;
                }
            }
        }
    } elseif (is_array($extended_payload)) {
        $payload = $extended_payload;
    }

    $feature_meta_snapshot =
        is_array($payload) &&
        isset($payload["feature_meta"]) &&
        is_array($payload["feature_meta"])
            ? $payload["feature_meta"]
            : [];
    if (!empty($feature_meta_snapshot)) {
        $meta_table = $wpdb->prefix . "vaptsecure_feature_meta";
        foreach ($feature_meta_snapshot as $k => $meta) {
            $feature_key = strtoupper(trim((string) $k));
            if ($feature_key === "") {
                continue;
            }
            if (!is_array($meta)) {
                $meta = [];
            }

            $decode_b64 = function ($b64) {
                if (!is_string($b64) || $b64 === "") {
                    return null;
                }
                $decoded = base64_decode($b64, true);
                return is_string($decoded) ? $decoded : null;
            };

            $impl_json = isset($meta["implementation_data_b64"])
                ? $decode_b64($meta["implementation_data_b64"])
                : null;
            $impl_arr =
                is_string($impl_json) && $impl_json !== ""
                    ? json_decode($impl_json, true)
                    : null;
            if (!is_array($impl_arr)) {
                $impl_arr = [];
            }
            $risk_suffix = str_replace("-", "_", strtolower($feature_key));
            $auto_key = "vapt_risk_{$risk_suffix}_enabled";
            $toggle_val = null;
            if (array_key_exists("enabled", $impl_arr)) {
                $toggle_val = $impl_arr["enabled"];
            } elseif (array_key_exists("feat_enabled", $impl_arr)) {
                $toggle_val = $impl_arr["feat_enabled"];
            } elseif (array_key_exists("prot_enabled", $impl_arr)) {
                $toggle_val = $impl_arr["prot_enabled"];
            } elseif (array_key_exists($auto_key, $impl_arr)) {
                $toggle_val = $impl_arr[$auto_key];
            }

            // [v3.2.x] Check if is_enabled/is_enforced are explicitly provided in the build config
            // If explicitly provided, use those values; otherwise derive from implementation_data
            if (array_key_exists("is_enabled", $meta)) {
                $is_enabled_flag = intval($meta["is_enabled"]) === 1;
            } elseif (array_key_exists("is_enforced", $meta)) {
                $is_enabled_flag = intval($meta["is_enforced"]) === 1;
            } else {
                // Fallback: derive from implementation_data toggle, default to true (original behavior)
                $is_enabled_flag =
                    $toggle_val === null
                        ? true
                        : (bool) filter_var(
                            $toggle_val,
                            FILTER_VALIDATE_BOOLEAN,
                        );
            }

            $row = [
                "feature_key" => $feature_key,
                "generated_schema" => isset($meta["generated_schema_b64"])
                    ? $decode_b64($meta["generated_schema_b64"])
                    : null,
                "implementation_data" => $impl_json,
                "override_schema" => isset($meta["override_schema_b64"])
                    ? $decode_b64($meta["override_schema_b64"])
                    : null,
                "override_implementation_data" => isset(
                    $meta["override_implementation_data_b64"],
                )
                    ? $decode_b64($meta["override_implementation_data_b64"])
                    : null,
                "include_test_method" => isset($meta["include_test_method"])
                    ? (int) $meta["include_test_method"]
                    : 0,
                "include_verification" => isset($meta["include_verification"])
                    ? (int) $meta["include_verification"]
                    : 0,
                "include_verification_engine" => isset(
                    $meta["include_verification_engine"],
                )
                    ? (int) $meta["include_verification_engine"]
                    : 0,
                "include_verification_guidance" => isset(
                    $meta["include_verification_guidance"],
                )
                    ? (int) $meta["include_verification_guidance"]
                    : 1,
                "include_manual_protocol" => isset(
                    $meta["include_manual_protocol"],
                )
                    ? (int) $meta["include_manual_protocol"]
                    : 1,
                "include_operational_notes" => isset(
                    $meta["include_operational_notes"],
                )
                    ? (int) $meta["include_operational_notes"]
                    : 1,
                "wireframe_url" => isset($meta["wireframe_url"])
                    ? (string) $meta["wireframe_url"]
                    : null,
                "dev_instruct" => isset($meta["dev_instruct"])
                    ? (string) $meta["dev_instruct"]
                    : null,
                "is_adaptive_deployment" => isset(
                    $meta["is_adaptive_deployment"],
                )
                    ? (int) $meta["is_adaptive_deployment"]
                    : 0,
                "active_enforcer" => isset($meta["active_enforcer"])
                    ? (string) $meta["active_enforcer"]
                    : null,
                "is_enabled" => $is_enabled_flag ? 1 : 0,
                "is_enforced" => $is_enabled_flag ? 1 : 0,
            ];

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$meta_table} (feature_key, generated_schema, implementation_data, override_schema, override_implementation_data, include_test_method, include_verification, include_verification_engine, include_verification_guidance, include_manual_protocol, include_operational_notes, wireframe_url, dev_instruct, is_adaptive_deployment, active_enforcer, is_enabled, is_enforced)
                     VALUES (%s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %d, %s, %s, %d, %s, %d, %d)
                     ON DUPLICATE KEY UPDATE
                       generated_schema = VALUES(generated_schema),
                       implementation_data = VALUES(implementation_data),
                       override_schema = VALUES(override_schema),
                       override_implementation_data = VALUES(override_implementation_data),
                       include_test_method = VALUES(include_test_method),
                       include_verification = VALUES(include_verification),
                       include_verification_engine = VALUES(include_verification_engine),
                       include_verification_guidance = VALUES(include_verification_guidance),
                       include_manual_protocol = VALUES(include_manual_protocol),
                       include_operational_notes = VALUES(include_operational_notes),
                       wireframe_url = VALUES(wireframe_url),
                       dev_instruct = VALUES(dev_instruct),
                       is_adaptive_deployment = VALUES(is_adaptive_deployment),
                       active_enforcer = VALUES(active_enforcer),
                       is_enabled = VALUES(is_enabled),
                       is_enforced = VALUES(is_enforced)",
                    $row["feature_key"],
                    $row["generated_schema"],
                    $row["implementation_data"],
                    $row["override_schema"],
                    $row["override_implementation_data"],
                    $row["include_test_method"],
                    $row["include_verification"],
                    $row["include_verification_engine"],
                    $row["include_verification_guidance"],
                    $row["include_manual_protocol"],
                    $row["include_operational_notes"],
                    $row["wireframe_url"],
                    $row["dev_instruct"],
                    $row["is_adaptive_deployment"],
                    $row["active_enforcer"],
                    $row["is_enabled"],
                    $row["is_enforced"],
                ),
            );
        }
    }

    foreach ($features as $feature_key) {
        if (class_exists("VAPTSECURE_DB")) {
            VAPTSECURE_DB::update_feature_status($feature_key, "Release");
        }
        $meta_table = $wpdb->prefix . "vaptsecure_feature_meta";

        // [v3.2.x] Check if this feature has explicit config in the build
        $has_explicit_config =
            is_array($feature_meta_snapshot) &&
            isset($feature_meta_snapshot[strtoupper($feature_key)]);

        if ($has_explicit_config) {
            // Feature has explicit config - already handled in the snapshot loop above, skip
        } else {
            // No explicit meta: feature is licensed in the build so default to ENABLED
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$meta_table} (feature_key, is_enabled, is_enforced) VALUES (%s, 1, 1) ON DUPLICATE KEY UPDATE is_enabled = 1, is_enforced = 1",
                    $feature_key,
                ),
            );
        }
    }

    // [v3.2.x] Clear enforcement cache to ensure fresh count
    delete_transient("vaptsecure_active_enforcements");

    $domains_table = $wpdb->prefix . "vaptsecure_domains";
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$domains_table} (domain, is_wildcard, license_type, first_activated_at, is_enabled, license_scope, installation_limit)
             VALUES (%s, %d, %s, %s, %d, %s, %d)
             ON DUPLICATE KEY UPDATE is_enabled = 1, license_type = VALUES(license_type), license_scope = VALUES(license_scope), installation_limit = VALUES(installation_limit)",
            $host,
            0,
            defined("VAPTSECURE_LICENSE_TYPE")
                ? (string) VAPTSECURE_LICENSE_TYPE
                : "standard",
            current_time("mysql"),
            1,
            defined("VAPTSECURE_LICENSE_SCOPE")
                ? (string) VAPTSECURE_LICENSE_SCOPE
                : "single",
            defined("VAPTSECURE_DOMAIN_LIMIT")
                ? intval(VAPTSECURE_DOMAIN_LIMIT)
                : 1,
        ),
    );

    $domain_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$domains_table} WHERE domain = %s",
            $host,
        ),
    );
    if ($domain_id > 0) {
        $domain_features_table = $wpdb->prefix . "vaptsecure_domain_features";
        foreach ($features as $feature_key) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$domain_features_table} (domain_id, feature_key, enabled)
                     VALUES (%d, %s, 1)",
                    $domain_id,
                    $feature_key,
                ),
            );
        }
    }

    if (class_exists("VAPTSECURE_Enforcer")) {
        VAPTSECURE_Enforcer::rebuild_all();
    }

    update_option($seed_key, current_time("mysql"));
}

add_action("init", "vaptsecure_seed_client_release_features", 1);

/**
 * Check if the current environment meets the build's requirements.
 */
function vaptsecure_check_requirements()
{
    if (is_admin() && !defined("DOING_AJAX")) {
        // [v3.2.3] Check domain match before showing notices
        if (!vaptsecure_is_domain_match()) {
            return;
        }

        // WordPress Version Check
        if (defined("VAPTSECURE_REQUIRE_WP") && !empty(VAPTSECURE_REQUIRE_WP)) {
            $current_wp = get_bloginfo("version");
            if (version_compare($current_wp, VAPTSECURE_REQUIRE_WP, "<")) {
                add_action("admin_notices", function () use ($current_wp) {
                    echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> This build requires WordPress <strong>' .
                        esc_html(VAPTSECURE_REQUIRE_WP) .
                        "</strong> or higher. Your current version is " .
                        esc_html($current_wp) .
                        ". Please upgrade WordPress to enable protection.</p></div>";
                });
            }
        }

        // PHP Version Check
        if (
            defined("VAPTSECURE_REQUIRE_PHP") &&
            !empty(VAPTSECURE_REQUIRE_PHP)
        ) {
            $current_php = PHP_VERSION;
            if (version_compare($current_php, VAPTSECURE_REQUIRE_PHP, "<")) {
                add_action("admin_notices", function () use ($current_php) {
                    echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> This build requires PHP <strong>' .
                        esc_html(VAPTSECURE_REQUIRE_PHP) .
                        "</strong> or higher. Your current version is " .
                        esc_html($current_php) .
                        ". Please contact your host to upgrade PHP.</p></div>";
                });
            }
        }
    }
}
add_action("plugins_loaded", "vaptsecure_check_requirements", 10);

function vaptsecure_enforce_installation_limit()
{
    // [v3.2.3] Skip limit enforcement if domain matches (including wildcards)
    if (vaptsecure_is_domain_match()) {
        return;
    }

    if (
        !defined("VAPTSECURE_LICENSE_SCOPE") ||
        VAPTSECURE_LICENSE_SCOPE !== "multisite"
    ) {
        return;
    }

    if (
        defined("VAPTSECURE_LICENSE_TYPE") &&
        VAPTSECURE_LICENSE_TYPE === "developer_unbound"
    ) {
        return;
    }

    if (!defined("VAPTSECURE_DOMAIN_LIMIT")) {
        return;
    }

    $limit = (int) VAPTSECURE_DOMAIN_LIMIT;
    if ($limit <= 0) {
        return;
    }

    if (!is_multisite()) {
        return;
    }

    $count = get_transient("vaptsecure_network_site_count");
    if (!is_int($count)) {
        $sites = get_sites(["fields" => "ids", "number" => 0]);
        $count = is_array($sites) ? count($sites) : 0;
        set_transient(
            "vaptsecure_network_site_count",
            $count,
            6 * HOUR_IN_SECONDS,
        );
    }

    if ($count <= $limit) {
        if (get_option("vaptsecure_over_installation_limit")) {
            $prev = get_option("vaptsecure_global_protection_prev");
            if ($prev !== null) {
                update_option("vaptsecure_global_protection", (int) $prev);
            }
            delete_option("vaptsecure_over_installation_limit");
            delete_option("vaptsecure_global_protection_prev");
            delete_transient("vaptsecure_active_enforcements");
        }
        return;
    }

    if (!get_option("vaptsecure_over_installation_limit")) {
        $current = get_option("vaptsecure_global_protection", 1);
        update_option("vaptsecure_global_protection_prev", (int) $current);
        update_option("vaptsecure_over_installation_limit", 1);
    }

    update_option("vaptsecure_global_protection", 0);
    delete_transient("vaptsecure_active_enforcements");

    $notice_cb = function () use ($count, $limit) {
        $can_see =
            (function_exists("is_super_admin") && is_super_admin()) ||
            current_user_can("manage_network_options") ||
            current_user_can("manage_options");
        if (!$can_see) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>VAPTSecure Clean:</strong> This build is disabled because the Multi-Site installation limit was exceeded (' .
            esc_html((string) $count) .
            "/" .
            esc_html((string) $limit) .
            ").</p></div>";
    };

    add_action("admin_notices", $notice_cb);
    add_action("network_admin_notices", $notice_cb);

    add_action(
        "init",
        function () use ($count, $limit) {
            if (is_admin()) {
                return;
            }
            wp_die(
                "<h1>VAPTSecure Clean</h1><p>This build is disabled because the Multi-Site installation limit was exceeded (" .
                    esc_html((string) $count) .
                    "/" .
                    esc_html((string) $limit) .
                    ").</p>",
            );
        },
        0,
    );
}

/**
 * Initialize Global Services
 * Deferred to plugins_loaded to avoid DB access during activation.
 */
add_action("plugins_loaded", "vaptsecure_enforce_installation_limit", 0);
add_action("plugins_loaded", ["VAPTSECURE_Enforcer", "init"]);

/**
 * Instantiate service objects on plugins_loaded so their constructors can hook into WP.
 */
add_action("plugins_loaded", "vaptsecure_initialize_services");

/**
 * Service initialization callback.
 */
function vaptsecure_initialize_services()
{
    if (class_exists("VAPTSECURE_REST")) {
        new VAPTSECURE_REST();
    }
    if (class_exists("VAPTSECURE_Auth")) {
        // Auth may provide static helpers but instantiate to register hooks if needed
        new VAPTSECURE_Auth();
    }
    if (class_exists("VAPTSECURE_Admin")) {
        new VAPTSECURE_Admin();
    }
}

/**
 * Activation Hook: Initialize Database Tables
 */
register_activation_hook(__FILE__, "vaptsecure_activate_plugin");
register_deactivation_hook(__FILE__, ["VAPT_Lifecycle", "on_deactivate"]);
register_uninstall_hook(__FILE__, ["VAPT_Lifecycle", "on_uninstall"]);

add_action("vapt_feature_enabled", function ($id) {
    if (class_exists("VAPT_Self_Check")) {
        VAPT_Self_Check::run("feature_enable", ["feature_id" => $id]);
    }
});
add_action("vapt_feature_disabled", function ($id) {
    if (class_exists("VAPT_Self_Check")) {
        VAPT_Self_Check::run("feature_disable", ["feature_id" => $id]);
    }
});
add_action("vapt_license_expired", function () {
    if (class_exists("VAPT_Self_Check")) {
        VAPT_Self_Check::run("license_expire");
    }
});

function vaptsecure_activate_plugin()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    include_once ABSPATH . "wp-admin/includes/upgrade.php";
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
        user_notes TEXT DEFAULT NULL,
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
    if (!file_exists(VAPTSECURE_PATH . "data")) {
        wp_mkdir_p(VAPTSECURE_PATH . "data");
    }

    $existing_version = get_option("vaptsecure_version");
    if (empty($existing_version)) {
        vaptsecure_send_activation_email();
    }

    // Run manual DB fix to add missing columns
    vaptsecure_manual_db_fix();

    // Run Self-Check lifecycle instantiation
    if (class_exists("VAPT_Lifecycle")) {
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
    $table_name = $wpdb->prefix . "vaptsecure_feature_meta";

    // Check and add is_enabled if missing
    $column = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            "is_enabled",
        ),
    );
    if (empty($column)) {
        $wpdb->query(
            "ALTER TABLE $table_name ADD COLUMN is_enabled TINYINT(1) DEFAULT 0",
        );
    }

    // Check and add is_enforced if missing
    $column = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            "is_enforced",
        ),
    );
    if (empty($column)) {
        $wpdb->query(
            "ALTER TABLE $table_name ADD COLUMN is_enforced TINYINT(1) DEFAULT 0",
        );
    }

    // Check and add active_enforcer if missing
    $column = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            "active_enforcer",
        ),
    );
    if (empty($column)) {
        $wpdb->query(
            "ALTER TABLE $table_name ADD COLUMN active_enforcer VARCHAR(100) DEFAULT NULL",
        );
    }
}

/**
 * Send Activation Email
 * Notifies the superadmin when the plugin is activated on a new site.
 */
function vaptsecure_send_activation_email()
{
    $to = (string) get_option("admin_email");
    if (!$to) {
        return;
    }
    if (function_exists("is_email") && !is_email($to)) {
        return;
    }
    $site_name = get_bloginfo("name");
    $site_url = get_site_url();
    $admin_url = admin_url("admin.php?page=vaptsecure-domain-admin");

    $subject = sprintf("[VAPT Alert] Plugin Activated on %s", $site_name);
    $message = "VAPTSecure Clean has been activated on a new site.\n\n";
    $message .= "Site Name: $site_name\n";
    $message .= "Site URL: $site_url\n";
    $message .= "Activation Date: " . current_time("mysql") . "\n";
    $message .= "Access Dashboard: $admin_url\n\n";
    $message .= "This is an automated security notification.";

    $headers = ["Content-Type: text/plain; charset=UTF-8"];

    wp_mail($to, $subject, $message, $headers);
}

/**
 * Manual DB Fix Trigger (Force Run)
 */
add_action("init", "vaptsecure_manual_db_fix");

/**
 * Auto-update DB on version change
 */
add_action("init", "vaptsecure_auto_update_db");

/**
 * Logic to run database updates if version mismatch.
 */
function vaptsecure_auto_update_db()
{
    $saved_version = get_option("vaptsecure_version");
    if ($saved_version !== VAPTSECURE_VERSION) {
        vaptsecure_activate_plugin();
        update_option("vaptsecure_version", VAPTSECURE_VERSION);
    }
}

/**
 * Manual database schema fix.
 * Can be triggered via ?vaptsecure_fix_db=1.
 */
if (!function_exists("vaptsecure_manual_db_fix")) {
    function vaptsecure_manual_db_fix()
    {
        if (
            isset($_GET["vaptsecure_fix_db"]) &&
            current_user_can("manage_options")
        ) {
            include_once ABSPATH . "wp-admin/includes/upgrade.php";
            global $wpdb;
            // 1. Run standard dbDelta
            vaptsecure_activate_plugin();
            // 2. Force add column just in case dbDelta missed it
            $table = $wpdb->prefix . "vaptsecure_domains";
            $col = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    "manual_expiry_date",
                ),
            );
            if (empty($col)) {
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN manual_expiry_date DATETIME DEFAULT NULL",
                );
            }
            // 3. Migrate Status ENUM to Title Case
            $status_table = $wpdb->prefix . "vaptsecure_feature_status";
            $wpdb->query(
                "ALTER TABLE {$status_table} MODIFY COLUMN status ENUM('Draft', 'Develop', 'Release') DEFAULT 'Draft'",
            );
            // 4. Update existing lowercase statuses to Title Case
            $wpdb->query(
                "UPDATE {$status_table} SET status = 'Draft' WHERE status IN ('draft', 'available')",
            );
            $wpdb->query(
                "UPDATE {$status_table} SET status = 'Develop' WHERE status IN ('develop', 'in_progress', 'test', 'Test')",
            );
            $wpdb->query(
                "UPDATE {$status_table} SET status = 'Release' WHERE status IN ('release', 'implemented')",
            );
            // 5. Ensure wireframe_url column exists
            $meta_table = $wpdb->prefix . "vaptsecure_feature_meta";
            $meta_col = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "wireframe_url",
                ),
            );
            if (empty($meta_col)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN wireframe_url TEXT DEFAULT NULL",
                );
            }
            echo '<div class="notice notice-success"><p>Database migration complete. Statuses normalized to Draft, Develop, Release.</p></div>';
            // 4. Force drop is_enforced column (deprecated)
            $table_meta = $wpdb->prefix . "vaptsecure_feature_meta";
            $col_enforced = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_meta} LIKE %s",
                    "is_enforced",
                ),
            );
            if (!empty($col_enforced)) {
                $wpdb->query(
                    "ALTER TABLE {$table_meta} DROP COLUMN is_enforced",
                );
            }
            // 5. Force add assigned_to column
            $col_assigned = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$status_table} LIKE %s",
                    "assigned_to",
                ),
            );
            if (empty($col_assigned)) {
                $wpdb->query(
                    "ALTER TABLE {$status_table} ADD COLUMN assigned_to BIGINT(20) UNSIGNED DEFAULT NULL",
                );
            }
            // 3. Force add generated_schema column
            $col_schema = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "generated_schema",
                ),
            );
            if (empty($col_schema)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN generated_schema LONGTEXT DEFAULT NULL",
                );
            }
            $col_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "implementation_data",
                ),
            );
            if (empty($col_data)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN implementation_data LONGTEXT DEFAULT NULL",
                );
            }
            $col_verif = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "include_verification_engine",
                ),
            );
            if (empty($col_verif)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN include_verification_engine TINYINT(1) DEFAULT 0",
                );
            }
            $col_guidance = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "include_verification_guidance",
                ),
            );
            if (empty($col_guidance)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN include_verification_guidance TINYINT(1) DEFAULT 1",
                );
            }
            $col_proto = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "include_manual_protocol",
                ),
            );
            if (empty($col_proto)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN include_manual_protocol TINYINT(1) DEFAULT 1",
                );
            }
            $col_notes = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "include_operational_notes",
                ),
            );
            if (empty($col_notes)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN include_operational_notes TINYINT(1) DEFAULT 1",
                );
            }
            if (empty($col_dev)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN dev_instruct LONGTEXT DEFAULT NULL",
                );
            }
            $col_adaptive = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "is_adaptive_deployment",
                ),
            );
            if (empty($col_adaptive)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN is_adaptive_deployment TINYINT(1) DEFAULT 0",
                );
            }
            $col_ov_schema = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "override_schema",
                ),
            );
            if (empty($col_ov_schema)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN override_schema LONGTEXT DEFAULT NULL",
                );
            }
            $col_ov_impl = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$meta_table} LIKE %s",
                    "override_implementation_data",
                ),
            );
            if (empty($col_ov_impl)) {
                $wpdb->query(
                    "ALTER TABLE {$meta_table} ADD COLUMN override_implementation_data LONGTEXT DEFAULT NULL",
                );
            }
            $col_enabled = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    "is_enabled",
                ),
            );
            if (empty($col_enabled)) {
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN is_enabled TINYINT(1) DEFAULT 1",
                );
            }
            $col_id = $wpdb->get_results(
                $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", "id"),
            );
            if (empty($col_id)) {
                $wpdb->query("ALTER TABLE {$table} DROP PRIMARY KEY");
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)",
                );
            } else {
                $pk_check = $wpdb->get_row(
                    $wpdb->prepare(
                        "SHOW KEYS FROM {$table} WHERE Key_name = %s",
                        "PRIMARY",
                    ),
                );
                if (!$pk_check || $pk_check->Column_name !== "id") {
                    $wpdb->query("ALTER TABLE {$table} DROP PRIMARY KEY");
                    $wpdb->query(
                        "ALTER TABLE {$table} MODIFY COLUMN id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)",
                    );
                }
            }
            $col_scope = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    "license_scope",
                ),
            );
            if (empty($col_scope)) {
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN license_scope VARCHAR(50) DEFAULT 'single'",
                );
            }
            $col_limit = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    "installation_limit",
                ),
            );
            if (empty($col_limit)) {
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN installation_limit INT DEFAULT 1",
                );
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
            $domain_features_table =
                $wpdb->prefix . "vaptsecure_domain_features";
            $feature_status_table = $wpdb->prefix . "vaptsecure_feature_status";

            // Get all features that are NOT in Release state
            $non_release_features = $wpdb->get_col(
                "SELECT feature_key FROM {$feature_status_table} WHERE status != 'Release'",
            );

            if (!empty($non_release_features)) {
                $placeholders = array_fill(
                    0,
                    count($non_release_features),
                    "%s",
                );
                $placeholders_string = implode(", ", $placeholders);

                // Delete domain-feature relationships for non-release features
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$domain_features_table} WHERE feature_key IN ({$placeholders_string})",
                        $non_release_features,
                    ),
                );

                echo '<div class="notice notice-success"><p>Cleaned up domain-feature relationships: removed ' .
                    intval($deleted) .
                    " entries for features not in Release state.</p></div>";
            }

            $msg =
                "Database schema updated (History Table + Security Events + assigned_to + Status Enum + Manual Expiry + Generated Schema + Implementation Data + Domain Enabled + Robust ID column + License Scope + Inst. Limit + Removed is_enforced + Cleaned non-Release domain-feature relationships).";
            wp_die(
                sprintf(
                    "<h1>VAPTSecure Clean Database Updated</h1><p>Schema refresh run. %s</p><p>Please go back to the dashboard.</p>",
                    esc_html($msg),
                ),
            );
        }
    }
}

/**
 * Workbench Action Handler (Ajax-Alternative via GET)
 */
add_action("init", function () {
    if (
        isset($_GET["vaptsecure_action"]) &&
        current_user_can("manage_options")
    ) {
        $action = sanitize_text_field($_GET["vaptsecure_action"]);
        if ($action === "reset_rate_limits") {
            include_once VAPTSECURE_PATH .
                "includes/enforcers/class-vaptsecure-hook-driver.php";
            VAPTSECURE_Hook_Driver::reset_limit();
            wp_die("Rate limits reset successfully.", "VAPTSecure Clean Reset", [
                "response" => 200,
                "back_link" => true,
            ]);
        }
    }
});

/**
 * Detect Localhost Environment
 * Verified against standard localhost IP and hostnames.
 *
 * @return bool True if on localhost.
 */
if (!function_exists("is_vaptsecure_localhost")) {
    function is_vaptsecure_localhost()
    {
        $whitelist = ["127.0.0.1", "::1", "localhost"];
        $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "";
        $addr = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
        if (in_array($addr, $whitelist) || in_array($host, $whitelist)) {
            return true;
        }
        $dev_suffixes = [".local", ".test", ".dev", ".wp", ".site"];
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
add_action("admin_menu", "vaptsecure_add_admin_menu");

/**
 * Check Strict Permissions
 * Terminates execution if the current user is not a superadmin.
 */
if (!function_exists("vaptsecure_check_permissions")) {
    function vaptsecure_check_permissions($require_auth = false)
    {
        if (!is_vaptsecure_superadmin($require_auth)) {
            wp_die(
                __(
                    "You do not have permission to access the VAPTSecure Clean Dashboard.",
                    "vaptsecure",
                ),
            );
        }
    }
}

/**
 * Registers the VAPTSecure Clean and VAPT Domain Admin menu pages.
 */
if (!function_exists("vaptsecure_add_admin_menu")) {
    function vaptsecure_add_admin_menu()
    {
        $is_superadmin_identity = is_vaptsecure_superadmin(false);

        // 1. Parent Menu (Visible to all admins with manage_options)
        add_menu_page(
            __("VAPTSecure Clean", "vaptsecure"),
            __("VAPTSecure Clean", "vaptsecure"),
            "manage_options",
            "vaptsecure",
            "vaptsecure_render_client_status_page",
            "dashicons-shield",
            80,
        );

        if ($is_superadmin_identity) {
            // Sub-menu 1: Workbench
            add_submenu_page(
                "vaptsecure",
                __("VAPTSecure Workbench", "vaptsecure"),
                __("VAPTSecure Workbench", "vaptsecure"),
                "manage_options",
                "vaptsecure-workbench",
                "vaptsecure_render_workbench_page",
            );

            // Sub-menu 2: Domain Admin
            add_submenu_page(
                "vaptsecure",
                __("VAPTSecure Domain Admin", "vaptsecure"),
                __("VAPTSecure Domain Admin", "vaptsecure"),
                "manage_options",
                "vaptsecure-domain-admin",
                "vaptsecure_render_admin_page",
            );
        }

        // Remove the default duplicate submenu item created by WordPress
        remove_submenu_page("vaptsecure", "vaptsecure");
    }
}

/**
 * Handle Legacy Slug Redirects
 */
add_action("admin_init", "vaptsecure_handle_legacy_redirects");
if (!function_exists("vaptsecure_handle_legacy_redirects")) {
    function vaptsecure_handle_legacy_redirects()
    {
        if (!isset($_GET["page"])) {
            return;
        }
        $legacy_slugs = [
            "vapt-secure",
            "vapt-domain-admin",
            "vapt-copilot",
            "vapt-copilot-main",
            "vapt-copilot-status",
            "vapt-copilot-domain-build",
            "vapt-client",
        ];
        if (in_array($_GET["page"], $legacy_slugs)) {
            $target =
                $_GET["page"] === "vapt-domain-admin"
                    ? "vaptsecure-domain-admin"
                    : "vaptsecure";
            wp_safe_redirect(admin_url("admin.php?page=" . $target));
            exit();
        }
    }
}

/**
 * Localhost Admin Notice
 */

/**
 * Render Client Status Page
 */
if (!function_exists("vaptsecure_render_client_status_page")) {
    function vaptsecure_render_client_status_page()
    {
        ?>
    <div class="wrap">
      <div id="vapt-client-root">
        <div style="padding: 40px; text-align: center; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
          <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
          <p><?php _e(
              "Loading Implementation Workbench...",
              "vaptsecure",
          ); ?></p>
        </div>
      </div>
    </div>
        <?php
    }
}

/**
 * Render Superadmin Workbench Page
 */
if (!function_exists("vaptsecure_render_workbench_page")) {
    function vaptsecure_render_workbench_page()
    {
        if (!is_vaptsecure_superadmin(true)) {
            if (is_vaptsecure_superadmin(false)) {
                $user_id = get_current_user_id();
                if (
                    !$user_id ||
                    !get_transient("vaptsecure_otp_email_" . $user_id)
                ) {
                    VAPTSECURE_Auth::send_otp();
                }
                VAPTSECURE_Auth::render_otp_form();
            } else {
                wp_die(
                    __(
                        "You do not have permission to access the VAPTSecure Clean Dashboard.",
                        "vaptsecure",
                    ),
                );
            }
            return;
        } ?>
    <div class="wrap">
      <h1 class="wp-heading-inline"><?php _e(
          "VAPTSecure Clean Workbench",
          "vaptsecure",
      ); ?></h1>
      <hr class="wp-header-end" />
      <div id="vapt-workbench-root">
        <div style="padding: 40px; text-align: center; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
          <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
          <p><?php _e("Loading Superadmin Workbench...", "vaptsecure"); ?></p>
        </div>
      </div>
    </div>
        <?php
    }
}

if (!function_exists("vaptsecure_render_admin_page")) {
    function vaptsecure_render_admin_page()
    {
        vaptsecure_master_dashboard_page();
    }
}

if (!function_exists("vaptsecure_master_dashboard_page")) {
    function vaptsecure_master_dashboard_page()
    {
        // Verify Strict Identity AND Session
        if (!is_vaptsecure_superadmin(true)) {
            // If they match identity but lack auth, show OTP.
            // If they don't match identity, they already failed is_vaptsecure_superadmin() and was blocked by parent layer?
            // Actually vaptsecure_add_admin_menu blocks them from seeing it.
            // But if they access via direct URL, this check will trigger.

            if (is_vaptsecure_superadmin(false)) {
                // Identity matches, but needs auth.
                $user_id = get_current_user_id();
                if (
                    !$user_id ||
                    !get_transient("vaptsecure_otp_email_" . $user_id)
                ) {
                    VAPTSECURE_Auth::send_otp();
                }
                VAPTSECURE_Auth::render_otp_form();
            } else {
                // Identity DOES NOT match. Hard block.
                wp_die(
                    __(
                        "You do not have permission to access the VAPTSecure Clean Dashboard.",
                        "vaptsecure",
                    ),
                );
            }
            return;
        } ?>
    <div id="vapt-admin-root" class="wrap">
      <h1><?php _e("VAPTSecure Domain Admin", "vaptsecure"); ?></h1>
      <div style="padding: 20px; text-align: center;">
        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
        <p><?php _e("Loading VAPTSecure Clean...", "vaptsecure"); ?></p>
      </div>
    </div>
        <?php
    }
}

/**
 * Enqueue Admin Assets
 */
add_action("admin_enqueue_scripts", "vaptsecure_enqueue_admin_assets");

/**
 * Enqueue Assets for React App
 */
function vaptsecure_enqueue_admin_assets($hook)
{
    global $vaptsecure_hooks;
    $GLOBALS["vaptsecure_current_hook"] = $hook;
    $screen = get_current_screen();
    $current_user = wp_get_current_user();
    $is_superadmin = is_vaptsecure_superadmin();
    if (!$screen) {
        return;
    }
    // Enqueue Shared Styles
    wp_enqueue_style(
        "vapt-admin-css",
        VAPTSECURE_URL . "assets/css/admin.css",
        ["wp-components"],
        VAPTSECURE_VERSION,
    );
    // 1. Superadmin Dashboard (admin.js)
    if (
        $screen->id === "toplevel_page_vaptsecure-domain-admin" ||
        $screen->id === "vaptsecure_page_vaptsecure-domain-admin" ||
        strpos($screen->id, "vaptsecure-domain-admin") !== false
    ) {
        if (!VAPTSECURE_Auth::is_authenticated()) {
            return; // Do not enqueue heavy React apps if OTP is pending
        }

        error_log("VAPT Admin Assets Enqueued for: " . $screen->id);
        wp_enqueue_script(
            "vapt-admin-logger",
            plugin_dir_url(__FILE__) . "assets/js/admin-modules/logger.js",
            [],
            VAPTSECURE_VERSION,
            true,
        );
        wp_enqueue_script(
            "vapt-admin-api-fetch-hotpatch",
            plugin_dir_url(__FILE__) .
                "assets/js/admin-modules/api-fetch-hotpatch.js",
            ["wp-api-fetch", "vapt-admin-logger"],
            VAPTSECURE_VERSION,
            true,
        );
        wp_enqueue_script(
            "vapt-admin-modals",
            plugin_dir_url(__FILE__) . "assets/js/admin-modules/modals.js",
            ["wp-element", "wp-components", "wp-i18n", "vapt-admin-logger"],
            VAPTSECURE_VERSION,
            true,
        );
        wp_enqueue_script(
            "vapt-admin-field-mapping",
            plugin_dir_url(__FILE__) .
                "assets/js/admin-modules/field-mapping.js",
            ["wp-element", "wp-components", "wp-i18n", "vapt-admin-logger"],
            VAPTSECURE_VERSION,
            true,
        );
        wp_enqueue_script(
            "vapt-admin-design-modal",
            plugin_dir_url(__FILE__) .
                "assets/js/admin-modules/design-modal.js",
            [
                "wp-element",
                "wp-components",
                "wp-i18n",
                "wp-api-fetch",
                "vapt-admin-logger",
                "vapt-admin-api-fetch-hotpatch",
                "vapt-admin-modals",
                "vapt-admin-field-mapping",
            ],
            VAPTSECURE_VERSION,
            true,
        );
        wp_enqueue_script(
            "vapt-admin-domains",
            plugin_dir_url(__FILE__) . "assets/js/admin-modules/domains.js",
            ["wp-element", "wp-components", "wp-i18n", "vapt-admin-logger"],
            VAPTSECURE_VERSION,
            true,
        );
        // Enqueue Auto-Interface Generator (Module)
        wp_enqueue_script(
            "vapt-interface-generator",
            plugin_dir_url(__FILE__) .
                "assets/js/modules/interface-generator.js",
            [], // No deps, but strictly before admin.js
            VAPTSECURE_VERSION,
            true,
        );
        // Enqueue A+ Adaptive Generator (Module)
        wp_enqueue_script(
            "vapt-aplus-generator",
            plugin_dir_url(__FILE__) . "assets/js/modules/aplus-generator.js",
            [],
            VAPTSECURE_VERSION,
            true,
        );
        // Enqueue Generated Interface UI Component
        wp_enqueue_script(
            "vapt-generated-interface-ui",
            plugin_dir_url(__FILE__) .
                "assets/js/modules/generated-interface.js",
            ["wp-element", "wp-components", "wp-i18n"],
            VAPTSECURE_VERSION,
            true,
        );
        // Enqueue Admin Dashboard Script
        wp_enqueue_script(
            "vapt-admin-js",
            plugin_dir_url(__FILE__) . "assets/js/admin.js",
            [
                "wp-element",
                "wp-components",
                "wp-api-fetch",
                "wp-i18n",
                "vapt-admin-logger",
                "vapt-admin-api-fetch-hotpatch",
                "vapt-admin-modals",
                "vapt-admin-field-mapping",
                "vapt-admin-design-modal",
                "vapt-admin-domains",
                "vapt-interface-generator",
                "vapt-aplus-generator",
                "vapt-generated-interface-ui",
            ],
            VAPTSECURE_VERSION,
            true,
        );
    }

    // Common Settings Localization
    $localized_settings = [
        "root" => esc_url_raw(rest_url()),
        "nonce" => wp_create_nonce("wp_rest"),
        "domainLocked" => defined("VAPTSECURE_DOMAIN_LOCKED")
            ? VAPTSECURE_DOMAIN_LOCKED
            : false,
        "buildVersion" => VAPTSECURE_VERSION,
        "activeData" => VAPTSECURE_ACTIVE_DATA_FILE,
    ];
    $vapt_settings = [
        "isSuper" => $is_superadmin,
        "pluginVersion" => defined("VAPTSECURE_BUILD_VERSION")
            ? VAPTSECURE_BUILD_VERSION
            : VAPTSECURE_VERSION,
        "pluginName" => "VAPTSecure Clean",
        "currentDomain" => parse_url(home_url(), PHP_URL_HOST),
        "adminEmail" => get_option("admin_email"),
        "abspath" => ABSPATH,
        "pluginPath" => VAPTSECURE_PATH,
        "uploadPath" => wp_upload_dir()["basedir"],
        "buildAt" => defined("VAPTSECURE_BUILD_AT")
            ? VAPTSECURE_BUILD_AT
            : null,
    ];

    $home_url = esc_url_raw(home_url());
    $inline_patch = "
    (function() {
      if (typeof wp === 'undefined' || !wp.apiFetch) return;
      if (wp.apiFetch.__vaptsecure_patched) return;

      try {
        localStorage.removeItem('vaptsecure_rest_broken');
      } catch (e) { }

      const originalApiFetch = wp.apiFetch;

      const patchedApiFetch = (args) => {
        // Get nonce from WordPress standard wpApiSettings or custom vaptSecureSettings
        const wpNonce = (window.wpApiSettings && window.wpApiSettings.nonce) || '';
        const vaptNonce = (window.vaptSecureSettings && window.vaptSecureSettings.nonce) || '';
        const effectiveNonce = wpNonce || vaptNonce;

        const home = '{$home_url}';

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

        // on all endpoints when only one endpoint was broken.
        // Fallbacks will only occur per-request dynamically.

        return originalApiFetch(args).catch(err => {
          const status = err.status || (err.data && err.data.status);
          const isFallbackTrigger = status === 404 || status === 403 || err.code === 'rest_no_route' || err.code === 'invalid_json';

          if (isFallbackTrigger && (args.path || args.url) && home) {
            const fallbackUrl = getFallbackUrl(args.path || args.url);
            if (!fallbackUrl) throw err;

            // Notice: We are trying fallback dynamically, but NOT saving it to localStorage
            console.warn('VAPTSecure Clean: Original API request failed, attempting fallback (?rest_route=...).');

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
      console.log('VAPTSecure Clean: Persistent Global REST Hotpatch Active (v3.8.17)');
    })();
  ";
    wp_add_inline_script("wp-api-fetch", $inline_patch);

    if (
        $screen->id === "toplevel_page_vaptsecure-domain-admin" ||
        $screen->id === "vaptsecure_page_vaptsecure-domain-admin" ||
        strpos($screen->id, "vaptsecure-domain-admin") !== false
    ) {
        wp_localize_script(
            "vapt-admin-js",
            "vaptSecureSettings",
            $vapt_settings,
        );
    }
    // 2. Shared: Generated Interface UI Component
    if (
        vaptsecure_is_client_dashboard_screen($screen->id) ||
        strpos($screen->id, "vaptsecure-workbench") !== false
    ) {
        wp_enqueue_script(
            "vapt-generated-interface-ui",
            plugin_dir_url(__FILE__) .
                "assets/js/modules/generated-interface.js",
            ["wp-element", "wp-components", "wp-i18n"],
            VAPTSECURE_VERSION,
            true,
        );
    }

    // 2a. Client Dashboard (client.js) - WordPress Admin view - "VAPTSecure Clean" page (Release features only)
    if (vaptsecure_is_client_dashboard_screen($screen->id)) {
        wp_enqueue_script(
            "vapt-client-js",
            plugin_dir_url(__FILE__) . "assets/js/client.js",
            [
                "wp-element",
                "wp-components",
                "wp-api-fetch",
                "wp-i18n",
                "vapt-generated-interface-ui",
            ],
            VAPTSECURE_VERSION,
            true,
        );
        wp_localize_script(
            "vapt-client-js",
            "vaptSecureSettings",
            $vapt_settings,
        );
    }

    // 2b. Superadmin Workbench (workbench.js) - "VAPTSecure Clean Workbench" page (All features, unscoped)
    if (strpos($screen->id, "vaptsecure-workbench") !== false) {
        wp_enqueue_script(
            "vapt-workbench-js",
            plugin_dir_url(__FILE__) . "assets/js/workbench.js",
            [
                "wp-element",
                "wp-components",
                "wp-api-fetch",
                "wp-i18n",
                "vapt-generated-interface-ui",
            ],
            VAPTSECURE_VERSION,
            true,
        );
        wp_localize_script(
            "vapt-workbench-js",
            "vaptSecureSettings",
            $vapt_settings,
        );
    }
}
?>
