<?php
/**
 * Debug logging helper functions
 *
 * Provides conditional logging based on VAPTSECURE_DEBUG constant
 * Only errors are logged by default; other levels require debug mode
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Debug Mode Control - set in wp-config.php or vaptsecure.php
if (!defined('VAPTSECURE_DEBUG')) {
    define('VAPTSECURE_DEBUG', false);
}

/**
 * Conditional debug logging
 * 
 * @param string $message The message to log
 * @param string $level The log level (debug, info, warning, error)
 * @param mixed $data Optional data to include in log
 */
function vapt_log($message, $level = 'debug', $data = null) {
    if (!VAPTSECURE_DEBUG && $level !== 'error') {
        return; // Only log errors when debug is off
    }
    
    $prefix = '[VAPT]';
    $log_message = sprintf('%s %s: %s', $prefix, strtoupper($level), $message);
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log_message .= ' ' . json_encode($data);
        } else {
            $log_message .= ' ' . (string)$data;
        }
    }
    
    // Only log if error_log function exists (WordPress environment)
    if (function_exists('error_log')) {
        error_log($log_message);
    }
}

function vapt_debug($message, $data = null) {
    vapt_log($message, 'debug', $data);
}

function vapt_info($message, $data = null) {
    vapt_log($message, 'info', $data);
}

function vapt_warning($message, $data = null) {
    vapt_log($message, 'warning', $data);
}

function vapt_error($message, $data = null) {
    vapt_log($message, 'error', $data);
}