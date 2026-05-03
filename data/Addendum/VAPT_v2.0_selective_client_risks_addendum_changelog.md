# VAPT v2.0 Selective Independent Client-Reported Risk Addendum

Corrects the over-inclusive independent addendum by keeping exact-title matches on existing RiskIDs and adding only non-exact client-reported titles as independent risks. Total risks after patch: **135**.

## Existing exact-title matches retained

| Existing RiskID | Title |
|---|---|
| RISK-007 | Lack of Rate Limiting on WordPress Login |
| RISK-003 | Username Enumeration via WordPress REST API |
| RISK-008 | Username Enumeration via wp-login.php |
| RISK-126 | Outdated and Vulnerable WordPress Plugins |
| RISK-011 | Information Disclosure via readme.html |
| RISK-127 | No Input Validation |

## New independent risks added

| New RiskID | Title | Based on |
|---|---|---|
| RISK-128 | WordPress Cron Job Vulnerability (DoS) | RISK-001 |
| RISK-129 | XML-RPC Leads to Unauthenticated Blind SSRF | RISK-002 |
| RISK-130 | Directory Listing Vulnerability | RISK-013 |
| RISK-131 | Lack of Rate Limiting on Contact Form | RISK-009 |
| RISK-132 | Banner Grabbing Vulnerability | RISK-010 |
| RISK-133 | Unauthenticated Exposure of WordPress REST API Endpoints | RISK-006 |
| RISK-134 | Clickjacking | RISK-014 |
| RISK-135 | Public Exposure of Debug Log File | RISK-034 |

## Validation summary

- JSON parse passed for all four patched JSON files.
- Risk count is 135 in interface schema, pattern library, driver manifest, and AI risk index.
- RiskID sets match across JSON data files.
- No duplicate RiskIDs.
- Existing exact-title risks were not duplicated as new RiskIDs.
- Interface `lib_key` references resolve into the pattern library.
- Driver manifest required fields are present.
- Every driver `write_block` contains its begin/end marker.
- No `insert_at_anchor` step has a null `anchor_string`.
- RISK-010 executable code uses valid Nginx `server_tokens off;`.
- PHP syntax check passed for the PHP driver reference.
