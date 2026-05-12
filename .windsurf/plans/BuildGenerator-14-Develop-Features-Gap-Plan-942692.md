# Build Generator Hardening Plan — Universal Framework (Verified on 14 Develop Features)

Review the BuildGenerator-Plan.md against the current codebase with special attention to 14 Non-Draft (Develop-status) features, identifying server-side SSoT misalignment, client-build packaging gaps, and runtime verification drift so the remediation framework can scale across all 135 risks.

## 1. The 14 Develop-Stage Features

| # | Feature Name | RISK ID | Category | Severity |
|---|-------------|---------|----------|----------|
| 01 | Lack of Rate Limiting on WordPress Login | RISK-007 | Authentication | high |
| 02 | WordPress Cron Job Vulnerability (DoS) | RISK-001 | Configuration | high |
| 03 | XML-RPC Leads to Unauthenticated Blind SSRF | RISK-002 | Information Disclosure | high |
| 04 | Directory Listing Vulnerability | RISK-013 | Configuration | high |
| 05 | Lack of Rate Limiting on Contact Form | RISK-009 | Configuration | medium |
| 06 | Banner Grabbing Vulnerability | RISK-010 | Information Disclosure | low |
| 07 | Username Enumeration via WordPress REST API | RISK-003 | Authentication | medium |
| 08 | Username Enumeration via wp-login.php | RISK-008 | Authentication | medium |
| 09 | Outdated and Vulnerable WordPress Plugins | RISK-126 | Configuration | high |
| 10 | Unauthenticated Exposure of WordPress REST API Endpoints | RISK-006 | API Security | low |
| 11 | Clickjacking | RISK-014 | Configuration | medium |
| 12 | Public Exposure of Debug Log File | RISK-034 | Information Disclosure | medium |
| 13 | Information Disclosure via readme.html | RISK-011 | Information Disclosure | medium |
| 14 | No Input Validation | RISK-127 | Configuration | critical |

---

## 2. Server-Level (SSoT) Configuration Review

### 2.1 Source of Truth Architecture
Each feature is defined across three SSoT layers:
- **`vapt_driver_manifest_v2.0.json`** — Machine-executable enforcer steps (write_block, target_file, idempotency markers)
- **`interface_schema_v2.0.json`** — UI component definitions, platform implementations, test probes
- **`enforcer_pattern_library_v2.0.json`** — Reusable code snippets referenced by `code_ref`

### 2.2 Critical SSoT Defects Found in the 14 Features

#### RISK-014 (Clickjacking / Missing X-Frame-Options) — BROKEN PLACEHOLDERS
- **Nginx step**: `location ~* RISK-014 { deny all; }` — Uses the risk ID as a URI path. Should be a header directive or frame-options location block.
- **PHP Functions step**: `if (strpos($_SERVER['REQUEST_URI'], 'RISK-014') !== false)` — Also uses risk ID as request URI. Should set `X-Frame-Options` header via `add_action('send_headers', ...)`.
- **Impact**: If a client build selects Nginx or PHP Functions as the active enforcer for RISK-014, it will write broken rules that do nothing or deny random URIs containing "RISK-014".

#### RISK-006 (Endpoint Disclosure) — MISMATCHED STEPS
- Step 2 in the driver manifest is a PHP `add_action('init')` block that checks for `author=\d+` — this appears to be copy-paste residue from RISK-005/RISK-008. RISK-006 should only contain REST API authentication lockdown logic.
- **Impact**: Client build may package an irrelevant author-enumeration block under the RISK-006 feature key.

#### RISK-003 (REST API Enumeration) — KNOWN DISCREPANCY
- Confirmed in `ClientBuild-SSoT-Discrepancy-Map.md`: The client verifier reuses REST enumeration probes too broadly. RISK-003 should test `/wp-json/wp/v2/users` blocking, but the client build may test the wrong surface or infer the probe from label text rather than the saved `test_config`.

#### RISK-007 / RISK-009 (Rate Limiting) — PLATFORM CANONICALIZATION GAP
- Both features declare `fail2ban` as a primary enforcer in the driver manifest.
- `class-vaptsecure-build.php::detect_catalog_primary_enforcer()` normalizes only a subset of platform names (`htaccess`, `nginx`, `php_functions`, `wp_config`, `apache`, `cloudflare`).
- `fail2ban`, `server_cron`, and `wordpress_core` are not consistently canonicalized.
- **Impact**: Client builds for these features may derive an empty or wrong `active_enforcer`, breaking UI routing and verification.

#### RISK-126 (Outdated Plugins) & RISK-127 (No Input Validation) — NO FILE-BASED ENFORCER
- These features have UI definitions in `interface_schema_v2.0.json` but lack concrete `write_block` steps in `vapt_driver_manifest_v2.0.json`.
- They appear to be scan/notification-only features. The build generator still tries to package `feature_meta` for them, but there is no runtime enforcer code to ship.
- **Impact**: Dead payload weight in the config; client UI may show "Apply Protection" buttons with no underlying driver step.

---

## 3. Client-Side (Build & Runtime) Configuration Review

### 3.1 How the 14 Features Are Packaged

In `class-vaptsecure-build.php::generate()`:
1. `get_feature_meta_snapshot($features)` queries `vaptsecure_feature_meta` for the 14 keys.
2. For each key, it hydrates `platform_implementations` from the pattern library and normalizes controls via `normalize_client_schema_controls()`.
3. The resulting `feature_meta_snapshot` is passed to `generate_config_content()`.
4. `generate_config_content()` embeds the snapshot into the **Extended payload** (`$extended_payload['feature_meta']`).

### 3.2 Gaps in Client Packaging

| Gap | File | Details |
|-----|------|---------|
| **3.2.1 Extended payload carries full feature_meta** | `class-vaptsecure-build.php` | `feature_meta` includes `override_schema`, `override_implementation_data`, `wireframe_url`, `dev_instruct` — all shipped to the client. Per Phase 4 of BuildGenerator-Plan.md, this is excessive sensitive exposure. |
| **3.2.2 No per-feature integrity check** | `class-vaptsecure-build.php` | The build generates hashes for the entire Default and Extended payloads, but there is no manifest entry verifying that each of the 14 (or selected) features resolved its `code_ref` and `active_enforcer` correctly before packaging. |
| **3.2.3 Broken enforcer code is packaged unchecked** | `class-vaptsecure-build.php` | The `hydrate_platform_implementations()` method resolves `code_ref` into `code` from the pattern library, but there is no validation that the hydrated code is syntactically valid or semantically correct. RISK-014's broken Nginx/PHP blocks would ship as-is. |
| **3.2.4 feature_meta can override Default constants** | `vaptsecure.php` (generated) | The generated `vaptsecure_apply_config_payload()` allows Extended to define `FEATURE_*` constants. If a tampered Extended payload adds or overrides features, it bypasses the protected baseline. |

### 3.3 Gaps in Runtime Resolution

| Gap | File | Details |
|-----|------|---------|
| **3.3.1 `active_enforcer` may be empty** | `class-vaptsecure-rest.php::verify_implementation()` | If `active_enforcer` is empty, the verifier falls back to inference from labels and test paths, which is the exact drift documented in `ClientBuild-SSoT-Discrepancy-Map.md`. |
| **3.3.2 Client build tests wrong probe** | `assets/js/modules/generated-interface.js` (inferred) | RISK-008 (wp-login enumeration) may be tested with a REST users endpoint probe because of broad probe reuse in the client verifier. |
| **3.3.3 No runtime hash verification** | `vaptsecure.php` | `VAPTSECURE_DEFAULT_CONFIG_HASH` and `VAPTSECURE_EXTENDED_CONFIG_HASH` are defined but **never checked** at runtime. Tampering with the 14 feature constants in the config file goes undetected. |

---

## 4. Verified Gaps vs. BuildGenerator-Plan.md Phases

### Phase 1: Domain Enforcement
- **Status**: Partial. The 14 features are not directly involved in domain matching, but wildcard builds that include them could be deployed on lookalike domains, allowing the broken RISK-014 enforcer to run on unintended hosts.
- **Gap**: Wildcard suffix check still permits `example.com.evil.com`.

### Phase 2: Build Integrity
- **Status**: Incomplete.
- **Gap for 14 features**: No per-feature manifest entry. No verification that `code_ref` resolved correctly. No build-time check that `active_enforcer` is non-empty and valid for each Develop feature.

### Phase 3: Strip Unsafe Runtime Surfaces
- **Status**: Incomplete.
- **Gap**: Public `/ping` and `/reset-limit` endpoints remain registered even when the 14 features are packaged. If a client admin triggers a reset, rate-limit counters for RISK-007/RISK-009 could be cleared by any visitor IP.

### Phase 4: Reduce Sensitive Payload
- **Status**: Partial.
- **Gap for 14 features**: `feature_meta` for all 14 features ships in Extended. This includes `dev_instruct`, `wireframe_url`, and `override_schema` — none of which are needed at runtime. For RISK-126 and RISK-127, which have no enforcer code, the payload is entirely wasted surface.

### Phase 5: Rewrite Safety & Fail-Closed
- **Status**: Missing.
- **Gap**: `safe_preg_replace` in `rewrite_main_plugin_file()` returns original content on regex failure. If a rewrite fails, builder-only code (including endpoints that could manipulate the 14 features) remains in the client build.
- **Additional**: No post-build audit scans for banned strings like `vaptsecure_render_workbench_page` or `class-vaptsecure-build.php`.

---

## 5. Remediation Plan (Framework for All 135 Features, Verified on the 14)

### 5.1 P0 — Fix SSoT Defects in the 14 Features Before Build Packaging

1. **RISK-014**: Replace broken Nginx step with proper `add_header X-Frame-Options "SAMEORIGIN" always;` inside the server block. Replace broken PHP step with `add_action('send_headers', function() { header('X-Frame-Options: SAMEORIGIN'); });`.
2. **RISK-006**: Remove the orphaned `author=\d+` PHP init step from the driver manifest. It belongs to RISK-008.
3. **RISK-003 / RISK-008**: Lock the `test_config` in `interface_schema_v2.0.json` so the client verifier cannot infer a different probe. The test must use the exact `test_config` from the SSoT.

### 5.2 P0 — Strengthen Build Packaging for Develop Features

1. **Trim `feature_meta` before packaging**:
   - Keep only: `feature_key`, `active_enforcer`, `is_enabled`, `is_enforced`, `platform_implementations.code` (hydrated), `test_config`.
   - Remove: `override_schema`, `override_implementation_data`, `dev_instruct`, `wireframe_url`, `generated_schema` (full blob).
2. **Add build-time validation for each feature**:
   - After `hydrate_platform_implementations()`, verify `active_enforcer` is non-empty and maps to a known canonical platform.
   - Verify hydrated `code` is non-empty and does not contain placeholder strings like `RISK-014` or `author=\d+` in unrelated risks.
   - If validation fails for any feature, abort the build with a clear error.
3. **Generate a `build-manifest.json`** per Phase 2 of BuildGenerator-Plan.md:
   - Include a `features` array with `feature_key`, `active_enforcer`, `code_hash` (SHA-256 of hydrated code), `platform_count`.
   - This creates tamper-evident per-feature integrity.

### 5.3 P1 — Harden Runtime for Packaged Features

1. **Add runtime hash verification** in `vaptsecure.php`:
   - Verify `VAPTSECURE_DEFAULT_CONFIG_HASH` against actual `sha256(VAPTSECURE_DEFAULT_CONFIG_B64)`.
   - Verify `VAPTSECURE_EXTENDED_CONFIG_HASH` similarly.
   - Fail closed (disable plugin) on mismatch.
2. **Harden Default/Extended boundary** in generated `vaptsecure_apply_config_payload()`:
   - Remove the ability for Extended to define `VAPTSECURE_FEATURE_*` constants.
   - Add `if (defined($const)) { continue; }` so Extended can never override a Default constant.
   - Rationale: The 14 features' baseline must be immutable; client overlay should not be able to add or remove features from the protected set.

### 5.4 P1 — Gate Public Endpoints

1. In `class-vaptsecure-rest.php`, wrap **all** builder-only endpoints behind `! $is_client_build`.
2. Specifically remove from client builds: `/reset-limit`, `/ping`, `/features/update`, `/features/transition`, `/upload-json`, `/domains/*`, `/settings/enforcement`.
3. This prevents any client-side admin or attacker from clearing rate limits (affecting RISK-007/RISK-009) or toggling enforcement for the 14 features.

### 5.5 P2 — Add Post-Rewrite Audit

1. Change `safe_preg_replace()` to throw on regex failure instead of returning original content.
2. After `rewrite_main_plugin_file()`, scan the generated `vaptsecure.php` for:
   - Banned strings: `vaptsecure_get_superadmin_identity`, `vaptsecure_render_workbench_page`, `class-vaptsecure-build.php`.
   - Presence of builder-only REST route registrations.
3. If audit fails, delete the temp build directory and abort.

### 5.6 P2 — Fix Wildcard Domain Matching

1. Replace substring wildcard matching in `vaptsecure.php` and the generated guard code with dot-delimited label comparison.
2. Verify with test vectors including the 14-feature build on `sub.example.com`, `a.b.example.com`, and reject `example.com.evil.com`.

---

## 6. Verification Checklist (Per-Feature for the 14)

Before marking the plan as implemented, verify each of the 14 features:

1. **SSoT Integrity**: Each feature's driver manifest steps match its interface schema `platform_implementations` and contain no placeholder strings.
2. **Hydration Check**: `hydrate_platform_implementations()` resolves a non-empty `code` for the primary `active_enforcer`.
3. **Build Manifest**: The generated ZIP contains a `build-manifest.json` entry for the feature with a matching `code_hash`.
4. **Payload Minimization**: `feature_meta` in Extended does not contain `override_schema`, `dev_instruct`, or `wireframe_url`.
5. **Runtime Constant**: The generated config defines `VAPTSECURE_FEATURE_{RISK_XXX}` for each enabled feature.
6. **Client Verifier Alignment**: The client build tests the exact `test_config` from the SSoT, not an inferred probe.
7. **Fail-Closed Default**: Tampering with the config hash disables the plugin; the 14 features cannot be activated by a modified Extended payload.
8. **No Public Surface**: Client build does not expose `/reset-limit` or `/ping`, preventing rate-limit bypass for RISK-007/RISK-009.
9. **Domain Lock**: Wildcard build including the 14 features works on `sub.example.com` but fails on `example.com.evil.com`.
10. **Rewrite Audit**: Generated `vaptsecure.php` contains no builder-only function references.

---

## 7. Scaling Framework

Once the 14 Develop features pass the checklist above, apply the same validation pipeline to all 135 risks:

1. Run the SSoT placeholder scanner across all RISK-001 to RISK-135 entries in `vapt_driver_manifest_v2.0.json`.
2. Run the `active_enforcer` non-empty check across all features.
3. Enforce the trimmed `feature_meta` schema for all builds.
4. Use the same `build-manifest.json` per-feature integrity model universally.

---

## 8. Risks

- **RISK-014 placeholder fix requires updating the pattern library** if the broken code is also cached there.
- **Trimming `feature_meta`** may break client UI preview panels that currently expect `generated_schema` to be present. Verify UI components before removing.
- **Runtime hash verification** will cause existing client builds to fail if they were generated before hash constants were verified — ensure backward compatibility or require rebuild.
- **fail2ban canonicalization** may need new driver logic if the client build is expected to validate fail2ban-specific deployments.
