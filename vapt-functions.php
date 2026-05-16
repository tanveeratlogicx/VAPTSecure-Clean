<?php

/**
 * VAPTSecure Clean: Centralized PHP Protections
 */

if (!defined("ABSPATH")) {
    exit();
}

// BEGIN VAPT FEATURE: RISK-003
add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2/users') !== false) {
        header('x-vapt-enforced: php-author-enum');
        wp_die('Access Denied');
    }
});
// END VAPT FEATURE: RISK-003

// BEGIN VAPT FEATURE: RISK-007
add_action('send_headers', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
        header('x-vapt-enforced: php-rate-limit');
    }
});
add_action('wp_login_failed', function($username) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $key = 'vapt_risk007_' . md5($ip);
    $attempts = get_transient($key);
    if ($attempts === false) {
        set_transient($key, 1, 3600);
    } else {
        if ($attempts >= 5) {
            header('x-vapt-enforced: php-rate-limit');
            wp_die('Too many failed login attempts. Please try again later.', '', array('response' => 429));
        }
        set_transient($key, $attempts + 1, 3600);
    }
});
// END VAPT FEATURE: RISK-007

// BEGIN VAPT FEATURE: RISK-008
add_filter('login_errors', 'vapt_hide_login_errors');
function vapt_hide_login_errors() {
    return 'Invalid credentials. Please try again.';
}
add_action('send_headers', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
        header('x-vapt-enforced: php-headers');
    }
});
// END VAPT FEATURE: RISK-008

// BEGIN VAPT FEATURE: RISK-133
add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-133') !== false) { wp_die('Access Denied'); }
});
// END VAPT FEATURE: RISK-133

// BEGIN VAPT FEATURE: RISK-011
add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'readme.html') !== false) { wp_die('Access Denied'); }
});
// END VAPT FEATURE: RISK-011
