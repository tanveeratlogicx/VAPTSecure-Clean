---
description: Smart Git commit with semantic versioning messages and temp file cleanup
---

# Git Commit Workflow

Intelligently commits changes to Git with semantic commit messages, automatically excluding and cleaning up temporary/verification/test files.

## What This Workflow Does

1. **Detects** modified and untracked files in the repository
2. **Excludes** temporary/verification/test files from the commit
3. **Removes** excluded files from git cache (unstages them if staged)
4. **Generates** semantic commit message based on the actual changes
5. **Commits** only relevant files with an appropriate message

## Excluded Patterns (Auto-Removed)

The following file patterns are automatically excluded from commits:

| Pattern | Description |
|---------|-------------|
| `*.log` | Log files |
| `test-*.php` | PHP test files |
| `test-*.txt` | Text test files |
| `debug-*` | Debug output files |
| `vapt-debug.txt` | VAPT debug output |
| `*.tmp` | Temporary files |
| `*.temp` | Temp files |
| `*~` | Backup files |
| `*.cache` | Cache files |
| `verify-*.php` | Verification scripts |
| `check-*.php` | Check scripts |
| `.kilo/worktrees/` | AI worktree directories |

## Usage

### Standard Commit (Auto-Generated Message)
// turbo
1. Run: `powershell -ExecutionPolicy Bypass -File tools/git-commit.ps1`

### Commit with Custom Message
// turbo
2. Run: `powershell -ExecutionPolicy Bypass -File tools/git-commit.ps1 -Message "feat: add new security scanner"`

### Dry Run (Preview Only)
// turbo
3. Run: `powershell -ExecutionPolicy Bypass -File tools/git-commit.ps1 -DryRun`

## Semantic Commit Message Format

The auto-generated message follows [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>: <description>

[optional body]
```

### Types (Auto-Detected from File Changes)

| Type | When Used |
|------|-----------|
| `feat` | New features, UI components, endpoints |
| `fix` | Bug fixes, patches, corrections |
| `docs` | Documentation updates, comments |
| `style` | CSS, formatting, UI adjustments |
| `refactor` | Code restructuring, no behavior change |
| `test` | Test files (if explicitly included) |
| `chore` | Build scripts, tooling, cleanup |
| `security` | Security hardening, VAPT updates |

### Detection Rules

- **security**: Files in `/includes/security/`, `.htaccess` changes
- **feat**: New files in `/includes/`, `/assets/`, new REST endpoints
- **fix**: Modifications to existing PHP logic, bug fixes
- **docs**: `README.md`, `*.md`, comment changes
- **style**: `*.css`, `*.scss`, styling changes
- **chore**: `tools/`, workflow files, config updates

## How It Works

1. **Stage Check**: Runs `git status` to identify all changes
2. **Filter**: Excludes temporary/verification files based on patterns
3. **Unstage**: Runs `git rm --cached` on excluded files if they were staged
4. **Analyze**: Categorizes remaining files by type and location
5. **Message**: Generates semantic message based on dominant change type
6. **Commit**: Executes `git commit` with generated or provided message

## Requirements

- PowerShell 5.1+ or PowerShell Core 7+
- Git repository initialized (`git init`)
- User name and email configured in Git

## Safety Features

- **Dry-run mode**: Preview what would be committed without making changes
- **Confirmation prompt**: Asks for confirmation before committing (unless `-Force` flag used)
- **Backup check**: Warns if committing more than 20 files at once
- **Empty commit prevention**: Exits gracefully if no valid files to commit

---
// turbo-all
