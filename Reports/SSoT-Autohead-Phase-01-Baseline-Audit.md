# SSoT-Autohead Phase 01 Baseline Audit

**Branch:** `SSoT-Autohead-Phase-01`  
**Purpose:** Establish the current source-of-truth and cache read paths before the runtime refactor.

## Scope Audited

### Canonical Data Reads

The codebase reads the live bundle from:

- `data/interface_schema_v2.0.json`
- `data/enforcer_pattern_library_v2.0.json`
- `data/vapt_driver_manifest_v2.0.json`
- `data/ai_agent_instructions_v2.0.json`
- `data/vapt_platform_contract_v3.0.json`

Primary consumers found:

- `includes/class-vaptsecure-build.php`
- `includes/class-vaptsecure-enforcer.php`
- `includes/class-vaptsecure-php-driver.php`
- `includes/class-vaptsecure-config-driver.php`
- `includes/class-vaptsecure-hook-driver.php`
- `assets/js/admin.js`
- `assets/js/client.js`
- `assets/js/modules/generated-interface.js`

### Cached Runtime Reads

The runtime also reads from stored feature meta in `vaptsecure_feature_meta` via:

- `includes/class-vaptsecure-db.php`
- `includes/class-vaptsecure-enforcer.php`
- `includes/class-vaptsecure-workflow.php`
- `assets/js/admin.js`
- `assets/js/client.js`

## Baseline Findings

1. The live `data/` bundle is already treated as canonical at load time in several paths.
2. Stored feature meta can still override or outlive the canonical bundle.
3. `resolve_schema()` currently prefers `generated_schema` / `override_schema` when present, then falls back to catalog data.
4. The technical trace can therefore display an older platform selection than the implementation summary.
5. `Risk-007` is the clearest regression case because the live bundle currently declares PHP Functions plus `wp-config.php`, while the trace path can still surface `.htaccess`.

## Drift Case Captured

### Risk-007

Observed canonical inputs:

- `available_platforms`: `PHP Functions`, `wp-config.php`
- `platform_implementations`: `php_functions`, `wp_config`

Observed runtime drift:

- technical trace may still render `./.htaccess`
- live implementation summary can show the canonical PHP/wp-config paths
- the two views can disagree because cached meta is still authoritative in parts of the runtime

## Conclusion

The audit confirms the problem is not isolated to one risk. The plugin has a broader source-of-truth split:

- `data/` is the canonical bundle
- `vaptsecure_feature_meta` behaves like a durable cache
- some UI and runtime surfaces still treat the cache as authoritative

That is the architecture gap Phase 2 and later phases must close.

## Next Gate

Do not advance to the autoheal/refactor phases until the resolver is changed to prefer live bundle data and auto-refresh stale runtime state.
