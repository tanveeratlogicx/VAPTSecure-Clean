# VAPT Risk Catalogue — AI Agent System
## Unified Bundle v2.0
**Version:** 2.0.0 | **Date:** 2026-02-21 | **Source:** VAPT-Risk-Catalogue-Full-125-v3_4_1.json (125 risks)

> **v2.0 — Full Rebuild.** All five files generated together from source as a single coherent bundle. Every cross-reference between files was validated before release (0 errors). This replaces all prior versioned files (v1.0–v1.3).

---

## What This Bundle Is

This package transforms the VAPT Risk Catalogue into a **five-file system** covering two layers:

- **AI Agent layer** (3 files) — the AI reads these to generate UI schemas and enforcement code
- **Driver layer** (2 files) — the WordPress plugin reads these to physically write rules to disk

All five files share the same version (`2.0.0`), the same `bundle_files` cross-reference map, and consistent naming conventions throughout.

---

## Bundle Files

### AI Agent Layer

| File | Role | Size |
|------|------|------|
| `enforcer_pattern_library_v2.0.json` | All 125 risks × all enforcer types — corrected code, driver sub-objects, Cloudflare/IIS/Caddy derivations | ~204 KB |
| `interface_schema_v2.0.json` | UI component definitions, `code_ref` and `driver_ref` pointers, available platforms per risk | ~281 KB |
| `ai_agent_instructions_v2.0.json` | System prompt, task definitions, .htaccess syntax guard, 19-point self-check rubric, example workflows | ~20 KB |

### Driver Layer

| File | Role | Size |
|------|------|------|
| `vapt_driver_manifest_v2.0.json` | Machine-executable instructions for all 125 risks — every field directly usable by the PHP driver | ~145 KB |
| `VAPT_Driver_Reference_v2.0.php` | Drop-in `VAPT_Driver` PHP class — implements the full apply/rollback contract against the manifest | ~8 KB |

---

## How the Files Connect

```
interface_schema_v2.0.json
  └─ platform_implementations[platform]
       ├─ code_ref  → enforcer_pattern_library_v2.0.patterns[risk_id][lib_key]
       └─ driver_ref → vapt_driver_manifest_v2.0.risks[risk_id].steps[*]

enforcer_pattern_library_v2.0.json
  └─ patterns[risk_id][lib_key]
       └─ driver{}  → fields consumed by vapt_driver_manifest_v2.0

vapt_driver_manifest_v2.0.json
  └─ risks[risk_id].steps[n]  → consumed by VAPT_Driver_Reference_v2.0.php
```

---

## Enforcer Key Map

The pattern library uses consistent snake_case keys for every enforcer type. These same keys appear in `interface_schema.platform_implementations[platform].lib_key` and `driver_manifest.risks[rid].steps[n].lib_key`.

| lib_key | Enforcer | File Written |
|---------|----------|-------------|
| `htaccess` | Apache / .htaccess | `{ABSPATH}.htaccess` |
| `wp_config` | wp-config.php | `{ABSPATH}wp-config.php` |
| `php_functions` | PHP Functions | `{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php` |
| `wordpress` | WordPress hooks | Same plugin file |
| `wordpress_core` | WordPress Core filters | Same plugin file |
| `fail2ban` | fail2ban | `/etc/fail2ban/jail.local` |
| `nginx` | Nginx | `/etc/nginx/conf.d/vapt-security.conf` |
| `apache` | Apache | `/etc/apache2/conf-available/vapt-security.conf` |
| `caddy_native` | Native Caddy (RISK-123, RISK-124) | `/etc/caddy/Caddyfile` |
| `server_cron` | Server Cron | `crontab` |
| `cloudflare` | Cloudflare (derived from htaccess) | Dashboard / API |
| `iis` | IIS web.config (derived from htaccess) | `web.config` |
| `caddy` | Caddyfile v2 (derived from htaccess) | `/etc/caddy/Caddyfile` |

**Special case — RISK-020:** `target_file` = `wp-content/uploads/.htaccess` (a separate file in the uploads directory, not the WordPress root `.htaccess`).

---

## Source Risk Coverage

| Enforcer | Risks |
|----------|-------|
| .htaccess (Apache) | 28 |
| PHP Functions | 28 |
| wp-config.php | 21 |
| fail2ban | 16 |
| Server Cron | 10 |
| WordPress | 10 |
| Nginx | 7 |
| Apache | 2 |
| Caddy (native) | 2 |
| WordPress Core | 1 |

Platform derivations (Cloudflare, IIS, Caddy v2) added for all 28 `.htaccess` risks.

| Severity | Count |
|----------|-------|
| Critical | 9 |
| High | 36 |
| Medium | 46 |
| Low | 34 |

---

## AI Agent Workflow

### Step 1 — Select Task Type

| Task | When to Use |
|------|-------------|
| `generate_ui_component_schema` | Build UI JSON for one or more risks |
| `generate_enforcement_code` | Platform code for one risk + one platform |
| `generate_full_risk_package` | UI schema + all platform codes for one risk |
| `diagnose_driver_failure` | Inspect driver manifest to find why a rule wasn't applied |
| `generate_new_manifest_entry` | Add a new risk across all three data files |

### Step 2 — Schema-First Lookup

Always read `interface_schema_v2.0.json` before writing any UI output:

```
interface_schema_v2.0.risk_interfaces[RISK-XXX]
  → risk_id, title, category, severity.level, severity.colors
  → ui_layout   { card_id, section, order, collapsible, default_expanded }
  → components  [{ component_id, type, label, default_value, on_change, settings_key }]
  → actions     [{ action_id, type, label, confirmation_required, rest_endpoint }]
  → available_platforms[]
  → platform_implementations[platform]
       { lib_key, code_ref, driver_ref, operation, requires, allowoverride, note }
```

### Step 3 — Pattern Library Lookup

Read `enforcer_pattern_library_v2.0.json` using the `lib_key` from the schema. **Never write enforcement code from memory.**

```
enforcer_pattern_library_v2.0.patterns[RISK-XXX][lib_key]
  → code           bare code only
  → wrapped_code   code with begin_marker / end_marker already included
  → begin_marker   e.g. "# BEGIN VAPT RISK-003"
  → end_marker     e.g. "# END VAPT RISK-003"
  → insertion_point  canonical token (before_wordpress_rewrite, beginning_of_file, …)
  → anchor         { search, position, fallback }
  → requires       [ "mod_rewrite", "mod_headers", … ]
  → allowoverride  "FileInfo" | "Options" | ""
  → note           human-readable caveats
  → verification   { command, expected }
  → driver         { write_mode, target_file, write_block, anchor_string, … }

  .htaccess risks also have:
  → cloudflare     { implementation_type, … }
  → iis            { implementation_type, web_config_snippet, … }
  → caddy          { implementation_type, caddyfile_snippet, … }
```

### Step 4 — Run the .htaccess Syntax Guard

Before emitting **any** `.htaccess` code, check every directive against these rules:

**Hard-forbidden (silently ignored by Apache):**

| Directive | Why Forbidden | Correct Alternative |
|-----------|--------------|---------------------|
| `TraceEnable off` | Server-level only | mod_rewrite TRACE blocker wrapped in `<IfModule>` |
| `ServerSignature Off` | Server-level only | `Header unset Server` (mod_headers) |
| `ServerTokens Prod` | Server-level only | `Header unset Server` (mod_headers) |
| `<Directory ...>` | Silently ignored | `<FilesMatch>` in the target subdirectory's `.htaccess` |

**Required structure for every RewriteRule/RewriteCond block:**

```apache
# BEGIN VAPT RISK-XXX
# Requires: mod_rewrite | AllowOverride: FileInfo or All
# Position: BEFORE # BEGIN WordPress
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    {your_rules}
</IfModule>
# END VAPT RISK-XXX
```

**Why BEFORE `# BEGIN WordPress`:** WordPress's block ends with `RewriteRule . /index.php [L]`. The `[L]` flag stops all further rewrite processing — any RewriteRule placed after `# END WordPress` is in a dead zone and will never execute.

**Required companions:**

| Directive | Requirement |
|-----------|-------------|
| `Header` | `mod_headers` — note: `sudo a2enmod headers` |
| `Options` | `AllowOverride Options` or `All` in server config |
| `<Files>` / `<FilesMatch>` | `AllowOverride Limit` or `All` |

### Step 5 — Self-Check Rubric (≥16 / 19 required)

| # | Check | Weight |
|---|-------|--------|
| 1 | Component IDs match `interface_schema_v2.0` exactly | 2 |
| 2 | Enforcement code read from `enforcer_pattern_library_v2.0` — not written from memory | 2 |
| 3 | Severity badge colors match `global_ui_config.severity_badge_colors` | 1 |
| 4 | Handler names follow naming conventions | 1 |
| 5 | Platform listed in `available_platforms` for this risk | 1 |
| 6 | VAPT block markers present in all enforcement code output | 1 |
| 7 | `verification.command` present and matches platform CLI | 1 |
| 8 | No forbidden naming patterns | 1 |
| 9 | No forbidden `.htaccess` directives (`TraceEnable`, `ServerSignature`, `<Directory>`) | 2 |
| 10 | All `RewriteRule`/`RewriteCond` placed BEFORE `# BEGIN WordPress` | 1 |
| 11 | All `RewriteRule`/`RewriteCond` wrapped in `<IfModule mod_rewrite.c>` with `RewriteEngine On` and `RewriteBase /` | 1 |
| 12 | `mod_headers` requirement noted for all `Header` directives | 1 |
| 13 | `AllowOverride` requirement noted for `Options` directives | 1 |
| 14 | RISK-020 `target_file` = `wp-content/uploads/.htaccess` | 1 |
| 15 | IIS `<rewrite>` sections note URL Rewrite Module 2.1 requirement | 1 |
| 16 | Caddy output uses v2 syntax only — no Apache directives, no semicolons | 1 |
| 17 | `code_ref` uses correct `lib_key` (e.g. `htaccess`, not `_htaccess`) | 1 |
| 18 | `driver_ref` points to `vapt_driver_manifest_v2.0` | 1 |
| 19 | Driver diagnosis/generation includes all required `driver{}` sub-fields | 1 |

**Score < 16 → identify failing checks and regenerate. Never deliver output scoring < 16.**

### Step 6 — Understand the Driver Layer

The AI Agent does not run the driver, but must understand it for two tasks:

**Diagnosing a driver failure** — read `vapt_driver_manifest_v2.0.risks[RISK-XXX].steps[n]` and check:

| Field to Inspect | Common Failure |
|-----------------|----------------|
| `idempotency.check_string` | Already-present marker triggered skip |
| `insertion.anchor_string` | Anchor string not found in target file |
| `target_file` | `{ABSPATH}` resolved to wrong path |
| `write_block` | Empty or missing begin/end markers |
| `write_mode` | Wrong mode — appended to dead zone |

**Generating a new manifest entry** — every step must include all of these fields: `write_mode`, `target_file`, `write_block`, `begin_marker`, `end_marker`, `insertion.anchor_string`, `insertion.anchor_position`, `insertion.fallback`, `idempotency.check_string`, `idempotency.if_found`, `backup_required`, `verification.command`, `verification.expected`, `rollback.begin_marker`, `rollback.end_marker`, `rollback.target_file`.

---

## Driver Layer — How It Works

The `VAPT_Driver` class in `VAPT_Driver_Reference_v2.0.php` reads `vapt_driver_manifest_v2.0.json` and executes this sequence for every step:

```
1.  Resolve {ABSPATH} in target_file → full filesystem path
2.  Create target file if it does not exist (e.g. uploads/.htaccess)
3.  Read idempotency.check_string from file
    → if found AND if_found=skip → return "already applied"
    → if found AND if_found=replace → remove existing block first
4.  Backup target file (append .vapt.bak.{timestamp})
5.  Find insertion.anchor_string in file content
6.  Insert write_block at anchor_position (before / after / prepend / append)
7.  If anchor not found → use insertion.fallback strategy
8.  Write new content to target_file
9.  On any write failure → remove begin_marker..end_marker block (rollback)
```

**API:**
```php
$driver = new VAPT_Driver( ABSPATH, plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' );
$driver->apply( 'RISK-003' );    // returns [['success'=>bool, 'message'=>string], ...]
$driver->rollback( 'RISK-003' ); // removes all VAPT blocks for this risk
```

---

## Enforcer Block Markers

Every block written to disk is wrapped with markers so the driver can locate, verify, and remove it precisely.

| Enforcer | Begin Marker | End Marker |
|----------|-------------|------------|
| `.htaccess`, Nginx, Apache, Caddy, fail2ban, Server Cron | `# BEGIN VAPT RISK-XXX` | `# END VAPT RISK-XXX` |
| `wp-config.php` | `/* BEGIN VAPT RISK-XXX */` | `/* END VAPT RISK-XXX */` |
| PHP Functions, WordPress, WordPress Core | `// BEGIN VAPT RISK-XXX` | `// END VAPT RISK-XXX` |

---

## Insertion Point Reference

| Token | Anchor String | Position | Use For |
|-------|--------------|----------|---------|
| `before_wordpress_rewrite` | `# BEGIN WordPress` | before | **All RewriteRule/RewriteCond — mandatory** |
| `after_wordpress_rewrite` | `# END WordPress` | after | Non-rewrite directives only |
| `beginning_of_file` | *(none)* | prepend | All Header, Options, Files blocks |
| `end_of_file` | *(none)* | append | Non-rewrite directives |
| `before_wp_settings` | `require_once ABSPATH` | before | All wp-config.php constants |
| `functions_php` | *(none)* | append | PHP Functions / WordPress hooks |
| `jail_local` | *(none)* | append | fail2ban rules |
| `http_block` | `http {` | after | Nginx directives |
| `crontab_entry` | *(none)* | append | Server Cron jobs |

---

## Platform Verification Commands

| Platform | Validate | Reload | Test |
|----------|----------|--------|------|
| Apache/.htaccess | `apachectl -t` | `service apache2 reload` | `curl -sI https://yoursite.com` |
| wp-config.php | `wp config get CONSTANT` | *(next request)* | `wp config list` |
| PHP Functions | `wp plugin list` | *(next request)* | `wp eval "do_action('hook');"` |
| Nginx | `nginx -t` | `nginx -s reload` | `curl -sI https://yoursite.com` |
| Apache (httpd.conf) | `apachectl -t` | `service apache2 reload` | `curl -sI https://yoursite.com` |
| Caddy | `caddy validate --config /etc/caddy/Caddyfile` | `caddy reload` | `curl -sI https://yoursite.com` |
| Cloudflare | Dashboard review | Instant on save | `curl -sI https://yoursite.com` |
| IIS | `appcmd.exe validate config` | `iisreset /restart` | `curl -sI https://yoursite.com` |
| fail2ban | `fail2ban-client -t` | `fail2ban-client reload` | `fail2ban-client status jail-name` |
| Server Cron | `crontab -l` | *(auto)* | `crontab -l \| grep vapt` |

---

## Naming Conventions

| Entity | Pattern | Example |
|--------|---------|---------|
| Component ID | `UI-RISK-{NNN}-{SEQ}` | `UI-RISK-003-001` |
| Action ID | `ACTION-{NNN}-{SEQ}` | `ACTION-003-001` |
| Toggle handler | `handleRISK{NNN}ToggleChange` | `handleRISK003ToggleChange` |
| Dropdown handler | `handleRISK{NNN}DropdownChange` | `handleRISK003DropdownChange` |
| Settings key | `vapt_risk_{nnn}_enabled` | `vapt_risk_003_enabled` |
| REST endpoint | `/wp-json/vapt/v1/risk-{nnn}` | `/wp-json/vapt/v1/risk-003` |
| PHP function | `vapt_{descriptive_name}` | `vapt_disable_xmlrpc` |
| Caddy matcher | `@risk{nnn}` (alphanumeric, no hyphens) | `@risk003` |

---

## Platform Coverage

### Cloudflare (derived from .htaccess for all 28 htaccess risks)

| Type | Cloudflare Product | Example Risks |
|------|--------------------|---------------|
| `transform_rule` | HTTP Response Header Modification | RISK-012, RISK-014–018, RISK-022, RISK-031–033 |
| `waf_custom_rule` | WAF Custom Rules | RISK-002, RISK-003, RISK-005, RISK-023, RISK-027–030 |
| `notes_only` | Origin only — no edge equivalent | RISK-013, RISK-025, RISK-026 |

### IIS (derived from .htaccess)

| Type | IIS Config Section | Example |
|------|--------------------|---------|
| `web_config_http_headers` | `<httpProtocol><customHeaders>` | All header risks |
| `web_config_url_rewrite` | `<rewrite><rules>` | RISK-003, RISK-005, RISK-023 |
| `web_config_request_filtering` | `<requestFiltering>` | File block risks |
| `web_config_request_filtering_extensions` | `<fileExtensions>` | Extension-based blocks |
| `web_config_directory_browsing` | `<directoryBrowse>` | RISK-013 |
| `web_config_remove_headers` | `removeServerHeader="true"` | RISK-024 |

**Requirement:** IIS URL Rewrite Module 2.1 for `web_config_url_rewrite` rules.

### Caddy (Caddyfile v2 — derived from .htaccess)

| Type | Directive | Example |
|------|-----------|---------|
| `caddy_header` | `header { Name "Value" defer }` | All header risks |
| `caddy_respond_block` | `@matcher { } respond @matcher 403` | Rewrite/file block risks |
| `caddy_file_server` | `file_server` (no `browse`) | RISK-013 |
| `notes_only` | No Caddy equivalent | RISK-026 |

**Always run `caddy validate` before `caddy reload`.**

---

## Platform Limitations

### Apache / .htaccess
- `AllowOverride` must permit the relevant directive class in `httpd.conf` or VirtualHost — without it, `.htaccess` rules are silently ignored
- `TraceEnable`, `ServerSignature`, `ServerTokens` are server-level only — add them to `httpd.conf` in addition to the `.htaccess` workaround
- `<Directory>` is silently ignored in `.htaccess` — use `<FilesMatch>` or a per-directory `.htaccess`
- `mod_rewrite` and `mod_headers` must be enabled (`a2enmod`) before rewrite/header rules take effect

### Cloudflare
- Cannot enforce `Options -Indexes`, `FileETag`, or PHP-level directives from the edge
- `Header unset Server` → Transform Rule (all plans including Free)
- Rate limiting beyond simple thresholds requires Cloudflare Pro or higher

### IIS
- `removeServerHeader="true"` requires IIS 10.0+
- PHP `php_flag engine off` has no IIS equivalent — use `requestFiltering` to block `.php` files instead

### Caddy
- Caddyfile v2 syntax only — v1 directives are incompatible
- Directory listing is disabled by default — no directive needed unless `browse` was explicitly added
- Named matchers must use alphanumeric identifiers only (no hyphens)

---

## Usage Examples

### Example 1 — Generate .htaccess enforcement for RISK-003

**Prompt:** `Generate .htaccess enforcement for RISK-003 (Username Enumeration via REST API)`

**Agent steps:**
1. Read `enforcer_pattern_library_v2.0.patterns.RISK-003.htaccess`
2. Syntax guard: no forbidden directives ✓; `insertion_point=before_wordpress_rewrite` ✓; `<IfModule>` wrapper present ✓; `RewriteBase /` present ✓
3. Output `wrapped_code`:

```apache
# BEGIN VAPT RISK-003
# Requires: mod_rewrite | AllowOverride: FileInfo or All
# Position: BEFORE # BEGIN WordPress
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^wp-json/wp/v2/users$ - [F,L]
    RewriteRule ^wp-json/wp/v2/users/ - [F,L]
</IfModule>
# END VAPT RISK-003
```

4. Verification: `apachectl -t && curl -sI 'https://yoursite.com/wp-json/wp/v2/users' | grep HTTP`
5. Self-check: checks 2, 6, 9, 10, 11 all pass → score ≥16 → deliver

---

### Example 2 — Diagnose why RISK-003 didn't apply

**Prompt:** `RISK-003 was applied but the rule isn't in .htaccess — diagnose`

**Agent steps:**
1. Read `vapt_driver_manifest_v2.0.risks.RISK-003.steps[0]`
2. Check `idempotency.check_string`: `# BEGIN VAPT RISK-003` — was this already in the file? → skip triggered
3. Check `insertion.anchor_string`: `# BEGIN WordPress` — does the file contain this string? → if absent, fallback used
4. Check `target_file`: `{ABSPATH}.htaccess` — did `ABSPATH` resolve correctly?
5. Check `write_block` is non-empty and contains both markers
6. Report: most likely cause is `{ABSPATH}` resolving to wrong directory or `# BEGIN WordPress` absent from file

---

### Example 3 — Full risk package for RISK-022 across all platforms

**Prompt:** `Generate full package for RISK-022 (Missing CSP Header) for .htaccess, Cloudflare, IIS, Caddy`

**Agent steps:**
1. Read `interface_schema_v2.0.risk_interfaces.RISK-022` → available_platforms, components, actions
2. Read `enforcer_pattern_library_v2.0.patterns.RISK-022` → htaccess, cloudflare, iis, caddy keys
3. Output `.htaccess`: `Header always set Content-Security-Policy "..."` wrapped in VAPT markers
4. Output `Cloudflare`: Transform Rule → Set `Content-Security-Policy` header
5. Output `IIS`: `<customHeaders><add name="Content-Security-Policy" value="..." />`
6. Output `Caddy`: `header { Content-Security-Policy "..." defer }`
7. Self-check all four → score ≥16 each → deliver package

---

### Example 4 — Generate a new manifest entry for a new risk

**Prompt:** `Add RISK-126 (Missing Cache-Control Header) to the bundle`

**Agent steps:**
1. Generate `enforcer_pattern_library_v2.0.patterns.RISK-126.htaccess` — include `driver{}` sub-object
2. Derive `cloudflare`, `iis`, `caddy` from the htaccess entry
3. Generate `interface_schema_v2.0.risk_interfaces.RISK-126` — `code_ref` must use `lib_key=htaccess`, `driver_ref` must point to `vapt_driver_manifest_v2.0`
4. Generate `vapt_driver_manifest_v2.0.risks.RISK-126.steps` — include all required driver fields
5. Self-check: rubric checks 17, 18, 19 all pass → score ≥16 → deliver three JSON fragments

---

## What Changed from Prior Versions

### v2.0 — Full Rebuild (this version)

All five files generated together from source in a single build pass. Prior versions were patched incrementally, leaving stale references, mismatched key names, and gaps discovered only at runtime.

**Structural changes:**

| Issue in v1.x | Fix in v2.0 |
|---------------|-------------|
| `code_ref` used dot-notation string requiring driver parsing | Replaced with structured `lib_key` field + consistent key names |
| Enforcer keys were inconsistent (`_htaccess`, `php_functions`, `wordpress_core`) | Unified: `htaccess`, `php_functions`, `wordpress_core` — all snake_case, no leading underscores |
| `code_ref` referenced v1.1 files in v1.2 schema | All refs rebuilt pointing to v2.0 files |
| `driver_ref` field absent from interface schema | Added to every `platform_implementations` entry |
| Driver manifest was a separate add-on with different version | Rebuilt as integral bundle member, same version |
| 76 `code_ref` fields in schema pointed to non-existent lib keys | All resolved — 0 cross-reference errors |
| `<IfModule>` wrapper and `RewriteBase /` missing from rewrite rules | All 4 rewrite risks (RISK-003, RISK-005, RISK-023, RISK-035) rebuilt correctly |
| Forbidden directives (`TraceEnable`, `ServerSignature`, `<Directory>`) in `.htaccess` | All corrected with safe alternatives |
| RISK-005 regex matched only single-digit author IDs | Fixed to `(^|&)author=\d+` |
| 19 wp-config risks missing `target_constants[]` | All 21 wp-config risks enriched |
| Self-check rubric was 8-point then 15-point then 17-point | Unified 19-point rubric, threshold ≥16 |

---

*VAPT Risk Catalogue Transformation System — Bundle v2.0.0 | 2026-02-21*
