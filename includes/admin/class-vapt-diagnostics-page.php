<?php
if (!defined('ABSPATH')) {
    exit;
}

class VAPT_Diagnostics_Page {

    public static function init(): void {
        add_action('admin_menu', [ __CLASS__, 'register_menu' ]);
        add_action('wp_ajax_vapt_run_diagnostics', [ __CLASS__, 'ajax_run_diagnostics' ]);
    }

    public static function register_menu(): void {
        if (!function_exists('is_vaptsecure_superadmin') || !is_vaptsecure_superadmin(true)) {
            return;
        }
        add_submenu_page(
            'vaptsecure',
            'Diagnostics & Self-Check',
            'Diagnostics',
            'manage_options',
            'vaptsecure-diagnostics',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if (!function_exists('is_vaptsecure_superadmin') || !is_vaptsecure_superadmin(true)) {
            wp_die('Unauthorized');
        }
        if ( ! current_user_can('manage_options') ) {
            wp_die('Unauthorized');
        }
        ?>
        <div class="wrap">
            <h1>VAPTSecure Diagnostics</h1>
            <p>Run a full self-check to validate .htaccess integrity, WordPress endpoints,
               feature consistency, file permissions, and license state.</p>

            <button id="vapt-run-check" class="button button-primary">
                Run Diagnostics Now
            </button>

            <div id="vapt-check-results" style="margin-top:20px;"></div>

            <h2>Audit Log</h2>
            <?php self::render_audit_log(); ?>
        </div>

        <script>
        document.getElementById('vapt-run-check').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Running…';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=vapt_run_diagnostics&nonce=<?php echo wp_create_nonce('vapt_diagnostics'); ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('vapt-check-results').innerHTML = data.data.html;
                } else {
                    document.getElementById('vapt-check-results').innerHTML = '<div class="notice notice-error"><p>Error: ' + (data.data || 'Unknown error occurred') + '</p></div>';
                }
                btn.disabled   = false;
                btn.textContent = 'Run Diagnostics Now';
            })
            .catch(err => {
                document.getElementById('vapt-check-results').innerHTML = '<div class="notice notice-error"><p>Server Error: Request failed. Ensure the plugin is fully activated or check debug logs.</p></div>';
                btn.disabled   = false;
                btn.textContent = 'Run Diagnostics Now';
            });
        });
        </script>
        <?php
    }

    public static function ajax_run_diagnostics(): void {
        check_ajax_referer('vapt_diagnostics', 'nonce');

        if (!function_exists('is_vaptsecure_superadmin') || !is_vaptsecure_superadmin(true)) {
            wp_send_json_error('Unauthorized');
        }
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized');
        }

        try {
            if(!class_exists('VAPT_Self_Check')) {
                wp_send_json_error('Self-check engine is not loaded.');
            }

            // Auto-heal: Ensure DB table exists if skipped
            global $wpdb;
            if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}vapt_audit_log'" ) ) {
                if ( class_exists( 'VAPT_Lifecycle' ) ) {
                    VAPT_Lifecycle::on_activate();
                }
            }

            $result = VAPT_Self_Check::run('manual_trigger', [
                'requested_by' => get_current_user_id() ? get_current_user_id() : 0,
                'checks'       => ['all'],
            ]);

            $status_class = 'notice-info';
            switch ( $result->get_overall_status() ) {
                case 'pass':
                    $status_class = 'notice-success';
                    break;
                case 'warning':
                    $status_class = 'notice-warning';
                    break;
                case 'fail':
                    $status_class = 'notice-error';
                    break;
            }

            $html  = "<div class='notice {$status_class}'>";
            $html .= "<p><strong>Overall: " . strtoupper($result->get_overall_status()) . "</strong> — ";
            $html .= "Passed: {$result->get_passed_count()}, ";
            $html .= "Failed: {$result->get_failed_count()}, ";
            $html .= "Warnings: {$result->get_warning_count()}, ";
            $html .= "Corrections Applied: " . count($result->get_applied_corrections()) . "</p>";
            $html .= "</div>";

            foreach ( $result->get_all_results() as $item ) {
                $icon  = $item['status'] === 'pass' ? '✅' : ($item['status'] === 'warning' ? '⚠️' : '❌');
                $html .= "<p>{$icon} <strong>{$item['check_id']}</strong>: {$item['message']}</p>";
            }

            wp_send_json_success([ 'html' => $html ]);
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    private static function render_audit_log(): void {
        global $wpdb;

        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vapt_audit_log'");
        if(!$tableExists) {
            echo '<p>Audit log table missing. Please wait for engine activation.</p>';
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}vapt_audit_log ORDER BY timestamp DESC LIMIT 50"
        );
        if ( empty($rows) ) {
            echo '<p>No audit log entries yet.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Time</th><th>Trigger</th><th>Status</th><th>Passed</th><th>Failed</th><th>Corrections</th>';
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $status_color = $row->overall_status === 'pass' ? 'green'
                          : ($row->overall_status === 'warning' ? 'orange' : 'red');
            echo "<tr>
                <td>{$row->timestamp}</td>
                <td><code>{$row->trigger_event}</code></td>
                <td style='color:{$status_color}'><strong>{$row->overall_status}</strong></td>
                <td>{$row->checks_passed}</td>
                <td>{$row->checks_failed}</td>
                <td>{$row->corrections_applied}</td>
            </tr>";
        }
        echo '</tbody></table>';
    }
}

add_action('init', ['VAPT_Diagnostics_Page', 'init']);
