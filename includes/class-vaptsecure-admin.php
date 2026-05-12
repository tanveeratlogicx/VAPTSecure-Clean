<?php

/**
 * VAPT Admin Interface
 */

if (! defined('ABSPATH')) {
    exit;
}

class VAPTSECURE_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'show_nginx_notice'));
        add_action('admin_init', array($this, 'maybe_run_background_verification'));
    }

    /**
     * [v4.0.x] Continuous Self-Healing: Run background verification on admin pages.
     * If a feature is enabled but its rules are missing from target files, re-inject them.
     */
    public function maybe_run_background_verification()
    {
        // Only run on VAPT admin pages to avoid overhead elsewhere
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->id, 'vapt') === false) {
            return;
        }

        // Rate limit: once per 5 minutes per admin session
        $transient_key = 'vapt_bg_verify_' . get_current_user_id();
        if (get_transient($transient_key)) {
            return;
        }
        set_transient($transient_key, time(), 5 * MINUTE_IN_SECONDS);

        if (class_exists('VAPTSECURE_Enforcer') && method_exists('VAPTSECURE_Enforcer', 'run_background_verification')) {
            $healed = VAPTSECURE_Enforcer::run_background_verification();
            if (!empty($healed)) {
                error_log('VAPT ADMIN: Background verification healed features: ' . implode(', ', $healed));
            }
        }
    }

    public function show_nginx_notice()
    {
        if (!is_vaptsecure_superadmin()) { return;
        }

        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';
        if (strpos($server, 'nginx') === false) { return;
        }

        $upload_dir = wp_upload_dir();
        $rules_file = $upload_dir['basedir'] . '/vapt-nginx-rules.conf';

        if (file_exists($rules_file)) {
            $include_path = $rules_file;
            ?>
      <div class="notice notice-info is-dismissible">
        <p><strong>VAPT Nginx Configuration (Action Required)</strong></p>
        <p>To apply VAPT security rules on Nginx, you must include the generated rules file in your main <code>nginx.conf</code> server block:</p>
        <code style="display:block; padding:10px; background:#fff; margin:5px 0;">include <?php echo esc_html($include_path); ?>;</code>
        <p><em>After adding this line, restart Nginx to apply changes.</em></p>
      </div>
            <?php
        }
    }



    public function add_admin_menu()
    {
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_vapt-auditor' && $hook !== 'vaptsecure_page_vapt-auditor') {
            return;
        }

        // 1. Enqueue Dependencies
        wp_enqueue_script('vapt-admin-logger', VAPTSECURE_URL . 'assets/js/admin-modules/logger.js', array(), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-admin-api-fetch-hotpatch', VAPTSECURE_URL . 'assets/js/admin-modules/api-fetch-hotpatch.js', array('wp-api-fetch', 'vapt-admin-logger'), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-admin-modals', VAPTSECURE_URL . 'assets/js/admin-modules/modals.js', array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-admin-field-mapping', VAPTSECURE_URL . 'assets/js/admin-modules/field-mapping.js', array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-admin-design-modal', VAPTSECURE_URL . 'assets/js/admin-modules/design-modal.js', array('wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'vapt-admin-logger', 'vapt-admin-api-fetch-hotpatch', 'vapt-admin-modals', 'vapt-admin-field-mapping'), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-admin-domains', VAPTSECURE_URL . 'assets/js/admin-modules/domains.js', array('wp-element', 'wp-components', 'wp-i18n', 'vapt-admin-logger'), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-interface-generator', VAPTSECURE_URL . 'assets/js/modules/interface-generator.js', array(), VAPTSECURE_VERSION, true);
        wp_enqueue_script('vapt-generated-interface-ui', VAPTSECURE_URL . 'assets/js/modules/generated-interface.js', array('wp-element', 'wp-components'), VAPTSECURE_VERSION, true);

        // 2. Enqueue Admin Dashboard Script with full dependency block
        wp_enqueue_script(
            'vapt-admin-js',
            VAPTSECURE_URL . 'assets/js/admin.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'vapt-admin-logger', 'vapt-admin-api-fetch-hotpatch', 'vapt-admin-modals', 'vapt-admin-field-mapping', 'vapt-admin-design-modal', 'vapt-admin-domains', 'vapt-interface-generator', 'vapt-generated-interface-ui'),
            VAPTSECURE_VERSION,
            true
        );

        wp_enqueue_style('vapt-admin-css', VAPTSECURE_URL . 'assets/css/admin.css', array('wp-components'), VAPTSECURE_VERSION);

        wp_localize_script(
            'vapt-admin-js', 'vaptSecureSettings', array(
            'root' => esc_url_raw(rest_url()),
            'homeUrl' => esc_url_raw(home_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'isSuper' => is_vaptsecure_superadmin(),
            'pluginVersion' => VAPTSECURE_VERSION
            )
        );

        wp_localize_script(
            'vapt-admin-js', 'vaptsecure_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vaptsecure_scan_nonce')
            )
        );
    }


    public function admin_page()
    {
        wp_die(__('The VAPT Auditor has been removed.', 'vaptsecure'));
    }
}
