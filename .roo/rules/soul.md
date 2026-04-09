# SOUL.md — Universal AI Configuration for VAPTSecure Plugin

> **⚠️ CRITICAL DOCUMENT**
> This file is the **single source of truth** for all AI agent behavior in the VAPTSecure plugin project.
>
> **Symlinked to ALL editors and extensions via:**
>
> | # | Editor / Extension | Platform | Symlink / Config Path | Type |
> |---|-------------------|----------|-----------------------|------|
> | 1 | **Cursor** | VS Code fork | `.cursor/cursor.rules → .ai/SOUL.md` | Rules file |
> | 2 | **Gemini / Antigravity** | VS Code | `.gemini/gemini.md → .ai/SOUL.md` | Rules file |
> | 3 | **Claude Code** | CLI / VS Code | `.claude/settings.json → .ai/rules/claude-settings.json` | Settings |
> | 4 | **Qoder** | VS Code | `.qoder/qoder.rules → .ai/SOUL.md` | Rules file |
> | 5 | **Trae** | VS Code fork | `.trae/trae.rules → .ai/SOUL.md` | Rules file |
> | 6 | **Windsurf** | VS Code fork | `.windsurfrules → .ai/SOUL.md` | Rules file |
> | 7 | **Kilo Code** | VS Code | `.kilocode/rules/soul.md → .ai/SOUL.md` | Rules dir |
> | 8 | **Continue** | VS Code / JetBrains | `.continue/rules/soul.md → .ai/SOUL.md` | Rules dir |
> | 9 | **Roo Code** | VS Code | `.clinerules → .ai/SOUL.md` (fallback: `.roorules`) | Rules file |
> | 10 | **GitHub Copilot** | VS Code / JetBrains / Visual Studio | `.github/copilot-instructions.md → .ai/SOUL.md` | Instructions file |
> | 11 | **JetBrains Junie** | IntelliJ / PyCharm / WebStorm / GoLand / PhpStorm | `.junie/guidelines.md → .ai/SOUL.md` | Guidelines file |
> | 12 | **Zed** | Zed Editor | `.rules → .ai/SOUL.md` | Rules file |
> | 13 | **OpenCode / ECC** | CLI / Agent | `.opencode/instructions/SOUL.md → .ai/SOUL.md` | Instructions file |
> | 14 | **VS Code** | VS Code | `.vscode/SOUL.md → .ai/SOUL.md` | Rules file |
>
> **Edit this file once — changes propagate to ALL editors and extensions automatically.**

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
**Version**: 2.5.9
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
| `/.roo/` | Roo Code rules and context |
| `/.opencode/` | **OpenCode agent configuration and instructions** |
| `/.ai/` | **Universal AI configuration (active standard)** |
| `/.ai/skills/vapt-expert/` | VAPT Security Expert skill |
| `/.ai/skills/security-auditor/` | Security Audit skill |
| `/.ai/workflows/` | Reusable automation workflows |
| `/.ai/rules/` | Editor-specific rule symlinks |

### Symlink Registry (complete — mirrors README.md)

```
.ai/
├── SOUL.md                              ← THIS FILE (single source of truth)
├── AGENTS.md                            ← Multi-agent orchestration
├── skills/
│   ├── vapt-expert/SKILL.md
│   └── security-auditor/SKILL.md
├── workflows/
│   ├── security-scan.yml
│   ├── reset-to-draft.yml
│   └── validation.yml
└── rules/
    ├── cursor.rules       → ../SOUL.md
    ├── gemini.md          → ../SOUL.md
    ├── kilo.rules         → ../SOUL.md
    ├── roo.rules          → ../SOUL.md
    └── opencode.md        → ../SOUL.md

# ── VS Code fork / standalone editors ────────────────────────────────────────

.cursor/
├── skills/            → ../../.ai/skills/
└── cursor.rules       → ../.ai/rules/cursor.rules

.windsurf/
└── skills/            → ../../.ai/skills/
.windsurfrules         → .ai/SOUL.md

.zed/
└── settings.json      (model config — agent rules loaded from .rules at project root)
.rules                 → .ai/SOUL.md        ← Zed priority-1 rule file

# ── VS Code extensions ────────────────────────────────────────────────────────

.gemini/
├── antigravity/skills/ → ../../../.ai/skills/
└── gemini.md           → ../.ai/rules/gemini.md

.claude/
├── skills/            → ../../.ai/skills/
└── settings.json      → ../.ai/rules/claude-settings.json

.qoder/
├── skills/            → ../../.ai/skills/
└── qoder.rules        → ../.ai/SOUL.md

.trae/
├── skills/            → ../../.ai/skills/
└── trae.rules         → ../.ai/SOUL.md

.kilocode/
└── rules/
    └── soul.md        → ../../.ai/SOUL.md  ← Kilo Code loads all .md in this dir

.continue/
└── rules/
    └── soul.md        → ../../.ai/SOUL.md  ← Continue loads all .md in this dir

.roo/
├── rules/
│   └── soul.md        → ../../.ai/SOUL.md  ← Roo Code loads all .md in this dir
├── skills/            → ../../.ai/skills/
.roorules              → .ai/SOUL.md        ← Roo Code fallback (if .roo/rules/ empty)
.clinerules            → .ai/SOUL.md        ← Roo Code (modern entry point)

# ── Cross-IDE (GitHub Copilot, JetBrains Junie) ───────────────────────────────

.github/
└── copilot-instructions.md → ../.ai/SOUL.md   ← GitHub Copilot (VS Code / JetBrains / Visual Studio)

.junie/
└── guidelines.md      → ../.ai/SOUL.md    ← JetBrains Junie (IntelliJ/PyCharm/WebStorm/GoLand/PhpStorm)

# ── OpenCode (Agentic Engine) ────────────────────────────────────────────────

.opencode/
├── instructions/
│   └── SOUL.md        → ../../.ai/SOUL.md  ← OpenCode loads instructions from this dir
└── opencode.json      (loads instructions/SOUL.md)

# ── VS Code (native settings only — no rule symlink required) ────────────────

.vscode/
└── settings.json      (editor settings only)
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
  yoursite.com/wp-admin/           ← no scheme, no placeholder
  http://example.com/wp-json/      ← hardcoded domain, wrong scheme
  /wp-admin/                       ← relative path forbidden in security rules
  example.com                      ← no scheme, no placeholder
```

### PHP Runtime Replacement Pattern

```php
// Runtime domain resolution — ALWAYS use this pattern
$domain    = get_site_url();                       // e.g. https://example.com
$admin_url = admin_url();                          // e.g. https://example.com/wp-admin/
$rest_url  = rest_url('wp/v2/');                   // e.g. https://example.com/wp-json/wp/v2/

// Replace {domain} placeholder in generated .htaccess rules
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

add_action('vapt_feature_enabled',  function( $id ) {
    VAPT_Self_Check::run('feature_enable',  ['feature_id' => $id]);
});
add_action('vapt_feature_disabled', function( $id ) {
    VAPT_Self_Check::run('feature_disable', ['feature_id' => $id]);
});
add_action('vapt_license_expired',  function() {
    VAPT_Self_Check::run('license_expire');
});
```

### WP-Cron Scheduled Events

```php
// File: /includes/self-check/class-vapt-cron.php

class VAPT_Cron {

    /**
     * Register all VAPTSecure scheduled events on plugin activation
     */
    public static function register(): void {
        // Daily health check — fires every 24 hours
        if ( ! wp_next_scheduled('vapt_daily_self_check') ) {
            wp_schedule_event( time(), 'daily', 'vapt_daily_self_check' );
        }

        // License validation — fires every 12 hours
        if ( ! wp_next_scheduled('vapt_license_check') ) {
            wp_schedule_event( time(), 'twicedaily', 'vapt_license_check' );
        }
    }

    /**
     * Remove all scheduled events on plugin deactivation
     */
    public static function deregister(): void {
        wp_clear_scheduled_hook('vapt_daily_self_check');
        wp_clear_scheduled_hook('vapt_license_check');
    }

    /**
     * Wire up cron action callbacks
     * Called once during plugin bootstrap
     */
    public static function init(): void {
        add_action('vapt_daily_self_check', [ __CLASS__, 'run_daily_health_check' ] );
        add_action('vapt_license_check',    [ __CLASS__, 'run_license_check'       ] );
    }

    public static function run_daily_health_check(): void {
        VAPT_Self_Check::run('daily_health_check');
    }

    public static function run_license_check(): void {
        $status = get_option('vapt_license_status');
        $expiry = get_option('vapt_license_expiry');

        if ( $expiry && strtotime($expiry) < time() && $status !== 'expired' ) {
            update_option('vapt_license_status', 'expired');
            do_action('vapt_license_expired');
        }
    }
}

// Register cron callbacks on init
add_action('init', ['VAPT_Cron', 'init']);
```

### VAPT_Lifecycle Class

```php
// File: /includes/self-check/class-vapt-lifecycle.php

class VAPT_Lifecycle {

    /**
     * Runs on plugin activation
     * Creates DB tables, registers cron events, runs baseline self-check
     */
    public static function on_activate(): void {
        self::create_tables();
        VAPT_Cron::register();
        VAPT_Self_Check::run('plugin_activate');
    }

    /**
     * Runs on plugin deactivation (plugin is disabled but NOT removed)
     * Removes .htaccess rules, deregisters cron. Data is PRESERVED.
     */
    public static function on_deactivate(): void {
        // 1. Run self-check first — captures current state for audit log
        $result = VAPT_Self_Check::run('plugin_deactivate');

        // 2. Auto-correct will have removed .htaccess rules if check found them
        //    Confirm final state
        $htaccess_path = ABSPATH . '.htaccess';
        if ( file_exists($htaccess_path) ) {
            $content = file_get_contents($htaccess_path);
            if ( strpos($content, '# BEGIN VAPT-') !== false ) {
                // Force remove if auto-correct didn't catch it
                $clean = preg_replace('/\n?# BEGIN VAPT-.*?# END VAPT-[^\n]*\n?/s', '', $content);
                file_put_contents($htaccess_path, $clean);
            }
        }

        // 3. Deregister cron events
        VAPT_Cron::deregister();

        // 4. Mark all features as deactivated (not deleted — data preserved)
        update_option('vapt_active_features', []);
        update_option('vapt_plugin_status', 'deactivated');
    }

    /**
     * Runs when plugin is deleted from the site (Plugins > Delete)
     * Full cleanup: tables, options, generated files, .htaccess rules
     */
    public static function on_uninstall(): void {
        global $wpdb;

        // 1. Run full self-check to capture pre-uninstall state
        VAPT_Self_Check::run('plugin_uninstall');

        // 2. Remove all .htaccess rules (no backup — full uninstall)
        $htaccess_path = ABSPATH . '.htaccess';
        if ( file_exists($htaccess_path) ) {
            $content = file_get_contents($htaccess_path);
            $clean   = preg_replace('/\n?# BEGIN VAPT-.*?# END VAPT-[^\n]*\n?/s', '', $content);
            file_put_contents($htaccess_path, $clean);
        }

        // 3. Drop all VAPT database tables
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}vapt_%'", ARRAY_N);
        foreach ( array_column($tables, 0) as $table ) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        // 4. Delete all plugin options
        $options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vapt_%'"
        );
        foreach ( $options as $option ) {
            delete_option($option);
        }

        // 5. Remove generated config files
        $generated = VAPT_PLUGIN_DIR . 'data/generated/';
        if ( is_dir($generated) ) {
            self::recursive_remove_directory($generated);
        }

        // 6. Deregister cron (safety — should already be gone from deactivation)
        VAPT_Cron::deregister();
    }

    /**
     * Create plugin database tables
     */
    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vapt_features (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feature_id   VARCHAR(100)        NOT NULL,
            status       ENUM('draft','develop','deploy','active') NOT NULL DEFAULT 'draft',
            created_at   DATETIME            NOT NULL,
            updated_at   DATETIME            NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   feature_id (feature_id)
        ) {$charset};

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}vapt_audit_log (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp           DATETIME            NOT NULL,
            trigger_event       VARCHAR(100)        NOT NULL,
            user_id             BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            ip_address          VARCHAR(45)         NOT NULL DEFAULT '',
            overall_status      ENUM('pass','warning','fail') NOT NULL,
            checks_passed       SMALLINT            NOT NULL DEFAULT 0,
            checks_failed       SMALLINT            NOT NULL DEFAULT 0,
            checks_warning      SMALLINT            NOT NULL DEFAULT 0,
            corrections_applied SMALLINT            NOT NULL DEFAULT 0,
            details             LONGTEXT,
            PRIMARY KEY         (id),
            KEY                 trigger_event (trigger_event),
            KEY                 timestamp (timestamp)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private static function recursive_remove_directory( string $path ): void {
        if ( ! is_dir($path) ) { return; }
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ( $items as $item ) {
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? self::recursive_remove_directory($full) : unlink($full);
        }
        rmdir($path);
    }
}
```

### Data Classes

```php
// File: /includes/self-check/class-vapt-check-item.php

class VAPT_Check_Item {

    public string $check_id;
    public string $status;    // 'pass' | 'warning' | 'fail'
    public string $message;
    public array  $data;      // issues list or corrections list

    public function __construct(
        string $check_id,
        string $status,
        string $message,
        array  $data = []
    ) {
        $this->check_id = $check_id;
        $this->status   = $status;
        $this->message  = $message;
        $this->data     = $data;
    }

    public function is_pass():    bool { return $this->status === 'pass';    }
    public function is_warning(): bool { return $this->status === 'warning'; }
    public function is_fail():    bool { return $this->status === 'fail';    }

    public function to_array(): array {
        return [
            'check_id' => $this->check_id,
            'status'   => $this->status,
            'message'  => $this->message,
            'data'     => $this->data,
        ];
    }
}
```

```php
// File: /includes/self-check/class-vapt-self-check-result.php

class VAPT_Self_Check_Result {

    private array $items       = [];
    private array $corrections = [];
    private array $applied     = [];

    public function add( VAPT_Check_Item $item ): void {
        $this->items[] = $item;

        // Collect corrections from failed checks
        if ( $item->is_fail() || $item->is_warning() ) {
            foreach ( $item->data as $entry ) {
                if ( isset($entry['type']) ) {
                    $this->corrections[] = $entry;
                }
            }
        }
    }

    public function apply_corrections(): void {
        if ( empty($this->corrections) ) { return; }
        $corrector     = new VAPT_Auto_Correct();
        $this->applied = $corrector->apply($this->corrections);
    }

    public function has_failures():         bool  { return $this->get_failed_count() > 0;    }
    public function has_critical_failures(): bool  { return $this->get_failed_count() > 0;    }

    public function get_overall_status(): string {
        foreach ( $this->items as $item ) {
            if ( $item->is_fail() ) { return 'fail'; }
        }
        foreach ( $this->items as $item ) {
            if ( $item->is_warning() ) { return 'warning'; }
        }
        return 'pass';
    }

    public function get_passed_count():  int { return count(array_filter($this->items, fn($i) => $i->is_pass()));    }
    public function get_failed_count():  int { return count(array_filter($this->items, fn($i) => $i->is_fail()));    }
    public function get_warning_count(): int { return count(array_filter($this->items, fn($i) => $i->is_warning())); }

    public function get_failures():            array { return array_filter($this->items, fn($i) => $i->is_fail());    }
    public function get_applied_corrections(): array { return $this->applied; }
    public function get_all_results():         array { return array_map(fn($i) => $i->to_array(), $this->items);     }
}
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
                $results->add( $this->check_cleanup_required()       );
                $results->add( $this->check_htaccess_rules_removal() );
                break;

            case 'plugin_uninstall':
                $results->add( $this->check_complete_cleanup()  );
                $results->add( $this->check_database_tables()   );
                break;

            case 'license_expire':
                $results->add( $this->check_license_degradation()  );
                $results->add( $this->check_feature_deactivation() );
                break;

            case 'feature_enable':
            case 'feature_disable':
                $results->add( $this->check_feature_consistency()   );
                $results->add( $this->check_rule_block_format()     );
                $results->add( $this->check_wordpress_endpoints()   ); // re-verify after state change
                break;

            case 'htaccess_modify':
                $results->add( $this->check_rule_block_format()         );
                $results->add( $this->check_rewrite_syntax()            );
                $results->add( $this->check_blank_line_requirement()    );
                $results->add( $this->check_wordpress_whitelist_rules() ); // WP-specific guard
                break;

            case 'config_update':
                $results->add( $this->check_json_validity()     );
                $results->add( $this->check_schema_compliance() );
                break;

            case 'plugin_activate':
                $results->add( $this->check_database_tables()   );
                $results->add( $this->check_file_permissions()  );
                break;

            case 'daily_health_check':
                $results->add( $this->check_feature_consistency()       );
                $results->add( $this->check_htaccess_integrity()        );
                $results->add( $this->check_wordpress_whitelist_rules() );
                $results->add( $this->check_license_degradation()       );
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

        preg_match( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $begin_marker, $id_match );
        $feature_id = $id_match[1];

        if ( ! preg_match( '/\n\n$/', $rule_content ) ) {
            $issues[]      = "{$feature_id}: Must have exactly one blank line before END marker";
            $corrections[] = [
                'type'        => 'fix_blank_line',
                'feature_id'  => $feature_id,
                'description' => 'Ensure exactly one blank line before END marker',
            ];
        }

        if ( preg_match( '/\n{3,}/', $rule_content ) ) {
            $issues[]      = "{$feature_id}: Multiple consecutive blank lines detected inside block";
            $corrections[] = [
                'type'        => 'collapse_blank_lines',
                'feature_id'  => $feature_id,
                'description' => 'Collapse multiple blank lines to single blank line',
            ];
        }

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
            // 401 = auth required (expected for admin), 200/302 = accessible
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
 * Validates that every VAPT deny/redirect block contains the mandatory WordPress whitelist header.
 */
public function check_wordpress_whitelist_rules(): VAPT_Check_Item {
    $htaccess_path     = ABSPATH . '.htaccess';
    $content           = file_get_contents( $htaccess_path );
    $issues            = [];
    $corrections       = [];

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
public function check_complete_cleanup(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    $tables = $wpdb->get_results( "SHOW TABLES LIKE 'wp_vapt_%'", ARRAY_N );
    if ( ! empty( $tables ) ) {
        $table_names = array_column( $tables, 0 );
        $issues[]    = 'Database tables remaining: ' . implode( ', ', $table_names );
        foreach ( $table_names as $table ) {
            $corrections[] = [ 'type' => 'drop_table', 'table' => $table, 'backup' => true ];
        }
    }

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

    $generated_dir = VAPT_PLUGIN_DIR . 'data/generated/';
    if ( is_dir( $generated_dir ) && ! empty( glob( $generated_dir . '*' ) ) ) {
        $issues[]      = 'Generated config files remaining in data/generated/';
        $corrections[] = [ 'type' => 'remove_directory', 'path' => $generated_dir, 'recursive' => true ];
    }

    $htaccess_path = ABSPATH . '.htaccess';
    if ( file_exists( $htaccess_path ) ) {
        $content = file_get_contents( $htaccess_path );
        if ( strpos( $content, '# BEGIN VAPT-' ) !== false ) {
            $issues[]      = 'VAPT .htaccess rules still present after uninstall';
            $corrections[] = [ 'type' => 'remove_all_htaccess', 'backup' => false ];
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
public function check_license_degradation(): VAPT_Check_Item {
    $license_status   = get_option( 'vapt_license_status' );
    $active_features  = get_option( 'vapt_active_features', [] );
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
public function check_feature_consistency(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    $db_features       = $wpdb->get_col( "SELECT feature_id FROM {$wpdb->prefix}vapt_features WHERE status = 'active'" );
    $option_features   = get_option( 'vapt_active_features', [] );
    $htaccess_features = $this->get_htaccess_feature_ids();

    foreach ( array_diff( $db_features, $option_features ) as $id ) {
        $issues[]      = "Feature in DB but not options: {$id}";
        $corrections[] = [ 'type' => 'sync_to_options',   'feature_id' => $id, 'action' => 'add_to_active_features'      ];
    }
    foreach ( array_diff( $option_features, $db_features ) as $id ) {
        $issues[]      = "Feature in options but not DB: {$id}";
        $corrections[] = [ 'type' => 'sync_from_options', 'feature_id' => $id, 'action' => 'remove_from_active_features'  ];
    }
    foreach ( array_diff( $htaccess_features, $db_features ) as $id ) {
        $issues[]      = "Orphaned .htaccess rules for inactive feature: {$id}";
        $corrections[] = [ 'type' => 'remove_htaccess_rules', 'feature_id' => $id, 'reason' => 'Feature not active'       ];
    }
    foreach ( array_diff( $db_features, $htaccess_features ) as $id ) {
        $issues[]      = "Active feature missing .htaccess rules: {$id}";
        $corrections[] = [ 'type' => 'add_htaccess_rules',    'feature_id' => $id, 'reason' => 'Feature active, no rules' ];
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
    if ( ! file_exists( $htaccess_path ) ) { return []; }
    $content = file_get_contents( $htaccess_path );
    preg_match_all( '/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $matches );
    return $matches[1] ?? [];
}
```

### 9. File Permissions Check

```php
public function check_file_permissions(): VAPT_Check_Item {
    $checks = [
        [ 'path' => ABSPATH . 'wp-config.php', 'max' => 0640, 'expected' => 0600 ],
        [ 'path' => ABSPATH . '.htaccess',      'max' => 0644, 'expected' => 0644 ],
        [ 'path' => WP_CONTENT_DIR,             'max' => 0755, 'expected' => 0755 ],
        [ 'path' => ABSPATH . 'wp-admin/',      'max' => 0755, 'expected' => 0755 ],
    ];
    $issues = []; $corrections = [];

    foreach ( $checks as $check ) {
        if ( ! file_exists( $check['path'] ) ) { continue; }
        $current = fileperms( $check['path'] ) & 0777;
        if ( $current > $check['max'] ) {
            $issues[]      = sprintf( '%s: %04o exceeds max %04o', basename( $check['path'] ), $current, $check['max'] );
            $corrections[] = [ 'type' => 'fix_permission', 'path' => $check['path'], 'chmod' => $check['expected'] ];
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

### 10. `.htaccess` Rules Removal Check

```php
/**
 * Confirms all VAPT .htaccess rule blocks have been removed on deactivation.
 * Auto-corrects any blocks that remain.
 */
public function check_htaccess_rules_removal(): VAPT_Check_Item {
    $htaccess_path = ABSPATH . '.htaccess';
    $issues        = [];
    $corrections   = [];

    if ( ! file_exists($htaccess_path) ) {
        return new VAPT_Check_Item('htaccess_rules_removal', 'pass', 'No .htaccess file — nothing to remove');
    }

    $content = file_get_contents($htaccess_path);
    preg_match_all('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $content, $matches);
    $remaining = $matches[1] ?? [];

    if ( ! empty($remaining) ) {
        $issues[]      = 'VAPT rule blocks still present: ' . implode(', ', $remaining);
        $corrections[] = [
            'type'        => 'remove_all_htaccess',
            'backup'      => true,
            'description' => 'Remove all remaining VAPT blocks on deactivation',
        ];
    }

    return new VAPT_Check_Item(
        'htaccess_rules_removal',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All VAPT .htaccess blocks removed' : implode('; ', $issues),
        $corrections
    );
}
```

### 11. Database Tables Check

```php
/**
 * Verifies that required VAPT database tables exist and have correct schema.
 * Used on activation and as part of complete cleanup on uninstall.
 */
public function check_database_tables(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    $required_tables = [
        "{$wpdb->prefix}vapt_features",
        "{$wpdb->prefix}vapt_audit_log",
        "{$wpdb->prefix}vapt_feature_meta",
    ];

    foreach ( $required_tables as $table ) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if ( ! $exists ) {
            $issues[]      = "Required table missing: {$table}";
            $corrections[] = [
                'type'        => 'create_table',
                'table'       => $table,
                'description' => "Create missing table {$table}",
            ];
        }
    }

    // On uninstall: check for tables that should be GONE
    if ( $this->trigger === 'plugin_uninstall' ) {
        $leftover = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}vapt_%'", ARRAY_N);
        if ( ! empty($leftover) ) {
            foreach ( array_column($leftover, 0) as $table ) {
                $issues[]      = "Table still present after uninstall: {$table}";
                $corrections[] = [
                    'type'   => 'drop_table',
                    'table'  => $table,
                    'backup' => false,
                ];
            }
        }
    }

    return new VAPT_Check_Item(
        'database_tables',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'Database tables verified' : implode('; ', $issues),
        $corrections
    );
}
```

### 12. Feature Deactivation Check

```php
/**
 * Validates that all features are properly deactivated when license expires.
 * Checks each premium feature's .htaccess rules are removed and state is updated.
 */
public function check_feature_deactivation(): VAPT_Check_Item {
    global $wpdb;
    $issues      = [];
    $corrections = [];

    $premium_features = get_option('vapt_premium_features', []);
    $active_features  = get_option('vapt_active_features',  []);

    // Find premium features that are still marked active
    $still_active = array_intersect($active_features, $premium_features);

    foreach ( $still_active as $feature_id ) {
        $issues[] = "Premium feature still active after license event: {$feature_id}";

        $corrections[] = [
            'type'            => 'disable_feature',
            'feature_id'      => $feature_id,
            'action'          => 'set_inactive',
            'remove_htaccess' => true,
            'wipe_data'       => false,
            'notify_admin'    => true,
        ];
    }

    // Check .htaccess for premium feature rule blocks that should be gone
    $htaccess_features = $this->get_htaccess_feature_ids();
    $orphaned_premium  = array_intersect($htaccess_features, $premium_features);

    foreach ( $orphaned_premium as $feature_id ) {
        $issues[]      = ".htaccess rules still present for premium feature: {$feature_id}";
        $corrections[] = [
            'type'       => 'remove_htaccess_rules',
            'feature_id' => $feature_id,
            'reason'     => 'Premium feature must be deactivated on license expiry',
        ];
    }

    return new VAPT_Check_Item(
        'feature_deactivation',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All premium features properly deactivated' : implode('; ', $issues),
        $corrections
    );
}
```

### 13. Apache Rewrite Syntax Check

```php
/**
 * Validates Apache mod_rewrite syntax inside all VAPT rule blocks.
 * Catches common errors that would cause 500 errors after deployment.
 */
public function check_rewrite_syntax(): VAPT_Check_Item {
    $htaccess_path = ABSPATH . '.htaccess';
    $content       = file_get_contents($htaccess_path);
    $issues        = [];

    preg_match_all(
        '/(# BEGIN VAPT-RISK-[a-z0-9-]+\n)(.*?)(\n# END VAPT-RISK-[a-z0-9-]+)/s',
        $content, $blocks, PREG_SET_ORDER
    );

    foreach ( $blocks as $block ) {
        preg_match('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $block[1], $id_match);
        $feature_id   = $id_match[1];
        $block_content = $block[2];

        // Rule 1: RewriteEngine must be declared before any RewriteRule
        if ( preg_match('/RewriteRule/i', $block_content) &&
             ! preg_match('/RewriteEngine\s+On/i', $block_content) ) {
            $issues[] = "{$feature_id}: RewriteRule used without RewriteEngine On";
        }

        // Rule 2: RewriteCond must immediately precede its RewriteRule (no non-blank lines between)
        if ( preg_match('/RewriteCond[^\n]+\n\s*\n\s*RewriteRule/i', $block_content) ) {
            $issues[] = "{$feature_id}: Blank line between RewriteCond and RewriteRule breaks chaining";
        }

        // Rule 3: [OR] flag must not appear on the last RewriteCond before a RewriteRule
        if ( preg_match('/RewriteCond[^\n]+\[(?:[^,\]]*,)*\s*OR\s*(?:,[^,\]]*)*\]\s*\nRewriteRule/i', $block_content ) ) {
            $issues[] = "{$feature_id}: [OR] flag on last RewriteCond before RewriteRule — this makes the rule always match";
        }

        // Rule 4: <IfModule> wrapper must be present
        if ( preg_match('/RewriteRule/i', $block_content) &&
             ! preg_match('/<IfModule\s+mod_rewrite\.c>/i', $block_content) ) {
            $issues[] = "{$feature_id}: Missing <IfModule mod_rewrite.c> wrapper";
        }

        // Rule 5: Forbidden directives in .htaccess context
        $forbidden = ['TraceEnable', 'ServerSignature', 'ServerTokens', '<Directory', '<Location'];
        foreach ( $forbidden as $directive ) {
            if ( stripos($block_content, $directive) !== false ) {
                $issues[] = "{$feature_id}: Forbidden directive '{$directive}' in .htaccess context";
            }
        }
    }

    return new VAPT_Check_Item(
        'rewrite_syntax',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All rewrite syntax valid' : implode('; ', $issues),
        $issues
    );
}
```

### 14. Blank Line Requirement Check

```php
/**
 * Standalone check specifically for the blank line contract.
 * Each rule block must end with EXACTLY ONE blank line before the END marker.
 * Each END marker must be followed by EXACTLY ONE blank line (between blocks).
 *
 * This is enforced as a separate check (in addition to check_rule_block_format)
 * because blank line violations are the most common formatting error.
 */
public function check_blank_line_requirement(): VAPT_Check_Item {
    $htaccess_path = ABSPATH . '.htaccess';
    $content       = file_get_contents($htaccess_path);
    $issues        = [];
    $corrections   = [];

    preg_match_all(
        '/(# BEGIN VAPT-RISK-[a-z0-9-]+\n)(.*?)(\n# END VAPT-RISK-[a-z0-9-]+)(\n*)/s',
        $content, $blocks, PREG_SET_ORDER
    );

    foreach ( $blocks as $block ) {
        preg_match('/# BEGIN VAPT-RISK-([a-z0-9-]+)/', $block[1], $id_match);
        $feature_id    = $id_match[1];
        $rule_content  = $block[2];  // everything between BEGIN and END markers
        $after_end     = $block[4];  // newlines after END marker

        // Contract A: rule content must end with exactly \n\n (one blank line before END)
        $trailing = strlen($rule_content) - strlen(rtrim($rule_content));
        if ( $trailing === 0 ) {
            $issues[]      = "{$feature_id}: No blank line before END marker (need exactly one)";
            $corrections[] = [ 'type' => 'fix_blank_line', 'feature_id' => $feature_id, 'position' => 'before_end' ];
        } elseif ( $trailing === 1 ) {
            $issues[]      = "{$feature_id}: Only one newline before END marker (need blank line = two newlines)";
            $corrections[] = [ 'type' => 'fix_blank_line', 'feature_id' => $feature_id, 'position' => 'before_end' ];
        } elseif ( $trailing > 2 ) {
            $issues[]      = "{$feature_id}: Multiple blank lines before END marker (need exactly one)";
            $corrections[] = [ 'type' => 'fix_blank_line', 'feature_id' => $feature_id, 'position' => 'before_end' ];
        }

        // Contract B: after END marker must be exactly \n\n (one blank line between blocks)
        // Only check if this isn't the last block (i.e. more content follows)
        if ( strlen($after_end) > 0 ) {
            if ( strlen($after_end) === 1 ) {
                $issues[]      = "{$feature_id}: No blank line after END marker (need exactly one between blocks)";
                $corrections[] = [ 'type' => 'fix_blank_line', 'feature_id' => $feature_id, 'position' => 'after_end' ];
            } elseif ( strlen($after_end) > 2 ) {
                $issues[]      = "{$feature_id}: Multiple blank lines after END marker (need exactly one)";
                $corrections[] = [ 'type' => 'fix_blank_line', 'feature_id' => $feature_id, 'position' => 'after_end' ];
            }
        }
    }

    return new VAPT_Check_Item(
        'blank_line_requirement',
        empty($issues) ? 'pass' : 'fail',
        empty($issues) ? 'All blank line contracts satisfied' : implode('; ', $issues),
        $corrections
    );
}
```

### 15. Admin Diagnostics Page

```php
// File: /includes/admin/class-vapt-diagnostics-page.php

class VAPT_Diagnostics_Page {

    public static function init(): void {
        add_action('admin_menu', [ __CLASS__, 'register_menu' ]);
        add_action('wp_ajax_vapt_run_diagnostics', [ __CLASS__, 'ajax_run_diagnostics' ]);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'vaptsecure',
            'Diagnostics & Self-Check',
            'Diagnostics',
            'manage_options',
            'vaptsecure-diagnostics',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die('Unauthorized');
        }
        ?>
        <div class="wrap">
            <h1>VAPTSecure Diagnostics</h1>
            <p>Run a full self-check to validate .htaccess integrity, WordPress endpoints,
               feature consistency, file permissions, and license state.</p>

            <button id="vapt-run-check" class="button button-primary">
                Run Diagnostics Now
            </button>

            <div id="vapt-check-results" style="margin-top:20px;"></div>

            <h2>Audit Log</h2>
            <?php self::render_audit_log(); ?>
        </div>

        <script>
        document.getElementById('vapt-run-check').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Running…';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=vapt_run_diagnostics&nonce=<?php echo wp_create_nonce('vapt_diagnostics'); ?>'
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('vapt-check-results').innerHTML = data.html;
                btn.disabled   = false;
                btn.textContent = 'Run Diagnostics Now';
            });
        });
        </script>
        <?php
    }

    public static function ajax_run_diagnostics(): void {
        check_ajax_referer('vapt_diagnostics', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized');
        }

        $result = VAPT_Self_Check::run('manual_trigger', [
            'requested_by' => get_current_user_id(),
            'checks'       => ['all'],
        ]);

        $status_class = [
            'pass'    => 'notice-success',
            'warning' => 'notice-warning',
            'fail'    => 'notice-error',
        ][ $result->get_overall_status() ] ?? 'notice-info';

        $html  = "<div class='notice {$status_class}'>";
        $html .= "<p><strong>Overall: " . strtoupper($result->get_overall_status()) . "</strong> — ";
        $html .= "Passed: {$result->get_passed_count()}, ";
        $html .= "Failed: {$result->get_failed_count()}, ";
        $html .= "Warnings: {$result->get_warning_count()}, ";
        $html .= "Corrections Applied: " . count($result->get_applied_corrections()) . "</p>";
        $html .= "</div>";

        foreach ( $result->get_all_results() as $item ) {
            $icon  = $item['status'] === 'pass' ? '✅' : ($item['status'] === 'warning' ? '⚠️' : '❌');
            $html .= "<p>{$icon} <strong>{$item['check_id']}</strong>: {$item['message']}</p>";
        }

        wp_send_json_success([ 'html' => $html ]);
    }

    private static function render_audit_log(): void {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}vapt_audit_log ORDER BY timestamp DESC LIMIT 50"
        );
        if ( empty($rows) ) {
            echo '<p>No audit log entries yet.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Time</th><th>Trigger</th><th>Status</th><th>Passed</th><th>Failed</th><th>Corrections</th>';
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $status_color = $row->overall_status === 'pass' ? 'green'
                          : ($row->overall_status === 'warning' ? 'orange' : 'red');
            echo "<tr>
                <td>{$row->timestamp}</td>
                <td><code>{$row->trigger_event}</code></td>
                <td style='color:{$status_color}'><strong>{$row->overall_status}</strong></td>
                <td>{$row->checks_passed}</td>
                <td>{$row->checks_failed}</td>
                <td>{$row->corrections_applied}</td>
            </tr>";
        }
        echo '</tbody></table>';
    }
}

add_action('init', ['VAPT_Diagnostics_Page', 'init']);
```

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

    private function fix_blank_line( array $correction ): array {
        $feature_id    = $correction['feature_id'];
        $htaccess_path = ABSPATH . '.htaccess';
        $id            = preg_quote( $feature_id, '/' );
        $content       = file_get_contents( $htaccess_path );

        $new_content = preg_replace_callback(
            "/(# BEGIN VAPT-RISK-{$id}\n)(.*?)(\n# END VAPT-RISK-{$id})/s",
            function( $m ) { return $m[1] . rtrim( $m[2] ) . "\n\n" . ltrim( $m[3], "\n" ); },
            $content
        );
        file_put_contents( $htaccess_path, $new_content );
        return [ 'status' => 'success', 'type' => 'fix_blank_line', 'feature_id' => $feature_id ];
    }

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
            'timestamp'           => current_time('mysql'),
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
        $site_name = get_bloginfo('name');
        $site_url  = get_site_url();
        $admin_url = admin_url('admin.php?page=vaptsecure-diagnostics');

        wp_mail(
            get_option('admin_email'),
            "[VAPTSecure] Critical Issue on {$site_name}",
            implode("\n", [
                "A critical issue was detected during the '{$trigger}' self-check.",
                "",
                "Site:                 {$site_url}",
                "Failed Checks:        " . $results->get_failed_count(),
                "Warnings:             " . $results->get_warning_count(),
                "Corrections Applied:  " . count( $results->get_applied_corrections() ),
                "",
                "Review the audit log: {$admin_url}",
                "Time: "                . current_time('mysql'),
            ])
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
    RewriteCond %{REQUEST_URI} !^/wp-admin/               [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-login\.php           [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-json/                [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-cron\.php            [NC]

    # ── Feature-specific security rule ───────────────────────────────────
    RewriteCond %{YOUR_CONDITION} your-value
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-{FEATURE-ID}

```

> **Blank line rule**: Exactly **one blank line** between last directive and `# END VAPT-RISK-...`, and **one blank line** after `# END VAPT-RISK-...`. No trailing whitespace. Self-check auto-corrects violations.

---

### Registry: Add / Remove Blocks per Feature

#### VAPT-RISK-BOT-PROTECTION

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-BOT-PROTECTION
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_URI} !^/wp-admin/               [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-login\.php           [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-json/                [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC,OR]
    RewriteCond %{REQUEST_URI} !^/wp-cron\.php            [NC]

    RewriteCond %{HTTP_USER_AGENT} ^.*(masscan|nikto|sqlmap|nmap|zgrab|dirbuster|nuclei).*$ [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-BOT-PROTECTION

```
**Remove on Disable:** Strip entire block including trailing blank line.

---

#### VAPT-RISK-REST-API-GUARD

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-REST-API-GUARD
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/           [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/oembed/          [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-json/vaptsecure/v1/   [NC]
    RewriteRule .* - [L]

    RewriteCond %{REQUEST_URI} ^/wp-json/                 [NC]
    RewriteCond %{HTTP_COOKIE}  !wordpress_logged_in      [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/      [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-REST-API-GUARD

```
**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-XMLRPC-BLOCK

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-XMLRPC-BLOCK
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_URI} ^/xmlrpc\.php$             [NC]
    RewriteCond %{HTTP_COOKIE}  !wordpress_logged_in      [NC]
    RewriteCond %{HTTP_USER_AGENT} !^(Jetpack)            [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-XMLRPC-BLOCK

```
**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-ADMIN-AJAX-GUARD

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-ADMIN-AJAX-GUARD
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTP_COOKIE} wordpress_logged_in        [NC]
    RewriteRule ^wp-admin/admin-ajax\.php$ - [L]

    RewriteCond %{REQUEST_URI} ^/wp-admin/admin-ajax\.php$ [NC]
    RewriteCond %{HTTP_REFERER} !^https?://{domain}/       [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-ADMIN-AJAX-GUARD

```
**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-FILE-PROTECT

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-FILE-PROTECT
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{REQUEST_URI} ^/wp-config\.php$          [NC,OR]
    RewriteCond %{REQUEST_URI} ^/readme\.html$            [NC,OR]
    RewriteCond %{REQUEST_URI} ^/license\.txt$            [NC,OR]
    RewriteCond %{REQUEST_URI} ^/wp-content/debug\.log$   [NC]
    RewriteRule .* - [F,L]

    RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\.php$ [NC]
    RewriteRule .* - [F,L]

</IfModule>

# END VAPT-RISK-FILE-PROTECT

```
**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-WP-INCLUDES

**Add on Enable:**
```apache
# BEGIN VAPT-RISK-WP-INCLUDES
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteRule ^wp-admin/includes/ - [F,L]

    RewriteRule !^wp-includes/ - [S=3]
    RewriteRule ^wp-includes/[^/]+\.php$                - [F,L]
    RewriteRule ^wp-includes/js/tinymce/langs/.+\.php   - [F,L]
    RewriteRule ^wp-includes/theme-compat/              - [F,L]

</IfModule>

# END VAPT-RISK-WP-INCLUDES

```
**Remove on Disable:** Strip entire block.

---

#### VAPT-RISK-SECURITY-HEADERS

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
function vapt_htaccess_write( string $feature_id, string $rule_content ): VAPT_Self_Check_Result {
    $htaccess_path = ABSPATH . '.htaccess';
    $domain_host   = wp_parse_url( get_site_url(), PHP_URL_HOST );

    $rule_content  = str_replace( '{domain}', $domain_host, $rule_content );
    $rule_content  = rtrim( $rule_content ) . "\n\n"; // exactly one blank line

    $block   = "# BEGIN VAPT-RISK-{$feature_id}\n" . $rule_content
             . "# END VAPT-RISK-{$feature_id}\n\n"; // one blank line after END

    $existing = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : '';
    $id       = preg_quote( $feature_id, '/' );
    $pattern  = "/(# BEGIN VAPT-RISK-{$id}\n).*?(# END VAPT-RISK-{$id}\n\n?)/s";
    $new      = preg_match( $pattern, $existing )
              ? preg_replace( $pattern, $block, $existing )
              : $existing . $block;

    file_put_contents( $htaccess_path, $new );

    return VAPT_Self_Check::run('htaccess_modify', ['feature_id' => $feature_id, 'rules_added' => $rule_content]);
}
```

### PHP: Removing a Rule Block

```php
function vapt_htaccess_remove( string $feature_id, bool $backup = true ): VAPT_Self_Check_Result {
    $htaccess_path = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        return VAPT_Self_Check::run('htaccess_modify', ['feature_id' => $feature_id]);
    }
    $content = file_get_contents( $htaccess_path );
    if ( $backup ) {
        copy( $htaccess_path, $htaccess_path . '.vapt-backup-' . date('Ymd-His') );
    }
    $id      = preg_quote( $feature_id, '/' );
    $new     = preg_replace( "/# BEGIN VAPT-RISK-{$id}\n.*?# END VAPT-RISK-{$id}\n\n?/s", '', $content );
    file_put_contents( $htaccess_path, $new );

    return VAPT_Self_Check::run('htaccess_modify', ['feature_id' => $feature_id, 'rules_removed' => true]);
}
```

---

## 🚫 MANDATORY RULES (Violations = Fail)

### Rule 1 — Never Block Core WordPress Paths

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

### Rule 2 — `.htaccess`-Safe Directives Only

| Status | Directive |
|--------|-----------|
| ✅ Allowed | `RewriteEngine`, `RewriteCond`, `RewriteRule` |
| ✅ Allowed | `Header set`, `<IfModule mod_headers.c>` |
| ✅ Allowed | `<IfModule mod_rewrite.c>` wrapper |
| ❌ Forbidden | `TraceEnable`, `ServerSignature`, `ServerTokens` |
| ❌ Forbidden | `<Directory>`, `<Location>`, `<Files>`, `<FilesMatch>` |

### Rule 3 — Rules Must Go Before WordPress Block

```apache
# CORRECT: VAPT rules ABOVE the WordPress block
# BEGIN VAPT-RISK-{FEATURE-ID}
... your rules ...
# END VAPT-RISK-{FEATURE-ID}

# BEGIN WordPress
...
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
{directives}                        ← Apache rules
                                    ← ✅ exactly ONE blank line
# END VAPT-RISK-{FEATURE-ID}       ← marker on its own line
                                    ← ✅ exactly ONE blank line (between blocks)
```

### Rule 6 — Self-Check Must Fire on All Critical Events

```php
vapt_htaccess_write( 'feature-id', $rules );   // auto-triggers self-check
vapt_htaccess_remove( 'feature-id' );           // auto-triggers self-check
register_deactivation_hook( __FILE__, ['VAPT_Lifecycle', 'on_deactivate'] );
register_uninstall_hook(    __FILE__, ['VAPT_Lifecycle', 'on_uninstall']  );
```

### Rule 7 — Mandatory ID for `div` Elements

Every `div` element generated in HTML, PHP, or JavaScript output—whether **newly created** or an **existing element being updated/modified**—**MUST** have a unique and descriptive `id` attribute. If an existing `div` lacks an `id`, it must be assigned one during the modification process. This is a non-negotiable requirement to ensure that the USER can easily identify and tweak specific components via CSS or JavaScript after they are generated.

---

## 🚀 Development Workflow

The following workflow must be followed for every code generation task:

1.  **Session Cleanup**: Clear `VAPT-Secure/vapt-debug.txt` at the start of every new session or major task. This file MUST NEVER be committed to Git.
2.  **Requirement Analysis**: Understand the feature or fix requested.
3.  **Schema Alignment**: Ensure all output aligns with the project's JSON schemas and architecture.
4.  **Mandatory Identifiers**: Ensure every **`div`** created or modified has a unique and descriptive **`id`**.
5.  **Self-Check**: Run the 19-point rubric and self-check engine BEFORE delivery.
6.  **Toggle Intelligence**: Ensure the code correctly responds to the feature's enabled/disabled state.

---

## 🔒 WordPress-Specific Security Rules

### Core Whitelist Table

| Endpoint | `.htaccess` Negative Condition |
|----------|-------------------------------|
| `https://{domain}/wp-admin/` | `RewriteCond %{REQUEST_URI} !^/wp-admin/ [NC]` |
| `https://{domain}/wp-login.php` | `RewriteCond %{REQUEST_URI} !^/wp-login\.php [NC]` |
| `https://{domain}/wp-json/` | `RewriteCond %{REQUEST_URI} !^/wp-json/ [NC]` |
| `https://{domain}/wp-admin/admin-ajax.php` | `RewriteCond %{REQUEST_URI} !^/wp-admin/admin-ajax\.php [NC]` |
| `https://{domain}/wp-cron.php` | `RewriteCond %{REQUEST_URI} !^/wp-cron\.php [NC]` |
| `https://{domain}/xmlrpc.php` | `RewriteCond %{REQUEST_URI} !^/xmlrpc\.php [NC]` |
| `https://{domain}/wp-content/uploads/` | `RewriteCond %{REQUEST_URI} !^/wp-content/uploads/ [NC]` |

---

## 📋 Feature Lifecycle Rules

### Draft → Develop
1. **Automated Initiation Check**: The system automatically validates and repairs all AI configuration symlinks via `VAPTSECURE_AI_Config::verify_and_repair()`.
2. Verify plugin dependencies exist
3. Apply `.htaccess` rules via `vapt_htaccess_write()`
4. Set up feature-specific database tables
5. Enable debug logging
6. Test: `https://{domain}/wp-json/wp/v2/`
7. **Trigger**: `VAPT_Self_Check::run('feature_enable', ['feature_id' => $id])`

### Develop → Deploy
1. Run all validation workflows
2. Disable debug logging
3. Test REST API: `https://{domain}/wp-json/wp/v2/`
4. Test admin AJAX: `https://{domain}/wp-admin/admin-ajax.php`
5. **Run full self-check** before final deployment

### Deploy → Reset to Draft

```javascript
actions:
  - trigger_self_check:     { event: 'feature_disable', feature_id: '{FEATURE-ID}', auto_correct: true }
  - remove_htaccess_rules:  { scope: "feature-specific", backup_before_remove: true }
  - wipe_feature_data:      { tables: ["wp_vapt_features", "wp_vapt_feature_meta"], cascade: true }
  - remove_config_files:    { path: "data/generated/{FEATURE-ID}/" }
  - log_operation:          { level: "info", category: "feature_lifecycle", action: "reset_to_draft" }
  - update_feature_state:   { new_state: "Draft", previous_state: "Develop" }
```

---

## 🔧 Technical Constraints

1. All feature JSON must validate against `/data/VAPTSchema-Builder/`
2. Use `interface_schema_v2.0.json` as blueprint; `ai_agent_instructions_v2.0.json` for formatting
3. **Always reference the enforcer library** — never write patterns from memory
4. **4-step workflow**: Rulebook → Blueprint → Enforcement → Self-Check
5. **Score output against 19-point rubric** before delivering
6. **Unique ID Requirement**: Every `div` element must have a mandatory `id` attribute for easier identification and tweaking.
7. **Naming**: `UI-RISK-XXX-YYY` format
8. **Domain**: always `{domain}` — never any literal hostname

---

## 💬 Communication Style

1. **Be concise and direct** — avoid unnecessary qualifiers
2. **Provide working code** — not pseudocode
3. **Include security context** — explain the "why" behind each rule
4. **Reference documentation** — link to relevant JSON schema files
5. **Use `{domain}` placeholder** — never literal domains in any generated output

---

## 🚦 Rate Limiting Standards

**Purpose:** Protect WordPress native forms and endpoints from abuse through configurable rate limiting.

**Protected Forms:**
- WordPress Native Login Form (`/wp-login.php`)
- WordPress Registration Form (`/wp-login.php?action=register`)
- Lost Password Form (`/wp-login.php?action=lostpassword`)
- Comment Submission Form
- XML-RPC endpoint (`/xmlrpc.php`)

**Configuration Options:**

| Setting | Options | Default |
|---------|---------|---------|
| **Time Window** | Per Minute / Per Hour / Per Day | Per Minute |
| **Allowed Limit** | 1-1000 requests | 5 (per minute) |
| **Test Limit** | 1.0x - 2.0x of Allowed Limit | 1.25x |

**Rate Limit Tiers:**
- `strict` - Low limits, immediate blocking (admin/login areas)
- `moderate` - Balanced limits with warning threshold
- `lenient` - Higher limits for public content

**Technical Requirements:**
- Store counters in transients (not database for performance)
- Whitelist logged-in administrators (via `wordpress_logged_in_` cookie)
- Return HTTP 429 on limit exceeded with `Retry-After` header
- Auto-purge expired counters via WP-Cron

**Test & Verify Functionality:**

| Component | Description |
|-----------|-------------|
| **Test Limit Button** | Simulates requests up to test limit (1.25x allowed) without actual blocking |
| **Realtime Statistics** | Live counter showing: Attempts, Allowed, Blocked |
| **Visual Indicators** | Progress bar showing usage vs limit |
| **Reset Counters** | Manual reset for testing purposes |

**Realtime Statistics Display:**
```
┌─────────────────────────────────────────┐
│  Realtime Test Statistics               │
├───────────┬───────────┬─────────────────┤
│ Attempts  │ Allowed   │ Blocked         │
│    12     │     5     │       7         │
└───────────┴───────────┴─────────────────┘
[████████░░░░░░░░░░] 58% of limit used
```

**Example .htaccess Pattern:**
```apache
# BEGIN VAPT-RATE-LIMIT-LOGIN
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Exempt logged-in users (via cookie check)
    RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
    # Rate limiting via environment variables
    RewriteRule .* - [E=VAPT_LOGIN_LIMIT:1]
</IfModule>
# END VAPT-RATE-LIMIT-LOGIN
```

---

## 🔒 Progressive IP Lockout & Blacklisting

**Purpose:** Escalating lockout penalties for repeated failed login attempts with permanent blacklist for persistent attackers.

**Lockout Escalation Matrix:**

| Failed Attempts | Lockout Duration | Status |
|-----------------|------------------|--------|
| 3 | 3 minutes | Temporary |
| 5 | 5 minutes | Temporary |
| 10 | 30 minutes | Temporary |
| >10 | Permanent | Blacklisted (Admin release required) |

**Algorithm Flow:**
```
User Attempts Login
        ↓
Check IP Blacklist → If BLACKLISTED → Block + Show admin contact message
        ↓ (not blacklisted)
Check Active Lockout → If LOCKED → Show remaining time
        ↓ (not locked)
Validate Credentials
        ↓
   Failure? → Increment Fail Count → Check Escalation Threshold → Apply Lockout
        ↓ (success)
   Clear Fail Count + Log Success
```

**Data Storage Structure:**
```php
// IP tracking entry (wp_vapt_ip_lockouts)
{
    "ip_address": "192.168.1.100",
    "fail_count": 4,
    "first_attempt": "2026-04-05 10:00:00",
    "last_attempt": "2026-04-05 10:15:00",
    "lockout_until": "2026-04-05 10:18:00",  // 3 min lockout
    "status": "locked"  // active | locked | blacklisted
}

// Permanent blacklist entry (wp_vapt_ip_blacklist)
{
    "ip_address": "192.168.1.100",
    "blacklisted_at": "2026-04-05 10:45:00",
    "reason": "Exceeded 10 failed attempts",
    "release_requires": "admin_action",
    "released_by": null,
    "released_at": null
}
```

**PHP Implementation:**
```php
class VAPT_IP_Lockout {
    
    private const LOCKOUT_RULES = [
        3  => 180,   // 3 minutes in seconds
        5  => 300,   // 5 minutes
        10 => 1800,  // 30 minutes
    ];
    
    private const BLACKLIST_THRESHOLD = 10;
    
    /**
     * Check if IP is blocked (locked or blacklisted)
     */
    public function is_blocked( string $ip ): array {
        // Check blacklist first
        if ( $this->is_blacklisted( $ip ) ) {
            return [
                'blocked'  => true,
                'reason'   => 'blacklisted',
                'message'  => 'Access permanently blocked. Contact site administrator.',
                'until'    => null
            ];
        }
        
        // Check active lockout
        $lockout = $this->get_lockout( $ip );
        if ( $lockout && strtotime( $lockout['lockout_until'] ) > time() ) {
            $remaining = strtotime( $lockout['lockout_until'] ) - time();
            return [
                'blocked'   => true,
                'reason'    => 'locked',
                'message'   => 'Too many failed attempts.',
                'until'     => $lockout['lockout_until'],
                'remaining' => $remaining
            ];
        }
        
        return ['blocked' => false];
    }
    
    /**
     * Record failed attempt and apply lockout if threshold reached
     */
    public function record_failure( string $ip ): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vapt_ip_lockouts';
        $data  = $this->get_or_create_entry( $ip );
        
        $data['fail_count']++;
        $data['last_attempt'] = current_time( 'mysql' );
        
        // Check for permanent blacklist
        if ( $data['fail_count'] > self::BLACKLIST_THRESHOLD ) {
            $this->blacklist_ip( $ip, $data['fail_count'] );
            return [
                'action'  => 'blacklisted',
                'message' => 'IP permanently blocked due to excessive failures.'
            ];
        }
        
        // Check for temporary lockout
        foreach ( self::LOCKOUT_RULES as $threshold => $duration ) {
            if ( $data['fail_count'] === $threshold ) {
                $lockout_until = time() + $duration;
                $data['status'] = 'locked';
                $data['lockout_until'] = date( 'Y-m-d H:i:s', $lockout_until );
                
                $this->update_entry( $ip, $data );
                
                return [
                    'action'    => 'locked',
                    'duration'  => $duration,
                    'until'     => $data['lockout_until'],
                    'message'   => "IP locked for {$duration} seconds after {$threshold} failed attempts."
                ];
            }
        }
        
        $this->update_entry( $ip, $data );
        return ['action' => 'counted', 'fails' => $data['fail_count']];
    }
    
    /**
     * Clear fail count on successful login
     */
    public function clear_failures( string $ip ): void {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'vapt_ip_lockouts',
            ['ip_address' => $ip],
            ['%s']
        );
    }
}
```

**Admin IP Release Interface:**

Admin page: VAPT Security → IP Blacklist

```php
class VAPT_IP_Blacklist_Admin {
    
    public function render_page() {
        // Two tables:
        // 1. Currently Locked IPs with "Release Now" buttons
        // 2. Permanently Blacklisted IPs with "Remove from Blacklist" buttons
        // AJAX handlers for release actions with nonce validation
    }
}
```

**Release Action Handler:**
```php
add_action( 'wp_ajax_vapt_release_blacklist', function() {
    check_ajax_referer( 'vapt_blacklist_release', '_ajax_nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $ip = sanitize_text_field( $_POST['ip'] ?? '' );
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        wp_send_json_error( 'Invalid IP address' );
    }
    
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'vapt_ip_blacklist',
        [
            'released_by' => get_current_user_id(),
            'released_at' => current_time( 'mysql' )
        ],
        ['ip_address' => $ip, 'released_at' => null]
    );
    
    // Also clear from lockouts table
    $wpdb->delete( $wpdb->prefix . 'vapt_ip_lockouts', ['ip_address' => $ip] );
    
    // Log admin action
    vapt_log_admin_action( 'ip_release', $ip, get_current_user_id() );
    
    wp_send_json_success( ['released' => $ip] );
} );
```

---

## 🌐 FQDN Path Standards

**CRITICAL:** All paths in security rules MUST be converted to Fully Qualified Domain Name (FQDN) format with `https://` scheme.

**MANDATORY Path Conversions:**

| Relative Path | FQDN Format |
|---------------|-------------|
| `/xmlrpc.php` | `https://{domain}/xmlrpc.php` |
| `/wp/v2/users` | `https://{domain}/wp-json/wp/v2/users` |
| `/wp-login.php` | `https://{domain}/wp-login.php` |
| `/wp-admin/` | `https://{domain}/wp-admin/` |
| `/wp-cron.php` | `https://{domain}/wp-cron.php` |
| `/wp-json/wp/v2/` | `https://{domain}/wp-json/wp/v2/` |
| `/wp-admin/admin-ajax.php` | `https://{domain}/wp-admin/admin-ajax.php` |

**PHP FQDN Conversion Helper:**
```php
/**
 * Convert relative path to FQDN format
 * 
 * @param string $path Relative path (e.g., '/xmlrpc.php')
 * @return string FQDN URL (e.g., 'https://example.com/xmlrpc.php')
 */
function vapt_get_fqdn( string $path ): string {
    $domain = get_site_url();  // https://example.com
    $path   = ltrim( $path, '/' );
    return trailingslashit( $domain ) . $path;
}

// Usage examples
$xmlrpc_url = vapt_get_fqdn( 'xmlrpc.php' );  // https://{domain}/xmlrpc.php
$rest_users = vapt_get_fqdn( 'wp-json/wp/v2/users' );  // https://{domain}/wp-json/wp/v2/users
```

**Documentation URL Standard:**
- ✅ `https://{domain}/wp-admin/`
- ✅ `https://{domain}/wp-json/wp/v2/`
- ❌ `yoursite.com/wp-admin/` (no scheme)
- ❌ `/wp-admin/` (relative path)
- ❌ `example.com/xmlrpc.php` (no placeholder)

---

## 🔐 Native Endpoints Security

**Purpose:** Protect WordPress REST API and critical endpoints from enumeration attacks and unauthorized access.

**Critical Endpoints Registry:**

| Endpoint | Protection Type | Risk Level |
|----------|----------------|------------|
| `/wp-json/wp/v2/users` | Enumeration prevention | High |
| `/wp-json/wp/v2/posts` | Data exposure limit | Medium |
| `/wp-json/wp/v2/media` | Unauthorized access | High |
| `/wp-json/wp/v2/comments` | Spam prevention | Medium |
| `/xmlrpc.php` | Disable/Restrict | Critical |
| `/wp-trackback.php` | Disable | Low |
| `/wp-json/wp/v2/users/me` | Allow own data | Low |

**Implementation Rules:**
- Always use FQDN format in documentation
- Use `RewriteCond %{REQUEST_URI}` patterns for blocking
- Preserve admin access via capability checks
- Log blocked attempts to audit trail
- Never block `/wp-json/vaptsecure/v1/` (plugin API)

**Example .htaccess Rules:**
```apache
# BEGIN VAPT-RISK-ENDPOINT-PROTECTION
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Block user enumeration via REST API
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/users [NC]
    RewriteCond %{QUERY_STRING} !^.*context=edit.*$
    RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
    RewriteRule .* - [F,L]
    
    # Disable XML-RPC
    RewriteCond %{REQUEST_URI} ^/xmlrpc\.php [NC]
    RewriteRule .* - [F,L]
</IfModule>
# END VAPT-RISK-ENDPOINT-PROTECTION
```

---

## 📝 Contact Form Security Standards

**Purpose:** Provide security guidelines for sanitization and validation across popular WordPress contact form plugins.

**Supported Plugins (10 Total):**

**Tier 1 - Most Popular:**
1. **Contact Form 7** - Free, 5M+ installs
2. **WPForms** - Freemium, 6M+ installs
3. **Elementor Forms** - Bundled with Elementor Pro
4. **Gravity Forms** - Premium, industry standard

**Tier 2 - Widely Used:**
5. **Ninja Forms** - Freemium, 900K+ installs
6. **Formidable Forms** - Freemium, 300K+ installs
7. **Fluent Forms** - Freemium, 400K+ installs

**Tier 3 - Custom/Bespoke:**
8. **Custom HTML Forms** - Hand-coded forms
9. **Custom JavaScript Forms** - AJAX-based forms
10. **Theme-Integrated Forms** - Forms from themes (Divi, Avada, etc.)

**Universal Sanitation Requirements:**
- **Input Validation:** Whitelist allowed characters per field type
- **SQL Injection Prevention:** Use `$wpdb->prepare()` for any DB operations
- **XSS Prevention:** Escaping with `wp_kses_post()`, `esc_html()`, `esc_attr()`
- **CSRF Protection:** Nonce validation with `wp_nonce_field()` and `wp_verify_nonce()`
- **Honeypot Fields:** Hidden field spam detection
- **Rate Limiting:** Per-IP submission limits

**Plugin-Specific Implementation:**
```php
// Contact Form 7
add_filter( 'wpcf7_validate_text', 'vapt_sanitize_cf7_field', 10, 2 );
add_filter( 'wpcf7_validate_email', 'vapt_validate_cf7_email', 10, 2 );

// WPForms
add_action( 'wpforms_process_validate_text', 'vapt_sanitize_wpforms_field', 10, 3 );

// Elementor Forms
add_action( 'elementor_pro/forms/validation', 'vapt_validate_elementor_form', 10, 2 );

// Custom HTML/JS Forms
function vapt_sanitize_custom_form() {
    check_ajax_referer( 'vapt_custom_form_nonce', 'nonce' );
    $name  = sanitize_text_field( $_POST['name'] ?? '' );
    $email = sanitize_email( $_POST['email'] ?? '' );
    $message = sanitize_textarea_field( $_POST['message'] ?? '' );
    // ... validation logic
}
```

---

## 🛡️ OWASP Top 10 (2025) Compliance

**Purpose:** Map VAPTSecure features to OWASP Top 10 2025 security categories.

| OWASP 2025 | VAPTSecure Implementation | Priority |
|------------|---------------------------|----------|
| **A01 - Broken Access Control** | Endpoint whitelisting, capability checks | Critical |
| **A02 - Cryptographic Failures** | HTTPS enforcement, secure cookies | High |
| **A03 - Injection** | Input validation, `$wpdb->prepare()` | Critical |
| **A04 - Insecure Design** | Rate limiting, security by default | High |
| **A05 - Security Misconfiguration** | Self-check automation, secure defaults | High |
| **A06 - Vulnerable Components** | Dependency scanning, auto-updates | Medium |
| **A07 - Auth Failures** | 2FA support, brute force protection | High |
| **A08 - Data Integrity Failures** | File integrity monitoring | Medium |
| **A09 - Logging Failures** | Comprehensive audit logging | High |
| **A10 - SSRF** | URL validation, request filtering | Medium |

**Implementation Guidelines:**
- Tag features with `owasp_a01` through `owasp_a10` metadata
- Generate compliance reports per OWASP category
- Map every security rule to corresponding OWASP category

---

## ⚡ Severity Response Matrix

**Purpose:** Calibrate security responses based on threat severity.

| Severity | Response | Example |
|----------|----------|---------|
| **Critical** | Block + Alert + Log | SQL injection attempt |
| **High** | Block + Log | Failed admin login (5x) |
| **Medium** | Rate Limit + Log | Excessive API requests |
| **Low** | Log Only | Suspicious user agent |

**PHP Implementation:**
```php
function vapt_get_response_action( string $severity ): array {
    $actions = [
        'critical' => [
            'block' => true, 
            'alert' => true, 
            'log' => true, 
            'notify_admin' => true
        ],
        'high' => [
            'block' => true, 
            'alert' => false, 
            'log' => true, 
            'notify_admin' => false
        ],
        'medium' => [
            'block' => false, 
            'rate_limit' => true, 
            'log' => true, 
            'notify_admin' => false
        ],
        'low' => [
            'block' => false, 
            'log' => true, 
            'notify_admin' => false
        ],
    ];
    return $actions[$severity] ?? $actions['low'];
}
```

---

## 📋 WordPress Coding Standards

**Purpose:** Enforce WordPress security coding standards for all generated code.

**Database Queries:**
```php
// ALWAYS use prepare()
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vapt_logs WHERE user_id = %d AND created_at > %s",
        $user_id,
        $date_from
    )
);
```

**Nonce Validation:**
```php
// ALWAYS verify for state-changing actions
if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'vapt_action_' . $feature_id ) ) {
    wp_send_json_error( ['message' => 'Invalid security token'] );
    wp_die();
}
```

**Capability Checks:**
```php
// ALWAYS check permissions
current_user_can( 'manage_options' );           // Admin only
current_user_can( 'activate_plugins' );       // Plugin management
current_user_can( 'vapt_manage_security' );   // Custom capability
```

**Output Escaping:**
```php
echo esc_html( $title );           // Plain text
echo esc_attr( $class_name );      // HTML attributes
echo wp_kses_post( $content );     // Limited HTML allowed
echo esc_url( $url );              // URLs
echo esc_js( $javascript_var );    // JavaScript
```

---

## 🔗 IDE Loader Verification

**Purpose:** Verify all IDE loader files correctly point to the canonical `.ai/SOUL.md`.

**Symlink Verification Script:**

```bash
#!/bin/bash
# verify-symlinks.sh - Run to verify all IDE loaders point to canonical SOUL.md

CANONICAL="$(pwd)/.ai/SOUL.md"
LOADERS=(
  ".cursor/cursor.rules"
  ".trae/trae.rules"
  ".gemini/gemini.md"
  ".vscode/SOUL.md"
  ".windsurfrules"
  ".roo/rules/soul.md"
  ".clinerules"
  ".roorules"
)

for loader in "${LOADERS[@]}"; do
  if [ -L "$loader" ]; then
    target=$(readlink "$loader")
    if [ "$target" = "$CANONICAL" ]; then
      echo "✅ $loader -> $target"
    else
      echo "⚠️  $loader -> $target (expected: $CANONICAL)"
    fi
  elif [ -f "$loader" ]; then
    echo "❌ $loader (file, not symlink)"
  else
    echo "❌ $loader (missing)"
  fi
done
```

**Loader File Status:**

| IDE | Loader File | Status | Target |
|-----|-------------|--------|--------|
| Cursor | `.cursor/cursor.rules` | ✅ Symlink | `.ai/SOUL.md` |
| Trae | `.trae/trae.rules` | ✅ Symlink | `.ai/SOUL.md` |
| Gemini | `.gemini/gemini.md` | ✅ Symlink | `.ai/SOUL.md` |
| VS Code | `.vscode/SOUL.md` | ✅ Symlink | `.ai/SOUL.md` |
| Windsurf | `.windsurfrules` | ✅ Symlink | `.ai/SOUL.md` |
| Roo Code | `.clinerules` | ✅ Symlink | `.ai/SOUL.md` |
| Kilo Code | `.kilocode/rules/soul.md` | ✅ Symlink | `.ai/SOUL.md` |
| Continue | `.continue/rules/soul.md` | ✅ Symlink | `.ai/SOUL.md` |
| OpenCode | `.opencode/instructions/SOUL.md` | ✅ Symlink | `.ai/SOUL.md` |
| Junie | `.junie/guidelines.md` | ✅ Symlink | `.ai/SOUL.md` |
| Zed | `.rules` | ✅ Symlink | `.ai/SOUL.md` |

---

## 🎓 Domain Expertise Areas

1. **Apache `.htaccess`** — [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html), [mod_headers](https://httpd.apache.org/docs/current/mod/mod_headers.html)
2. **WordPress Security** — [Security Handbook](https://developer.wordpress.org/apis/security/)
3. **WordPress REST API** — [REST API Handbook](https://developer.wordpress.org/rest-api/)
4. **WordPress Hardening** — [Hardening WordPress](https://developer.wordpress.org/advanced-administration/security/hardening/)
5. **Plugin Lifecycle Hooks** — [Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
6. **Vulnerability Catalogs** — [OWASP Top 10](https://owasp.org/Top10/), [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)
7. **JSON Schema Validation** — VAPT schemas in `/data/VAPTSchema-Builder/`
8. **Self-Check Automation** — event-driven validation and auto-correction
9. **Rate Limiting & IP Blocking** — progressive lockout algorithms
10. **Contact Form Security** — plugin-specific sanitization patterns

---

## 🔍 Troubleshooting

### Common Issues

**500 Errors after `.htaccess` modification**
- Check `RewriteCond`/`RewriteRule` syntax
- Verify insertion is `before_wordpress_rewrite`
- Test: `curl -I https://{domain}/wp-json/wp/v2/`

**REST API returning 403**
- Verify `https://{domain}/wp-json/` is whitelisted
- Test: `curl https://{domain}/wp-json/wp/v2/posts`

**`admin-ajax.php` returning 403**
- Correct path: `/wp-admin/admin-ajax.php` (NOT `/admin-ajax.php`)
- Verify `wordpress_logged_in` cookie bypass is present

**Rule block format violations**
- Self-check auto-corrects blank line issues automatically
- Verify manually: `cat -A .htaccess | grep 'VAPT'`

### WordPress Endpoint Testing

```bash
curl -I https://{domain}/wp-json/wp/v2/
curl -I https://{domain}/wp-json/oembed/1.0/
curl -I https://{domain}/wp-admin/admin-ajax.php
curl -I https://{domain}/wp-login.php
curl -I https://{domain}/wp-json/vaptsecure/v1/
grep -n 'VAPT' /path/to/webroot/.htaccess
```

### Self-Check Manual Trigger

```php
$result = VAPT_Self_Check::run('manual_trigger', [
    'requested_by' => get_current_user_id(),
    'checks'       => ['all'],
]);
if ( $result->has_failures() ) {
    echo 'Issues: ' . $result->get_failed_count();
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
| Kilo Code Docs | [https://kilo.ai/docs](https://kilo.ai/docs) |
| Continue Docs | [https://docs.continue.dev/](https://docs.continue.dev/) |
| Roo Code Docs | [https://docs.roocode.com/](https://docs.roocode.com/) |
| GitHub Copilot Custom Instructions | [https://docs.github.com/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot](https://docs.github.com/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot) |
| JetBrains Junie Docs | [https://www.jetbrains.com/help/junie/](https://www.jetbrains.com/help/junie/) |
| Zed AI Rules Docs | [https://zed.dev/docs/ai/rules](https://zed.dev/docs/ai/rules) |

---

*This `SOUL.md` defines universal AI behavior for the VAPTSecure plugin project.*
*Edit this file once — changes propagate to **all 14 editors and extensions** via their respective symlinks.*
*Version: 2.6.0 | Last Updated: April 2026*
