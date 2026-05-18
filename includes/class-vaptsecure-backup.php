<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * VAPTSecure Backup Manager
 * Handles schema preservation, archiving, and recovery.
 */
class VAPTSECURE_Backup {

    /**
     * Save or update a backup record.
     *
     * @param string $feature_key Feature identifier.
     * @param array  $data        Backup data payload.
     * @param string $status      Current feature status.
     * @return bool|int
     */
    public static function save_backup($feature_key, $data, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backups';

        $payload = array(
            'feature_key'                   => $feature_key,
            'feature_title'                 => isset($data['feature_title']) ? $data['feature_title'] : null,
            'feature_category'              => isset($data['feature_category']) ? $data['feature_category'] : null,
            'generated_schema'              => isset($data['generated_schema']) ? $data['generated_schema'] : null,
            'implementation_data'           => isset($data['implementation_data']) ? (is_array($data['implementation_data']) ? json_encode($data['implementation_data']) : $data['implementation_data']) : null,
            'override_schema'               => isset($data['override_schema']) ? $data['override_schema'] : null,
            'override_implementation_data'  => isset($data['override_implementation_data']) ? $data['override_implementation_data'] : null,
            'is_enabled'                    => isset($data['is_enabled']) ? (int) $data['is_enabled'] : 0,
            'is_enforced'                   => isset($data['is_enforced']) ? (int) $data['is_enforced'] : 0,
            'active_enforcer'               => isset($data['active_enforcer']) ? $data['active_enforcer'] : null,
            'include_verification_engine'   => isset($data['include_verification_engine']) ? (int) $data['include_verification_engine'] : 0,
            'status_at_backup'              => $status,
            'backed_up_at'                  => current_time('mysql')
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s');

        return $wpdb->replace($table, $payload, $formats);
    }

    /**
     * Load backup data for a specific feature.
     *
     * @param string $feature_key Feature identifier.
     * @return array|false
     */
    public static function load_backup($feature_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backups';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE feature_key = %s", $feature_key),
            ARRAY_A
        );

        return $row ?: false;
    }

    /**
     * Move backup to archive table.
     * Called when feature transitions to Draft.
     *
     * @param string $feature_key Feature identifier.
     * @return bool
     */
    public static function archive_backup($feature_key) {
        global $wpdb;
        $backup_table = $wpdb->prefix . 'vaptsecure_feature_backups';
        $archive_table = $wpdb->prefix . 'vaptsecure_feature_backup_archive';

        // Check if backup exists
        $backup = self::load_backup($feature_key);
        if (!$backup) {
            return false;
        }

        // Insert into archive
        $archive_data = array(
            'feature_key'                   => $backup['feature_key'],
            'feature_title'                 => isset($backup['feature_title']) ? $backup['feature_title'] : null,
            'feature_category'              => isset($backup['feature_category']) ? $backup['feature_category'] : null,
            'generated_schema'              => $backup['generated_schema'],
            'implementation_data'           => $backup['implementation_data'],
            'override_schema'               => $backup['override_schema'],
            'override_implementation_data'  => $backup['override_implementation_data'],
            'is_enabled'                    => $backup['is_enabled'],
            'is_enforced'                   => $backup['is_enforced'],
            'active_enforcer'               => $backup['active_enforcer'],
            'include_verification_engine'   => $backup['include_verification_engine'],
            'status_at_archive'             => $backup['status_at_backup'],
            'original_backup_date'          => $backup['backed_up_at'],
            'archived_at'                   => current_time('mysql')
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s');

        $result = $wpdb->insert($archive_table, $archive_data, $formats);

        if ($result) {
            // Delete from active backups
            $wpdb->delete($backup_table, array('feature_key' => $feature_key));
            error_log("VAPT Backup: Archived backup for {$feature_key}");
        }

        return (bool) $result;
    }

    /**
     * Restore feature from archive.
     * Superadmin only. Restores to original status.
     *
     * @param string $feature_key Feature identifier.
     * @return array|false Result with success status and message.
     */
    public static function restore_from_archive($feature_key) {
        global $wpdb;
        $backup_table = $wpdb->prefix . 'vaptsecure_feature_backups';
        $archive_table = $wpdb->prefix . 'vaptsecure_feature_backup_archive';
        $meta_table = $wpdb->prefix . 'vaptsecure_feature_meta';
        $status_table = $wpdb->prefix . 'vaptsecure_feature_status';

        // Get archive record
        $archive = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $archive_table WHERE feature_key = %s", $feature_key),
            ARRAY_A
        );

        if (!$archive) {
            return array('success' => false, 'message' => 'Archive record not found.');
        }

        // 1. Restore to active backups
        $backup_data = array(
            'feature_key'                   => $archive['feature_key'],
            'feature_title'                 => isset($archive['feature_title']) ? $archive['feature_title'] : null,
            'feature_category'              => isset($archive['feature_category']) ? $archive['feature_category'] : null,
            'generated_schema'              => $archive['generated_schema'],
            'implementation_data'           => $archive['implementation_data'],
            'override_schema'               => $archive['override_schema'],
            'override_implementation_data'  => $archive['override_implementation_data'],
            'is_enabled'                    => $archive['is_enabled'],
            'is_enforced'                   => $archive['is_enforced'],
            'active_enforcer'               => $archive['active_enforcer'],
            'include_verification_engine'   => $archive['include_verification_engine'],
            'status_at_backup'              => $archive['status_at_archive'],
            'backed_up_at'                  => current_time('mysql')
        );

        $backup_formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s');
        $wpdb->replace($backup_table, $backup_data, $backup_formats);

        // 2. Restore to feature meta
        $meta_data = array(
            'feature_key'                   => $feature_key,
            'generated_schema'              => $archive['generated_schema'],
            'implementation_data'           => $archive['implementation_data'],
            'override_schema'               => $archive['override_schema'],
            'override_implementation_data'  => $archive['override_implementation_data'],
            'is_enabled'                    => $archive['is_enabled'],
            'is_enforced'                   => $archive['is_enforced'],
            'active_enforcer'               => $archive['active_enforcer'],
            'include_verification_engine'   => $archive['include_verification_engine']
        );

        $meta_formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d');
        $wpdb->replace($meta_table, $meta_data, $meta_formats);

        // 3. Restore status to original status
        $original_status = $archive['status_at_archive'] ?: 'Develop';
        $wpdb->replace(
            $status_table,
            array('feature_key' => $feature_key, 'status' => ucfirst($original_status), 'implemented_at' => null),
            array('%s', '%s', '%s')
        );

        // 4. Remove from archive
        $wpdb->delete($archive_table, array('feature_key' => $feature_key));

        error_log("VAPT Backup: Restored {$feature_key} from archive to {$original_status}");

        return array(
            'success' => true,
            'message' => "Restored {$feature_key} to {$original_status} status.",
            'status' => ucfirst($original_status)
        );
    }

    /**
     * Manually move a backup from active to archive.
     * Superadmin only.
     *
     * @param string $feature_key Feature identifier.
     * @return array Result with success status and message.
     */
    public static function manual_archive_backup($feature_key) {
        global $wpdb;
        $backup_table = $wpdb->prefix . 'vaptsecure_feature_backups';
        $archive_table = $wpdb->prefix . 'vaptsecure_feature_backup_archive';

        // Get backup record
        $backup = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $backup_table WHERE feature_key = %s", $feature_key),
            ARRAY_A
        );

        if (!$backup) {
            return array('success' => false, 'message' => 'Backup record not found.');
        }

        // Insert into archive
        $archive_data = array(
            'feature_key'                   => $backup['feature_key'],
            'feature_title'                 => isset($backup['feature_title']) ? $backup['feature_title'] : null,
            'feature_category'              => isset($backup['feature_category']) ? $backup['feature_category'] : null,
            'generated_schema'              => $backup['generated_schema'],
            'implementation_data'           => $backup['implementation_data'],
            'override_schema'               => $backup['override_schema'],
            'override_implementation_data'  => $backup['override_implementation_data'],
            'is_enabled'                    => $backup['is_enabled'],
            'is_enforced'                   => $backup['is_enforced'],
            'active_enforcer'               => $backup['active_enforcer'],
            'include_verification_engine'   => $backup['include_verification_engine'],
            'status_at_archive'             => $backup['status_at_backup'],
            'original_backup_date'          => $backup['backed_up_at'],
            'archived_at'                   => current_time('mysql')
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s');
        $result = $wpdb->insert($archive_table, $archive_data, $formats);

        if ($result) {
            $wpdb->delete($backup_table, array('feature_key' => $feature_key));
            error_log("VAPT Backup: Manually archived {$feature_key}");
            return array('success' => true, 'message' => "Archived {$feature_key} successfully.");
        }

        return array('success' => false, 'message' => 'Failed to archive backup.');
    }

    /**
     * Get paginated list of archived backups.
     *
     * @param int $limit  Number of records.
     * @param int $offset Offset.
     * @return array
     */
    public static function get_archived_backups($limit = 20, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backup_archive';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY archived_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get total count of archived backups.
     *
     * @return int
     */
    public static function get_archived_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backup_archive';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get paginated list of active backups.
     *
     * @param int $limit  Number of records.
     * @param int $offset Offset.
     * @return array
     */
    public static function get_active_backups($limit = 20, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backups';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY backed_up_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get total count of active backups.
     *
     * @return int
     */
    public static function get_active_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backups';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Purge selected backups.
     *
     * @param array $feature_keys Array of feature keys.
     * @param bool  $from_archive True if purging from archive table.
     * @return int Number of deleted rows.
     */
    public static function purge_backups($feature_keys, $from_archive = false) {
        global $wpdb;
        $table = $from_archive
            ? $wpdb->prefix . 'vaptsecure_feature_backup_archive'
            : $wpdb->prefix . 'vaptsecure_feature_backups';

        if (empty($feature_keys)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($feature_keys), '%s'));
        $query = "DELETE FROM $table WHERE feature_key IN ($placeholders)";

        return $wpdb->query($wpdb->prepare($query, $feature_keys));
    }

    /**
     * Check if backup exists for a feature.
     *
     * @param string $feature_key Feature identifier.
     * @return bool
     */
    public static function backup_exists($feature_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'vaptsecure_feature_backups';
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE feature_key = %s", $feature_key)
        );
        return (int) $exists > 0;
    }

    /**
     * Get features that are in Develop/Release status but missing backups.
     *
     * @return array List of feature keys.
     */
    public static function get_features_missing_backups() {
        global $wpdb;
        $status_table = $wpdb->prefix . 'vaptsecure_feature_status';
        $backup_table = $wpdb->prefix . 'vaptsecure_feature_backups';
        $meta_table = $wpdb->prefix . 'vaptsecure_feature_meta';

        // Find features in Develop/Release with schema in meta but no backup
        $query = "
            SELECT s.feature_key 
            FROM $status_table s
            INNER JOIN $meta_table m ON s.feature_key = m.feature_key
            LEFT JOIN $backup_table b ON s.feature_key = b.feature_key
            WHERE s.status IN ('Develop', 'Release', 'Test')
              AND m.generated_schema IS NOT NULL 
              AND m.generated_schema != ''
              AND b.feature_key IS NULL
        ";

        $results = $wpdb->get_col($query);
        return $results ?: array();
    }
}
