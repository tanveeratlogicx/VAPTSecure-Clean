<?php

/**
 * VAPT Secure: Centralized PHP Protections
 */

if (!defined('ABSPATH')) exit;

// BEGIN VAPT SECURITY RULES
// BEGIN VAPT RISK-009

add_action('wp_enqueue_scripts', 'vapt_add_recaptcha_v3');
function vapt_add_recaptcha_v3() {
    wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY');
}

// END VAPT RISK-009
// END VAPT SECURITY RULES
