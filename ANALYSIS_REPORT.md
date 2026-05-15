# VAPTSecure Clean - WordPress Plugin Analysis Report

**Generated:** April 15, 2026
**Plugin Version:** 3.5.1

---

## 1. Plugin Overview

**VAPTSecure Clean** is a sophisticated WordPress security plugin focused on Vulnerability Assessment and Penetration Testing (VAPT) and OWASP protection. It operates as a dual-purpose system:

- **Creator/Builder Mode:** A build generator for creating client-specific security plugins
- **Client Mode:** Deployed security plugin with domain-locked licensing

---

## 2. Core Architecture

### 2.1 Main Entry Point
- **File:** `vaptsecure.php` (1,100+ lines)
- Manages plugin bootstrap, configuration loading, and license validation

### 2.2 Key Components

| Component | Purpose |
|-----------|---------|
| `class-vaptsecure-build.php` | Generates client builds with white-label support |
| `class-vaptsecure-rest.php` | REST API (2,200+ lines) for feature management |
| `class-vaptsecure-enforcer.php` | Runtime security rule enforcement |
| `class-vaptsecure-admin.php` | Admin dashboard interface |
| `class-vaptsecure-db.php` | Database operations |
| `class-vaptsecure-auth.php` | Authentication system |
| `class-vaptsecure-license-manager.php` | License management |

### 2.3 Self-Check System
Located in `/includes/self-check/`:
- `class-vapt-self-check.php` - Core automation engine
- `class-vapt-check-item.php` - Validation result items
- `class-vapt-self-check-result.php` - Aggregated results
- `class-vapt-audit-log.php` - Audit trail logging
- `class-vapt-auto-correct.php` - Automatic corrective actions
- `class-vapt-cron.php` - Scheduled health checks
- `class-vapt-lifecycle.php` - Activation/deactivation hooks

### 2.4 Enforcers (Server-Specific Drivers)
Located in `/includes/enforcers/`:
- `class-vaptsecure-htaccess-driver.php` - Apache .htaccess rules
- `class-vaptsecure-nginx-driver.php` - Nginx configuration
- `class-vaptsecure-apache-deployer.php` - Apache deployment
- `class-vaptsecure-nginx-deployer.php` - Nginx deployment
- `class-vaptsecure-hook-driver.php` - WordPress PHP hooks
- `class-vaptsecure-php-driver.php` - PHP function injection
- `class-vaptsecure-config-driver.php` - wp-config.php rules
- `class-vaptsecure-litespeed-driver.php` - LiteSpeed configuration

---

## 3. Data Architecture

### 3.1 JSON Schema Files
Located in `/data/`:

| File | Purpose |
|------|---------|
| `interface_schema_v2.0.json` | UI definitions for 125 vulnerability features |
| `enforcer_pattern_library_v2.0.json` | Security rule patterns (Apache, Nginx, PHP, etc.) |
| `ai_agent_instructions_v2.0.json` | AI agent instructions |
| `vapt_driver_manifest_v2.0.json` | Driver interface contracts |
| `VAPT_Driver_Reference_v2.0.php` | PHP driver reference |

### 3.2 Enforcer Templates
Located in `/data/Enforcers/`:
- `apache-template.json`
- `nginx-template.json`
- `htaccess-template.json`
- `php-functions-template.json`
- `wp-config-template.json`
- `wordpress-template.json`
- `server-cron-template.json`
- `apache-template.json`
- `caddy-template.json`

---

## 4. Database Schema

### 4.1 Tables Created on Activation

1. **`wp_vaptsecure_domains`** - Licensed domains registry
2. **`wp_vaptsecure_domain_features`** - Domain-feature mappings
3. **`wp_vaptsecure_feature_status`** - Feature lifecycle (Draft/Develop/Release)
4. **`wp_vaptsecure_feature_meta`** - Feature schemas, implementation data
5. **`wp_vaptsecure_feature_history`** - Audit trail
6. **`wp_vaptsecure_domain_builds`** - Build history
7. **`wp_vaptsecure_security_events`** - Security event logs

---

## 5. Feature Dataset

- **Total Features:** 125 vulnerabilities from the VAPT Risk Catalogue
- **Categories:** Configuration, Authentication, Authorization, Injection, Exposure, Cryptography, Misc
- **OWASP Mapping:** PCI DSS, GDPR compliance indicators
- **UI Components:** Each feature has React-based frontend interface

---

## 6. License & Protection System

### 6.1 License Types
- `standard` - Single domain
- `developer_unbound` - Multi-domain, unlimited
- `7-day-trial` - Trial
- `15-day-demo` - Demo

### 6.2 Protection Features
- Domain-locked builds (exact or wildcard)
- Feature restrictions
- Installation limit enforcement (Multi-Site)
- Configuration file integrity monitoring
- Auto-restore from baseline

---

## 7. REST API Endpoints

Prefix: `/wp-json/vaptsecure/v1/`

Key endpoints:
- `GET /features` - List all features
- `POST /features/update` - Update feature
- `POST /features/transition` - Transition state (Draft→Develop→Release)
- `POST /features/{key}/verify` - Verify implementation
- `GET /domains` - List licensed domains
- `POST /domains/update` - Update domain
- `POST /upload-json` - Upload JSON schema
- `POST /update-hidden-files` - Update hidden files

---

## 8. Frontend Assets

Located in `/assets/js/`:

| Directory | Purpose |
|-----------|---------|
| `admin.js` | Main admin dashboard |
| `admin-modules/` | Modal, field-mapping, domains, design, logger |
| `modules/` | Interface generator, A+ generator |
| `client.js` | Client-facing scripts |
| `workbench.js` | Workbench UI |

---

## 9. AI Agent Configuration

Located in `/.ai/`:

- **SOUL.md** - Single source of truth for all AI editors
- **AGENTS.md** - Multi-agent orchestration workflows
- Rules for: Cursor, Claude, Gemini, Qoder, Trae, Windsurf, Kilo Code, Continue, Roo Code, GitHub Copilot, JetBrains Junie, Zed, OpenCode

---

## 10. Build Generator Flow

1. **Input:** Domain, features list, version, white-label settings
2. **Process:**
   - Filter release-status features from interface_schema
   - Generate config.php with base64-encoded payload
   - Copy plugin files (excluding dev/AI configs)
   - Rewrite main plugin headers
   - Remove superadmin functionality
3. **Output:** ZIP archive or config-only PHP file

---

## 11. Key Constants

| Constant | Purpose |
|-----------|---------|
| `VAPTSECURE_VERSION` | Plugin version |
| `VAPTSECURE_BUILD_VERSION` | Build-specific version |
| `VAPTSECURE_LICENSE_TYPE` | License type |
| `VAPTSECURE_DOMAIN_LOCKED` | Locked domain |
| `VAPTSECURE_RESTRICT_FEATURES` | Feature restriction mode |
| `VAPTSECURE_FEATURE_*` | Feature enable flags |

---

## 12. Lifecycle Hooks

- **Activation:** `register_activation_hook()` → Creates DB tables, registers cron, baseline self-check
- **Deactivation:** `register_deactivation_hook()` → Removes .htaccess rules, deregisters cron
- **Uninstall:** `register_uninstall_hook()` → Full cleanup

---

## 13. Security Features

- Configuration integrity monitoring (auto-restore)
- Domain mismatch detection and blocking
- Multi-Site installation limits
- PHP version requirements
- WordPress version requirements
- Automatic security alert emails

---

## Summary

This is a **production-grade WordPress security plugin** with:
- 125 vulnerability protection features
- Multiple server enforcer support (Apache, LiteSpeed, Nginx, Cloudflare, PHP)
- White-label client build generator
- Domain-locked licensing with wildcard support
- Self-check automation engine
- Full audit logging

The codebase is well-structured with clear separation between:
- Core plugin logic
- REST API handlers
- Enforcer drivers
- Self-check automation
- Frontend React components
- AI agent configurations

---

*Ready to accept queries. Reference this document for any future analysis needs.*
