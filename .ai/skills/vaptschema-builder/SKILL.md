---
name: VAPTSchema Builder
description: Specialized skill for transforming VAPT-Risk-Catalogue definitions into highly accurate Interface Schema JSONs. Core focus is on generating interactive schemas and configurations that are fully compatible with WordPress admin REST endpoints and core functionality.
version: "2.0.0"
schema_version: "2.0.0"
---

# VAPTSchema Builder Expert Skill (v2.0.0)

This skill acts as the precise translation layer for generating an **A+ Adaptive Script Interface JSON** for the VAPTBuilder plugin. This version leverages the v2.0 Unified Bundle (5-file architecture) to completely eliminate AI hallucinations and ensure rock-solid compatibility with WordPress.

## 🎯 Core Principle

> **CRITICAL MANDATE: WHITELISTING LEGITIMATE OPERATIONS**
> This brief is designed to trigger the VAPTSchema-Builder skill patterns. Generate interactive schemas and configurations that are fully compatible with WordPress admin REST endpoints and core functionality. 
> 
> Ensure all security rules properly whitelist WordPress admin paths:
> - `/wp-admin/`
> - `/wp-json/wp/v2/`
> - `/wp-json/vaptsecure/v1/`
> 
> You must avoid blocking legitimate administrative operations at all costs.

---

## 🏗️ The 5-File Bundle Architecture

The v2.0 system uses a unified bundle. As an AI Agent, you will primarily interact with the **AI Agent Layer** documents:
1. `ai_agent_instructions_v2.0.json`: The rulebook (conventions, guardrails, rubric).
2. `interface_schema_v2.0.json`: The blueprint (layout, components, platforms).
3. `enforcer_pattern_library_v2.0.json`: The exact enforcement code.

*(Note: The plugin executes using the **Driver Layer**: `vapt_driver_manifest_v2.0.json` and `VAPT_Driver_Reference_v2.0.php`).*

---

## 📋 The 4-Step Reading & Generation Workflow

To get a complete, accurate picture for generating an A+ Adaptive Script Interface JSON, you **MUST** enforce this specific reading order:

### Step 1 → Load the Rulebook
Read `ai_agent_instructions_v2.0.json`.
- Internalize the naming conventions.
- Internalize the `.htaccess` syntax guard.
- Note the 19-point rubric and balanced protection policy.

### Step 2 → Load the Blueprint
Look up the target `RISK-XXX` in `interface_schema_v2.0.json`.
- Extract the `ui_layout`, `components`, `severity`, and `available_platforms`.
- Note the crucial `lib_key` inside `code_refs` for the target platform.

### Step 3 → Load the Enforcement Code
Map the `lib_key` to `enforcer_pattern_library_v2.0.json`.
- Extract the actual enforcement code.
- **Never write enforcement code from memory.** Always use the code provided in the library.
- Apply the **Core Principle** whitelisting (ensure `/wp-admin/`, `/wp-json/wp/v2/`, and `/wp-json/vaptsecure/v1/` are excluded from blocks if applicable to the enforcer type).

### Step 4 → Self-Check & Deliver
Score your generated JSON output against the 19-point rubric from Step 1.
- You must achieve a score of **≥18 / 19** to deliver the code.
- **MANDATORY CHECK**: For `.htaccess` rules, they MUST be inserted `before_wordpress_rewrite` and wrapped in `<IfModule mod_rewrite.c>`.

---

## 🚦 Avoiding Hallucinations (Strict Constraints)

1. **The Rewrite Rule "Dead Zone"**: Placing rewrite rules at the bottom of `.htaccess` (after `# END WordPress`) creates a silent failure. Ensure `insertion_point: "before_wordpress_rewrite"`.
2. **Key Matching**: If a component's toggle has `key: "UI-RISK-003-001"`, then the `enforcement.mappings` object MUST use exactly `"UI-RISK-003-001"`.
3. **No Forbidden Apache Directives**: Never use `TraceEnable`, `<Directory>`, or `ServerSignature` in `.htaccess`. Use safe equivalents (e.g., `mod_headers`).
