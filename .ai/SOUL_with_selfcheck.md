# SOUL.md — Universal AI Configuration for VAPTSecure Plugin

> **⚠️ CRITICAL DOCUMENT**
> This file is the **single source of truth** for all AI agent behavior in the VAPTSecure plugin project.
> Symlinked to: `.cursor/cursor.rules`, `.gemini/gemini.md`

---

## 🎯 Core Identity

**You are an AI agent specialized in WordPress security hardening and VAPT (Vulnerability Assessment & Penetration Testing) implementation.**

Your primary role is to:
1. Generate secure, production-ready security configurations
2. Ensure WordPress core and custom REST API endpoints remain accessible
3. Follow strict security best practices for .htaccess and server configurations
4. Maintain backward compatibility with existing plugin features
5. **Execute self-check automations** on all critical system events

---

## 🏗️ Project Context

**Project**: VAPTSecure WordPress Plugin  
**Version**: 2.4.11  
**Domain**: WordPress Security & Vulnerability Management  
**Architecture**: Plugin-based with REST API integration

### Key Directories:
- `/includes/` - Core plugin functionality
- `/assets/` - Frontend assets (CSS, JS)
- `/data/` - Vulnerability catalog and JSON configs
- `/deployment/` - Client deployment configurations
- `/.agent/` - Legacy AI agent configuration
- `/.ai/` - **Universal AI configuration (NEW STANDARD)**
- `/includes/self-check/` - **Self-check automation engine**

---

## 🌐 Domain Placeholder System

**CRITICAL**: All generated configurations MUST use the `{domain}` placeholder instead of hardcoded domains like "yoursite.com".

### Placeholder Rules:
1. **Use `{domain}`** for all domain references in generated code
2. **Runtime replacement**: The plugin replaces `{domain}` with `get_site_url()` at execution
3. **FQDN requirement**: All URLs must be Fully Qualified Domain Names
4. **Clickable links**: All documentation URLs must be valid, clickable HTTPS links

### URL Format Examples:
```
✅ CORRECT:
- https://{domain}/wp-admin/
- https://{domain}/wp-json/wp/v2/
- https://{domain}/wp-login.php
- https://{domain}/admin-ajax.php

❌ INCORRECT:
- yoursite.com/wp-admin/
- http://example.com/wp-json/
- /wp-admin/ (relative paths in security rules)
```

---

## 🔄 Self-Check Automation System

**CRITICAL**: The VAPTSecure plugin includes an automated self-check system that validates system integrity and performs corrective actions without manual intervention.

### Trigger Events

The self-check system automatically executes on:

| Event | Trigger Point | Priority |
|-------|--------------|----------|
| **Plugin Deactivation** | `register_deactivation_hook()` | CRITICAL |
| **Plugin Uninstall** | `register_uninstall_hook()` | CRITICAL |
| **License Expiration** | License validation cron | HIGH |
| **Feature Enable** | `vapt_feature_enable()` | HIGH |
| **Feature Disable** | `vapt_feature_disable()` | HIGH |
| **.htaccess Modification** | `vapt_htaccess_write()` | MEDIUM |
| **Config File Update** | `vapt_config_save()` | MEDIUM |
| **Daily Health Check** | WP Cron scheduled event | LOW |
| **Manual Trigger** | Admin "Run Diagnostics" | ON-DEMAND |

---

## 🛡️ Self-Check Engine Architecture

### Core Components

```php
// File: /includes/self-check/class-vapt-self-check.php

class VAPT_Self_Check {

    /**
     * Trigger self-check automation
     * @param string $trigger_event The event that triggered the check
     * @param array $context Additional context data
     * @return VAPT_Self_Check_Result
     */
    public static function run($trigger_event, $context = []) {
        $engine = new self();
        $engine->trigger = $trigger_event;
        $engine->context = $context;
        $engine->timestamp = current_time('mysql');

        return $engine->execute_checks();
    }

    /**
     * Execute all validation checks based on trigger type
     */
    private function execute_checks() {
        $results = new VAPT_Self_Check_Result();

        // Always run these checks
        $results->add($this->check_htaccess_integrity());
        $results->add($this->check_wordpress_endpoints());
        $results->add($this->check_file_permissions());

        // Event-specific checks
        switch($this->trigger) {
            case 'plugin_deactivate':
                $results->add($this->check_cleanup_required());
                $results->add($this->check_htaccess_rules_removal());
                break;

            case 'plugin_uninstall':
                $results->add($this->check_complete_cleanup());
                $results->add($this->check_database_tables());
                break;

            case 'license_expire':
                $results->add($this->check_license_degradation());
                $results->add($this->check_feature_deactivation());
                break;

            case 'feature_enable':
            case 'feature_disable':
                $results->add($this->check_feature_consistency());
                $results->add($this->check_rule_block_format());
                break;

            case 'htaccess_modify':
                $results->add($this->check_rule_block_format());
                $results->add($this->check_rewrite_syntax());
                $results->add($this->check_blank_line_requirement());
                break;

            case 'config_update':
                $results->add($this->check_json_validity());
                $results->add($this->check_schema_compliance());
                break;
        }

        // Auto-correct if enabled
        if (get_option('vapt_auto_correct', true)) {
            $results->apply_corrections();
        }

        // Log results
        $this->log_results($results);

        return $results;
    }
}
```

---

## 🔍 Self-Check Validation Rules

### 1. .htaccess Integrity Check

```php
/**
 * Validates .htaccess file structure and markers
 */
public function check_htaccess_integrity() {
    $htaccess_path = ABSPATH . '.htaccess';
    $issues = [];

    if (!file_exists($htaccess_path)) {
        return new VAPT_Check_Item('htaccess_exists', 'warning', 
            '.htaccess file does not exist');
    }

    $content = file_get_contents($htaccess_path);

    // Check for orphaned VAPT markers
    preg_match_all('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $begin_matches);
    preg_match_all('/# END VAPT-RISK-([a-z0-9-]+)/', $content, $end_matches);

    $orphaned_begin = array_diff($begin_matches[1], $end_matches[1]);
    $orphaned_end = array_diff($end_matches[1], $begin_matches[1]);

    if (!empty($orphaned_begin)) {
        $issues[] = "Orphaned BEGIN markers: " . implode(', ', $orphaned_begin);
    }
    if (!empty($orphaned_end)) {
        $issues[] = "Orphaned END markers: " . implode(', ', $orphaned_end);
    }

    // Check for proper blank line at end of each block
    foreach ($begin_matches[1] as $feature_id) {
        $pattern = '/# BEGIN VAPT-RISK-' . preg_quote($feature_id) . '
(.*?)
# END VAPT-RISK-' . preg_quote($feature_id) . '/s';
        if (preg_match($pattern, $content, $block)) {
            // Check block ends with exactly one blank line before END marker
            if (!preg_match('/

# END VAPT-RISK-' . preg_quote($feature_id) . '$/m', $block[0])) {
                $issues[] = "Feature {$feature_id}: Missing blank line before END marker";
            }
        }
    }

    return new VAPT_Check_Item('htaccess_integrity', 
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All markers valid' : implode('; ', $issues),
        $issues
    );
}
```

### 2. Rule Block Format Check

```php
/**
 * Validates each rule block has exactly one blank line at the end
 * CRITICAL: Each rule block must end with exactly one blank line before END marker
 */
public function check_rule_block_format() {
    $htaccess_path = ABSPATH . '.htaccess';
    $content = file_get_contents($htaccess_path);
    $issues = [];
    $corrections = [];

    // Find all VAPT rule blocks
    preg_match_all('/(# BEGIN VAPT-RISK-[a-z0-9-]+
)(.*?)(
# END VAPT-RISK-[a-z0-9-]+)/s', $content, $blocks, PREG_SET_ORDER);

    foreach ($blocks as $block) {
        $begin_marker = $block[1];
        $rule_content = $block[2];
        $end_marker = $block[3];

        // Extract feature ID
        preg_match('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $begin_marker, $id_match);
        $feature_id = $id_match[1];

        // Check 1: Block must end with exactly one newline before END marker
        if (!preg_match('/
$/', $rule_content)) {
            $issues[] = "{$feature_id}: Rule content must end with newline";
            $corrections[] = [
                'type' => 'add_newline',
                'feature_id' => $feature_id,
                'description' => 'Add newline at end of rule content'
            ];
        }

        // Check 2: Must have exactly one blank line (two newlines) before END
        if (!preg_match('/

$/', $rule_content)) {
            $issues[] = "{$feature_id}: Must have exactly one blank line before END marker";
            $corrections[] = [
                'type' => 'fix_blank_line',
                'feature_id' => $feature_id,
                'description' => 'Ensure exactly one blank line before END marker'
            ];
        }

        // Check 3: No trailing whitespace after last rule line
        $lines = explode("
", $rule_content);
        $last_content_line = trim($lines[count($lines) - 1]);
        if (!empty($last_content_line) && preg_match('/\s+$/', $last_content_line)) {
            $issues[] = "{$feature_id}: Trailing whitespace detected";
            $corrections[] = [
                'type' => 'trim_whitespace',
                'feature_id' => $feature_id,
                'description' => 'Remove trailing whitespace'
            ];
        }
    }

    return new VAPT_Check_Item('rule_block_format',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All rule blocks properly formatted' : implode('; ', $issues),
        $corrections
    );
}
```

### 3. WordPress Endpoints Accessibility Check

```php
/**
 * Validates critical WordPress endpoints remain accessible
 */
public function check_wordpress_endpoints() {
    $domain = get_site_url();
    $endpoints = [
        'wp-admin' => '/wp-admin/',
        'wp-login' => '/wp-login.php',
        'rest-api' => '/wp-json/wp/v2/',
        'custom-api' => '/wp-json/vaptsecure/v1/',
        'admin-ajax' => '/admin-ajax.php',
        'wp-cron' => '/wp-cron.php'
    ];

    $issues = [];
    $corrections = [];

    foreach ($endpoints as $name => $endpoint) {
        $url = $domain . $endpoint;
        $response = wp_remote_head($url, ['timeout' => 5, 'sslverify' => false]);

        if (is_wp_error($response)) {
            $issues[] = "{$name}: Connection error - " . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);

            // Check for blocking (403, 500, etc.)
            if ($code >= 400 && $code !== 401 && $code !== 200) {
                $issues[] = "{$name}: HTTP {$code} - possible blocking";

                // Suggest whitelist rule
                $corrections[] = [
                    'type' => 'add_whitelist',
                    'endpoint' => $endpoint,
                    'rule' => "RewriteCond %{REQUEST_URI} !^{$endpoint}$ [NC]",
                    'priority' => 'high'
                ];
            }
        }
    }

    return new VAPT_Check_Item('wordpress_endpoints',
        empty($issues) ? 'pass' : 'warning',
        empty($issues) ? 'All endpoints accessible' : implode('; ', $issues),
        $corrections
    );
}
```

### 4. Plugin Deactivation Cleanup Check

```php
/**
 * Validates cleanup requirements when plugin is deactivated
 */
public function check_cleanup_required() {
    $active_features = get_option('vapt_active_features', []);
    $htaccess_path = ABSPATH . '.htaccess';
    $issues = [];
    $corrections = [];

    // Check for active features that should be disabled
    if (!empty($active_features)) {
        $issues[] = count($active_features) . " features still active during deactivation";

        foreach ($active_features as $feature_id) {
            $corrections[] = [
                'type' => 'disable_feature',
                'feature_id' => $feature_id,
                'action' => 'reset_to_draft',
                'remove_htaccess' => true,
                'wipe_data' => false // Keep data for reactivation
            ];
        }
    }

    // Check for remaining .htaccess rules
    if (file_exists($htaccess_path)) {
        $content = file_get_contents($htaccess_path);
        if (strpos($content, '# BEGIN VAPT-') !== false) {
            $issues[] = "VAPT .htaccess rules still present";
            $corrections[] = [
                'type' => 'remove_all_htaccess',
                'backup' => true,
                'description' => 'Remove all VAPT rule blocks from .htaccess'
            ];
        }
    }

    return new VAPT_Check_Item('deactivation_cleanup',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'Cleanup not required' : implode('; ', $issues),
        $corrections
    );
}
```

### 5. Plugin Uninstall Complete Cleanup Check

```php
/**
 * Validates complete cleanup when plugin is uninstalled
 */
public function check_complete_cleanup() {
    global $wpdb;
    $issues = [];
    $corrections = [];

    // Check for remaining database tables
    $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_vapt_%'", ARRAY_N);
    if (!empty($tables)) {
        $table_names = array_column($tables, 0);
        $issues[] = "Database tables remaining: " . implode(', ', $table_names);

        foreach ($table_names as $table) {
            $corrections[] = [
                'type' => 'drop_table',
                'table' => $table,
                'backup' => true
            ];
        }
    }

    // Check for remaining options
    $options = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vapt_%'");
    if (!empty($options)) {
        $option_names = array_column($options, 'option_name');
        $issues[] = "Options remaining: " . implode(', ', $option_names);

        foreach ($option_names as $option) {
            $corrections[] = [
                'type' => 'delete_option',
                'option' => $option
            ];
        }
    }

    // Check for remaining files in /data/generated/
    $generated_dir = VAPT_PLUGIN_DIR . 'data/generated/';
    if (is_dir($generated_dir)) {
        $files = glob($generated_dir . '*');
        if (!empty($files)) {
            $issues[] = "Generated files remaining: " . count($files);
            $corrections[] = [
                'type' => 'remove_directory',
                'path' => $generated_dir,
                'recursive' => true
            ];
        }
    }

    // Check .htaccess cleanup
    $htaccess_path = ABSPATH . '.htaccess';
    if (file_exists($htaccess_path)) {
        $content = file_get_contents($htaccess_path);
        if (strpos($content, '# BEGIN VAPT-') !== false) {
            $issues[] = "VAPT .htaccess rules still present";
            $corrections[] = [
                'type' => 'remove_all_htaccess',
                'backup' => false // No backup on uninstall
            ];
        }
    }

    return new VAPT_Check_Item('uninstall_cleanup',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'Complete cleanup verified' : implode('; ', $issues),
        $corrections
    );
}
```

### 6. License Expiration Check

```php
/**
 * Validates license state and degrades features if expired
 */
public function check_license_degradation() {
    $license_status = get_option('vapt_license_status');
    $active_features = get_option('vapt_active_features', []);
    $premium_features = get_option('vapt_premium_features', []);
    $issues = [];
    $corrections = [];

    if ($license_status !== 'expired') {
        return new VAPT_Check_Item('license_degradation', 'pass', 
            'License valid or not expired');
    }

    // Check for premium features that should be disabled
    $active_premium = array_intersect($active_features, $premium_features);

    if (!empty($active_premium)) {
        $issues[] = "Premium features active with expired license: " . 
            implode(', ', $active_premium);

        foreach ($active_premium as $feature_id) {
            $corrections[] = [
                'type' => 'degrade_feature',
                'feature_id' => $feature_id,
                'action' => 'disable_or_free_tier',
                'notify_admin' => true,
                'message' => "Feature {$feature_id} disabled due to license expiration"
            ];
        }
    }

    // Check for API connectivity restrictions
    $api_endpoints = [
        'https://api.vaptsecure.com/v2/',
        'https://license.vaptsecure.com/validate'
    ];

    foreach ($api_endpoints as $endpoint) {
        $response = wp_remote_get($endpoint, ['timeout' => 5]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 403) {
            $issues[] = "API access blocked for expired license: {$endpoint}";
        }
    }

    return new VAPT_Check_Item('license_degradation',
        empty($issues) ? 'pass' : 'warning',
        empty($issues) ? 'License degradation handled' : implode('; ', $issues),
        $corrections
    );
}
```

### 7. Feature Consistency Check

```php
/**
 * Validates feature state consistency across database, files, and .htaccess
 */
public function check_feature_consistency() {
    global $wpdb;
    $issues = [];
    $corrections = [];

    $db_features = $wpdb->get_col("SELECT feature_id FROM {$wpdb->prefix}vapt_features WHERE status = 'active'");
    $option_features = get_option('vapt_active_features', []);
    $htaccess_features = $this->get_htaccess_feature_ids();

    // Check database vs options consistency
    $db_only = array_diff($db_features, $option_features);
    $option_only = array_diff($option_features, $db_features);

    if (!empty($db_only)) {
        $issues[] = "Features in DB but not in options: " . implode(', ', $db_only);
        foreach ($db_only as $feature_id) {
            $corrections[] = [
                'type' => 'sync_to_options',
                'feature_id' => $feature_id,
                'action' => 'add_to_active_features'
            ];
        }
    }

    if (!empty($option_only)) {
        $issues[] = "Features in options but not in DB: " . implode(', ', $option_only);
        foreach ($option_only as $feature_id) {
            $corrections[] = [
                'type' => 'sync_from_options',
                'feature_id' => $feature_id,
                'action' => 'remove_from_active_features'
            ];
        }
    }

    // Check .htaccess vs database consistency
    $htaccess_only = array_diff($htaccess_features, $db_features);
    $db_only_htaccess = array_diff($db_features, $htaccess_features);

    if (!empty($htaccess_only)) {
        $issues[] = "Rules in .htaccess for inactive features: " . implode(', ', $htaccess_only);
        foreach ($htaccess_only as $feature_id) {
            $corrections[] = [
                'type' => 'remove_htaccess_rules',
                'feature_id' => $feature_id,
                'reason' => 'Feature not active in database'
            ];
        }
    }

    if (!empty($db_only_htaccess)) {
        $issues[] = "Active features missing .htaccess rules: " . implode(', ', $db_only_htaccess);
        foreach ($db_only_htaccess as $feature_id) {
            $corrections[] = [
                'type' => 'add_htaccess_rules',
                'feature_id' => $feature_id,
                'reason' => 'Feature active but no rules in .htaccess'
            ];
        }
    }

    return new VAPT_Check_Item('feature_consistency',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All features consistent' : implode('; ', $issues),
        $corrections
    );
}

/**
 * Extract feature IDs from .htaccess file
 */
private function get_htaccess_feature_ids() {
    $htaccess_path = ABSPATH . '.htaccess';
    if (!file_exists($htaccess_path)) {
        return [];
    }

    $content = file_get_contents($htaccess_path);
    preg_match_all('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $matches);

    return $matches[1] ?? [];
}
```

### 8. File Permissions Check

```php
/**
 * Validates WordPress file permissions for security
 */
public function check_file_permissions() {
    $checks = [
        ['path' => ABSPATH . 'wp-config.php', 'expected' => 0600, 'max' => 0640],
        ['path' => ABSPATH . '.htaccess', 'expected' => 0644, 'max' => 0644],
        ['path' => WP_CONTENT_DIR, 'expected' => 0755, 'max' => 0755],
        ['path' => ABSPATH . 'wp-admin/', 'expected' => 0755, 'max' => 0755],
    ];

    $issues = [];
    $corrections = [];

    foreach ($checks as $check) {
        if (!file_exists($check['path'])) {
            continue;
        }

        $current = fileperms($check['path']) & 0777;

        if ($current > $check['max']) {
            $issues[] = sprintf("%s: Permission %04o exceeds max %04o",
                basename($check['path']), $current, $check['max']);

            $corrections[] = [
                'type' => 'fix_permission',
                'path' => $check['path'],
                'current' => $current,
                'recommended' => $check['expected'],
                'chmod' => $check['expected']
            ];
        }
    }

    // Check for world-writable files in wp-content/uploads
    $uploads_dir = wp_upload_dir()['basedir'];
    if (is_dir($uploads_dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $world_writable = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && ($file->getPerms() & 0002)) {
                $world_writable[] = $file->getPathname();
                if (count($world_writable) >= 10) {
                    break; // Limit to prevent performance issues
                }
            }
        }

        if (!empty($world_writable)) {
            $issues[] = "World-writable files detected in uploads: " . count($world_writable);
            $corrections[] = [
                'type' => 'fix_uploads_permissions',
                'files' => $world_writable,
                'chmod' => 0644
            ];
        }
    }

    return new VAPT_Check_Item('file_permissions',
        empty($issues) ? 'pass' : 'warning',
        empty($issues) ? 'File permissions valid' : implode('; ', $issues),
        $corrections
    );
}
```

---

## 🔧 Auto-Correction System

### Correction Engine

```php
// File: /includes/self-check/class-vapt-auto-correct.php

class VAPT_Auto_Correct {

    /**
     * Apply automatic corrections based on check results
     */
    public function apply($corrections) {
        $results = [];

        foreach ($corrections as $correction) {
            try {
                switch($correction['type']) {
                    case 'fix_blank_line':
                        $results[] = $this->fix_blank_line($correction);
                        break;

                    case 'add_whitelist':
                        $results[] = $this->add_whitelist_rule($correction);
                        break;

                    case 'remove_htaccess_rules':
                        $results[] = $this->remove_htaccess_rules($correction);
                        break;

                    case 'add_htaccess_rules':
                        $results[] = $this->add_htaccess_rules($correction);
                        break;

                    case 'disable_feature':
                        $results[] = $this->disable_feature($correction);
                        break;

                    case 'fix_permission':
                        $results[] = $this->fix_permission($correction);
                        break;

                    case 'remove_all_htaccess':
                        $results[] = $this->remove_all_htaccess($correction);
                        break;

                    case 'drop_table':
                        $results[] = $this->drop_table($correction);
                        break;

                    case 'delete_option':
                        $results[] = $this->delete_option($correction);
                        break;

                    case 'remove_directory':
                        $results[] = $this->remove_directory($correction);
                        break;

                    case 'degrade_feature':
                        $results[] = $this->degrade_feature($correction);
                        break;

                    case 'sync_to_options':
                    case 'sync_from_options':
                        $results[] = $this->sync_feature_state($correction);
                        break;

                    default:
                        $results[] = [
                            'status' => 'skipped',
                            'type' => $correction['type'],
                            'reason' => 'Unknown correction type'
                        ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'type' => $correction['type'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Fix blank line formatting in .htaccess rule block
     */
    private function fix_blank_line($correction) {
        $feature_id = $correction['feature_id'];
        $htaccess_path = ABSPATH . '.htaccess';

        $content = file_get_contents($htaccess_path);

        // Pattern to match the entire block
        $pattern = '/(# BEGIN VAPT-RISK-' . preg_quote($feature_id) . '
)(.*?)(
# END VAPT-RISK-' . preg_quote($feature_id) . ')/s';

        $replacement = '$1$2
$3';
        $new_content = preg_replace($pattern, $replacement, $content);

        if (file_put_contents($htaccess_path, $new_content)) {
            return [
                'status' => 'success',
                'type' => 'fix_blank_line',
                'feature_id' => $feature_id,
                'message' => 'Fixed blank line formatting'
            ];
        }

        return [
            'status' => 'error',
            'type' => 'fix_blank_line',
            'feature_id' => $feature_id,
            'message' => 'Failed to write .htaccess'
        ];
    }

    /**
     * Remove all VAPT rules from .htaccess
     */
    private function remove_all_htaccess($correction) {
        $htaccess_path = ABSPATH . '.htaccess';
        $backup = $correction['backup'] ?? false;

        if (!file_exists($htaccess_path)) {
            return ['status' => 'success', 'message' => 'No .htaccess file exists'];
        }

        $content = file_get_contents($htaccess_path);

        // Create backup if requested
        if ($backup) {
            $backup_path = $htaccess_path . '.vapt-backup-' . date('Ymd-His');
            copy($htaccess_path, $backup_path);
        }

        // Remove all VAPT blocks
        $new_content = preg_replace('/
?# BEGIN VAPT-.*?# END VAPT-.*?
?/s', '', $content);

        if (file_put_contents($htaccess_path, $new_content)) {
            return [
                'status' => 'success',
                'type' => 'remove_all_htaccess',
                'backup_created' => $backup ? $backup_path : false,
                'message' => 'All VAPT rules removed from .htaccess'
            ];
        }

        return [
            'status' => 'error',
            'type' => 'remove_all_htaccess',
            'message' => 'Failed to write .htaccess'
        ];
    }
}
```

---

## 📊 Logging & Reporting

### Audit Trail System

```php
// File: /includes/self-check/class-vapt-audit-log.php

class VAPT_Audit_Log {

    const TABLE_NAME = 'vapt_audit_log';

    /**
     * Log self-check execution
     */
    public static function log_check($trigger, $results) {
        global $wpdb;

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'trigger_event' => $trigger,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'overall_status' => $results->get_overall_status(),
            'checks_passed' => $results->get_passed_count(),
            'checks_failed' => $results->get_failed_count(),
            'checks_warning' => $results->get_warning_count(),
            'corrections_applied' => count($results->get_applied_corrections()),
            'details' => wp_json_encode($results->get_all_results()),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . self::TABLE_NAME, $log_entry);

        // Send notification if critical failures
        if ($results->has_critical_failures()) {
            self::notify_admin($trigger, $results);
        }

        return $wpdb->insert_id;
    }

    /**
     * Send admin notification for critical issues
     */
    private static function notify_admin($trigger, $results) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[VAPTSecure] Critical Issue Detected on %s', $site_name);

        $message = sprintf(
            "A critical issue was detected during the '%s' self-check on %s.

" .
            "Failed Checks: %d
" .
            "Warnings: %d
" .
            "Corrections Applied: %d

" .
            "Please review the audit log in your WordPress admin panel.

" .
            "Domain: https://%s
" .
            "Time: %s",
            $trigger,
            $site_name,
            $results->get_failed_count(),
            $results->get_warning_count(),
            count($results->get_applied_corrections()),
            $_SERVER['HTTP_HOST'] ?? '{domain}',
            current_time('mysql')
        );

        wp_mail($admin_email, $subject, $message);
    }
}
```

---

## 🚫 MANDATORY RULES (Violations = Fail)

### Security Guardrails

1. **NEVER block WordPress admin paths**:
   - `https://{domain}/wp-admin/`
   - `https://{domain}/wp-login.php`
   - `https://{domain}/wp-json/wp/v2/`
   - `https://{domain}/wp-json/vaptsecure/v1/` (custom API)
   - `https://{domain}/admin-ajax.php`
   - `https://{domain}/wp-cron.php`
   - `https://{domain}/xmlrpc.php` (when explicitly enabled)

2. **ALWAYS use .htaccess-safe directives only**:
   - ✅ Allowed: `RewriteEngine`, `RewriteCond`, `RewriteRule`
   - ✅ Allowed: `Header set`, `RequestHeader set`
   - ✅ Allowed: `mod_headers.c` conditional blocks
   - ❌ Forbidden: `TraceEnable`, `<Directory>`, `ServerSignature`
   - ❌ Forbidden: `<Location>`, `<FilesMatch>` (use RewriteCond instead)

3. **MUST insert rules at correct position**:
   - All custom rewrite rules MUST go `before_wordpress_rewrite`
   - WRONG: After `# END WordPress` comment
   - WRONG: Using directives like `<Directory *.php>`
   - CORRECT: Between `# BEGIN WordPress` and the first RewriteRule

4. **MUST wrap in proper modules**:
   ```apache
   <IfModule mod_rewrite.c>
       # Your rewrite rules here
   </IfModule>
   ```

5. **MUST maintain rule block format**:
   - Each rule block MUST have exactly **ONE blank line** before the END marker
   - Format: `

# END VAPT-RISK-{FEATURE-ID}`
   - NO trailing whitespace after last rule line
   - NO extra blank lines within rule content

6. **MUST trigger self-check on critical events**:
   - Plugin deactivation/uninstall
   - License expiration
   - Feature enable/disable
   - Any .htaccess or config modification

---

## 🔒 WordPress-Specific Security Rules

### Core WordPress Endpoints - ALWAYS Whitelist

These endpoints are critical for WordPress functionality and must NEVER be blocked:

| Endpoint | Purpose | Whitelist Pattern |
|----------|---------|-------------------|
| `https://{domain}/wp-admin/` | Admin dashboard | `RewriteCond %{REQUEST_URI} !^/wp-admin/` |
| `https://{domain}/wp-login.php` | Authentication | `RewriteCond %{REQUEST_URI} !^/wp-login.php` |
| `https://{domain}/wp-json/wp/v2/` | Core REST API | `RewriteCond %{REQUEST_URI} !^/wp-json/wp/v2/` |
| `https://{domain}/wp-json/vaptsecure/v1/` | Plugin REST API | `RewriteCond %{REQUEST_URI} !^/wp-json/vaptsecure/v1/` |
| `https://{domain}/admin-ajax.php` | AJAX handler | `RewriteCond %{REQUEST_URI} !^/admin-ajax.php` |
| `https://{domain}/wp-cron.php` | Scheduled tasks | `RewriteCond %{REQUEST_URI} !^/wp-cron.php` |
| `https://{domain}/xmlrpc.php` | XML-RPC API | `RewriteCond %{REQUEST_URI} !^/xmlrpc.php` |
| `https://{domain}/wp-content/uploads/` | Media uploads | `RewriteCond %{REQUEST_URI} !^/wp-content/uploads/` |

### WordPress REST API Security Patterns

```apache
# ✅ CORRECT: Protect REST API while allowing core endpoints
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Whitelist core WordPress endpoints FIRST
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/vaptsecure/v1/ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/oembed/ [NC]
    RewriteRule .* - [L]

    # Block unauthorized access to other REST endpoints
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/ [NC]
    RewriteCond %{REQUEST_URI} ^/wp-json/ [NC]
    RewriteRule .* - [F,L]
</IfModule>
```

### Admin AJAX Protection

```apache
# ✅ CORRECT: Rate limit admin-ajax.php while maintaining functionality
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Skip rate limiting for logged-in users (cookie check)
    RewriteCond %{HTTP_COOKIE} wordpress_logged_in [NC]
    RewriteRule ^admin-ajax.php$ - [L]

    # Rate limit anonymous requests to admin-ajax.php
    RewriteCond %{REQUEST_URI} ^/admin-ajax.php$ [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/ [NC]
    RewriteRule .* - [F,L]
</IfModule>
```

### WordPress File Protection

```apache
# ✅ CORRECT: Protect sensitive WordPress files
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Block access to sensitive files but allow necessary access
    RewriteCond %{REQUEST_URI} ^/wp-config.php$ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/.htaccess$ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/readme.html$ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/license.txt$ [NC]
    RewriteRule .* - [F,L]

    # Allow access to wp-content but block PHP execution in uploads
    RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\.php$ [NC]
    RewriteRule .* - [F,L]
</IfModule>
```

### WordPress Hardening - wp-includes Protection

```apache
# ✅ CORRECT: Block direct access to wp-includes PHP files
# Place OUTSIDE # BEGIN WordPress / # END WordPress tags
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Block wp-admin/includes directory
    RewriteRule ^wp-admin/includes/ - [F,L]

    # Skip if not wp-includes
    RewriteRule !^wp-includes/ - [S=3]

    # Block PHP files in wp-includes root
    RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]

    # Block tinymce language PHP files
    RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]

    # Block theme-compat directory
    RewriteRule ^wp-includes/theme-compat/ - [F,L]
</IfModule>
```

### XML-RPC Control

```apache
# ✅ CORRECT: Conditionally block XML-RPC
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Block xmlrpc.php if not explicitly enabled
    RewriteCond %{REQUEST_URI} ^/xmlrpc.php$ [NC]
    RewriteCond %{HTTP_COOKIE} !wordpress_logged_in [NC]
    RewriteRule .* - [F,L]
</IfModule>
```

---

## 📋 Feature Lifecycle Rules

### Draft → Develop Transition
When a feature moves from Draft to Develop:
1. Verify all required dependencies exist
2. Apply necessary .htaccess rules for testing
3. Set up feature-specific database tables
4. Enable debug logging for this feature
5. **Test WordPress core endpoints remain accessible** at `https://{domain}/wp-json/wp/v2/`
6. **Trigger self-check**: `VAPT_Self_Check::run('feature_enable', ['feature_id' => $id])`

### Develop → Deploy Transition
Before deployment:
1. Run all validation workflows
2. Ensure no debug logging is enabled
3. Verify security rules don't conflict
4. Test REST API endpoints remain accessible
5. **Verify admin-ajax.php functionality** at `https://{domain}/admin-ajax.php`
6. **Run full self-check suite** before final deploy

### Deploy → Reset to Draft
**CRITICAL**: When "Confirm Reset (Wipe Data)" is clicked:
1. **Trigger self-check**: `VAPT_Self_Check::run('feature_disable', ['feature_id' => $id])`
2. **Remove ALL .htaccess rules** added by this feature
3. **Wipe feature data** from database tables
4. **Remove generated configs** in `/data/generated/`
5. **Log operation** to `vapt_feature_history@Draft`
6. **Add audit trail entry** with timestamp and user

#### Specific Actions for "Reset to Draft":
```javascript
// On Confirm Reset (Wipe Data)
actions:
  - trigger_self_check: {
      event: 'feature_disable',
      feature_id: '{FEATURE-ID}',
      auto_correct: true
    }
  - remove_htaccess_rules: {
      scope: "feature-specific",
      backup_before_remove: true,
      patterns: [
        "# BEGIN VAPT-RISK-{FEATURE-ID}",
        "# END VAPT-RISK-{FEATURE-ID}"
      ]
    }
  - wipe_feature_data: {
      tables: ["wp_vapt_features", "wp_vapt_feature_meta"],
      feature_id: "{FEATURE-ID}",
      cascade: true
    }
  - remove_config_files: {
      path: "data/generated/{FEATURE-ID}/",
      archive: false
    }
  - log_operation: {
      level: "info",
      category: "feature_lifecycle",
      action: "reset_to_draft",
      user_id: "{CURRENT_USER_ID}"
    }
  - update_feature_state: {
      feature_id: "{FEATURE-ID}",
      new_state: "Draft",
      previous_state: "Develop"
    }
```

---

## 🔧 Technical Constraints

### JSON Schema Requirements
1. All feature JSON must validate against `/data/VAPTSchema-Builder/`
2. Use `interface_schema_v2.0.json` as blueprint
3. Follow `ai_agent_instructions_v2.0.json` for formatting
4. Interface MUST include:
   - Proper component keys matching `enforcer_pattern_library_v2.0.json`
   - UI layout definitions
   - Severity classifications
   - Platform availability flags

### Code Generation
1. **ALWAYS reference the enforcer library** - never write from memory
2. **Use the 4-step workflow**: Rulebook → Blueprint → Enforcement → Self-Check
3. **Score output against 19-point rubric** before delivering
4. **Maintain naming conventions**: `UI-RISK-XXX-YYY` format
5. **Use `{domain}` placeholder** for all domain references

### Domain Runtime Replacement
```php
// Example: Runtime domain replacement in PHP
$domain = get_site_url(); // Returns https://example.com
$htaccess_rules = str_replace('{domain}', $domain, $generated_rules);
```

### Self-Check Integration
```php
// Example: Trigger self-check after .htaccess modification
function vapt_htaccess_write($rules, $feature_id) {
    // Write rules to .htaccess...

    // Trigger self-check
    $result = VAPT_Self_Check::run('htaccess_modify', [
        'feature_id' => $feature_id,
        'rules_added' => $rules
    ]);

    // Return check results to caller
    return $result;
}
```

---

## 💬 Communication Style

### When Responding:
1. **Be concise and direct** - avoid unnecessary qualifiers
2. **Provide working code** - not pseudocode or suggestions
3. **Include security context** - explain the "why" for security rules
4. **Reference documentation** - point to relevant JSON files
5. **Use `{domain}` placeholder** - never use example.com or yoursite.com

### Code Examples:
```apache
# ✅ CORRECT: Before WordPress rewrite with domain placeholder
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Whitelist WordPress core endpoints
    RewriteCond %{REQUEST_URI} ^/wp-admin/ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-login.php$ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/ [NC,OR]
    RewriteCond %{REQUEST_URI} ^/admin-ajax.php$ [NC]
    RewriteRule .* - [L]

    # Your security rules here
    RewriteCond %{HTTP_USER_AGENT} ^.*(BadBot|Malicious).*$
    RewriteRule .* - [F,L]
</IfModule>

# ❌ INCORRECT: After WordPress rewrite
RewriteEngine On  # Wrong position
```

---

## 🎓 Domain Expertise Areas

1. **Apache .htaccess configurations** - mod_rewrite, mod_headers
2. **WordPress security best practices** - core protection, REST API security
3. **Vulnerability catalogs** - [OWASP Top 10](https://owasp.org/Top10/), [NIST guidelines](https://www.nist.gov/cyberframework)
4. **JSON schema validation** - VAPT interface schemas
5. **Feature lifecycle management** - Draft → Develop → Deploy → Reset
6. **WordPress REST API** - [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
7. **Self-check automation** - Event-driven validation and auto-correction

---

## 🔍 Troubleshooting

### Common Issues:

1. **500 Errors after .htaccess modification**:
   - Check for syntax errors in RewriteCond/RewriteRule
   - Verify `insertion_point` is `before_wordpress_rewrite`
   - Ensure no forbidden directives are used
   - Test at: `https://{domain}/wp-json/wp/v2/`
   - **Run self-check**: Check rewrite syntax validation

2. **REST API blocked**:
   - Verify `https://{domain}/wp-json/` paths are whitelisted
   - Check for overly broad blocking rules
   - Test with: `curl https://{domain}/wp-json/wp/v2/posts`
   - **Self-check auto-correct**: May add missing whitelist rules

3. **admin-ajax.php returning 403**:
   - Check referer-based blocking rules
   - Verify cookie-based whitelisting for logged-in users
   - Test AJAX functionality in WordPress admin
   - **Self-check**: Validates cookie detection patterns

4. **Rule block format violations**:
   - Ensure exactly one blank line before END marker
   - Check for trailing whitespace
   - **Self-check auto-correct**: Will fix blank line formatting

5. **Feature reset incomplete**:
   - Verify all `.htaccess` markers are removed
   - Check for orphaned database entries
   - Review log for failed operations
   - **Self-check**: Validates complete cleanup on uninstall

### WordPress Endpoint Testing Commands
```bash
# Test core REST API
curl -I https://{domain}/wp-json/wp/v2/

# Test admin AJAX
curl -I https://{domain}/admin-ajax.php

# Test login page
curl -I https://{domain}/wp-login.php

# Test custom API
curl -I https://{domain}/wp-json/vaptsecure/v1/
```

### Self-Check Manual Trigger
```php
// Run self-check manually from code
$result = VAPT_Self_Check::run('manual_trigger', [
    'requested_by' => get_current_user_id(),
    'checks' => ['all'] // or specific checks
]);

// Display results
if ($result->has_failures()) {
    echo "Issues found: " . $result->get_failed_count();
    print_r($result->get_failures());
}
```

---

## 📚 Resources

- [VAPT AI Agent Instructions](../../data/ai_agent_instructions_v2.0.json)
- [Interface Schema](../../data/interface_schema_v2.0.json)
- [Enforcer Pattern Library](../../data/enforcer_pattern_library_v2.0.json)
- [VAPTSchema Builder Skill](skills/vapt-expert/SKILL.md)
- [WordPress REST API Documentation](https://developer.wordpress.org/rest-api/)
- [WordPress Security Handbook](https://developer.wordpress.org/apis/security/)
- [Apache mod_rewrite Documentation](https://httpd.apache.org/docs/current/mod/mod_rewrite.html)
- [WordPress Hardening Guide](https://developer.wordpress.org/advanced-administration/security/hardening/)
- [WordPress Plugin Deactivation/Deletion Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)

---

*This SOUL.md defines the universal AI behavior for the VAPTSecure plugin project.*
*Edit this file to change AI behavior across ALL editors (Cursor, Claude, Gemini).*
