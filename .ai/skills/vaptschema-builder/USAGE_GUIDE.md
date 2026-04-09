# VAPTSchema Builder Usage Guide

## Engaging the Skill
To trigger the VAPTSchema-Builder, prompt the AI Agent with tasks that directly reference interface generation or VAPT deployment.

### Example Prompts
* *"Generate the VAPTSchema JSON for RISK-005 using the .htaccess platform."*
* *"Build a full interface schema package for RISK-022 across Cloudflare, IIS, and Caddy."*
* *"Diagnose why the VAPT_Driver failed to apply the schema for RISK-010."*

## The 4-Step Output Expectation
When you prompt the skill to generate a schema for a specific risk, the Agent will perform these internal steps:
1. **Load Rulebook:** Consult `ai_agent_instructions_v2.0.json`.
2. **Load Blueprint:** Consult `interface_schema_v2.0.json` for UI structure.
3. **Load Enforcer Code:** Consult `enforcer_pattern_library_v2.0.json` for the exact code implementation.
4. **Self-Check:** Grade its output against the 19-point rubric before printing to you.

## Reading the Output
The standard output from the AI should be a raw, valid JSON object following the `schema-template.json` structure:

```json
{
  "controls": [ ... ],
  "enforcement": { ... }
}
```

If the AI outputs code that violates the **Core Principle** (e.g., it blocks `/wp-admin/` without a whitelist condition), remind the AI to re-read `SKILL.md` to refresh its constraints.
