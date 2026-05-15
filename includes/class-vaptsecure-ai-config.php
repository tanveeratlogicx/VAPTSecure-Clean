<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class VAPTSECURE_AI_Config
 * Handles automated maintenance of the Universal AI Configuration system.
 */
class VAPTSECURE_AI_Config
{
    /**
     * Expected symlink registry
     */
    private static $symlinks = [
        '.windsurfrules' => '.ai/SOUL.md',
        '.clinerules' => '.ai/SOUL.md',
        '.roorules' => '.ai/SOUL.md',
        '.cursor/cursor.rules' => '../.ai/SOUL.md',
        '.gemini/gemini.md' => '../.ai/SOUL.md',
        '.qoder/qoder.rules' => '../.ai/SOUL.md',
        '.trae/trae.rules' => '../.ai/SOUL.md',
        '.kilocode/rules/soul.md' => '../../.ai/SOUL.md',
        '.continue/rules/soul.md' => '../../.ai/SOUL.md',
        '.roo/rules/soul.md' => '../../.ai/SOUL.md',
        '.opencode/instructions/SOUL.md' => '../../.ai/SOUL.md',
        '.github/copilot-instructions.md' => '../.ai/SOUL.md',
        '.junie/guidelines.md' => '../.ai/SOUL.md',
        '.rules' => '.ai/SOUL.md',
    ];

    /**
     * Automated verification and repair of the AI configuration system.
     * Fires on "Implementation Initiation" (Draft -> Develop).
     */
    public static function verify_and_repair()
    {
        $root = VAPTSECURE_PATH;
        $soul_path = $root . '.ai/SOUL.md';

        if (! file_exists($soul_path)) {
            return false;
        }

        $results = [];

        foreach (self::$symlinks as $target_rel => $source_rel) {
            $target_path = $root . $target_rel;
            $parent_dir = dirname($target_path);

            if (! is_dir($parent_dir)) {
                wp_mkdir_p($parent_dir);
            }

            // Check if repair is needed
            if (! self::is_link_valid($target_path, $source_rel)) {
                $results[$target_rel] = self::repair_link($target_path, $source_rel);
            }
        }

        return $results;
    }

    /**
     * Check if a symlink exists and points to the correct source.
     */
    private static function is_link_valid($path, $expected_source)
    {
        if (! file_exists($path) && ! is_link($path)) {
            return false;
        }

        if (is_link($path)) {
            $actual_source = readlink($path);
            // Normalize slashes for comparison
            $actual_source = str_replace('\\', '/', $actual_source);
            $expected_source = str_replace('\\', '/', $expected_source);
            
            return $actual_source === $expected_source;
        }

        // If it's a file but not a link, check if content matches (fallback for systems without links)
        if (file_exists($path) && file_exists(VAPTSECURE_PATH . '.ai/SOUL.md')) {
            return md5_file($path) === md5_file(VAPTSECURE_PATH . '.ai/SOUL.md');
        }

        return false;
    }

    /**
     * Repair or create a symlink.
     */
    private static function repair_link($path, $source)
    {
        if (file_exists($path) || is_link($path)) {
            @unlink($path);
        }

        // Try symlink first
        if (function_exists('symlink')) {
            try {
                if (@symlink($source, $path)) {
                    return 'symlink_created';
                }
            } catch (Exception $e) {
                // Fallback to copy
            }
        }

        // Fallback to copy if symlink fails (e.g., Windows without proper permissions)
        $soul_path = VAPTSECURE_PATH . '.ai/SOUL.md';
        if (file_exists($soul_path)) {
            if (@copy($soul_path, $path)) {
                return 'copy_created';
            }
        }

        return 'failed';
    }

    /**
     * [SSoT v1.0] Regenerate derived .ai artifacts from the canonical data bundle.
     * Updates the SOUL.md metadata header and ensures all symlinks are valid.
     * Call this when the bundle fingerprint changes to keep .ai/ in sync with data/.
     *
     * @return array Results with 'soul_updated', 'fingerprint_stamped', 'symlinks_repaired'
     */
    public static function regenerate_from_bundle()
    {
        $root = VAPTSECURE_PATH;
        $soul_path = $root . '.ai/SOUL.md';
        $results = array(
            'soul_updated' => false,
            'fingerprint_stamped' => '',
            'symlinks_repaired' => false,
        );

        // 1. Get current bundle fingerprint and feature count
        $fingerprint = '';
        $feature_count = 0;
        if (class_exists('VAPTSECURE_Bundle_Sync')) {
            $fingerprint = VAPTSECURE_Bundle_Sync::fingerprint();
        }
        if (class_exists('VAPTSECURE_Enforcer')) {
            $schema_path = $root . 'data/interface_schema_v2.0.json';
            if (file_exists($schema_path)) {
                $schema = json_decode((string) file_get_contents($schema_path), true);
                if (is_array($schema) && !empty($schema['risk_interfaces'])) {
                    $feature_count = count($schema['risk_interfaces']);
                }
            }
        }

        // 2. Stamp fingerprint into SOUL.md if it exists
        if (file_exists($soul_path)) {
            $content = (string) file_get_contents($soul_path);
            $timestamp = current_time('mysql');

            // Update or insert the bundle metadata block
            $meta_block = "<!-- SSoT Bundle: fingerprint={$fingerprint} features={$feature_count} synced={$timestamp} -->\n";
            if (preg_match('/<!-- SSoT Bundle:.*-->/', $content)) {
                $content = preg_replace('/<!-- SSoT Bundle:.*-->/', trim($meta_block), $content);
            } else {
                // Insert after the first heading
                $content = preg_replace('/^(.+?)\n/', "$1\n" . $meta_block, $content);
            }
            file_put_contents($soul_path, $content);
            $results['soul_updated'] = true;
            $results['fingerprint_stamped'] = $fingerprint;
        }

        // 3. Repair symlinks to propagate to all IDE/extension surfaces
        $symlink_results = self::verify_and_repair();
        $results['symlinks_repaired'] = is_array($symlink_results);

        return $results;
    }
}
