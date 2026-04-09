# VAPTSchema Builder ASCII Visual Flow

This document provides an ASCII-based visual flow of the transformation processes governed by the VAPTSchema-Builder skill. It illustrates how raw narrative instructions are transformed into executable Functional and Verification Controls via the AI Agent and the Plugin Driver.

---

## The Transformation Flow

```text
 +---------------------------------------------------------+
 |                 RAW RISK INSTRUCTIONS                   |
 |  (e.g., "Block XML-RPC to prevent pingback attacks")    |
 +---------------------------+-----------------------------+
                             |
                             v
 +=========================================================+
 |                PHASE 1: AI AGENT LAYER                  |
 |                 (Schema Generation)                     |
 +=========================================================+
                             |
         +-------------------v-------------------+
         | STEP 1: LOAD RULEBOOK                 |
         | Read ai_agent_instructions_v2.0       |
         | - Internalize naming conventions      |
         | - Internalize .htaccess guard         |
         | - Internalize WP Admin Whitelist Rule |
         +-------------------+-------------------+
                             |
         +-------------------v-------------------+
         | STEP 2: LOAD BLUEPRINT                |
         | Read interface_schema_v2.0            |
         | - Extract UI Layout & components      |
         | - Extract severity & platforms        |
         | - Retrieve `lib_key` for target       |
         +-------------------+-------------------+
                             |
         +-------------------v-------------------+
         | STEP 3: LOAD ENFORCEMENT CODE         |
         | Read enforcer_pattern_library_v2.0    |
         | - Match `lib_key` to get raw code     |
         | - Never write code from memory!       |
         | - APPLY WHITELIST (/wp-admin,         |
         |   /wp-json/wp/v2, /wp-json/vaptsecure)|
         +-------------------+-------------------+
                             |
         +-------------------v-------------------+
         | STEP 4: SELF-CHECK INSTRUCTIONS       |
         | Grade output against 19-point rubric. |
         |   [ ] Score >= 18?                    |
         |   [ ] WP Whitelist intact?            |
         |   [ ] No forbidden Apache directives? |
         +-------------------+-------------------+
                             |
 +===========================v=============================+
 |      [A+ ADAPTIVE SCRIPT INTERFACE JSON RECORD]         |
 |   (The perfectly formatted, valid JSON configuration)   |
 +===========================+=============================+
                             |
                             v
 +=========================================================+
 |              PHASE 2: PLUGIN DRIVER LAYER               |
 |                 (Execution & Output)                    |
 +=========================================================+
                             |
         +-------------------v-------------------+
         | PHP VAPT_DRIVER EXECUTION             |
         | 1. Parses generated JSON              |
         | 2. Reads driver_manifest_v2.0         |
         | 3. Performs Idempotency Check         |
         |    (Are markers already in file?)     |
         +-------------------+-------------------+
                             |
              /--------------+--------------\
             /                               \
 +-----------v---------+           +---------v-----------+
 | FUNCTIONAL CONTROLS |           |VERIFICATION CONTROLS|
 |  (Security Setup)   |           |    (Test Probes)    |
 +-----------+---------+           +---------+-----------+
             |                               |
             v                               v
 +---------------------+           +---------------------+
 | Server Files Updated|           | APIs & Universal    |
 | - /.htaccess        |           |   Probes Configured |
 | - /wp-config.php    |           | - Check HTTP Status |
 | - /etc/nginx/...    |           | - CLI verifications |
 +---------------------+           +---------------------+
```

---

## Core Principle Interaction

This flow visually emphasizes the **Core Principle**: The AI Agent must rigorously apply the **WP Admin Whitelist Rule** in Step 3, passing it through the **Self-Check** in Step 4. This guarantees that when the **A+ Adaptive Script Interface JSON** is handed over to the **Plugin Driver** in Phase 2, the resulting **Functional Controls** securely lock down the target vulnerability *without* locking administrators out of `/wp-admin/` or breaking fundamental `/wp-json/wp/v2/` operations.
