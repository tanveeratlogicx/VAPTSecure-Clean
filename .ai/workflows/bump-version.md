---
description: Bump the plugin version (major, minor, or patch)
---

# Version Bump Workflow

This workflow bumps the VAPT-Secure plugin version using semver (major.minor.patch).
It uses the standalone `tools/bump-version.ps1` script — independent of any IDE or extension.

## Version Locations (Auto-Synced by Script)

| Location | File | Format |
|----------|------|--------|
| Plugin Header | `vaptsecure.php` line ~6 | ` * Version: X.Y.Z` |
| PHP Constant | `vaptsecure.php` line ~55 | `define('VAPTSECURE_VERSION', 'X.Y.Z')` |

## Usage

### Patch Bump (e.g., 2.5.9 → 2.5.10)
// turbo
1. Run: `powershell -ExecutionPolicy Bypass -File tools/bump-version.ps1 patch`

### Minor Bump (e.g., 2.5.9 → 2.6.0)
// turbo
2. Run: `powershell -ExecutionPolicy Bypass -File tools/bump-version.ps1 minor`

### Major Bump (e.g., 2.5.9 → 3.0.0)
// turbo
3. Run: `powershell -ExecutionPolicy Bypass -File tools/bump-version.ps1 major`

### Set Exact Version
// turbo
4. Run: `powershell -ExecutionPolicy Bypass -File tools/bump-version.ps1 set X.Y.Z`

## When to Bump

- **patch**: Bug fixes, minor tweaks, console cleanup, CSS adjustments
- **minor**: New features, new UI components, new REST endpoints
- **major**: Breaking changes, architecture overhaul, schema version change

## Important Notes

- The script auto-verifies that BOTH version locations are updated
- The `VAPTSECURE_VERSION` constant is the single source of truth used by all `wp_enqueue_*` calls
- The plugin header `Version:` is what WordPress reads for the plugin list
- `VAPTSECURE_DATA_VERSION` is NOT bumped by this script (it tracks data schema version separately)

---
// turbo-all
