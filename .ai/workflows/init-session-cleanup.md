---
description: Session Initiation Cleanup Workflow
---

# Session Initiation Cleanup Workflow

This workflow is triggered at the start of every new session or major task. It ensures that the environment is clean and that debug logs from previous sessions are cleared.

## 1. Clear Debug Log
- **Action**: Empty the contents of `VAPT-Secure/vapt-debug.txt`.
- **Reason**: Prevents debug logs from growing indefinitely and ensures that any new logs are relevant only to the current session.
- **Command**: Clear the file content if it exists.

## 2. Verify Git Exclusion
- **Action**: Ensure `vapt-debug.txt` is present in `.gitignore`.
- **Reason**: Prevent accidental commitment of sensitive or large debug data to the repository.

---
// turbo-all
