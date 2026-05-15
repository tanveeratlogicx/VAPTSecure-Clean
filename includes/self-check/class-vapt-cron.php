<?php
if (!defined('ABSPATH')) {
    exit;
}

class VAPT_Cron {

    /**
     * Register all VAPTSecure scheduled events on plugin activation
     */
    public static function register(): void {
        // Daily health check — fires every 24 hours
        if ( ! wp_next_scheduled('vapt_daily_self_check') ) {
            wp_schedule_event( time(), 'daily', 'vapt_daily_self_check' );
        }

        // Bundle drift check — keeps runtime cache aligned with the live data bundle
        if ( ! wp_next_scheduled('vapt_bundle_drift_check') ) {
            wp_schedule_event( time(), 'daily', 'vapt_bundle_drift_check' );
        }

        // License validation — fires every 12 hours
        if ( ! wp_next_scheduled('vapt_license_check') ) {
            wp_schedule_event( time(), 'twicedaily', 'vapt_license_check' );
        }
    }

    /**
     * Remove all scheduled events on plugin deactivation
     */
    public static function deregister(): void {
        wp_clear_scheduled_hook('vapt_daily_self_check');
        wp_clear_scheduled_hook('vapt_bundle_drift_check');
        wp_clear_scheduled_hook('vapt_license_check');
    }

    /**
     * Wire up cron action callbacks
     * Called once during plugin bootstrap
     */
    public static function init(): void {
        add_action('vapt_daily_self_check', [ __CLASS__, 'run_daily_health_check' ] );
        add_action('vapt_bundle_drift_check', [ __CLASS__, 'run_bundle_drift_check' ] );
        add_action('vapt_license_check',    [ __CLASS__, 'run_license_check'       ] );
    }

    public static function run_daily_health_check(): void {
        if(class_exists('VAPT_Self_Check')) {
            VAPT_Self_Check::run('daily_health_check');
        }
    }

    public static function run_bundle_drift_check(): void {
        // [SSoT v1.0] Trigger full rehydration when drift is detected on cron
        if ( class_exists('VAPTSECURE_DB') && VAPTSECURE_DB::bundle_is_stale() ) {
            if ( class_exists('VAPTSECURE_Enforcer') ) {
                VAPTSECURE_Enforcer::rehydrate_all_stale_meta();
            } else {
                VAPTSECURE_DB::sync_bundle_fingerprint();
            }
        }

        if ( class_exists('VAPT_Self_Check') ) {
            VAPT_Self_Check::run('bundle_drift_check');
        }
    }

    public static function run_license_check(): void {
        $status = get_option('vapt_license_status');
        $expiry = get_option('vapt_license_expiry');

        if ( $expiry && strtotime($expiry) < time() && $status !== 'expired' ) {
            update_option('vapt_license_status', 'expired');
            do_action('vapt_license_expired');
        }
    }
}
