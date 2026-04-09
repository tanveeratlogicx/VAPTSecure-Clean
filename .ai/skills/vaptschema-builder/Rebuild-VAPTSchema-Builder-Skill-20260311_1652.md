# Rebuild VAPTSchema-Builder Skill Implementation Plan

## Changelog

* **20260311_@1652**: Initialized plan to rebuild the `VAPTSchema-Builder` skill from scratch based on `VAPT_AI_Agent_System_README_v2.0.md`. Awaiting review of the plan.
* **20260311_@1706**: Added comprehensive file examples for all 13 driver platforms into the `examples` directory, an updated `schema-template.json` in `resources`, and a `Validate-Schema.ps1` script for PowerShell workflows.

## Overview

The goal is to rebuild the VAPTSchema-Builder skill files cleanly to remove clutter, grounding it squarely on `VAPT_AI_Agent_System_README_v2.0.md` and emphasizing the core principle of whitelisting WordPress admin endpoints to avoid blocking legitimate operations.

## Core Principle

"This brief is designed to trigger the VAPTSchema-Builder skill patterns. Generate interactive schemas and configurations that are fully compatible with WordPress admin REST endpoints and core functionality. Ensure all security rules properly whitelist WordPress admin paths (`/wp-admin/`, `/wp-json/wp/v2/`, `/wp-json/vaptsecure/v1/`) to avoid blocking legitimate administrative operations."

## Reading Order (All Three Files)

We need to enforce a specific Files Reading order in order to get a complete, accurate picture for generating an A+ Adaptive Script Interface JSON is:

Step 1 → ai_agent_instructions   (load the rulebook: naming conventions,
                                   htaccess_syntax_guard, rubric,
                                   balanced_protection_policy, task steps)

Step 2 → interface_schema         (load the blueprint for the risk_id:
                                   ui_layout, components, severity,
                                   available_platforms, code_refs)

Step 3 → enforcer_pattern_library (load the actual enforcement code
                                   via lib_key from Step 2)

Step 4 → Self-check against rubric from Step 1 → score ≥18 → deliver

## Tasks

### 1. Recreate `SKILL.md`

- Draft a completely new `SKILL.md`.
* Include standard YAML frontmatter linking the version to `2.0.0`.
* Integrate the requested **Core Principle** prominently at the start.
* Transfer core knowledge from `VAPT_AI_Agent_System_README_v2.0.md` into the skill documentation formatting:
  * Mention the 5-file bundle architecture.
  * Detail the step-by-step workflow (Lookup schema -> Lookup pattern library -> .htaccess guards -> 19-point rubric).
  * Include specific guidelines on the whitelisting requirement for `wp-admin` and `wp-json`.
* **STATUS**: Done

### 2. Populate Contextual Directories (Examples, Scripts, Resources)

- Create JSON examples for all driver implementation platforms: htaccess, wp_config, php_functions, fail2ban, nginx, apache, caddy_native, server_cron, wordpress, wordpress_core, cloudflare, iis, caddy.
- Restore/Recreate `schema-template.json` to the `resources` directory.
- Create a `Validate-Schema.ps1` in scripts since the USER is on Windows `pwsh`.
* **STATUS**: Done

### 3. Recreate `DEVELOPER_GUIDE.md` and Add Visual Flow

- Rebuild `DEVELOPER_GUIDE.md` focusing on the exact workflow developers use for diagnosing driver failures or extending the manifest.
- Rebuild `USAGE_GUIDE.md` and `README.md` for broader context.
- Create a `VISUAL_FLOW.md` document using ASCII diagrams to show data flow between generation and execution layers.
* **STATUS**: Done

## Review Checkpoints

- **20260311_@1652**: Please review this plan before I proceed with crafting the new `SKILL.md`.
- **20260311_@1706**: Added script, resources, and examples. Awaiting final review.
- **20260311_@1724**: Added ASCII Visual Flow document. Task is fully completed.
