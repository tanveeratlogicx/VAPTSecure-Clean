---
description: VAPTSecure Plugin - AI Agent Compliance Rules
version: 2.6.1
type: mandatory_checklist
scope: VAPTSecure Plugin Only
last_updated: 2025-01-20
---

# VAPTSecure AI Agent Compliance Document

> **CRITICAL:** This document MUST be read and acknowledged before ANY code generation for the VAPTSecure plugin. Violations of these rules will result in broken WordPress sites and security vulnerabilities.

---

## Phase 1: Pre-Generation Checklist (MANDATORY)

**Before ANY code generation, the agent MUST complete ALL items:**

### 1.1 File Reading Verification
- [ ] Read `data/interface_schema_v2.0.json` - Understand risk structure and UI components
- [ ] Read `data/enforcer_pattern_library_v2.0.json` - Load enforcement patterns for all lib_keys
- [ ] Read `data/vapt_driver_manifest_v2.0.json` - Understand driver layer execution
- [ ] Read `data/ai_agent_instructions_v2.0.json` - Review syntax guards and rubric
- [ ] **Read `data/Enforcers/{lib_key}-template.json` for target platform** - Platform-specific risk templates (htaccess-template.json, nginx-template.json, wp-config-template.json, etc.)

### 1.2 Architecture Confirmation
- [ ] Confirm understanding: VAPTSecure is a **RULE ENGINE**, not a traditional plugin
- [ ] Confirm understanding: **NEVER** write enforcement code from memory - always read from pattern library
- [ ] Confirm understanding: `lib_key` determines syntax (htaccess, wp_config, php_functions, etc.)
- [ ] Confirm understanding: 5-file Unified Bundle v2.0 system:
  - AI Agent Layer: `enforcer_pattern_library_v2.0.json`, `interface_schema_v2.0.json`, `ai_agent_instructions_v2.0.json`
  - Driver Layer: `vapt_driver_manifest_v2.0.json`, `VAPT_Driver_Reference_v2.0.php`

### 1.3 Safety Guardrails Verification
- [ ] Verify: Will NOT block `/wp-admin/`, `/wp-login.php`, `/wp-json/wp/v2/`, `/wp-json/vaptsecure/v1/`
- [ ] Verify: htaccess RewriteRule directives go **BEFORE** `# BEGIN WordPress`
- [ ] Verify: No forbidden directives (`TraceEnable`, `ServerSignature`, `ServerTokens`, `<Directory>`, `<?php`)
- [ ] Verify: Using proper block markers (`# BEGIN VAPT RISK-XXX` / `# END VAPT RISK-XXX`)
- [ ] Verify: All RewriteRule blocks wrapped in `<IfModule mod_rewrite.c>` with `RewriteEngine On` and `RewriteBase /`

### 1.4 Naming Convention Confirmation
- [ ] Component IDs: `UI-RISK-{NNN}-{SEQ}` (e.g., `UI-RISK-003-001`)
- [ ] Action IDs: `ACTION-{NNN}-{SEQ}` (e.g., `ACTION-003-001`)
- [ ] Handler names: `handleRISK{NNN}ToggleChange`, `handleRISK{NNN}DropdownChange`
- [ ] Settings keys: `vapt_risk_{nnn}_enabled` (lowercase, underscores)
- [ ] PHP functions: `vapt_{descriptive_name}` (e.g., `vapt_disable_xmlrpc`)
- [ ] Caddy matchers: `@risk{nnn}` (alphanumeric only, NO hyphens)

---

## Phase 2: Post-Generation Validation (MANDATORY)

**After code generation, score against this rubric. Minimum score: 16/20. Never deliver output scoring < 16.**

### Self-Check Rubric Score Card

| # | Check | Weight | Status | Notes |
|---|-------|--------|--------|-------|
| 1 | Component IDs match interface_schema_v2.0 exactly - no fabricated IDs | 2 | [ ] | |
| 2 | All enforcement code read from enforcer_pattern_library_v2.0 - nothing from memory | 2 | [ ] | |
| 3 | Severity badge colors match global_ui_config.severity_badge_colors | 1 | [ ] | |
| 4 | Handler names follow naming_conventions (handleRISK{NNN}ToggleChange etc.) | 1 | [ ] | |
| 5 | Platform is listed in risk_interfaces[rid].available_platforms | 1 | [ ] | |
| 6 | VAPT block markers (begin_marker/end_marker) present in all code output | 1 | [ ] | |
| 7 | verification.command present and matches the platform CLI | 1 | [ ] | |
| 8 | No forbidden patterns (snake_case component IDs, fabricated hook names) | 1 | [ ] | |
| 9 | No forbidden .htaccess directives (TraceEnable, ServerSignature, ServerTokens, <Directory>) | 2 | [ ] | |
| 10 | All RewriteRule/RewriteCond placed BEFORE # BEGIN WordPress (not after) | 1 | [ ] | |
| 11 | All RewriteRule/RewriteCond wrapped in <IfModule mod_rewrite.c> with RewriteEngine On and RewriteBase / | 1 | [ ] | |
| 12 | mod_headers requirement noted for all Header directives | 1 | [ ] | |
| 13 | AllowOverride requirement noted for Options directives | 1 | [ ] | |
| 14 | RISK-020 target_file = wp-content/uploads/.htaccess (not root .htaccess) | 1 | [ ] | |
| 15 | IIS <rewrite> sections include URL Rewrite Module 2.1 requirement note | 1 | [ ] | |
| 16 | Caddy output uses v2 syntax only - no Apache directives, no semicolons, no Order/Deny | 1 | [ ] | |
| 17 | code_ref in interface schema uses correct lib_key (htaccess not _htaccess, wp_config not wp-config) | 1 | [ ] | |
| 18 | driver_ref in interface schema points to vapt_driver_manifest_v2.0 | 1 | [ ] | |
| 19 | For driver diagnosis: all required driver{} sub-fields present | 1 | [ ] | |
| 20 | Payload syntax matches target file engine (.htaccess != PHP, wp-config.php != Apache) | 1 | [ ] | |
|   | **TOTAL** | **20** | **[__/20]** | |

**Scoring Rule:** Sum weights of passing checks. Score < 16 → identify failing checks and regenerate. Never deliver output scoring < 16.

---

## VAPT_Driver Execution Reference

### Driver Apply/Rollback Contract

```php
$driver  = new VAPT_Driver( ABSPATH, plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' );
$results = $driver->apply( 'RISK-003' );    // Apply all steps for a risk
$results = $driver->rollback( 'RISK-003' ); // Remove all VAPT blocks for this risk
```

### Execution Sequence (9 Steps)

1. **Resolve** `{ABSPATH}` in `target_file` → full filesystem path
2. **Create** target file if missing (e.g., `wp-content/uploads/.htaccess`)
3. **Idempotency Check** - Read `idempotency.check_string` from file
   - If found AND `if_found=skip` → return "already applied"
   - If found AND `if_found=replace` → remove existing block first
4. **Backup** target file (append `.vapt.bak.{timestamp}`)
5. **Find** `insertion.anchor_string` in file content
6. **Insert** `write_block` at `anchor_position` (before/after/prepend/append)
7. **Fallback** if anchor not found → use `insertion.fallback` strategy
8. **Write** new content to `target_file`
9. **Rollback** on any write failure → remove `begin_marker..end_marker` block

### Required Driver Step Fields

| Field | Description |
|-------|-------------|
| `write_mode` | Mode for writing (insert, append, prepend, replace) |
| `target_file` | Path with `{ABSPATH}` placeholder |
| `write_block` | The actual code/content to write |
| `begin_marker` | Start marker for the block |
| `end_marker` | End marker for the block |
| `insertion.anchor_string` | String to find for insertion point |
| `insertion.anchor_position` | Position relative to anchor (before/after/prepend/append) |
| `insertion.fallback` | Strategy if anchor not found |
| `idempotency.check_string` | String to check for existing installation |
| `idempotency.if_found` | Behavior if check_string found (skip/replace) |
| `backup_required` | Boolean - create backup before write |
| `verification.command` | CLI command to verify installation |
| `verification.expected` | Expected output from verification command |
| `rollback.begin_marker` | Marker for rollback removal |
| `rollback.end_marker` | Marker for rollback removal |
| `rollback.target_file` | File to rollback (may differ from target) |

---

## Enforcer Block Markers Reference

| Enforcer Type | Begin Marker | End Marker |
|----------------|--------------|------------|
| `.htaccess` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `nginx` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `apache` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `caddy` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `fail2ban` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `server_cron` | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `wp_config` | `/* BEGIN VAPT RISK-XXX */` | `/* END VAPT RISK-XXX */` |
| `php_functions` | `// BEGIN VAPT RISK-XXX` | `// END VAPT RISK-XXX` |
| `wordpress` | `// BEGIN VAPT RISK-XXX` | `// END VAPT RISK-XXX` |
| `wordpress_core` | `// BEGIN VAPT RISK-XXX` | `// END VAPT RISK-XXX` |
| `cloudflare` | N/A (API-based) | N/A (API-based) |
| `iis` | XML comments or section markers | XML comments or section markers |

---

## Insertion Point Reference

| Token | Anchor String | Position | Use Case |
|-------|--------------|----------|----------|
| `before_wordpress_rewrite` | `# BEGIN WordPress` | before | **ALL RewriteRule/RewriteCond - MANDATORY** |
| `after_wordpress_rewrite` | `# END WordPress` | after | Header, Options, Files only |
| `beginning_of_file` | *(none)* | prepend | All non-rewrite directives |
| `end_of_file` | *(none)* | append | Non-rewrite directives only |
| `before_wp_settings` | `require_once ABSPATH` | before | All wp-config.php constants |
| `functions_php` | *(none)* | append | PHP Functions / WP hooks |
| `jail_local` | *(none)* | append | fail2ban rules |
| `http_block` | `http {` | after | Nginx directives |
| `crontab_entry` | *(none)* | append | Server Cron jobs |

### Why `before_wordpress_rewrite` is Mandatory

WordPress's block ends with:
```apache
RewriteRule . /index.php [L]
```

The `[L]` flag stops all further rewrite processing. Any RewriteRule placed **after** `# END WordPress` is in a **dead zone** and will **never execute**.

**CRITICAL:** Always place RewriteRule/RewriteCond **BEFORE** `# BEGIN WordPress`.

---

## lib_key Reference

| lib_key | Syntax Type | Target File | Notes |
|---------|-------------|-------------|-------|
| `htaccess` | Apache Rewrite | `{ABSPATH}.htaccess` | Most common platform |
| `nginx` | Nginx Config | `/etc/nginx/conf.d/vapt-security.conf` | Server-level config |
| `wp_config` | PHP Constants | `{ABSPATH}wp-config.php` | Define constants |
| `php_functions` | WP Hooks | Plugin functions file | add_action/add_filter |
| `wordpress` | WP Hooks | Theme/Plugin | Standard WP hooks |
| `wordpress_core` | Core Filters | Core files | Deep core integration |
| `fail2ban` | Jail Config | `/etc/fail2ban/jail.local` | Intrusion detection |
| `caddy` | Caddyfile v2 | `/etc/caddy/Caddyfile` | Modern web server |
| `caddy_native` | Native Caddy | Caddyfile | RISK-123, RISK-124 |
| `apache` | httpd.conf | `/etc/apache2/` | Server-level Apache |
| `server_cron` | Crontab | System crontab | Scheduled tasks |
| `cloudflare` | Edge Rules | Cloudflare Dashboard | Transform rules |
| `iis` | XML Config | `web.config` | Windows IIS |

---

## htaccess Syntax Guard (CRITICAL)

### Forbidden Directives in .htaccess

| Directive | Reason | Alternative |
|-----------|--------|-------------|
| `TraceEnable` | Server-level only - SILENTLY IGNORED | `RewriteCond %{REQUEST_METHOD} ^TRACE [NC]` + `RewriteRule .* - [F,L]` in `<IfModule>` |
| `ServerSignature` | Server-level only - SILENTLY IGNORED | `Header unset Server` + `Header always unset X-Powered-By` |
| `ServerTokens` | Server-level only - SILENTLY IGNORED | `Header unset Server` |
| `<Directory>` | SILENTLY IGNORED - processed in httpd.conf only | Use `<FilesMatch>` in subdirectory's own .htaccess |
| `<?php`, `define(`, `add_action(`, `add_filter(` | **CRITICAL: Causes 500 SERVER ERROR** | Use `lib_key: 'wp_config'` or `'php_functions'` |

### Required Structure for Rewrite Blocks

```apache
# BEGIN VAPT RISK-XXX
# Requires: mod_rewrite | AllowOverride: FileInfo or All
# Position: BEFORE # BEGIN WordPress
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    {your_rules_here}
</IfModule>
# END VAPT RISK-XXX
```

### Required Elements
- `<IfModule mod_rewrite.c>` wrapper
- `RewriteEngine On`
- `RewriteBase /`

### Required Companion Notes
- **Header directives:** Must note `mod_headers` requirement: `# Enable: sudo a2enmod headers && sudo service apache2 restart`
- **Options directives:** Must note `AllowOverride Options or All required in httpd.conf`
- **Files/FilesMatch:** Must note `AllowOverride Limit or All required`

### Apache 2.4+ Compatibility

| Old Syntax (2.2) | New Syntax (2.4+) |
|------------------|---------------------|
| `Order Deny,Allow` / `Deny from all` | `Require all denied` |

Always include both syntaxes or note the Apache 2.4+ alternative.

### Target File Exceptions

| Risk | Special Target File |
|------|-------------------|
| RISK-020 | `wp-content/uploads/.htaccess` - **SEPARATE file in uploads directory**, NOT root .htaccess |

---

## NEVER Block These Paths (WordPress Core)

| Path | Why Protected |
|------|---------------|
| `/wp-admin/` | Admin interface - blocking breaks site management |
| `/wp-login.php` | Authentication - blocking breaks login |
| `/wp-json/wp/v2/` | Core REST API - blocking breaks WP functionality |
| `/wp-json/vaptsecure/v1/` | Plugin's own API - blocking breaks VAPTSecure |

**Violating this rule will break WordPress functionality.**

---

## Enforcer Template Files (`data/Enforcers/`)

The `data/Enforcers/` directory contains **9 platform-specific template files** that provide structured risk catalogs for each enforcer type:

| Template File | lib_key | Purpose |
|---------------|---------|---------|
| `htaccess-template.json` | `htaccess` | Apache .htaccess rules for 23 risks |
| `nginx-template.json` | `nginx` | Nginx configuration directives |
| `wp-config-template.json` | `wp_config` | wp-config.php PHP constants (20 risks) |
| `php-functions-template.json` | `php_functions` | PHP hook-based implementations |
| `wordpress-template.json` | `wordpress` | WordPress action/filter hooks |
| `fail2ban-template.json` | `fail2ban` | fail2ban jail configurations |
| `caddy-template.json` | `caddy` | Caddyfile v2 syntax |
| `apache-template.json` | `apache` | Apache httpd.conf server-level config |
| `server-cron-template.json` | `server_cron` | Server cron job entries |

### Template Structure

Each template contains:
- `metadata` - Schema version, plugin info, validation status
- `risk_catalog[]` - Array of risks applicable to this platform
  - `risk_id`, `title`, `category`, `severity`
  - `description` - Summary, detailed, attack scenario
  - `owasp_mapping` - Compliance references
  - `remediation` - Platform-specific enforcement code

### When to Read Templates

**Read the specific `{lib_key}-template.json` for your target platform when:**
- Generating enforcement code for a specific platform
- Understanding platform-specific risk implementations
- Creating new platform entries for existing risks
- Validating platform-specific syntax

**Example:** If generating htaccess enforcement for RISK-003, read:
1. `data/enforcer_pattern_library_v2.0.json` - Source of truth
2. `data/Enforcers/htaccess-template.json` - Platform context

---

## Task Type Reference

### generate_ui_component_schema
**Input:** risk_id or list of risk_ids
**Steps:**
1. Read `interface_schema_v2.0.risk_interfaces[risk_id]`
2. Extract `ui_layout`, `components[]`, `actions[]`, `severity.colors`
3. Generate React/JSON component using `naming_conventions`
4. Self-check rubric checks 1,3,4,5 → score ≥16 → deliver

### generate_enforcement_code
**Input:** risk_id + platform
**Steps:**
1. Read `interface_schema_v2.0.risk_interfaces[risk_id].platform_implementations[platform]`
2. Read `lib_key` from `platform_implementations[platform].lib_key`
3. Read `enforcer_pattern_library_v2.0.patterns[risk_id][lib_key]`
4. If .htaccess: run `htaccess_syntax_guard`
5. Output `wrapped_code` with VAPT markers
6. Payload Syntax Verification: Ensure code matches enforcer type
7. Append `verification.command`
8. Self-check rubric → score ≥16 → deliver

### generate_full_risk_package
**Input:** risk_id
**Steps:**
1. Load interface schema entry + all pattern library keys for risk
2. Generate UI component schema
3. For each `available_platform`: generate enforcement code
4. For each .htaccess rule: run `htaccess_syntax_guard`
5. Self-check all outputs → score ≥16 each → deliver package

### diagnose_driver_failure
**Input:** risk_id + enforcer
**Steps:**
1. Read `vapt_driver_manifest_v2.0.risks[risk_id].steps[*enforcer=X]`
2. Check: `write_block` contains `begin_marker` and `end_marker`
3. Check: `anchor_string` exists in target file
4. Check: `idempotency.check_string` not already present (skipped?)
5. Check: `target_file` path resolves correctly with `{ABSPATH}`
6. Report which field caused the failure and the corrected value

### generate_new_manifest_entry
**Input:** new risk definition
**Steps:**
1. Generate pattern library entry with all applicable `lib_keys`
2. Generate interface schema entry with `code_ref` pointing to `lib_key`
3. Generate driver manifest entry with all `driver{}` sub-fields
4. Verify all cross-references resolve before delivering

---

## Emergency Reset Procedure

If uncertain about ANY VAPTSecure-specific rule:

1. **STOP** current implementation immediately
2. **Re-read** this document (`.ai/VAPTSECURE.md`)
3. **Re-read** `data/ai_agent_instructions_v2.0.json`
4. **Re-read** `data/VAPT_AI_Agent_System_README_v2.0.md`
5. **Re-start** from Phase 1 of this checklist

---

## Quick Reference: Forbidden Actions

### ❌ NEVER Do This
- Write enforcement code from memory
- Place RewriteRule after `# END WordPress`
- Use `TraceEnable`, `ServerSignature`, `ServerTokens` in .htaccess
- Use `<Directory>` in .htaccess
- Put PHP code (`<?php`, `define`, `add_action`) in .htaccess
- Block `/wp-admin/`, `/wp-login.php`, `/wp-json/wp/v2/`
- Skip the self-check rubric
- Deliver output scoring < 16/20
- Use snake_case for component IDs
- Fabricate handler names not in naming_conventions

### ✅ ALWAYS Do This
- Read from `enforcer_pattern_library_v2.0.json` before generating
- Place RewriteRule **BEFORE** `# BEGIN WordPress`
- Wrap in `<IfModule mod_rewrite.c>` with `RewriteEngine On` and `RewriteBase /`
- Use `lib_key: 'wp_config'` for PHP constants
- Use `lib_key: 'php_functions'` for WP hooks
- Include VAPT block markers in all output
- Run self-check rubric (minimum 16/20)
- Follow `naming_conventions` exactly
- Document `mod_headers` and `AllowOverride` requirements
- Verify driver fields are complete for new entries

---

## Success Criteria

A VAPTSecure task is complete when:

- [ ] Pre-generation checklist completed
- [ ] All relevant JSON files read and referenced
- [ ] Code generated following pattern library
- [ ] htaccess syntax guard passed (if applicable)
- [ ] Self-check rubric score documented (__/20)
- [ ] Score is ≥ 16/20
- [ ] All rubric failures explained (if any)
- [ ] No WordPress core paths blocked
- [ ] Proper block markers present
- [ ] Naming conventions followed

---

*Document Version: 2.6.1*  
*Based on: VAPT_AI_Agent_System_README_v2.0.md, ai_agent_instructions_v2.0.json, VAPT_Driver_Reference_v2.0.php*  
*For: VAPTSecure WordPress Plugin Only*
