<?php

/**
 * VAPTSECURE_Bundle_Sync
 * Shared helpers for canonical bundle discovery, snapshots, and drift fingerprints.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class VAPTSECURE_Bundle_Sync
{
    /**
     * Canonical top-level bundle files that drive runtime behavior.
     *
     * @return array<string, string> map of logical keys to absolute paths
     */
    public static function canonical_files()
    {
        $base = rtrim(VAPTSECURE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;

        return array(
            'interface_schema' => $base . 'interface_schema_v2.0.json',
            'pattern_library' => $base . 'enforcer_pattern_library_v2.0.json',
            'driver_manifest' => $base . 'vapt_driver_manifest_v2.0.json',
            'ai_instructions' => $base . 'ai_agent_instructions_v2.0.json',
            'platform_contract' => $base . 'vapt_platform_contract_v3.0.json',
            'autoheal_contract' => $base . 'vapt_autoheal_contract_v2.0.json',
        );
    }

    /**
     * Capture the current bundle state for drift detection.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function snapshot()
    {
        $snapshot = array();
        foreach (self::canonical_files() as $key => $path) {
            $snapshot[$key] = array(
                'path' => $path,
                'exists' => file_exists($path),
                'size' => file_exists($path) ? filesize($path) : 0,
                'mtime' => file_exists($path) ? filemtime($path) : 0,
                'hash' => file_exists($path) ? hash_file('sha256', $path) : '',
            );
        }

        return $snapshot;
    }

    /**
     * Compute a stable aggregate fingerprint for the live canonical bundle.
     *
     * @return string
     */
    public static function fingerprint()
    {
        $snapshot = self::snapshot();
        $parts = array();

        foreach ($snapshot as $key => $meta) {
            $parts[] = $key . ':' . ($meta['hash'] ?? '') . ':' . (int) ($meta['size'] ?? 0) . ':' . (int) ($meta['mtime'] ?? 0);
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Determine whether the stored fingerprint is stale relative to the live bundle.
     *
     * @param string $stored_fingerprint
     * @return bool
     */
    public static function is_stale($stored_fingerprint)
    {
        $stored_fingerprint = trim((string) $stored_fingerprint);
        if ($stored_fingerprint === '') {
            return true;
        }

        return !hash_equals($stored_fingerprint, self::fingerprint());
    }
}
