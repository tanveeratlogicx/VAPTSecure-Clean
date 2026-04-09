---
description: VAPT AI Agent Workflow: Transition to Develop (v2.0)
---

# VAPT AI Agent Workflow: Transition to Develop

This workflow is triggered when a feature is transitioned from `Draft` to `Develop`. It ensures that all generation follows the Schema-First Architecture v2.0 guidelines.

## 0. Core Principle: Preserve Core Functionality
* **Whitelisting**: Ensure all security rules generated explicitly whitelist or preserve access to:
    - WordPress Admin (`/wp-admin/`, `admin-ajax.php`)
    - REST API Endpoints (`/wp-json/`)
    - JSON core endpoints.
* Failure to do this will result in a site lockout or broken admin interface.

## 1. Task Selection
* Identify the target task type:
    - Use `generate_full_risk_package` for building a complete risk solution.
    - Use `generate_enforcement_code` for adding a specific platform implementation.

## 2. Schema-First Lookup (@VAPT-Secure/data/interface_schema_v2.0.json)
* Read the `risk_interfaces` for the target `risk_id`.
* Identify `available_platforms` and their `platform_implementations`.
* Extract `ui_layout`, `components`, and `actions` for the UI schema.

## 3. Pattern Library Lookup (@VAPT-Secure/data/enforcer_pattern_library_v2.0.json)
* Use the `lib_key` from the interface schema (e.g., `htaccess`, `wp_config`, `php_functions`) to look up the pattern.
* **DO NOT** write code from memory. Always use the `wrapped_code` or `code` from the pattern library.

## 4. Syntax Guard & Driver Context
* **.htaccess Guard**: run the `htaccess_syntax_guard` logic from `ai_agent_instructions_v2.0.json`.
* **Driver Ref**: Cross-reference with `vapt_driver_manifest_v2.0.json` via the `driver_ref` field in the schema to ensure compatibility with the PHP driver class.
* **Target Files**: Explicitly declare target configuration files (.htaccess, wp-config.php, etc.) in a `## 📁 Target Configuration Files` section.

## 5. Self-Check Rubric (Minimum Score 16/19)
* Run the 19-point self-check rubric from `ai_agent_instructions_v2.0.json`.
* Only deliver output if the score is 16 or higher.

---
// turbo-all
