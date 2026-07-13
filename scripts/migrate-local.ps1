# Local Windows: run pending migrations (XAMPP / MySQL must be running)
# Usage: .\scripts\migrate-local.ps1
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

Write-Host "==> Database check"
php artisan db:recover-check

Write-Host "==> Auto migrations (local)"
php artisan migrate:auto

Write-Host "==> Done"
