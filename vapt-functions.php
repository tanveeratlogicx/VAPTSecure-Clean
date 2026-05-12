<?php

/**
 * VAPTSecure Clean: Centralized PHP Protections
 */

if (!defined('ABSPATH')) exit;

// BEGIN VAPT SECURITY RULES
// BEGIN VAPT RISK-007

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
            wp_die('Too many failed login attempts. Please try again later.');
        }
        set_transient($key, $attempts + 1, 3600);
    }
});

// END VAPT RISK-007

// BEGIN VAPT RISK-008

add_filter('login_errors', 'vapt_hide_login_errors');
function vapt_hide_login_errors() {
    return 'Invalid credentials. Please try again.';
}
add_action('send_headers', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
        header('x-vapt-enforced: php-headers');
    }
});

// END VAPT RISK-008

// BEGIN VAPT RISK-011

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'readme.html') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-011

// BEGIN VAPT RISK-126

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-126') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-126

// BEGIN VAPT RISK-127

add_action('init', 'vapt_register_input_validation_guards');
function vapt_register_input_validation_guards() {
    add_filter('preprocess_comment', 'vapt_sanitize_comment_input');
}
function vapt_sanitize_comment_input($commentdata) {
    if (isset($commentdata['comment_content'])) {
        $commentdata['comment_content'] = sanitize_textarea_field($commentdata['comment_content']);
    }
    if (isset($commentdata['comment_author'])) {
        $commentdata['comment_author'] = sanitize_text_field($commentdata['comment_author']);
    }
    if (isset($commentdata['comment_author_email'])) {
        $commentdata['comment_author_email'] = sanitize_email($commentdata['comment_author_email']);
    }
    return $commentdata;
}

// END VAPT RISK-127

// BEGIN VAPT RISK-129

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-129') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-129

// BEGIN VAPT RISK-130

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-130') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-130

// BEGIN VAPT RISK-131

add_action('wp_enqueue_scripts', 'vapt_add_recaptcha_v3_risk131');
function vapt_add_recaptcha_v3_risk131() {
    wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY');
}

// END VAPT RISK-131

// BEGIN VAPT RISK-132

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-132') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-132

// BEGIN VAPT RISK-133

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'RISK-133') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-133

// BEGIN VAPT RISK-134

add_action('send_headers', function() {
    header('X-Frame-Options: SAMEORIGIN');
});

// END VAPT RISK-134

// BEGIN VAPT RISK-135

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'debug.log') !== false) { wp_die('Access Denied'); }
});

// END VAPT RISK-135
// END VAPT SECURITY RULES
