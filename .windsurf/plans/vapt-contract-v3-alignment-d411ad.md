# VAPT Platform Contract v3.0 Implementation Plan

This plan outlines the steps to align the VAPTSecure plugin's data files with the v3.0 contract by removing prohibited platforms, fixing corruption, and implementing LiteSpeed/Replacement enforcers.

## Gap Analysis Summary
- **Prohibited Platforms**: `fail2ban`, `Caddy`, and `IIS` still have references in root bundle files and `data/Enforcers/`.
- **Corruption**: `Apache_Legacy` string persists in descriptions and mapping fields (mostly in `enforcer_pattern_library`).
- **Risk Deletions**: `RISK-123` and `RISK-124` were partially removed but need a full sweep across all files.
- **LiteSpeed Integration**: Missing or inconsistent `litespeed` enforcer entries for applicable risks.
- **Replacement Gaps**: 17 risks (007, 081-095, 104) require replacement enforcers for the removed `fail2ban` functionality.

## Implementation Steps

### 1. Preparation & Cleanup
- [ ] Backup current `data` folder (internal record).
- [ ] Delete `data/Enforcers/fail2ban-template.json`.
- [ ] Perform a global search for `Caddyfile` or `iis` templates in `data/Enforcers/` (if any hidden) and delete.

### 2. Core Bundle Alignment (using Z_GLM5.1 as Reference)
- [ ] **ai_agent_instructions_v2.0.json**:
    - [ ] Update `lib_key_reference` (remove iis, caddy, fail2ban; add litespeed).
    - [ ] Replace self-check rubric items #15 and #16.
    - [ ] Clean up example workflows (e.g., RISK-022 should only show Apache, Cloudflare, LiteSpeed).
- [ ] **enforcer_pattern_library_v2.0.json**:
    - [ ] Fix `description` and `enforcer_key_map` (remove Apache_Legacy/Caddy/IIS/fail2ban).
    - [ ] Delete `RISK-123` and `RISK-124` entries entirely.
    - [ ] Implement `litespeed` enforcers for the 8 key risks (010, 013, 020, 023, 024, 035, 116, 117).
- [ ] **vapt_driver_manifest_v2.0.json**:
    - [ ] Update `target_file_map` (remove fail2ban).
    - [ ] Delete all steps involving prohibited enforcers.
    - [ ] Add `litespeed` driver steps for applicable risks.
- [ ] **interface_schema_v2.0.json**:
    - [ ] Remove `platform_implementations` for prohibited platforms.
    - [ ] Add `litespeed` implementation for applicable risks.
- [ ] **VAPT_AI_Agent_System_README_v2.0.md**:
    - [ ] Remove all IIS, Caddy, and fail2ban sections.
    - [ ] Update platform coverage and enforcer key maps.

### 3. Replacement Strategy for fail2ban Risks
- [ ] For the 17 affected risks (007, 081-095, 104):
    - [ ] Ensure `php_functions` (transient-based rate limiting) exists.
    - [ ] Add `nginx` (limit_req) and `cloudflare` (WAF custom rules) where applicable.
    - [ ] For `RISK-104`, replace fail2ban logrotate with a standard cron-based log management if possible, or mark as N/A for standard hosting.

### 4. Driver Logic & Documentation
- [ ] **VAPT_Driver_Reference_v2.0.php**:
    - [ ] Update comments to remove `fail2ban` and include `LiteSpeed` specific notes.

### 5. Validation (Contract Gates)
- [ ] **Gate 1-2**: Verify NO references to IIS, Caddy, or fail2ban in any bundle file.
- [ ] **Gate 3**: Verify `RISK-123` and `RISK-124` are gone.
- [ ] **Gate 5**: Verify zero occurrences of `Apache_Legacy`.
- [ ] **Gate 6**: Cross-reference check (interface schema -> pattern library -> manifest).
- [ ] **Gate 7**: Path validation (ensure all paths are {ABSPATH} or valid server paths).

## Success Criteria
- [ ] All 23 remediation actions (REM-001 to REM-023) in the contract are marked as completed.
- [ ] The `ai_agent_instructions_v2.0.json` scores itself 19/19 on the new rubric.
- [ ] The total risk count is confirmed at 133.
