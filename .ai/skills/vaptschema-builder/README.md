# VAPTSchema Builder Skill

## Overview
The **VAPTSchema Builder** is an expert AI skill designed exclusively for the VAPT-Secure WordPress plugin. Its primary purpose is to transform raw Risk Catalog definitions into highly accurate, executable Interface Schema JSONs.

By leveraging the v2.0 Unified Bundle architecture, this skill ensures zero AI hallucinations when writing security configurations and completely guarantees that WordPress core functionality (such as REST API and Admin Panels) remains accessible and unbroken.

## Skill Objectives
* **Generate Schema JSONs:** Translate narrative risk definitions into strict JSON blueprints containing UI controls and enforcement mappings.
* **Format Enforcement Code:** Map source data to the target schema via a deterministic pattern library.
* **Whitelist Critical Paths:** Ensure all generated `.htaccess`, Nginx, or PHP rules whitelist `/wp-admin/`, `/wp-json/wp/v2/`, and `/wp-json/vaptsecure/v1/`.
* **Zero Dead Zones:** Guarantee that WordPress `[L]` catch-all rewrite flags do not swallow security rules by inserting them *before* standard WordPress routing.

## Directory Structure
* `SKILL.md`: The core rulebook and instructions for the AI Agent.
* `DEVELOPER_GUIDE.md`: In-depth documentation on the underlying driver layer and how to troubleshoot.
* `USAGE_GUIDE.md`: Instructions on how to prompt the skill and what to expect as output.
* `/resources`: Core 5-file architecture JSONs and markdown specs (`interface_schema_v2.0.json`, `enforcer_pattern_library_v2.0.json`, etc.)
* `/examples`: Concrete JSON implementation examples mapped to the 13 supported driver paradigms.
* `/scripts`: Utility scripts used to validate and query the generated JSON outputs.

## Requirements
* VAPT-Secure Plugin core architecture.
* A compatible parser for v2.0 VAPT schema formats.
