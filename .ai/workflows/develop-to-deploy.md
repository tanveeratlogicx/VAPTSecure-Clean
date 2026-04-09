---
description: VAPT AI Agent Workflow: Develop to Deploy (v2.0)
---

# VAPT AI Agent Workflow: Develop to Deploy

This workflow is triggered when initiating the deployment of a feature (e.g., via the "A+ Workbench" button). It ensures that all deployment steps follow the Schema-First Architecture v2.0 and apply Toggle Intelligence.

## 0. Core Principle: Preserve Core Functionality
* **Whitelisting**: Ensure all security rules generated explicitly whitelist or preserve access to:
    - WordPress Admin (`/wp-admin/`, `admin-ajax.php`)
    - REST API Endpoints (`/wp-json/`)
    - JSON core endpoints.
* Failure to do this will result in a site lockout or broken admin interface.

## 1. Load Rulebook (@VAPT-Secure/data/ai_agent_instructions_v2.0.json)
* Internalize naming conventions, `.htaccess` syntax guard, and the balanced protection policy.
* Note the 19-point rubric for the final self-check.

## 2. Load Blueprint (@VAPT-Secure/data/interface_schema_v2.0.json)
* Look up the target `RISK-XXX`.
* Extract `ui_layout`, `components`, `severity`, and `available_platforms`.
* Identify the crucial `lib_key` inside `code_refs` for the target platform.

## 3. Load Enforcement Code (@VAPT-Secure/data/enforcer_pattern_library_v2.0.json)
* Map `lib_key` to the library and extract the actual enforcement code.
* **NEVER** write enforcement code from memory.
* **Apply Toggle Intelligence**: Check the `feat_enabled` state from the feature data.
    - If `true`: Inject the FULL enforcement block with VAPT markers.
    - If `false`: Comment out or remove the enforcement block, preserving VAPT markers.
* Use the snippet templates for .htaccess, wp-config, or PHP Hooks as defined in `develop-to-deploy.agrules`.

## 4. Final Self-Check & Rubric (Minimum Score 18/19)
* Run the 19-point self-check rubric from `ai_agent_instructions_v2.0.json`.
* **Deployment Standard**: Your self-evaluation score must be **≥ 18**.
* Only deliver output if the score is 18 or higher. If lower, refactor until the standard is met.

## 5. Delivery with Toggle State Documentation
When delivering, clearly document:
1. The current `feat_enabled` state (true/false).
2. What code was injected (if enabled) or removed/commented (if disabled).
3. Expected verification results (e.g., presence/absence of `X-VAPT-Feature` header).

---
// turbo-all
