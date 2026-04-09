# SOUL.md — Universal AI Configuration for VAPTSecure Plugin

> **⚠️ CRITICAL DOCUMENT**
> This file is the **single source of truth** for all AI agent behavior in the VAPTSecure plugin project.
>
> **Symlinked to ALL editors via:**
> | Editor | Symlink Path | Type |
> |--------|-------------|------|
> | Cursor | `.cursor/cursor.rules → .ai/rules/cursor.rules → SOUL.md` | Rules file |
> | Gemini / Antigravity | `.gemini/gemini.md → .ai/rules/gemini.md → SOUL.md` | Rules file |
> | Claude Code | `.claude/settings.json → .ai/rules/claude-settings.json` | Settings |
> | Qoder | `.qoder/qoder.rules → .ai/SOUL.md` | Rules file |
> | Trae | `.trae/trae.rules → .ai/SOUL.md` | Rules file |
> | Windsurf | `.windsurfrules → .ai/SOUL.md` | Rules file |
> | VS Code | `.vscode/settings.json` | Editor settings only (no symlink) |
>
> **Edit this file once — changes propagate to ALL editors automatically.**

---

## 🎯 Core Identity

**You are an AI agent specialized in WordPress security hardening and VAPT (Vulnerability Assessment & Penetration Testing) implementation.**

Your primary role is to:
1. Generate secure, production-ready security configurations
2. Ensure WordPress core, REST API, and admin endpoints remain fully accessible
3. Follow strict security best practices for `.htaccess` and server configurations
4. Maintain backward compatibility with existing plugin features
5. **Execute self-check automations** on all critical system lifecycle events
6. **Never hardcode domain names** — always use `{domain}` placeholder resolved at runtime

---

## 🏗️ Project Context

**Project**: VAPTSecure WordPress Plugin
**Version**: 2.4.11
**Domain**: WordPress Security & Vulnerability Management
**Architecture**: Plugin-based with REST API integration

### Key Directories

| Directory | Purpose |
|-----------|---------|
| `/includes/` | Core plugin functionality |
| `/includes/self-check/` | Self-check automation engine |
| `/assets/` | Frontend assets (CSS, JS) |
| `/data/` | Vulnerability catalog and JSON configs |
| `/data/generated/` | Runtime-generated feature configs |
| `/data/VAPTSchema-Builder/` | JSON schema definitions |
| `/deployment/` | Client deployment configurations |
| `/.agent/` | Legacy AI agent configuration |
| `/.ai/` | **Universal AI configuration (active standard)** |
| `/.ai/skills/vapt-expert/` | VAPT Security Expert skill |
| `/.ai/skills/security-auditor/` | Security Audit skill |
| `/.ai/workflows/` | Reusable automation workflows |
| `/.ai/rules/` | Editor-specific rule symlinks |

### Symlink Registry (mirrors README.md)

```
.ai/
├── SOUL.md                          ← THIS FILE (single source of truth)
├── AGENTS.md                        ← Multi-agent orchestration
├── skills/
│   ├── vapt-expert/SKILL.md
│   └── security-auditor/SKILL.md
├── workflows/
│   ├── security-scan.yml
│   ├── reset-to-draft.yml
│   └── validation.yml
└── rules/
    ├── cursor.rules  → ../SOUL.md
    └── gemini.md     → ../SOUL.md

.cursor/
├── skills/           → ../../.ai/skills/
└── cursor.rules      → ../.ai/rules/cursor.rules

.gemini/
├── antigravity/skills/ → ../../../.ai/skills/
└── gemini.md           → ../.ai/rules/gemini.md

.claude/
├── skills/           → ../../.ai/skills/
└── settings.json     → ../.ai/rules/claude-settings.json

.qoder/
├── skills/           → ../../.ai/skills/
└── qoder.rules       → ../.ai/SOUL.md

.trae/
├── skills/           → ../../.ai/skills/
└── trae.rules        → ../.ai/SOUL.md

.windsurf/
└── skills/           → ../../.ai/skills/
.windsurfrules        → .ai/SOUL.md

.vscode/
└── settings.json     (editor settings only — no symlink to SOUL.md)
```

---

## 🌐 Domain Placeholder System

**CRITICAL**: All generated configurations MUST use the `{domain}` placeholder instead of any hardcoded domain. The plugin resolves `{domain}` to the actual site URL at runtime.

### Placeholder Rules

1. **Use `{domain}`** for every domain reference in generated code, configs, and documentation examples
2. **Runtime replacement**: `str_replace('{domain}', get_site_url(), $rules)`
3. **FQDN requirement**: All URLs must be Fully Qualified Domain Names with `https://` scheme
4. **Clickable links**: Every documentation URL must be a valid, clickable `https://` link

### URL Format Standard

```
✅ CORRECT (FQDN with placeholder):
  https://{domain}/wp-admin/
  https://{domain}/wp-json/wp/v2/
  https://{domain}/wp-login.php
  https://{domain}/wp-admin/admin-ajax.php
  https://{domain}/wp-json/vaptsecure/v1/

❌ INCORRECT:
  yoursite.com/wp-admin/            ← no scheme, no placeholder
  http://example.com/wp-json/       ← hardcoded domain, wrong scheme
  /wp-admin/                        ← relative path forbidden in security rules
  example.com                       ← no scheme, no placeholder
```

### PHP Runtime Replacement Pattern

```php
// Runtime domain resolution — ALWAYS use this pattern
$domain     = get_site_url();          // e.g. https://example.com
$admin_url  = admin_url();             // e.g. https://example.com/wp-admin/
$rest_url   = rest_url('wp/v2/');      // e.g. https://example.com/wp-json/wp/v2/

// Replace placeholder in generated .htaccess rules
$rules = str_replace('{domain}', wp_parse_url($domain, PHP_URL_HOST), $rules);
```

---

## 🔄 Self-Check Automation System

**CRITICAL**: The VAPTSecure plugin includes an automated self-check engine that validates system integrity and performs corrective actions **without manual intervention**. It fires automatically on all lifecycle events listed below.

### Trigger Event Registry

| Event | WordPress Hook / Function | Priority | Auto-Correct |
|-------|--------------------------|----------|--------------|
| **Plugin Deactivation** | `register_deactivation_hook()` | CRITICAL | Yes |
| **Plugin Uninstall / Removal** | `register_uninstall_hook()` | CRITICAL | Yes |
| **License Expiration** | `vapt_license_check` WP-Cron | HIGH | Partial |
| **Feature Enabled** | `vapt_feature_enable($id)` | HIGH | Yes |
| **Feature Disabled** | `vapt_feature_disable($id)` | HIGH | Yes |
| **`.htaccess` Rule Added** | `vapt_htaccess_write()` | MEDIUM | Yes |
| **`.htaccess` Rule Removed** | `vapt_htaccess_remove()` | MEDIUM | Yes |
| **Config File Updated** | `vapt_config_save()` | MEDIUM | Yes |
| **Daily Health Check** | `vapt_daily_self_check` WP-Cron | LOW | Yes |
| **Manual Diagnostics** | Admin "Run Diagnostics" button | ON-DEMAND | Optional |

### Hook Registration (plugin bootstrap)

```php
// File: vaptsecure.php (main plugin file)

register_activation_hook(   __FILE__, ['VAPT_Lifecycle', 'on_activate']   );
register_deactivation_hook( __FILE__, ['VAPT_Lifecycle', 'on_deactivate'] );
register_uninstall_hook(    __FILE__, ['VAPT_Lifecycle', 'on_uninstall']  );

add_action('vapt_feature_enabled',  function($id) {
    VAPT_Self_Check::run('feature_enable',  ['feature_id' => $id]);
});
add_action('vapt_feature_disabled', function($id) {
    VAPT_Self_Check::run('feature_disable', ['feature_id' => $id]);
});
add_action('vapt_license_expired',  function() {
    VAPT_Self_Check::run('license_expire');
});
```

---

## 🛡️ Self-Check Engine Architecture

### Core Class

```php
// File: /includes/self-check/class-vapt-self-check.php

class VAPT_Self_Check {

    /**
     * Trigger self-check automation
     *
     * @param string $trigger_event  Event that triggered this check
     * @param array  $context        Additional context data
     * @return VAPT_Self_Check_Result
     */
    public static function run( string $trigger_event, array $context = [] ): VAPT_Self_Check_Result {
        $engine            = new self();
        $engine->trigger   = $trigger_event;
        $engine->context   = $context;
        $engine->timestamp = current_time('mysql');

        return $engine->execute_checks();
    }

    /**
     * Execute all validation checks based on trigger type
     */
    private function execute_checks(): VAPT_Self_Check_Result {
        $results = new VAPT_Self_Check_Result();

        // ── Always-run baseline checks ─────────────────────────────────────
        $results->add( $this->check_htaccess_integrity()     );
        $results->add( $this->check_wordpress_endpoints()    );
        $results->add( $this->check_file_permissions()       );

        // ── Event-specific checks ──────────────────────────────────────────
        switch ( $this->trigger ) {

            case 'plugin_deactivate':
                $results->add( $this->check_cleanup_required()      );
                $results->add( $this->check_htaccess_rules_removal() );
                break;

            case 'plugin_uninstall':
                $results->add( $this->check_complete_cleanup()  );
                $results->add( $this->check_database_tables()   );
                break;

            case 'license_expire':
                $results->add( $this->check_license_degradation()   );
                $results->add( $this->check_feature_deactivation()  );
                break;

            case 'feature_enable':
            case 'feature_disable':
                $results->add( $this->check_feature_consistency()   );
                $results->add( $this->check_rule_block_format()     );
                $results->add( $this->check_wordpress_endpoints()   ); // re-run after change
                break;

            case 'htaccess_modify':
                $results->add( $this->check_rule_block_format()         );
                $results->add( $this->check_rewrite_syntax()            );
                $results->add( $this->check_blank_line_requirement()    );
                $results->add( $this->check_wordpress_whitelist_rules() ); // WP-specific
                break;

            case 'config_update':
                $results->add( $this->check_json_validity()     );
                $results->add( $this->check_schema_compliance() );
                break;
        }

        // ── Auto-correct if enabled ────────────────────────────────────────
        if ( get_option('vapt_auto_correct', true) ) {
            $results->apply_corrections();
        }

        // ── Log everything ─────────────────────────────────────────────────
        VAPT_Audit_Log::log_check( $this->trigger, $results );

        return $results;
    }
}
```

---

## 🔍 Self-Check Validation Rules

### 1. `.htaccess` Integrity Check

```php
/**
 * Validates .htaccess file structure and VAPT block markers
 */
public function check_htaccess_integrity(): VAPT_Check_Item {
    $htaccess_path = ABSPATH . '.htaccess';
    $issues        = [];

    if ( ! file_exists( $htaccess_path ) ) {
        return new VAPT_Check_Item( 'htaccess_exists', 'warning',
            '.htaccess file does not exist' );
    }

    $content = file_get_contents( $htaccess_path );

    // ── Check for orphaned VAPT markers ───────────────────────────────────
    preg_match_all( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $begin_matches );
    preg_match_all( '/# END VAPT-RISK-([a-z0-9-]+)/',   $content, $end_matches   );

    $orphaned_begin = array_diff( $begin_matches[1], $end_matches[1] );
    $orphaned_end   = array_diff( $end_matches[1],   $begin_matches[1] );

    if ( ! empty( $orphaned_begin ) ) {
        $issues[] = 'Orphaned BEGIN markers: ' . implode( ', ', $orphaned_begin );
    }
    if ( ! empty( $orphaned_end ) ) {
        $issues[] = 'Orphaned END markers: ' . implode( ', ', $orphaned_end );
    }

    // ── Check each block ends with exactly one blank line ─────────────────
    foreach ( $begin_matches[1] as $feature_id ) {
        $id      = preg_quote( $feature_id, '/' );
        $pattern = "/# BEGIN VAPT-RISK-{$id}\n(.*?)\n# END VAPT-RISK-{$id}/s";

        if ( preg_match( $pattern, $content, $block ) ) {
            if ( ! preg_match( "/\n\n$/", $block[1] ) ) {
                $issues[] = "Feature {$feature_id}: Missing exactly one blank line before END marker";
            }
        }
    }

    return new VAPT_Check_Item(
        'htaccess_integrity',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'All markers valid' : implode( '; ', $issues ),
        $issues
    );
}
```

### 2. Rule Block Format Check

```php
/**
 * Validates each rule block has EXACTLY ONE blank line at the end (before END marker)
 *
 * CRITICAL FORMAT CONTRACT:
 *   # BEGIN VAPT-RISK-{FEATURE-ID}
 *   RewriteCond ...
 *   RewriteRule ...
 *                        ← exactly one blank line here
 *   # END VAPT-RISK-{FEATURE-ID}
 *                        ← exactly one blank line after END marker (between blocks)
 */
public function check_rule_block_format(): VAPT_Check_Item {
    $htaccess_path = ABSPATH . '.htaccess';
    $content       = file_get_contents( $htaccess_path );
    $issues        = [];
    $corrections   = [];

    preg_match_all(
        '/(# BEGIN VAPT-RISK-[a-z0-9-]+\n)(.*?)(\n# END VAPT-RISK-[a-z0-9-]+)/s',
        $content, $blocks, PREG_SET_ORDER
    );

    foreach ( $blocks as $block ) {
        $begin_marker = $block[1];
        $rule_content = $block[2];
        $end_marker   = $block[3];

        preg_match( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $begin_marker, $id_match );
        $feature_id = $id_match[1];

        // Check 1: Block must end with exactly one blank line (two newlines) before END marker
        if ( ! preg_match( '/\n\n$/', $rule_content ) ) {
            $issues[]      = "{$feature_id}: Must have exactly one blank line before END marker";
            $corrections[] = [
                'type'        => 'fix_blank_line',
                'feature_id'  => $feature_id,
                'description' => 'Ensure exactly one blank line before END marker',
            ];
        }

        // Check 2: Must NOT have more than one blank line (no double-blanks inside block)
        if ( preg_match( '/\n{3,}/', $rule_content ) ) {
            $issues[]      = "{$feature_id}: Multiple consecutive blank lines detected inside block";
            $corrections[] = [
                'type'        => 'collapse_blank_lines',
                'feature_id'  => $feature_id,
                'description' => 'Collapse multiple blank lines to single blank line',
            ];
        }

        // Check 3: No trailing whitespace on the last rule line
        $lines             = explode( "\n", rtrim( $rule_content ) );
        $last_content_line = end( $lines );
        if ( preg_match( '/\s+$/', $last_content_line ) ) {
            $issues[]      = "{$feature_id}: Trailing whitespace on last rule line";
            $corrections[] = [
                'type'        => 'trim_whitespace',
                'feature_id'  => $feature_id,
                'description' => 'Remove trailing whitespace from last rule line',
            ];
        }
    }

    return new VAPT_Check_Item(
        'rule_block_format',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'All rule blocks properly formatted' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 3. WordPress Endpoints Accessibility Check

```php
/**
 * Validates that critical WordPress endpoints remain accessible after any rule change.
 * Fires on: plugin_deactivate, feature_enable, feature_disable, htaccess_modify
 */
public function check_wordpress_endpoints(): VAPT_Check_Item {
    $base      = get_site_url();
    $endpoints = [
        'wp-admin'    => '/wp-admin/',
        'wp-login'    => '/wp-login.php',
        'rest-api-v2' => '/wp-json/wp/v2/',
        'rest-oembed' => '/wp-json/oembed/1.0/',
        'vapt-api'    => '/wp-json/vaptsecure/v1/',
        'admin-ajax'  => '/wp-admin/admin-ajax.php',
        'wp-cron'     => '/wp-cron.php',
    ];

    $issues      = [];
    $corrections = [];

    foreach ( $endpoints as $name => $path ) {
        $url      = $base . $path;
        $response = wp_remote_head( $url, [ 'timeout' => 5, 'sslverify' => false ] );

        if ( is_wp_error( $response ) ) {
            $issues[] = "{$name}: Connection error — " . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code( $response );

            // 401 = requires auth (expected for admin), 200/302 = accessible
            if ( $code >= 400 && $code !== 401 ) {
                $issues[]      = "{$name}: HTTP {$code} — endpoint may be blocked";
                $corrections[] = [
                    'type'     => 'add_whitelist',
                    'endpoint' => $path,
                    'rule'     => "RewriteCond %{REQUEST_URI} !^" . rtrim( $path, '/' ) . " [NC]",
                    'priority' => 'high',
                ];
            }
        }
    }

    return new VAPT_Check_Item(
        'wordpress_endpoints',
        empty( $issues ) ? 'pass' : 'warning',
        empty( $issues ) ? 'All WordPress endpoints accessible' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 4. WordPress Whitelist Rules Check

```php
/**
 * Validates that every VAPT rule block contains the mandatory WordPress whitelist header.
 * Prevents a rule block from accidentally blocking WP core functionality.
 */
public function check_wordpress_whitelist_rules(): VAPT_Check_Item {
    $htaccess_path  = ABSPATH . '.htaccess';
    $content        = file_get_contents( $htaccess_path );
    $issues         = [];
    $corrections    = [];

    // Mandatory whitelist conditions that must appear before any deny/redirect rule
    $required_whitelists = [
        '/wp-admin/'               => "RewriteCond %{REQUEST_URI} !^/wp-admin/",
        '/wp-login.php'            => "RewriteCond %{REQUEST_URI} !^/wp-login\\.php",
        '/wp-json/'                => "RewriteCond %{REQUEST_URI} !^/wp-json/",
        '/wp-admin/admin-ajax.php' => "RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\\.php",
        '/wp-cron.php'             => "RewriteCond %{REQUEST_URI} !^/wp-cron\\.php",
    ];

    preg_match_all(
        '/(# BEGIN VAPT-RISK-[a-z0-9-]+\n)(.*?)(\n# END VAPT-RISK-[a-z0-9-]+)/s',
        $content, $blocks, PREG_SET_ORDER
    );

    foreach ( $blocks as $block ) {
        $block_content = $block[2];
        preg_match( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $block[1], $id_match );
        $feature_id = $id_match[1];

        // Only check blocks that contain deny/redirect/forbidden rules
        if ( ! preg_match( '/RewriteRule.*\[.*F.*\]/i', $block_content ) &&
             ! preg_match( '/RewriteRule.*\[.*R=4/i', $block_content ) ) {
            continue;
        }

        foreach ( $required_whitelists as $endpoint => $expected_cond ) {
            if ( strpos( $block_content, $expected_cond ) === false ) {
                $issues[]      = "{$feature_id}: Missing whitelist for {$endpoint}";
                $corrections[] = [
                    'type'        => 'add_whitelist',
                    'feature_id'  => $feature_id,
                    'endpoint'    => $endpoint,
                    'rule'        => $expected_cond,
                    'priority'    => 'critical',
                ];
            }
        }
    }

    return new VAPT_Check_Item(
        'wordpress_whitelist_rules',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'All blocks contain WordPress whitelist rules' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 5. Plugin Deactivation Cleanup Check

```php
/**
 * Validates cleanup requirements when plugin is deactivated.
 * Data is preserved for reactivation; only .htaccess rules are removed.
 */
public function check_cleanup_required(): VAPT_Check_Item {
    $active_features = get_option( 'vapt_active_features', [] );
    $htaccess_path   = ABSPATH . '.htaccess';
    $issues          = [];
    $corrections     = [];

    if ( ! empty( $active_features ) ) {
        $issues[] = count( $active_features ) . ' features still active during deactivation';

        foreach ( $active_features as $feature_id ) {
            $corrections[] = [
                'type'            => 'disable_feature',
                'feature_id'      => $feature_id,
                'action'          => 'reset_to_draft',
                'remove_htaccess' => true,
                'wipe_data'       => false, // preserve data for reactivation
            ];
        }
    }

    if ( file_exists( $htaccess_path ) ) {
        $content = file_get_contents( $htaccess_path );
        if ( strpos( $content, '# BEGIN VAPT-' ) !== false ) {
            $issues[]      = 'VAPT .htaccess rules still present';
            $corrections[] = [
                'type'        => 'remove_all_htaccess',
                'backup'      => true,
                'description' => 'Remove all VAPT rule blocks from .htaccess',
            ];
        }
    }

    return new VAPT_Check_Item(
        'deactivation_cleanup',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'Cleanup not required' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 6. Plugin Uninstall Complete Cleanup Check

```php
/**
 * Validates complete cleanup when plugin is removed from the site.
 * All data, tables, options, generated files, and .htaccess rules are purged.
 */
public function check_complete_cleanup(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    // ── Database tables ───────────────────────────────────────────────────
    $tables = $wpdb->get_results( "SHOW TABLES LIKE 'wp_vapt_%'", ARRAY_N );
    if ( ! empty( $tables ) ) {
        $table_names = array_column( $tables, 0 );
        $issues[]    = 'Database tables remaining: ' . implode( ', ', $table_names );
        foreach ( $table_names as $table ) {
            $corrections[] = [ 'type' => 'drop_table', 'table' => $table, 'backup' => true ];
        }
    }

    // ── wp_options entries ────────────────────────────────────────────────
    $options = $wpdb->get_results(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vapt_%'"
    );
    if ( ! empty( $options ) ) {
        $option_names = array_column( $options, 'option_name' );
        $issues[]     = 'Options remaining: ' . implode( ', ', $option_names );
        foreach ( $option_names as $option ) {
            $corrections[] = [ 'type' => 'delete_option', 'option' => $option ];
        }
    }

    // ── Generated files ───────────────────────────────────────────────────
    $generated_dir = VAPT_PLUGIN_DIR . 'data/generated/';
    if ( is_dir( $generated_dir ) && ! empty( glob( $generated_dir . '*' ) ) ) {
        $issues[]      = 'Generated config files remaining in data/generated/';
        $corrections[] = [
            'type'      => 'remove_directory',
            'path'      => $generated_dir,
            'recursive' => true,
        ];
    }

    // ── .htaccess rules ───────────────────────────────────────────────────
    $htaccess_path = ABSPATH . '.htaccess';
    if ( file_exists( $htaccess_path ) ) {
        $content = file_get_contents( $htaccess_path );
        if ( strpos( $content, '# BEGIN VAPT-' ) !== false ) {
            $issues[]      = 'VAPT .htaccess rules still present after uninstall';
            $corrections[] = [
                'type'   => 'remove_all_htaccess',
                'backup' => false, // no backup on full uninstall
            ];
        }
    }

    return new VAPT_Check_Item(
        'uninstall_cleanup',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'Complete cleanup verified' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 7. License Expiration & Feature Degradation Check

```php
/**
 * Degrades or disables premium features when license expires.
 * Sends admin notification and logs the event.
 */
public function check_license_degradation(): VAPT_Check_Item {
    $license_status  = get_option( 'vapt_license_status' );
    $active_features = get_option( 'vapt_active_features', [] );
    $premium_features = get_option( 'vapt_premium_features', [] );
    $issues           = [];
    $corrections      = [];

    if ( $license_status !== 'expired' ) {
        return new VAPT_Check_Item( 'license_degradation', 'pass', 'License valid' );
    }

    $active_premium = array_intersect( $active_features, $premium_features );

    if ( ! empty( $active_premium ) ) {
        $issues[] = 'Premium features active with expired license: ' . implode( ', ', $active_premium );

        foreach ( $active_premium as $feature_id ) {
            $corrections[] = [
                'type'         => 'degrade_feature',
                'feature_id'   => $feature_id,
                'action'       => 'disable_or_free_tier',
                'notify_admin' => true,
                'message'      => "Feature {$feature_id} disabled — license expired",
            ];
        }
    }

    return new VAPT_Check_Item(
        'license_degradation',
        empty( $issues ) ? 'pass' : 'warning',
        empty( $issues ) ? 'License degradation handled' : implode( '; ', $issues ),
        $corrections
    );
}
```

### 8. Feature Consistency Check

```php
/**
 * Validates feature state is consistent across: database, wp_options, and .htaccess.
 */
public function check_feature_consistency(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    $db_features      = $wpdb->get_col( "SELECT feature_id FROM {$wpdb->prefix}vapt_features WHERE status = 'active'" );
    $option_features  = get_option( 'vapt_active_features', [] );
    $htaccess_features = $this->get_htaccess_feature_ids();

    // ── DB vs Options ─────────────────────────────────────────────────────
    foreach ( array_diff( $db_features, $option_features ) as $id ) {
        $issues[] = "Feature in DB but not options: {$id}";
        $corrections[] = [ 'type' => 'sync_to_options', 'feature_id' => $id, 'action' => 'add_to_active_features' ];
    }
    foreach ( array_diff( $option_features, $db_features ) as $id ) {
        $issues[] = "Feature in options but not DB: {$id}";
        $corrections[] = [ 'type' => 'sync_from_options', 'feature_id' => $id, 'action' => 'remove_from_active_features' ];
    }

    // ── .htaccess vs DB ───────────────────────────────────────────────────
    foreach ( array_diff( $htaccess_features, $db_features ) as $id ) {
        $issues[] = "Orphaned .htaccess rules for inactive feature: {$id}";
        $corrections[] = [ 'type' => 'remove_htaccess_rules', 'feature_id' => $id, 'reason' => 'Feature not active' ];
    }
    foreach ( array_diff( $db_features, $htaccess_features ) as $id ) {
        $issues[] = "Active feature missing .htaccess rules: {$id}";
        $corrections[] = [ 'type' => 'add_htaccess_rules', 'feature_id' => $id, 'reason' => 'Feature active but no rules' ];
    }

    return new VAPT_Check_Item(
        'feature_consistency',
        empty( $issues ) ? 'pass' : 'fail',
        empty( $issues ) ? 'All features consistent' : implode( '; ', $issues ),
        $corrections
    );
}

private function get_htaccess_feature_ids(): array {
    $htaccess_path = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        return [];
    }
    $content = file_get_contents( $htaccess_path );
    preg_match_all( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $matches );
    return $matches[1] ?? [];
}
```

### 9. File Permissions Check

```php
/**
 * Validates WordPress file permissions are within secure bounds.
 */
public function check_file_permissions(): VAPT_Check_Item {
    $checks = [
        [ 'path' => ABSPATH . 'wp-config.php', 'max' => 0640, 'expected' => 0600 ],
        [ 'path' => ABSPATH . '.htaccess',      'max' => 0644, 'expected' => 0644 ],
        [ 'path' => WP_CONTENT_DIR,             'max' => 0755, 'expected' => 0755 ],
        [ 'path' => ABSPATH . 'wp-admin/',      'max' => 0755, 'expected' => 0755 ],
    ];

    $issues      = [];
    $corrections = [];

    foreach ( $checks as $check ) {
        if ( ! file_exists( $check['path'] ) ) {
            continue;
        }
        $current = fileperms( $check['path'] ) & 0777;
        if ( $current > $check['max'] ) {
            $issues[]      = sprintf( '%s: %04o exceeds max %04o', basename( $check['path'] ), $current, $check['max'] );
            $corrections[] = [
                'type'        => 'fix_permission',
                'path'        => $check['path'],
                'current'     => $current,
                'recommended' => $check['expected'],
                'chmod'       => $check['expected'],
            ];
        }
    }

    // ── World-writable files in uploads ───────────────────────────────────
    $uploads_dir = wp_upload_dir()['basedir'];
    if ( is_dir( $uploads_dir ) ) {
        $world_writable = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( $file->isFile() && ( $file->getPerms() & 0002 ) ) {
                $world_writable[] = $file->getPathname();
                if ( count( $world_writable ) >= 10 ) { break; }
            }
        }
        if ( ! empty( $world_writable ) ) {
            $issues[]      = 'World-writable files in uploads: ' . count( $world_writable );
            $corrections[] = [ 'type' => 'fix_uploads_permissions', 'files' => $world_writable, 'chmod' => 0644 ];
        }
    }

    return new VAPT_Check_Item(
        'file_permissions',
        empty( $issues ) ? 'pass' : 'warning',
        empty( $issues ) ? 'File permissions valid' : implode( '; ', $issues ),
        $corrections
    );
}
```

---

## 🔧 Auto-Correction Engine

```php
// File: /includes/self-check/class-vapt-auto-correct.php

class VAPT_Auto_Correct {

    public function apply( array $corrections ): array {
        $results = [];

        foreach ( $corrections as $correction ) {
            try {
                $results[] = match ( $correction['type'] ) {
                    'fix_blank_line'        => $this->fix_blank_line( $correction ),
                    'collapse_blank_lines'  => $this->collapse_blank_lines( $correction ),
                    'add_whitelist'         => $this->add_whitelist_rule( $correction ),
                    'remove_htaccess_rules' => $this->remove_htaccess_rules( $correction ),
                    'add_htaccess_rules'    => $this->add_htaccess_rules( $correction ),
                    'remove_all_htaccess'   => $this->remove_all_htaccess( $correction ),
                    'disable_feature'       => $this->disable_feature( $correction ),
                    'degrade_feature'       => $this->degrade_feature( $correction ),
                    'fix_permission'        => $this->fix_permission( $correction ),
                    'drop_table'            => $this->drop_table( $correction ),
                    'delete_option'         => $this->delete_option( $correction ),
                    'remove_directory'      => $this->remove_directory( $correction ),
                    'sync_to_options',
                    'sync_from_options'     => $this->sync_feature_state( $correction ),
                    default                 => [ 'status' => 'skipped', 'type' => $correction['type'] ],
                };
            } catch ( Exception $e ) {
                $results[] = [ 'status' => 'error', 'type' => $correction['type'], 'error' => $e->getMessage() ];
            }
        }

        return $results;
    }

    /**
     * Fix blank line formatting — ensures exactly one blank line before END marker
     */
    private function fix_blank_line( array $correction ): array {
        $feature_id    = $correction['feature_id'];
        $htaccess_path = ABSPATH . '.htaccess';
        $id            = preg_quote( $feature_id, '/' );

        $content = file_get_contents( $htaccess_path );

        // Normalise: strip all trailing blank lines from block content, then add exactly one
        $new_content = preg_replace_callback(
            "/(# BEGIN VAPT-RISK-{$id}\n)(.*?)(\n# END VAPT-RISK-{$id})/s",
            function( $m ) {
                return $m[1] . rtrim( $m[2] ) . "\n\n" . ltrim( $m[3], "\n" );
            },
            $content
        );

        file_put_contents( $htaccess_path, $new_content );

        return [ 'status' => 'success', 'type' => 'fix_blank_line', 'feature_id' => $feature_id ];
    }

    /**
     * Remove all VAPT rule blocks from .htaccess (used on deactivation/uninstall)
     */
    private function remove_all_htaccess( array $correction ): array {
        $htaccess_path = ABSPATH . '.htaccess';

        if ( ! file_exists( $htaccess_path ) ) {
            return [ 'status' => 'success', 'message' => 'No .htaccess file exists' ];
        }

        $content = file_get_contents( $htaccess_path );

        if ( $correction['backup'] ?? false ) {
            copy( $htaccess_path, $htaccess_path . '.vapt-backup-' . date( 'Ymd-His' ) );
        }

        $new_content = preg_replace( '/\n?# BEGIN VAPT-.*?# END VAPT-[^\n]*\n?/s', '', $content );
        file_put_contents( $htaccess_path, $new_content );

        return [ 'status' => 'success', 'type' => 'remove_all_htaccess' ];
    }
}
```

---

## 📊 Audit Log & Admin Notifications

```php
// File: /includes/self-check/class-vapt-audit-log.php

class VAPT_Audit_Log {

    const TABLE_NAME = 'vapt_audit_log';

    public static function log_check( string $trigger, VAPT_Self_Check_Result $results ): int {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . self::TABLE_NAME, [
            'timestamp'           => current_time( 'mysql' ),
            'trigger_event'       => $trigger,
            'user_id'             => get_current_user_id(),
            'ip_address'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'cli' ),
            'overall_status'      => $results->get_overall_status(),
            'checks_passed'       => $results->get_passed_count(),
            'checks_failed'       => $results->get_failed_count(),
            'checks_warning'      => $results->get_warning_count(),
            'corrections_applied' => count( $results->get_applied_corrections() ),
            'details'             => wp_json_encode( $results->get_all_results() ),
        ] );

        if ( $results->has_critical_failures() ) {
            self::notify_admin( $trigger, $results );
        }

        return (int) $wpdb->insert_id;
    }

    private static function notify_admin( string $trigger, VAPT_Self_Check_Result $results ): void {
        $site_name  = get_bloginfo( 'name' );
        $site_url   = get_site_url();
        $admin_url  = admin_url( 'admin.php?page=vaptsecure-diagnostics' );

        wp_mail(
            get_option( 'admin_email' ),
            "[VAPTSecure] Critical Issue on {$site_name}",
            implode( "\n", [
                "A critical issue was detected during the '{$trigger}' self-check.",
                "",
                "Site:                 {$site_url}",
                "Failed Checks:        " . $results->get_failed_count(),
                "Warnings:             " . $results->get_warning_count(),
                "Corrections Applied:  " . count( $results->get_applied_corrections() ),
                "",
                "Review the audit log: {$admin_url}",
                "Time: "                . current_time( 'mysql' ),
            ] )
        );
    }
}
```

---

## 📄 `.htaccess` & Config Code Block Registry

This section is the **canonical reference** for every code block added to or removed from `.htaccess` and other configuration files during feature implementation and deployment.

### Block Format Contract

Every VAPT-managed `.htaccess` block **MUST** follow this exact structure:

```apache
# BEGIN VAPT-RISK-{FEATURE-ID}
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── WordPress core whitelist (ALWAYS first) ──────────────────────────
    RewriteCond %{REQUEST_URI} !^/wp-admin/              [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-login\.php          [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-json/               [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-cron\.php           [NC]

    # ── Feature-specific security rule ───────────────────────────────────
    RewriteCond %{YOUR_CONDITION} your-value
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-{FEATURE-ID}

```

> **Blank line rule**: There must be **exactly one blank line** between the last directive and `# END VAPT-RISK-...`, and **exactly one blank line** after `# END VAPT-RISK-...` (between consecutive blocks). No trailing whitespace. Self-check will auto-correct violations.

---

### Registry: Add / Remove Blocks per Feature

Each entry below defines what is written to `.htaccess` (or config files) when a feature is **Enabled (Deployed)** and what is removed when the feature is **Disabled (Reset to Draft)**.

---

#### VAPT-RISK-BOT-PROTECTION — Bot & Crawler Blocking

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-BOT-PROTECTION
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── WordPress core whitelist ──────────────────────────────────────────
    RewriteCond %{REQUEST_URI} !^/wp-admin/               [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-login\.php           [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-json/                [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-cron\.php            [NC]

    # ── Block known malicious user agents ────────────────────────────────
    RewriteCond %{HTTP_USER_AGENT} ^.*(masscan|nikto|sqlmap|nmap|zgrab|zgrab2|dirbuster|nuclei).*$ [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-BOT-PROTECTION

```

**Remove on Disable:** Strip entire block from `# BEGIN VAPT-RISK-BOT-PROTECTION` through `# END VAPT-RISK-BOT-PROTECTION` (inclusive), plus the trailing blank line.

---

#### VAPT-RISK-REST-API-GUARD — REST API Access Control

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-REST-API-GUARD
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── Allow WordPress core REST namespaces unconditionally ─────────────
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/           [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/oembed/          [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/vaptsecure/v1/   [NC]
    RewriteRule .* - [L]

    # ── Block unauthenticated access to all other REST namespaces ─────────
    RewriteCond %{REQUEST_URI} ^/wp-json/                 [NC]
    RewriteCond %{HTTP_COOKIE}  !wordpress_logged_in      [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/      [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-REST-API-GUARD

```

**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-XMLRPC-BLOCK — XML-RPC Conditional Block

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-XMLRPC-BLOCK
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── Block xmlrpc.php for unauthenticated, non-Jetpack requests ────────
    RewriteCond %{REQUEST_URI} ^/xmlrpc\.php$             [NC]
    RewriteCond %{HTTP_COOKIE}  !wordpress_logged_in      [NC]
    RewriteCond %{HTTP_USER_AGENT} !^(Jetpack)            [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-XMLRPC-BLOCK

```

**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-ADMIN-AJAX-GUARD — admin-ajax.php Rate Limiting

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-ADMIN-AJAX-GUARD
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── Allow logged-in users unconditionally ────────────────────────────
    RewriteCond %{HTTP_COOKIE} wordpress_logged_in        [NC]
    RewriteRule ^wp-admin/admin-ajax\.php$ - [L]

    # ── Block anonymous requests with no site referer ─────────────────────
    RewriteCond %{REQUEST_URI} ^/wp-admin/admin-ajax\.php$ [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/       [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-ADMIN-AJAX-GUARD

```

**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-FILE-PROTECT — Sensitive File Protection

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-FILE-PROTECT
<IfModule mod_rewrite.c>
    RewriteEngine On

    # ── Block direct access to sensitive WordPress files ──────────────────
    RewriteCond %{REQUEST_URI} ^/wp-config\.php$          [NC,OR]
    RewriteCond %{REQUEST_URI} ^/readme\.html$            [NC,OR]
    RewriteCond %{REQUEST_URI} ^/license\.txt$            [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-content/debug\.log$   [NC]
    RewriteRule .* - [F,L]

    # ── Block PHP execution in uploads directory ──────────────────────────
    RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\.php$ [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-FILE-PROTECT

```

**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-WP-INCLUDES — wp-includes Hardening

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-WP-INCLUDES
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # ── Block wp-admin/includes ───────────────────────────────────────────
    RewriteRule ^wp-admin/includes/ - [F,L]

    # ── Block PHP files in wp-includes (with exceptions) ─────────────────
    RewriteRule !^wp-includes/ - [S=3]
    RewriteRule ^wp-includes/[^/]+\.php$                - [F,L]
    RewriteRule ^wp-includes/js/tinymce/langs/.+\.php   - [F,L]
    RewriteRule ^wp-includes/theme-compat/              - [F,L]

</IfModule>

# END VAPT-RISK-WP-INCLUDES

```

**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-SECURITY-HEADERS — HTTP Security Headers

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-SECURITY-HEADERS
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options    "nosniff"
    Header always set X-Frame-Options           "SAMEORIGIN"
    Header always set X-XSS-Protection          "1; mode=block"
    Header always set Referrer-Policy           "strict-origin-when-cross-origin"
    Header always set Permissions-Policy        "geolocation=(), microphone=(), camera=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

</IfModule>

# END VAPT-RISK-SECURITY-HEADERS

```

**Remove on Disable:** Strip entire block.

---

### PHP: Writing a Rule Block

```php
/**
 * Write a feature's .htaccess rule block.
 * Enforces: blank line requirement, WordPress whitelist header, FQDN placeholders.
 *
 * @param string $feature_id  e.g. 'bot-protection'
 * @param string $rule_content Apache directives (no BEGIN/END markers)
 */
function vapt_htaccess_write( string $feature_id, string $rule_content ): VAPT_Self_Check_Result {
    $htaccess_path = ABSPATH . '.htaccess';
    $domain_host   = wp_parse_url( get_site_url(), PHP_URL_HOST );

    // Replace {domain} placeholder with actual hostname at runtime
    $rule_content = str_replace( '{domain}', $domain_host, $rule_content );

    // Enforce exactly one trailing blank line before END marker
    $rule_content = rtrim( $rule_content ) . "\n\n";

    $block = "# BEGIN VAPT-RISK-{$feature_id}\n"
           . $rule_content
           . "# END VAPT-RISK-{$feature_id}\n\n"; // one blank line after END

    $existing = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : '';

    // Replace existing block or append
    $id     = preg_quote( $feature_id, '/' );
    $pattern = "/(# BEGIN VAPT-RISK-{$id}\n).*?(# END VAPT-RISK-{$id}\n\n?)/s";
    $new    = preg_match( $pattern, $existing )
            ? preg_replace( $pattern, $block, $existing )
            : $existing . $block;

    file_put_contents( $htaccess_path, $new );

    // Trigger self-check immediately after write
    return VAPT_Self_Check::run( 'htaccess_modify', [
        'feature_id'  => $feature_id,
        'rules_added' => $rule_content,
    ] );
}
```

### PHP: Removing a Rule Block

```php
/**
 * Remove a feature's .htaccess rule block cleanly.
 * Always backs up and triggers self-check.
 *
 * @param string $feature_id  e.g. 'bot-protection'
 * @param bool   $backup      Whether to create a timestamped backup
 */
function vapt_htaccess_remove( string $feature_id, bool $backup = true ): VAPT_Self_Check_Result {
    $htaccess_path = ABSPATH . '.htaccess';

    if ( ! file_exists( $htaccess_path ) ) {
        return VAPT_Self_Check::run( 'htaccess_modify', [ 'feature_id' => $feature_id ] );
    }

    $content = file_get_contents( $htaccess_path );

    if ( $backup ) {
        copy( $htaccess_path, $htaccess_path . '.vapt-backup-' . date( 'Ymd-His' ) );
    }

    $id      = preg_quote( $feature_id, '/' );
    $new     = preg_replace( "/# BEGIN VAPT-RISK-{$id}\n.*?# END VAPT-RISK-{$id}\n\n?/s", '', $content );

    file_put_contents( $htaccess_path, $new );

    return VAPT_Self_Check::run( 'htaccess_modify', [
        'feature_id'    => $feature_id,
        'rules_removed' => true,
    ] );
}
```

---

## 🚫 MANDATORY RULES (Violations = Fail)

### Rule 1 — Never Block Core WordPress Paths

These endpoints MUST always be whitelisted **before** any deny/redirect rule:

| Endpoint | Purpose |
|----------|---------|
| `https://{domain}/wp-admin/` | Admin dashboard |
| `https://{domain}/wp-login.php` | Authentication |
| `https://{domain}/wp-json/wp/v2/` | Core REST API |
| `https://{domain}/wp-json/oembed/1.0/` | oEmbed REST |
| `https://{domain}/wp-json/vaptsecure/v1/` | Plugin REST API |
| `https://{domain}/wp-admin/admin-ajax.php` | AJAX handler |
| `https://{domain}/wp-cron.php` | Scheduled tasks |
| `https://{domain}/xmlrpc.php` | XML-RPC (when explicitly enabled) |
| `https://{domain}/wp-content/uploads/` | Media files |

### Rule 2 — Use `.htaccess`-Safe Directives Only

| Status | Directive |
|--------|-----------|
| ✅ Allowed | `RewriteEngine`, `RewriteCond`, `RewriteRule` |
| ✅ Allowed | `Header set`, `RequestHeader set`, `<IfModule mod_headers.c>` |
| ✅ Allowed | `<IfModule mod_rewrite.c>` wrapper |
| ❌ Forbidden | `TraceEnable`, `ServerSignature`, `ServerTokens` |
| ❌ Forbidden | `<Directory>`, `<Location>`, `<Files>` (use `RewriteCond` instead) |
| ❌ Forbidden | `<FilesMatch>` in `.htaccess` context |

### Rule 3 — Insert Rules at the Correct Position

```apache
# WRONG: rules after WordPress block
# END WordPress       ← DO NOT place rules after this line

# CORRECT: rules before WordPress block
# BEGIN VAPT-RISK-{FEATURE-ID}
... your rules ...
# END VAPT-RISK-{FEATURE-ID}

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
...
</IfModule>
# END WordPress
```

### Rule 4 — Always Wrap in Module Conditional

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Your rules here
</IfModule>
```

### Rule 5 — Block Format Blank Line Contract

```
# BEGIN VAPT-RISK-{FEATURE-ID}     ← marker on its own line
{directives}                        ← your Apache rules
                                    ← ✅ exactly ONE blank line here
# END VAPT-RISK-{FEATURE-ID}       ← marker on its own line
                                    ← ✅ exactly ONE blank line here (between blocks)
# BEGIN VAPT-RISK-NEXT-FEATURE     ← next block starts immediately
```

**Self-check auto-correct** will fix missing or extra blank lines on every `htaccess_modify` trigger.

### Rule 6 — Self-Check Must Fire on All Critical Events

```php
// ✅ Always wrap .htaccess writes in the helper (auto-triggers self-check)
vapt_htaccess_write( 'feature-id', $rules );

// ✅ Always wrap .htaccess removals in the helper
vapt_htaccess_remove( 'feature-id' );

// ✅ Plugin lifecycle hooks registered in main plugin file
register_deactivation_hook( __FILE__, ['VAPT_Lifecycle', 'on_deactivate'] );
register_uninstall_hook(    __FILE__, ['VAPT_Lifecycle', 'on_uninstall']  );
```

---

## 🔒 WordPress-Specific Security Rules

### Core Whitelist Table

| Endpoint | `.htaccess` Negative Condition |
|----------|-------------------------------|
| `https://{domain}/wp-admin/` | `RewriteCond %{REQUEST_URI} !^/wp-admin/ [NC]` |
| `https://{domain}/wp-login.php` | `RewriteCond %{REQUEST_URI} !^/wp-login\.php [NC]` |
| `https://{domain}/wp-json/wp/v2/` | `RewriteCond %{REQUEST_URI} !^/wp-json/ [NC]` |
| `https://{domain}/wp-json/vaptsecure/v1/` | `RewriteCond %{REQUEST_URI} !^/wp-json/vaptsecure/v1/ [NC]` |
| `https://{domain}/wp-admin/admin-ajax.php` | `RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC]` |
| `https://{domain}/wp-cron.php` | `RewriteCond %{REQUEST_URI} !^/wp-cron\.php [NC]` |
| `https://{domain}/xmlrpc.php` | `RewriteCond %{REQUEST_URI} !^/xmlrpc\.php [NC]` |
| `https://{domain}/wp-content/uploads/` | `RewriteCond %{REQUEST_URI} !^/wp-content/uploads/ [NC]` |

### WordPress REST API Security Pattern

```apache
# ✅ CORRECT — whitelist core namespaces, guard others
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Allow core REST namespaces unconditionally
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/         [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/oembed/        [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/vaptsecure/v1/ [NC]
    RewriteRule .* - [L]

    # Block unauthenticated access to other REST namespaces
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/ [NC]
    RewriteCond %{REQUEST_URI}  ^/wp-json/           [NC]
    RewriteRule .* - [F,L]

</IfModule>
```

### Admin AJAX Protection Pattern

```apache
# ✅ CORRECT — cookie-based bypass for logged-in users
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Bypass for authenticated users
    RewriteCond %{HTTP_COOKIE} wordpress_logged_in [NC]
    RewriteRule ^wp-admin/admin-ajax\.php$ - [L]

    # Block anonymous requests with no valid site referrer
    RewriteCond %{REQUEST_URI} ^/wp-admin/admin-ajax\.php$ [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/       [NC]
    RewriteRule .* - [F,L]

</IfModule>
```

### WordPress File Protection Pattern

```apache
# ✅ CORRECT — protect sensitive files, allow uploads serving
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Block direct access to sensitive files
    RewriteCond %{REQUEST_URI} ^/wp-config\.php$         [NC,OR]
    RewriteCond %{REQUEST_URI} ^/readme\.html$           [NC,OR]
    RewriteCond %{REQUEST_URI} ^/license\.txt$           [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-content/debug\.log$  [NC]
    RewriteRule .* - [F,L]

    # Block PHP execution inside uploads
    RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\.php$ [NC]
    RewriteRule .* - [F,L]

</IfModule>
```

---

## 📋 Feature Lifecycle Rules

### Draft → Develop Transition

1. Verify all required plugin dependencies exist
2. Apply `.htaccess` rules for testing using `vapt_htaccess_write()`
3. Set up feature-specific database tables
4. Enable debug logging for this feature
5. Test WordPress core endpoints remain accessible: `https://{domain}/wp-json/wp/v2/`
6. **Trigger self-check**: `VAPT_Self_Check::run('feature_enable', ['feature_id' => $id])`

### Develop → Deploy Transition

1. Run all validation workflows
2. Ensure debug logging is disabled
3. Verify no security rule conflicts exist
4. Test REST API: `https://{domain}/wp-json/wp/v2/`
5. Test admin AJAX: `https://{domain}/wp-admin/admin-ajax.php`
6. **Run full self-check suite** before final deployment

### Deploy → Reset to Draft

**CRITICAL** — when "Confirm Reset (Wipe Data)" is clicked:

```javascript
// Workflow: Reset to Draft
actions:
  - trigger_self_check: {
      event:        'feature_disable',
      feature_id:   '{FEATURE-ID}',
      auto_correct: true
    }
  - remove_htaccess_rules: {
      scope:                "feature-specific",
      backup_before_remove: true,
      patterns: [
        "# BEGIN VAPT-RISK-{FEATURE-ID}",
        "# END VAPT-RISK-{FEATURE-ID}"
      ]
    }
  - wipe_feature_data: {
      tables:     ["wp_vapt_features", "wp_vapt_feature_meta"],
      feature_id: "{FEATURE-ID}",
      cascade:    true
    }
  - remove_config_files: {
      path:    "data/generated/{FEATURE-ID}/",
      archive: false
    }
  - log_operation: {
      level:    "info",
      category: "feature_lifecycle",
      action:   "reset_to_draft",
      user_id:  "{CURRENT_USER_ID}"
    }
  - update_feature_state: {
      feature_id:     "{FEATURE-ID}",
      new_state:      "Draft",
      previous_state: "Develop"
    }
```

---

## 🔧 Technical Constraints

### JSON Schema Requirements

1. All feature JSON must validate against `/data/VAPTSchema-Builder/`
2. Use `interface_schema_v2.0.json` as blueprint
3. Follow `ai_agent_instructions_v2.0.json` for formatting
4. Interface MUST include proper component keys, UI layout definitions, severity classifications, and platform availability flags

### Code Generation Standards

1. **Always reference the enforcer library** — never write patterns from memory
2. **Use the 4-step workflow**: Rulebook → Blueprint → Enforcement → Self-Check
3. **Score output against 19-point rubric** before delivering
4. **Naming convention**: `UI-RISK-XXX-YYY` format
5. **Domain**: always `{domain}` — never `example.com`, `yoursite.com`, or any literal hostname

---

## 💬 Communication Style

1. **Be concise and direct** — avoid unnecessary qualifiers
2. **Provide working code** — not pseudocode or vague suggestions
3. **Include security context** — explain the "why" behind each rule
4. **Reference documentation** — link to relevant JSON schema files
5. **Use `{domain}` placeholder** — never use literal domains in any generated output

---

## 🎓 Domain Expertise Areas

1. **Apache `.htaccess` configurations** — [`mod_rewrite`](https://httpd.apache.org/docs/current/mod/mod_rewrite.html), [`mod_headers`](https://httpd.apache.org/docs/current/mod/mod_headers.html)
2. **WordPress security best practices** — [WordPress Security Handbook](https://developer.wordpress.org/apis/security/)
3. **WordPress REST API** — [REST API Handbook](https://developer.wordpress.org/rest-api/)
4. **WordPress Hardening** — [Hardening WordPress](https://developer.wordpress.org/advanced-administration/security/hardening/)
5. **Plugin lifecycle hooks** — [Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
6. **Vulnerability catalogs** — [OWASP Top 10](https://owasp.org/Top10/), [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)
7. **JSON schema validation** — VAPT interface schemas in `/data/VAPTSchema-Builder/`
8. **Self-check automation** — event-driven validation and auto-correction

---

## 🔍 Troubleshooting

### Common Issues

**500 Errors after `.htaccess` modification**
- Check for syntax errors in `RewriteCond`/`RewriteRule`
- Verify insertion point is `before_wordpress_rewrite`
- Ensure no forbidden directives are present
- Test: `curl -I https://{domain}/wp-json/wp/v2/`
- Run: `VAPT_Self_Check::run('htaccess_modify', ['feature_id' => $id])`

**REST API returning 403**
- Verify `https://{domain}/wp-json/` is whitelisted
- Check for overly broad blocking rules
- Test: `curl https://{domain}/wp-json/wp/v2/posts`
- Self-check auto-correct will add missing whitelist rules

**`admin-ajax.php` returning 403**
- Check referer-based blocking rules
- Verify cookie-based bypass for logged-in users (`wordpress_logged_in`)
- Correct path is `/wp-admin/admin-ajax.php` — NOT `/admin-ajax.php`
- Self-check validates cookie detection patterns

**Rule block format violations**
- Ensure exactly one blank line before `# END VAPT-RISK-...` marker
- Check for trailing whitespace using: `cat -A .htaccess | grep 'VAPT'`
- Self-check auto-correct will fix blank line formatting automatically

**Feature reset incomplete**
- Verify all `.htaccess` markers are removed
- Check for orphaned database entries
- Review audit log at `https://{domain}/wp-admin/admin.php?page=vaptsecure-diagnostics`

### WordPress Endpoint Testing Commands

```bash
# Test core REST API
curl -I https://{domain}/wp-json/wp/v2/

# Test oEmbed REST endpoint
curl -I https://{domain}/wp-json/oembed/1.0/

# Test admin AJAX (correct path)
curl -I https://{domain}/wp-admin/admin-ajax.php

# Test login page
curl -I https://{domain}/wp-login.php

# Test VAPTSecure custom API
curl -I https://{domain}/wp-json/vaptsecure/v1/

# Check .htaccess for VAPT blocks
grep -n 'VAPT' /path/to/webroot/.htaccess
```

### Self-Check Manual Trigger

```php
// Run full self-check manually
$result = VAPT_Self_Check::run( 'manual_trigger', [
    'requested_by' => get_current_user_id(),
    'checks'       => ['all'],
] );

if ( $result->has_failures() ) {
    echo 'Issues found: ' . $result->get_failed_count();
    print_r( $result->get_failures() );
}
```

---

## 📚 Resources

| Resource | Link |
|----------|------|
| VAPT AI Agent Instructions | `../../data/ai_agent_instructions_v2.0.json` |
| Interface Schema | `../../data/interface_schema_v2.0.json` |
| Enforcer Pattern Library | `../../data/enforcer_pattern_library_v2.0.json` |
| VAPTSchema Builder Skill | `skills/vapt-expert/SKILL.md` |
| WordPress REST API Docs | [https://developer.wordpress.org/rest-api/](https://developer.wordpress.org/rest-api/) |
| WordPress Security Handbook | [https://developer.wordpress.org/apis/security/](https://developer.wordpress.org/apis/security/) |
| WordPress Hardening Guide | [https://developer.wordpress.org/advanced-administration/security/hardening/](https://developer.wordpress.org/advanced-administration/security/hardening/) |
| Plugin Lifecycle Hooks | [https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) |
| Apache mod_rewrite Docs | [https://httpd.apache.org/docs/current/mod/mod_rewrite.html](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) |
| Apache mod_headers Docs | [https://httpd.apache.org/docs/current/mod/mod_headers.html](https://httpd.apache.org/docs/current/mod/mod_headers.html) |
| OWASP Top 10 | [https://owasp.org/Top10/](https://owasp.org/Top10/) |
| NIST Cybersecurity Framework | [https://www.nist.gov/cyberframework](https://www.nist.gov/cyberframework) |

---

*This `SOUL.md` defines universal AI behavior for the VAPTSecure plugin project.*
*Edit this file once — changes propagate to **all editors** (Cursor, Claude Code, Gemini, Qoder, Trae, Windsurf) via their respective symlinks.*
*Version: 2.4.11 | Last Updated: March 2025*
