<?php

/**
 * VAPTSECURE_License_Manager: Handles license validation, expiration, and restoration
 *
 * Manages the lifecycle of domain licenses including:
 * - License validation on plugin load
 * - Graceful degradation when license expires
 * - Settings preservation using WordPress transients
 * - Automatic restoration when license is renewed
 */

if (!defined("ABSPATH")) {
    exit();
}

class VAPTSECURE_License_Manager
{
    const CACHE_PREFIX = "vaptsecure_license_cache_";
    const EXPIRY_WARNING_DAYS = 14;
    const GRACE_PERIOD = 1296000; // 15 days in seconds (15 * 24 * 60 * 60)
    const CACHE_DURATION = 30 * DAY_IN_SECONDS; // 30 days

    /**
     * Initialize the license manager
     */
    public static function init()
    {
        // Check license on every admin page load
        add_action("admin_init", [__CLASS__, "check_license_status"]);

        // Check license on frontend (less frequently for performance)
        if (!is_admin()) {
            add_action("init", [__CLASS__, "check_license_status"], 5);
        }

        // Add admin notice for expired licenses
        add_action("admin_notices", [__CLASS__, "admin_notices"]);

        // AJAX handler for manual restore
        add_action("wp_ajax_vaptsecure_restore_from_cache", [
            __CLASS__,
            "ajax_restore_from_cache",
        ]);
    }

    /**
     * Check license status for current domain
     *
     * @return string Status: 'valid', 'expired', 'renewed', 'invalid', 'not_found'
     */
    public static function check_license_status()
    {
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        if (empty($domain)) {
            return "not_found";
        }

        // Check if we're in grace period (license was previously expired)
        $in_grace = get_transient(self::CACHE_PREFIX . $domain . "_grace");
        if ($in_grace) {
            // Check if license has been renewed
            $status = self::check_domain_license($domain);
            if ($status === "valid") {
                // License renewed! Restore from cache
                self::restore_from_cache($domain);
            }
            return $status;
        }

        // Normal license check
        $status = self::check_domain_license($domain);
        if ($status === "expired") {
            self::handle_expired_license($domain);
        }

        return $status;
    }

    /**
     * Validate license for a specific domain
     *
     * @param string $domain Domain name to check
     * @return string Status: 'valid', 'expired', 'invalid', 'not_found'
     */
    public static function check_domain_license($domain)
    {
        global $wpdb;

        $table = $wpdb->prefix . "vaptsecure_domains";

        // Check if domain exists
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE domain = %s", $domain),
        );

        if (!$row) {
            return "not_found";
        }

        // Check if domain is enabled
        if (!$row->is_enabled) {
            return "invalid";
        }

        // Check expiration date
        if (
            empty($row->manual_expiry_date) ||
            $row->manual_expiry_date === "0000-00-00 00:00:00"
        ) {
            // No expiry date means perpetual license
            return "valid";
        }

        $expiry_ts = strtotime($row->manual_expiry_date);
        $today_ts = strtotime(date("Y-m-d 00:00:00"));

        if ($expiry_ts < $today_ts) {
            // License has expired - check auto-renewal
            if ($row->auto_renew) {
                $renewed = self::auto_renew_license($domain, $row);
                if ($renewed) {
                    return "renewed";
                }
            }
            return "expired";
        }

        return "valid";
    }

    /**
     * Auto-renew license if enabled
     *
     * @param string $domain Domain name
     * @param object $domain_row Domain database row
     * @return bool True if renewal was successful
     */
    private static function auto_renew_license($domain, $domain_row)
    {
        global $wpdb;

        $license_type = $domain_row->license_type ?: "standard";

        // Determine renewal duration based on license type
        $duration = "+30 days";
        $days = 30;

        if ($license_type === "pro") {
            $duration = "+1 year";
            $days = 365;
        } elseif (
            $license_type === "developer" ||
            $license_type === "developer_unbound"
        ) {
            $duration = "+100 years";
            $days = 36500;
        } elseif ($license_type === "7-day-trial") {
            $duration = "+7 days";
            $days = 7;
        } elseif ($license_type === "15-day-demo") {
            $duration = "+15 days";
            $days = 15;
        }

        // Calculate new expiry date
        $current_expiry = strtotime($domain_row->manual_expiry_date);
        $new_expiry = strtotime($duration, $current_expiry);
        $new_expiry_date = date("Y-m-d 00:00:00", $new_expiry);

        // Update database
        $result = $wpdb->update(
            $wpdb->prefix . "vaptsecure_domains",
            [
                "manual_expiry_date" => $new_expiry_date,
                "renewals_count" => $domain_row->renewals_count + 1,
            ],
            ["domain" => $domain],
            ["%s", "%d"],
            ["%s"],
        );

        if ($result !== false) {
            // Update renewal history
            $history = !empty($domain_row->renewal_history)
                ? json_decode($domain_row->renewal_history, true)
                : [];
            $history[] = [
                "date_added" => current_time("mysql"),
                "duration_days" => $days,
                "license_type" => $license_type,
                "source" => "auto",
            ];

            $wpdb->update(
                $wpdb->prefix . "vaptsecure_domains",
                ["renewal_history" => json_encode($history)],
                ["domain" => $domain],
                ["%s"],
                ["%s"],
            );

            return true;
        }

        return false;
    }

    /**
     * Handle expired license - save settings and remove protections
     *
     * @param string $domain Domain name
     */
    public static function handle_expired_license($domain)
    {
        // Check if already handled (prevent duplicate processing)
        $already_handled = get_transient(
            self::CACHE_PREFIX . $domain . "_expired_handled",
        );
        if ($already_handled) {
            return;
        }

        // 1. Save current settings to transient cache
        self::save_settings_to_cache($domain);

        // 2. Remove all protections
        self::remove_all_protections($domain);

        // 3. Set grace period flag
        set_transient(
            self::CACHE_PREFIX . $domain . "_grace",
            true,
            self::GRACE_PERIOD,
        );

        // 4. Mark as handled to prevent duplicate processing
        set_transient(
            self::CACHE_PREFIX . $domain . "_expired_handled",
            true,
            self::GRACE_PERIOD,
        );

        // 5. Log the event
        error_log(
            sprintf(
                "[VAPTSecure Clean] License expired for domain %s. Settings saved to cache, protections removed.",
                $domain,
            ),
        );
    }

    /**
     * Save current settings to transient cache
     *
     * @param string $domain Domain name
     */
    private static function save_settings_to_cache($domain)
    {
        global $wpdb;

        // Get domain ID
        $domain_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s",
                $domain,
            ),
        );

        if (!$domain_id) {
            return;
        }

        // Get all domain features
        $features = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT feature_key, enabled FROM {$wpdb->prefix}vaptsecure_domain_features WHERE domain_id = %d",
                $domain_id,
            ),
            ARRAY_A,
        );

        // Get current enforcement state
        $enforcement_state = get_option("vaptsecure_global_protection", 1);

        // Get all feature meta for restoration
        $feature_meta = $wpdb->get_results(
            "SELECT feature_key, is_enabled, generated_schema, implementation_data, override_schema, override_implementation_data, is_adaptive_deployment
             FROM {$wpdb->prefix}vaptsecure_feature_meta",
            ARRAY_A,
        );

        // Get feature status
        $feature_status = $wpdb->get_results(
            "SELECT feature_key, status FROM {$wpdb->prefix}vaptsecure_feature_status",
            ARRAY_A,
        );

        // Compile cache data
        $cache_data = [
            "features" => $features,
            "enforcement_state" => $enforcement_state,
            "feature_meta" => $feature_meta,
            "feature_status" => $feature_status,
            "saved_at" => current_time("mysql"),
            "domain" => $domain,
            "domain_id" => $domain_id,
        ];

        // Save to transient
        set_transient(
            self::CACHE_PREFIX . $domain . "_settings",
            $cache_data,
            self::CACHE_DURATION,
        );
    }

    /**
     * Remove all VAPT protections from the site
     *
     * @param string $domain Domain name
     */
    private static function remove_all_protections($domain)
    {
        // 1. Disable global enforcement
        update_option("vaptsecure_global_protection", 0);

        // 2. Clear enforcement cache
        delete_transient("vaptsecure_active_enforcements");

        // 3. Clean all configuration files using shared utility
        if (class_exists("VAPTSECURE_Config_Cleaner")) {
            VAPTSECURE_Config_Cleaner::clean_all();
        } else {
            // Fallback: Use enforcer's clean method if available
            if (class_exists("VAPTSECURE_Enforcer")) {
                VAPTSECURE_Enforcer::clean_all_config_files();
            }
        }

        // 4. Log the removal
        error_log(
            sprintf(
                "[VAPTSecure Clean] All protections removed for domain %s",
                $domain,
            ),
        );
    }

    /**
     * Restore settings from cache when license is renewed
     *
     * @param string $domain Domain name
     * @return bool True if restoration was successful
     */
    public static function restore_from_cache($domain)
    {
        $cache_key = self::CACHE_PREFIX . $domain . "_settings";
        $cached = get_transient($cache_key);

        if (!$cached) {
            error_log(
                "[VAPTSecure Clean] No cached settings found for domain: $domain",
            );
            return false;
        }

        global $wpdb;

        try {
            // 1. Restore domain features
            if (!empty($cached["features"])) {
                $domain_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s",
                        $domain,
                    ),
                );

                if ($domain_id) {
                    foreach ($cached["features"] as $feature) {
                        $wpdb->replace(
                            $wpdb->prefix . "vaptsecure_domain_features",
                            [
                                "domain_id" => $domain_id,
                                "feature_key" => $feature["feature_key"],
                                "enabled" => $feature["enabled"],
                            ],
                            ["%d", "%s", "%d"],
                        );
                    }
                }
            }

            // 2. Restore feature meta
            if (!empty($cached["feature_meta"])) {
                foreach ($cached["feature_meta"] as $meta) {
                    $wpdb->replace(
                        $wpdb->prefix . "vaptsecure_feature_meta",
                        [
                            "feature_key" => $meta["feature_key"],
                            "is_enabled" => $meta["is_enabled"],
                            "generated_schema" => $meta["generated_schema"],
                            "implementation_data" =>
                                $meta["implementation_data"],
                            "override_schema" => $meta["override_schema"],
                            "override_implementation_data" =>
                                $meta["override_implementation_data"],
                            "is_adaptive_deployment" =>
                                $meta["is_adaptive_deployment"],
                        ],
                        ["%s", "%d", "%s", "%s", "%s", "%s", "%d"],
                    );
                }
            }

            // 3. Restore feature status
            if (!empty($cached["feature_status"])) {
                foreach ($cached["feature_status"] as $status) {
                    $wpdb->replace(
                        $wpdb->prefix . "vaptsecure_feature_status",
                        [
                            "feature_key" => $status["feature_key"],
                            "status" => $status["status"],
                        ],
                        ["%s", "%s"],
                    );
                }
            }

            // 4. Restore enforcement state
            if (isset($cached["enforcement_state"])) {
                update_option(
                    "vaptsecure_global_protection",
                    $cached["enforcement_state"],
                );
            }

            // 5. Rebuild protections
            delete_transient("vaptsecure_active_enforcements");

            if (class_exists("VAPTSECURE_Enforcer")) {
                VAPTSECURE_Enforcer::rebuild_all(false);
            }

            // 6. Clear cache
            delete_transient($cache_key);
            delete_transient(self::CACHE_PREFIX . $domain . "_grace");
            delete_transient(self::CACHE_PREFIX . $domain . "_expired_handled");

            // 7. Log restoration
            error_log(
                sprintf(
                    "[VAPTSecure Clean] Settings restored for domain %s from cache",
                    $domain,
                ),
            );

            return true;
        } catch (Exception $e) {
            error_log(
                sprintf(
                    "[VAPTSecure Clean] Error restoring settings for domain %s: %s",
                    $domain,
                    $e->getMessage(),
                ),
            );
            return false;
        }
    }

    /**
     * Display admin notices for license status
     */
    public static function admin_notices()
    {
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        if (empty($domain)) {
            return;
        }

        // Check grace period status
        $in_grace = get_transient(self::CACHE_PREFIX . $domain . "_grace");
        $expired_handled = get_transient(
            self::CACHE_PREFIX . $domain . "_expired_handled",
        );

        if ($in_grace && $expired_handled) {
            // License was expired, now in grace period
            echo '<div class="notice notice-warning is-dismissible">';
            echo "<p><strong>VAPTSecure Clean License Notice:</strong></p>";
            echo "<p>Your license has expired. All security protections have been temporarily disabled.</p>";
            echo "<p>Your settings have been saved and will be automatically restored when you renew your license.</p>";
            echo '<p><a href="' .
                admin_url("admin.php?page=vaptsecure-domain-admin") .
                '">Manage Licenses</a></p>';
            echo "</div>";
        }

        $row = self::get_domain_row($domain);
        if (
            $row &&
            !empty($row->manual_expiry_date) &&
            $row->manual_expiry_date !== "0000-00-00 00:00:00"
        ) {
            $expiry_context = self::get_expiry_context($row);
            if (
                !empty($expiry_context["expires_soon"]) &&
                empty($expiry_context["expired"])
            ) {
                $days_remaining = isset($expiry_context["days_remaining"])
                    ? (int) $expiry_context["days_remaining"]
                    : 0;
                $display_days = max(0, $days_remaining);
                $renew_url = admin_url("admin.php?page=vaptsecure-domain-admin");
                $due_text = $display_days === 0
                    ? "today"
                    : sprintf(
                        _n(
                            "%d day",
                            "%d days",
                            $display_days,
                            "vaptsecure",
                        ),
                        $display_days,
                    );

                echo '<div class="notice notice-warning is-dismissible">';
                echo "<p><strong>VAPTSecure Clean License Notice:</strong></p>";
                echo "<p>Your license will expire in " .
                    esc_html($due_text) .
                    ". Renew now to keep protection for your domain.</p>";
                echo "<p>When the license expires, protection will be removed until it is renewed.</p>";
                echo '<p><a class="button button-primary" href="' .
                    esc_url($renew_url) .
                    '">Review License</a></p>';
                echo "</div>";
            }
        }

        // Check for cached settings available for manual restore
        $cached = get_transient(self::CACHE_PREFIX . $domain . "_settings");
        if ($cached && !empty($cached["saved_at"])) {
            echo '<div class="notice notice-info is-dismissible vapt-license-cache-notice">';
            echo "<p><strong>VAPTSecure Clean Backup Available:</strong></p>";
            echo "<p>A backup of your settings from " .
                esc_html($cached["saved_at"]) .
                " is available.</p>";
            echo '<p><button class="button button-primary" onclick="vaptsecure_restore_cache()">Restore Settings</button></p>';
            echo '<script>
                function vaptsecure_restore_cache() {
                    if (confirm("Restore settings from cache? This will re-enable all protections.")) {
                        jQuery.post(ajaxurl, {
                            action: "vaptsecure_restore_from_cache",
                            domain: "' .
                esc_js($domain) .
                '",
                            nonce: "' .
                wp_create_nonce("vaptsecure_restore_cache") .
                '"
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert("Failed to restore settings: " + response.data.message);
                            }
                        });
                    }
                }
            </script>';
            echo "</div>";
        }
    }

    /**
     * Get the domain row for the current site.
     *
     * @param string $domain Domain name
     * @return object|null
     */
    private static function get_domain_row($domain)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s",
                $domain,
            ),
        );
    }

    /**
     * Build expiry warning context for a license row.
     *
     * @param object $row Domain database row
     * @return array{expiry_ts:int,days_remaining:int,expires_soon:bool,expired:bool}
     */
    private static function get_expiry_context($row)
    {
        $expiry_date = isset($row->manual_expiry_date)
            ? (string) $row->manual_expiry_date
            : "";
        if (
            $expiry_date === "" ||
            $expiry_date === "0000-00-00 00:00:00"
        ) {
            return [
                "expiry_ts" => 0,
                "days_remaining" => PHP_INT_MAX,
                "expires_soon" => false,
                "expired" => false,
            ];
        }

        $expiry_ts = strtotime($expiry_date);
        if (!$expiry_ts) {
            return [
                "expiry_ts" => 0,
                "days_remaining" => 0,
                "expires_soon" => false,
                "expired" => false,
            ];
        }

        $today_ts = strtotime(date("Y-m-d 00:00:00"));
        $days_remaining = (int) floor(($expiry_ts - $today_ts) / DAY_IN_SECONDS);
        $expired = $expiry_ts < $today_ts;
        $expires_soon =
            !$expired && $days_remaining <= self::EXPIRY_WARNING_DAYS;

        return [
            "expiry_ts" => $expiry_ts,
            "days_remaining" => $days_remaining,
            "expires_soon" => $expires_soon,
            "expired" => $expired,
        ];
    }

    /**
     * AJAX handler for manual restore from cache
     */
    public static function ajax_restore_from_cache()
    {
        check_ajax_referer("vaptsecure_restore_cache", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Permission denied"]);
        }

        $domain = isset($_POST["domain"])
            ? sanitize_text_field($_POST["domain"])
            : "";

        if (empty($domain)) {
            wp_send_json_error(["message" => "Missing domain"]);
        }

        $result = self::restore_from_cache($domain);

        if ($result) {
            wp_send_json_success([
                "message" => "Settings restored successfully",
            ]);
        } else {
            wp_send_json_error([
                "message" => "No cached settings found or restoration failed",
            ]);
        }
    }

    /**
     * Get license status for a domain (public method)
     *
     * @param string $domain Domain name
     * @return array Status information
     */
    public static function get_license_info($domain)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vaptsecure_domains WHERE domain = %s",
                $domain,
            ),
        );

        if (!$row) {
            return [
                "status" => "not_found",
                "message" => "Domain not found in license system",
            ];
        }

        $status = self::check_domain_license($domain);
        $in_grace = get_transient(self::CACHE_PREFIX . $domain . "_grace");
        $has_cache = (bool) get_transient(
            self::CACHE_PREFIX . $domain . "_settings",
        );
        $expiry_context = self::get_expiry_context($row);

        return [
            "status" => $status,
            "domain" => $domain,
            "license_id" => $row->license_id,
            "license_type" => $row->license_type,
            "expiry_date" => $row->manual_expiry_date,
            "days_remaining" => $expiry_context["days_remaining"],
            "expires_soon" => $expiry_context["expires_soon"],
            "is_enabled" => (bool) $row->is_enabled,
            "auto_renew" => (bool) $row->auto_renew,
            "renewals_count" => $row->renewals_count,
            "in_grace_period" => $in_grace,
            "has_cached_settings" => $has_cache,
        ];
    }

    /**
     * Manually trigger license check and handle expiration
     *
     * @return array Result of license check
     */
    public static function force_license_check()
    {
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $status = self::check_domain_license($domain);

        if ($status === "expired") {
            self::handle_expired_license($domain);
        }

        return [
            "domain" => $domain,
            "status" => $status,
            "handled" => $status === "expired",
        ];
    }

    /**
     * Clear all license-related transients for a domain
     *
     * @param string $domain Domain name
     */
    public static function clear_cache($domain)
    {
        delete_transient(self::CACHE_PREFIX . $domain . "_settings");
        delete_transient(self::CACHE_PREFIX . $domain . "_grace");
        delete_transient(self::CACHE_PREFIX . $domain . "_expired_handled");
    }
}

// Initialize the license manager
VAPTSECURE_License_Manager::init();
