# VAPT Bundle v2.0 Metadata Consistency Patch

Applied fixes:
- Aligned documented risk count from 125 to 127 where the bundle describes total coverage.
- Added RISK-126 and RISK-127 to ai_agent_instructions_v2.0 risk_index.
- Updated README Source Risk Coverage:
  - PHP Functions: 28 -> 29
  - WordPress Core: 1 -> 2
- Updated README Severity Coverage:
  - Critical: 9 -> 10
  - High: 36 -> 37
  - Medium: 46 unchanged
  - Low: 34 unchanged
- Updated descriptions/system prompt in:
  - ai_agent_instructions_v2.0_fixed.json
  - interface_schema_v2.0_fixed.json
  - enforcer_pattern_library_v2.0_fixed.json

No enforcement logic, driver write blocks, risk definitions, or PHP driver behavior were changed.
