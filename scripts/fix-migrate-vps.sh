#!/usr/bin/env bash
#
# One-shot VPS repair when migrate fails with “Table already exists”
# Usage on server:
#   cd /var/www/Marketing && bash scripts/fix-migrate-vps.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Pull latest"
git fetch origin
git pull origin main

echo "==> Migrate with repair (skips create_* when tables exist)"
php artisan migrate:auto --force --repair

echo "==> Admin login"
php artisan users:ensure-admin --email=infos@visaconsultantcanada.com --password='VisaCanada2026!'

echo "==> Clear caches"
php artisan optimize:clear

echo "==> Done"
echo "Login: infos@visaconsultantcanada.com / VisaCanada2026!"
