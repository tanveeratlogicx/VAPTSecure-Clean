# VAPT Secure Clean - Datafile Adoption Plan

**Version:** 2.0.0  
**Date:** 2026-05-14  
**Status:** PLANNING - Pending Execution Approval  
**Scope:** Adopt the updated JSON bundle in `data/` and establish a controlled derived mirror layer for `data/Resources/` while preserving current plugin compatibility.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Live Repo Baseline](#2-live-repo-baseline)
3. [Canonical Data Inventory](#3-canonical-data-inventory)
4. [Derived Resource Strategy](#4-derived-resource-strategy)
5. [Adoption Principles](#5-adoption-principles)
6. [Implementation Phases](#6-implementation-phases)
7. [Code Touchpoints](#7-code-touchpoints)
8. [Validation Gates](#8-validation-gates)
9. [Risks and Non-Goals](#9-risks-and-non-goals)
10. [Deliverables](#10-deliverables)
11. [Recommended Execution Order](#11-recommended-execution-order)

---

## 1. Executive Summary

The current VAPTSecure-Clean codebase is a WordPress plugin, not a Next.js app. The adoption plan therefore needs to focus on the existing PHP, REST, admin UI, and build pipeline that already consume the VAPT JSON bundle from `data/`.

The goal of this work is to:

- Treat `data/` as the canonical bundle source.
- Normalize the updated JSON files already present in the repo.
- Keep a derived mirror layer for platform/resource views.
- Support a `data/Resources/` target if we decide to introduce it, without breaking the existing `data/Enforcers/` workflow.
- Update the plugin's loaders, validators, admin screens, and build/deploy flow so they read the updated bundle consistently.
- Drive the `.ai` folder, SSoT framework, and IDE/extension artifacts from the same canonical bundle so they cannot drift independently.

This plan intentionally avoids rewriting the project into a different framework. It keeps the current WordPress plugin architecture and upgrades the data adoption path inside that boundary.

This does require a bounded architecture refactor in how the plugin resolves source-of-truth data, invalidates cache, and regenerates derived artifacts. It is not a full rewrite of the plugin shell or admin UX.

---

## 2. Live Repo Baseline

### What Exists Now

- Main plugin bootstrap: `vaptsecure.php`
- Admin and UI logic: `includes/class-vaptsecure-admin.php`, `assets/js/admin.js`, `assets/js/admin/App.jsx`
- Data and build logic: `includes/class-vaptsecure-build.php`, `includes/class-vaptsecure-enforcer.php`, `includes/class-vaptsecure-rest.php`
- Validation helpers: `includes/class-vaptsecure-ai-validator.php`, `includes/class-vaptsecure-schema-validator.php`
- Workflow and lifecycle helpers: `includes/class-vaptsecure-workflow.php`, `includes/self-check/class-vapt-lifecycle.php`

### Current Data Layout

The repository already contains:

- `data/` as the active bundle location
- `data/Enforcers/` as the current derived template folder
- `data_old/AutoHeal/Enforcers/` as historical reference material

### Important Current Behavior

- The plugin reads `interface_schema_v2.0.json`, `enforcer_pattern_library_v2.0.json`, and `vapt_driver_manifest_v2.0.json` from `data/`.
- The admin UI still assumes the current file naming scheme and active-file selection model.
- The build and REST layers already contain explicit references to the v2.0 JSON bundle.

This means the adoption task is an in-place migration of the bundle and its consumers, not a greenfield import.

### Global Artifact Chain

The canonical `data/` bundle must also drive:

- `.ai/` workflow and rule files
- `.ai/skills/vaptschema-builder/resources/` mirrored resources
- SSoT framework docs and prompts
- IDE-specific rule and adapter files
- extension-specific prompt or template artifacts

These outputs are derived artifacts only. They must not become alternate sources of truth.

---

## 3. Canonical Data Inventory

The canonical source of truth remains the top-level `data/` directory.

### Canonical Files

- `data/ai_agent_instructions_v2.0.json`
- `data/interface_schema_v2.0.json`
- `data/enforcer_pattern_library_v2.0.json`
- `data/vapt_driver_manifest_v2.0.json`
- `data/vapt_autoheal_contract_v2.0.json`
- `data/vapt_platform_contract_v3.0.json`
- `data/VAPT_AI_Agent_System_README_v2.0.md`
- `data/VAPT_Driver_Reference_v2.0.php`
- `data/VAPT_v2.0_changelog.md`

### Current Derived Templates

- `data/Enforcers/apache-template.json`
- `data/Enforcers/cloudflare-template.json`
- `data/Enforcers/htaccess-template.json`
- `data/Enforcers/litespeed-template.json`
- `data/Enforcers/nginx-template.json`
- `data/Enforcers/php-functions-template.json`
- `data/Enforcers/server-cron-template.json`
- `data/Enforcers/wordpress-core-template.json`
- `data/Enforcers/wordpress-template.json`
- `data/Enforcers/wp-config-template.json`

### Historical Reference Only

- `data_old/AutoHeal/`
- `data_old/Enforcers/`

These folders are useful for comparison and recovery, but they are not the source of truth for the active plugin.

---

## 4. Derived Resource Strategy

The updated bundle should support a derived resource mirror layer. The plan should allow either of these outcomes:

1. Keep `data/Enforcers/` as the active derived mirror and use it as the compatibility layer.
2. Introduce `data/Resources/` as the new mirror name and keep `data/Enforcers/` as a transitional alias until all consumers are updated.

### Recommended Rule

- `data/` is canonical.
- `data/Enforcers/` and `data/Resources/` are derived.
- Derived files must never be edited by hand if they can be regenerated from canonical bundle files.
- Canonical updates must happen first, then mirror export must follow.

### Why Keep a Mirror Layer

- It lets the admin UI load smaller platform-specific views.
- It keeps the per-platform templates easier to inspect and compare.
- It preserves compatibility with older code that expects `data/Enforcers/`.
- It gives us a clean path to adopt a `Resources` naming convention without breaking current readers.

---

## 5. Adoption Principles

1. Preserve the plugin architecture.
2. Preserve the current data contract shape unless a change is required by the updated JSON bundle.
3. Normalize new JSON variants in one loader path instead of scattering special cases through the UI.
4. Keep canonical bundle files and derived views in sync.
5. Validate before render, and validate before export.
6. Treat `data_old/` as reference only.
7. Do not let the resource mirror become a second source of truth.
8. Do not let `.ai`, SSoT, IDE, or extension artifacts diverge from the canonical bundle.
9. Prefer pointer-only or generated downstream artifacts where duplication would otherwise create drift.

---

## 6. Implementation Phases

### Phase A: Bundle Reconciliation

Goal: make sure the active JSON files in `data/` are the authoritative input for all plugin consumers.

Tasks:

- Verify the active file list and expected schema version.
- Reconcile any file name drift between canonical data and derived mirrors.
- Confirm `ai_agent_instructions_v2.0.json`, `interface_schema_v2.0.json`, `enforcer_pattern_library_v2.0.json`, and `vapt_driver_manifest_v2.0.json` are mutually consistent.
- Confirm the contracts in `vapt_platform_contract_v3.0.json` and `vapt_autoheal_contract_v2.0.json` are used as validation inputs, not just documentation.

### Phase B: Loader Normalization

Goal: centralize bundle loading so the rest of the plugin does not care whether a file came from `data/`, `data/Enforcers/`, or `data/Resources/`.

Tasks:

- Update the JSON loader path logic in the plugin core.
- Add a single normalization layer for interface schema, pattern library, driver manifest, and contract files.
- Make the active-file selection logic explicit instead of implicit.
- Preserve backward compatibility for the current `interface_schema_v2.0.json` default.

### Phase C: Derived Mirror Generation

Goal: generate the per-platform and per-resource views from canonical data.

Tasks:

- Generate or refresh `data/Enforcers/*.json`.
- If the project adopts `data/Resources/`, generate the same derived views there as well.
- Ensure platform-specific templates remain byte-for-byte deterministic where possible.
- Ensure mirror generation is one-way only: canonical -> derived.

### Phase D: Plugin Consumer Updates

Goal: make the existing PHP and JS consumers read the updated data consistently.

Tasks:

- Update admin screens to reflect the selected file and derived mirror state.
- Update the build and deploy logic to use the canonical bundle first.
- Update REST endpoints and validators so they resolve the same normalized data model.
- Keep the UI aligned with the updated bundle without inventing new data shapes.

### Phase E: SSoT, AI, and IDE Artifact Sync

Goal: propagate the canonical bundle into all downstream instruction and workflow artifacts.

Tasks:

- Update `.ai/` workflow files to reference the live canonical bundle.
- Refresh SSoT framework documents and prompts from the same source of truth.
- Keep IDE-specific and extension-specific files pointer-only or generated from canonical artifacts.
- Verify any mirrored skill resources remain aligned with the active bundle.

### Phase F: Validation and Export

Goal: ensure the updated bundle is safe to consume and safe to export.

Tasks:

- Validate bundle structure and cross-file references.
- Validate platform scope and forbidden platform removal.
- Validate derived mirror parity.
- Export only from a validated canonical bundle.

---

## 7. Code Touchpoints

These are the files that should be checked first during implementation:

- `vaptsecure.php`
- `includes/class-vaptsecure-build.php`
- `includes/class-vaptsecure-rest.php`
- `includes/class-vaptsecure-enforcer.php`
- `includes/class-vaptsecure-schema-validator.php`
- `includes/class-vaptsecure-ai-validator.php`
- `includes/class-vaptsecure-admin.php`
- `includes/class-vaptsecure-workflow.php`
- `includes/self-check/class-vapt-lifecycle.php`
- `assets/js/admin.js`
- `assets/js/admin/App.jsx`
- `assets/js/modules/generated-interface.js`

These files already reference the JSON bundle directly or indirectly, so they are the first places that can drift if the bundle layout changes.

---

## 8. Validation Gates

The adoption should not be considered complete until these gates pass:

1. Canonical bundle files load without parse errors.
2. Current plugin consumers resolve the updated bundle paths.
3. Derived mirror files match the canonical source of truth.
4. The schema loader can read the active file without hard-coded assumptions.
5. The admin UI can browse and select the updated data correctly.
6. No deprecated or prohibited platform references re-enter the active bundle.
7. Exported output remains deterministic and version-consistent.
8. `.ai`, SSoT, IDE, and extension artifacts stay synchronized with the canonical bundle.

If `data/Resources/` is introduced, it must also pass mirror parity checks against the canonical data.

---

## 9. Risks and Non-Goals

### Risks

- Introducing `data/Resources/` without updating all consumers will create duplicate truth sources.
- Updating the JSON bundle without refreshing the mirror files will leave the UI and export path out of sync.
- Mixing historical `data_old/` content into the active path will reintroduce deprecated fields and naming drift.

### Non-Goals

- Rewriting this repo into Next.js.
- Replacing the current WordPress admin flow.
- Treating `data_old/` as active input.
- Allowing derived mirror files to become independently edited sources.

---

## 10. Deliverables

At the end of this adoption slice, the repo should have:

- A canonical JSON bundle in `data/` that remains the source of truth.
- A defined mirror strategy for `data/Enforcers/` and, if adopted, `data/Resources/`.
- Loader and validator updates that understand the current bundle layout.
- A clear sync rule for canonical -> derived data flow.
- A clear sync rule for canonical -> `.ai` / SSoT / IDE / extension downstream artifacts.
- Validation evidence that the updated bundle is usable by the plugin without manual patching.

---

## 11. Recommended Execution Order

1. Canonical bundle reconciliation
2. Loader normalization
3. Runtime drift detection and autoheal
4. Plugin consumer updates
5. SSoT / AI / IDE artifact sync
6. Validation and export

This order keeps the canonical `data/` bundle first, the runtime second, and all downstream documentation/tooling surfaces synchronized last so they cannot become a competing source of truth.

---

*End of Document - VAPT Secure Clean Datafile Adoption Plan v2.0.0*
