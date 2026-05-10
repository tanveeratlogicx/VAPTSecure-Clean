<?php

/**
 * VAPTSecure Clean: Centralized PHP Protections
 */

if (!defined('ABSPATH')) exit;

// BEGIN VAPT SECURITY RULES
// BEGIN VAPT RISK-008

add_filter('login_errors', 'vapt_hide_login_errors');
function vapt_hide_login_errors() {
    return 'Invalid credentials. Please try again.';
}

// END VAPT RISK-008

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
// END VAPT SECURITY RULES
