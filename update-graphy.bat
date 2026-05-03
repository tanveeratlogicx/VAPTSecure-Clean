@echo off
echo Updating Graphy.md...
php update-graphy.php
echo Graphy.md has been updated with current directory structure.
echo.
echo To set up automatic updates when files change, consider:
echo 1. Adding a git hook (pre-commit/post-commit)
echo 2. Using a file watcher tool (like chokidar)
echo 3. Running this script manually when needed
pause