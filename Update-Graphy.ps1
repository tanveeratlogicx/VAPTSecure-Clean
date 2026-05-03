# PowerShell script to update Graphy.md
# Usage: .\Update-Graphy.ps1

Write-Host "Updating Graphy.md..." -ForegroundColor Green
php update-graphy.php
Write-Host "Graphy.md has been updated with current directory structure." -ForegroundColor Green
Write-Host ""
Write-Host "To set up automatic updates when files change, consider:" -ForegroundColor Yellow
Write-Host "1. Adding a git hook (pre-commit/post-commit)" -ForegroundColor Yellow
Write-Host "2. Using a file watcher tool (like chokidar)" -ForegroundColor Yellow
Write-Host "3. Running this script manually when needed" -ForegroundColor Yellow