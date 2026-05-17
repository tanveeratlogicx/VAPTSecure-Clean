<?php
/**
 * VAPTSecure Legacy Marker Cleanup Script
 * 
 * Removes legacy "BEGIN VAPT FEATURE / END VAPT FEATURE" blocks from wp-config.php
 * These were generated before the v4.1.6 marker standardization.
 * 
 * Usage: Run from command line or browser
 *   wp eval-file cleanup-legacy-markers.php
 *   OR visit: http://vaptsecure.local/wp-content/plugins/VAPTSecure-Clean/cleanup-legacy-markers.php
 */

// Prevent direct access unless explicitly allowed
if (!isset($_GET['run']) && !defined('WP_CLI')) {
    die('Access denied. Add ?run=1 to URL or run via WP-CLI.');
}

// Bootstrap WordPress
if (file_exists(dirname(__FILE__) . '/../../../wp-load.php')) {
    require_once dirname(__FILE__) . '/../../../wp-load.php';
} else {
    die('Could not find wp-load.php. Place this script in the plugin root directory.');
}

if (!current_user_can('manage_options')) {
    die('Insufficient permissions.');
}

$wp_config = ABSPATH . 'wp-config.php';

if (!file_exists($wp_config)) {
    die('wp-config.php not found at: ' . ABSPATH);
}

if (!is_writable($wp_config)) {
    die('wp-config.php is not writable.');
}

$content = file_get_contents($wp_config);
$original = $content;

// [v4.1.6] Remove legacy VAPT FEATURE blocks
// Pattern: // BEGIN VAPT FEATURE: RISK-XXX ... // END VAPT FEATURE: RISK-XXX
$count = 0;
$content = preg_replace('/\/\/ BEGIN VAPT FEATURE: [^\n]*\n.*?\/\/ END VAPT FEATURE: [^\n]*\n?/s', '', $content, -1, $count);
echo "Removed {$count} legacy 'VAPT FEATURE' blocks.\n";

// Also remove mixed END Of blocks in case any slipped through
$count2 = 0;
$content = preg_replace('/\/\/ BEGIN VAPT[^\n]*\n.*?\/\/ END (?:VAPT FEATURE:|Of) [^\n]*\n?/s', '', $content, -1, $count2);
echo "Removed {$count2} additional mixed-format blocks.\n";

// Clean up triple+ newlines left by removals
$content = preg_replace('/\n{3,}/', "\n\n", $content);

// Remove trailing whitespace before closing PHP tag if present
$content = preg_replace('/\n+\?>$/', "\n?>", $content);

if ($content === $original) {
    echo "No legacy markers found. wp-config.php is clean.\n";
    exit;
}

$backup = $wp_config . '.bak.' . time();
if (copy($wp_config, $backup)) {
    echo "Backup created: {$backup}\n";
}

$result = file_put_contents($wp_config, $content);
if ($result !== false) {
    echo "SUCCESS: wp-config.php cleaned.\n";
    echo "Original size: " . strlen($original) . " bytes\n";
    echo "New size: " . strlen($content) . " bytes\n";
    echo "Removed: " . (strlen($original) - strlen($content)) . " bytes\n";
} else {
    echo "ERROR: Failed to write wp-config.php.\n";
    echo "Backup restored from: {$backup}\n";
    copy($backup, $wp_config);
}
