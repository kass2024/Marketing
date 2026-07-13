#!/usr/bin/env bash
#
# Safe VPS deploy for Parrot Canada / WABA
# - Runs pending ALTER migrations automatically
# - Does NOT sync or overwrite Meta ads, campaigns, ad sets, or creatives
#
# Usage on VPS:
#   cd /var/www/Marketing
#   bash scripts/deploy-vps.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/Marketing}"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$APP_DIR"

echo "==> Pull latest code (${BRANCH})"
git fetch origin
git pull origin "$BRANCH"

echo "==> Install PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

if command -v npm >/dev/null 2>&1 && [ -f package.json ]; then
  echo "==> Build frontend assets"
  npm ci --no-audit --no-fund
  npm run build
fi

echo "==> Database check"
php artisan db:recover-check

echo "==> Safe deploy (auto-migrations + admin login + cache; no ad sync)"
php artisan deploy:safe

echo "==> Ensure admin login"
php artisan users:ensure-admin --email=infos@visaconsultantcanada.com --password='VisaCanada2026!'

echo "==> Ensure public storage link"
php artisan storage:link 2>/dev/null || true

echo "==> Reload PHP (adjust service name if needed)"
if command -v systemctl >/dev/null 2>&1; then
  for svc in php8.2-fpm php8.1-fpm php-fpm; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
      sudo systemctl reload "$svc" || true
      break
    fi
  done
fi

echo "==> Deploy complete"
