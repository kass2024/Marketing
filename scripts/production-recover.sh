#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/Marketing}"

echo "==> Recovering Parrot Canada / Marketing app"
echo "App directory: ${APP_DIR}"

if command -v systemctl >/dev/null 2>&1; then
  for service in mysql mariadb mysqld; do
    if systemctl list-unit-files | awk '{print $1}' | grep -qx "${service}.service"; then
      echo "==> Starting ${service}"
      sudo systemctl start "${service}" || true
      sudo systemctl enable "${service}" || true
      sudo systemctl --no-pager --full status "${service}" || true
      break
    fi
  done
fi

cd "${APP_DIR}"

echo "==> Clearing Laravel caches"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "==> Checking database connection"
php artisan db:show || {
  echo "Database still unreachable. Verify .env values:"
  echo "  DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SOCKET"
  exit 1
}

echo "==> Recovery complete"
