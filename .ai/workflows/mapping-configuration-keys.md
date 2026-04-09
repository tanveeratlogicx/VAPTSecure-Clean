---
description: Map Include Fields on the Mapping Configuration Modal - Generate Context-Aware AI Prompts for Design Implementation Modal
---

# Mapping Configuration Keys Workflow

**Trigger:** "Map Include Fields" button on the "Features Tab" header
**Goal:** Map datasource fields to generate context-aware prompts for the "Design Implementation" modal
**Target:** Enhance AI Output Quality with practical, auto-mappable fields

---

## Phase 1: Context Setup

* Set context to @VAPT-Secure

* Load the v2.0 bundle architecture:
  * `VAPT-Secure\data\interface_schema_v2.0.json` (UI blueprint)
  * `VAPT-Secure\data\ai_agent_instructions_v2.0.json` (System rules and rubric)

* Read `VAPT-Secure\data\VAPT_AI_Agent_System_README_v2.0.md` to understand:
  * Schema-First Architecture guidelines
  * Naming conventions
  * .htaccess syntax guard requirements

---

## Phase 2: Essential Field Analysis

### 2.1 Currently Mapped Fields (9 fields)

| Field | Purpose | Auto-Map Keywords | Note |
|-------|---------|-------------------|------|
| `description` | Risk description | summary, description, desc | Maps to `summary` in datasource |
| `severity` | Severity level | severity.level, severity, level, risk_level | Maps to `severity.level` in datasource |
| `ui_layout` | UI layout configuration | ui_layout, layout, ui | Maps to `ui_layout` in datasource |
| `components` | UI components | components, ui_components, fields | Maps to `components` in datasource |
| `actions` | UI actions | actions, ui_actions, buttons | Maps to `actions` in datasource |
| `available_platforms` | Supported platforms | available_platforms, platforms, platform_list | Maps to `available_platforms` in datasource |
| `platform_implementations` | Platform-specific implementations | platform_implementations, implementations, enforcer_map | Maps to `platform_implementations` in datasource |
| `operational_notes` | Operational context | operational_notes, notes, operation_notes, operation_details | **NOT IN DATASOURCE** - Will not auto-map |
| `verification_steps` | Verification procedures | verification_steps, manual_verification, steps, test_method, verification | **NOT IN DATASOURCE** - Will not auto-map |

### 2.2 Recommended Additional Fields (5 fields)

| Field | Purpose | Auto-Map Keywords | Value to Design Implementation Modal |
|-------|---------|-------------------|--------------------------------------|
| `risk_id` | Unique risk identifier | risk_id, id, risk identifier, risk id | Essential for tracking and referencing specific risks |
| `title` | Human-readable risk name | title, name, risk title, risk name | Display name in modal headers and lists |
| `category` | Risk classification | category, type, risk category | Grouping and filtering in the modal |
| `owasp.cwe` | CWE identifiers | owasp.cwe, cwe, cwe id, cwe identifier | Compliance tracking and vulnerability reference |
| `owasp.owasp_top_10_2025` | OWASP Top 10 2025 | owasp.owasp_top_10_2025, owasp top 10, owasp 2025, owasp | OWASP compliance reference |

### 2.3 Verification Fields (from enforcer_pattern_library_v2.0.json)

| Field | Purpose | Auto-Map Keywords | Note |
|-------|---------|-------------------|------|
| `verification.command` | CLI verification command | verification.command, verification command, test command | From enforcer_pattern_library_v2.0.json |
| `verification.expected` | Expected verification output | verification.expected, expected output, test result | From enforcer_pattern_library_v2.0.json |

**Note:** These fields are nested under each risk's enforcer type (htaccess, wp_config, php_functions, etc.) and will map to the corresponding verification fields.

**Note:** `operational_notes` and `verification_steps` are NOT in the datasource and will not auto-map. They remain available for manual mapping if needed.

---

## Phase 3: Enhanced Auto-Map Logic

### 3.1 Update Auto-Map Keywords

Add the new fields to the auto-mapping logic in [`admin.js`](VAPT-Secure/assets/js/admin.js:1417):

```javascript
const autoMapField = (key, keywords) => {
  if (!newMapping[key]) {
    const match = findBestMatch(keywords);
    if (match) { newMapping[key] = match; mappedCount++; }
  }
};

// Existing fields
autoMapField('description', ['summary', 'description', 'desc']);
autoMapField('severity', ['severity.level', 'severity', 'level', 'risk_level']);
      autoMapField('ui_layout', ['ui_layout', 'layout', 'ui']);
      autoMapField('components', ['components', 'ui_components', 'fields']);
      autoMapField('actions', ['actions', 'ui_actions', 'buttons']);
      autoMapField('available_platforms', ['available_platforms', 'platforms', 'platform_list']);
      autoMapField('platform_implementations', ['platform_implementations', 'implementations', 'enforcer_map']);
      autoMapField('operational_notes', ['operational_notes', 'notes', 'operation_notes', 'operation_details']);
      autoMapField('verification_steps', ['verification_steps', 'manual_verification', 'steps', 'test_method', 'verification']);

      // NEW: Additional fields for Design Implementation Modal
      autoMapField('risk_id', ['risk_id', 'id', 'risk identifier', 'risk id']);
      autoMapField('title', ['title', 'name', 'risk title', 'risk name']);
      autoMapField('category', ['category', 'type', 'risk category']);
      autoMapField('owasp_cwe', ['owasp.cwe', 'cwe', 'cwe id', 'cwe identifier']);
      autoMapField('owasp_top_10_2025', ['owasp.owasp_top_10_2025', 'owasp top 10', 'owasp 2025', 'owasp']);

      setFieldMapping(newMapping);

### 3.2 Add Select Controls for New Fields

Add the new field mappings to the [`FieldMappingModal`](VAPT-Secure/assets/js/admin.js:1404) component:

```javascript
// Existing fields
renderMappingSelect(__('Description', 'vaptsecure'), 'description'),
renderMappingSelect(__('Severity', 'vaptsecure'), 'severity'),
renderMappingSelect(__('UI Layout', 'vaptsecure'), 'ui_layout'),
renderMappingSelect(__('Components', 'vaptsecure'), 'components'),
renderMappingSelect(__('Actions', 'vaptsecure'), 'actions'),
renderMappingSelect(__('Available Platforms', 'vaptsecure'), 'available_platforms'),
renderMappingSelect(__('Platform Implementations', 'vaptsecure'), 'platform_implementations'),
renderMappingSelect(__('Operational Notes', 'vaptsecure'), 'operational_notes'),
renderMappingSelect(__('Verification Steps', 'vaptsecure'), 'verification_steps'),

// NEW: Additional fields
renderMappingSelect(__('Risk ID', 'vaptsecure'), 'risk_id'),
renderMappingSelect(__('Title', 'vaptsecure'), 'title'),
renderMappingSelect(__('Category', 'vaptsecure'), 'category'),
renderMappingSelect(__('OWASP CWE', 'vaptsecure'), 'owasp_cwe'),
renderMappingSelect(__('OWASP Top 10 2025', 'vaptsecure'), 'owasp_top_10_2025'),
```

---

## Phase 4: Quality Validation

### 4.1 Validation Checklist

Before delivering the mapping configuration:

* [ ] All 14 fields (9 existing + 5 new) are mappable
* [ ] Auto-Map keywords are comprehensive and accurate
* [ ] Field names follow naming conventions
* [ ] All fields add value to the Design Implementation modal
* [ ] No duplicate or redundant mappings

### 4.2 Expected Outcome

The enhanced mapping configuration will:

1. **Improve Risk Identification** - `risk_id` and `title` provide clear identification
2. **Enable Better Grouping** - `category` allows filtering and organization
3. **Support Compliance Tracking** - `owasp.cwe` and `owasp_top_10_2025` provide compliance context
4. **Maintain Simplicity** - Only 14 total fields (manageable and practical)
5. **Enable Auto-Mapping** - All fields have clear, unique keywords for auto-detection

---

## Phase 5: Deliver Mapping Configuration

### 5.1 Final Field List (14 fields)

| # | Field | Purpose | Priority | Auto-Map Keywords | Note |
|---|-------|---------|----------|-------------------|------|
| 1 | `risk_id` | Unique identifier | High | risk_id, id, risk identifier, risk id | NEW |
| 2 | `title` | Display name | High | title, name, risk title, risk name | NEW |
| 3 | `category` | Classification | Medium | category, type, risk category | NEW |
| 4 | `description` | Risk description | High | summary, description, desc | Maps to `summary` |
| 5 | `severity` | Severity level | High | severity.level, severity, level, risk_level | Maps to `severity.level` |
| 6 | `ui_layout` | UI configuration | Medium | ui_layout, layout, ui | Maps to `ui_layout` |
| 7 | `components` | UI components | High | components, ui_components, fields | Maps to `components` |
| 8 | `actions` | UI actions | Medium | actions, ui_actions, buttons | Maps to `actions` |
| 9 | `available_platforms` | Supported platforms | High | available_platforms, platforms, platform_list | Maps to `available_platforms` |
| 10 | `platform_implementations` | Platform implementations | High | platform_implementations, implementations, enforcer_map | Maps to `platform_implementations` |
| 11 | `operational_notes` | Operational context | Medium | operational_notes, notes, operation_notes, operation_details | **NOT IN DATASOURCE** - Manual only |
| 12 | `verification_steps` | Verification procedures | Medium | verification_steps, manual_verification, steps, test_method, verification | **NOT IN DATASOURCE** - Manual only |
| 13 | `owasp_cwe` | CWE identifiers | Low | owasp.cwe, cwe, cwe id, cwe identifier | NEW |
| 14 | `owasp_top_10_2025` | OWASP Top 10 2025 | Low | owasp.owasp_top_10_2025, owasp top 10, owasp 2025, owasp | NEW |

### 5.2 Implementation Steps

1. **Update [`admin.js`](VAPT-Secure/assets/js/admin.js:1417)** - Add new fields to `handleAutoMap()` function
2. **Update [`admin.js`](VAPT-Secure/assets/js/admin.js:1404)** - Add new Select controls to `FieldMappingModal` component
3. **Test Auto-Map** - Verify all 14 fields can be auto-mapped with the keywords
4. **Verify Design Implementation Modal** - Ensure new fields enhance the modal functionality

---

## Summary

This simplified workflow focuses on **14 practical fields** (9 existing + 5 new) that:

1. **Add clear value** to the Design Implementation modal
2. **Can be auto-mapped** with unique, descriptive keywords (12 fields auto-mappable, 2 manual-only)
3. **Maintain simplicity** - manageable number of fields
4. **Support compliance tracking** - OWASP fields for vulnerability reference
5. **Improve user experience** - better identification, grouping, and context

**Auto-Mappable Fields (12):**

* Core Context: description, severity
* UI Schema: ui_layout, components, actions
* Platform & Enforcement: available_platforms, platform_implementations
* Risk Identification: risk_id, title, category
* Compliance: owasp.cwe, owasp.owasp_top_10_2025

**Manual-Only Fields (2):**

* operational_notes (not in datasource)
* verification_steps (not in datasource)

The workflow is triggered when the "Map Include Fields" button is clicked and will enable the AI Agent to generate context-aware prompts with enhanced field coverage.
