# VAPT Bundle v2.0 Risk Consolidation Patch (LEGACY)

> **⚠️ LEGACY DOCUMENT — SUPERSEDED**
> This changelog documents the 14-finding consolidation patch applied to the original 127-risk catalogue. The live skill bundle has since been expanded to **133 risks** (RISK-001 through RISK-135, excluding RISK-123/RISK-124 which were deleted per the v3.0 platform scope contract). The `*.risk_consolidated.json` files referenced below no longer exist as separate artefacts; their contents were merged into the canonical v2.0 bundle files. Retain this document for historical reference only.

Patch purpose: incorporate 14 externally supplied WordPress/VAPT findings into the existing VAPT risk catalogue without creating duplicate RiskIDs or changing the JSON file structure.

## Applied Mapping

| Submitted # | Submitted Risk | Existing RiskID | Patch Action |
|---:|---|---|---|
| 01 | Lack of Rate Limiting on WordPress Login | RISK-007 | Existing definition retained; summary strengthened for brute-force/credential-stuffing wording. |
| 02 | WordPress Cron Job Vulnerability (DoS) | RISK-001 | Title and summary updated to explicitly reference cron-job DoS via wp-cron.php. |
| 03 | XML-RPC Leads to Unauthenticated Blind SSRF | RISK-002 | Title and summary updated to include unauthenticated blind SSRF alongside pingback/DDoS abuse. |
| 04 | Directory Listing Vulnerability | RISK-013 | Title and summary updated to match vulnerability wording and include exposed files/folders. |
| 05 | Lack of Rate Limiting on Contact Form | RISK-009 | Existing broader definition retained; summary strengthened for contact/registration form throttling. |
| 06 | Banner Grabbing Vulnerability | RISK-010 | Title and summary updated to use submitted wording and fingerprinting detail. |
| 07 | Username Enumeration via WordPress REST API | RISK-003 | Existing definition retained; summary strengthened for unauthenticated /wp-json/wp/v2/users exposure. |
| 08 | Username Enumeration via wp-login.php | RISK-008 | Existing definition retained; summary strengthened for login error discrepancy. |
| 09 | Outdated and Vulnerable WordPress Plugins | RISK-126 | Existing definition retained; summary strengthened for known-vulnerability exposure. |
| 10 | Unauthenticated Exposure of WordPress REST API Endpoints | RISK-006 | Title and summary updated to explicitly cover unauthenticated REST endpoint exposure. |
| 11 | Clickjacking | RISK-014 | Title and summary updated to explicitly reference clickjacking via missing anti-framing header. |
| 12 | Public Exposure of Debug Log File | RISK-034 | Title and summary updated to explicitly cover public wp-content/debug.log exposure. |
| 13 | Information Disclosure via readme.html | RISK-011 | Summary cleaned and strengthened; stray "New" text removed. |
| 14 | No Input Validation | RISK-127 | Existing definition retained; summary strengthened for untrusted input/injection risk. |

## Files Patched (Legacy — merged into canonical bundle)

- `interface_schema_v2.0.risk_consolidated.json` → merged into `interface_schema_v2.0.json`
- `enforcer_pattern_library_v2.0.risk_consolidated.json` → merged into `enforcer_pattern_library_v2.0.json`
- `vapt_driver_manifest_v2.0.risk_consolidated.json` → merged into `vapt_driver_manifest_v2.0.json`
- `ai_agent_instructions_v2.0.risk_consolidated.json` → merged into `ai_agent_instructions_v2.0.json`

## Validation Results (at time of patch — 127 risks)

- JSON parse validation: passed for all patched JSON files.
- Risk count retained: 127 in interface schema, pattern library, and driver manifest.
- RiskID set retained: no new RiskIDs, no removed RiskIDs, no duplicate RiskIDs.
- Internal `risk_id` values match object keys.
- Cross-file RiskID sets match between interface schema, pattern library, and driver manifest.
- Interface `lib_key` references resolve into the pattern library.
- Driver required fields remain present for all manifest steps.
- Driver `write_block` values still contain matching begin/end markers.

## Subsequent Changes Since This Patch

The 127-risk catalogue was later expanded to 133 risks with the addition of RISK-128 through RISK-135. RISK-123 and RISK-124 (Caddy-native risks) were deleted per the v3.0 platform scope contract (`vapt_platform_contract_v3.0.json`). The auto-heal consistency contract (`vapt_autoheal_contract_v2.0.json`) was added to document and remediate cross-file consistency gaps. All consolidated changes are now reflected in the canonical v2.0 bundle files.

## Scope Control

No enforcement code blocks, driver write blocks, rollback markers, target files, or driver behavior were changed. This patch was limited to catalogue wording/title/summary consolidation and AI risk index title alignment.
