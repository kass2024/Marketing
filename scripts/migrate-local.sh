#!/usr/bin/env bash
# Local / dev: run pending migrations after git pull or composer install
# Usage: bash scripts/migrate-local.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Database check"
php artisan db:recover-check

echo "==> Auto migrations (local)"
php artisan migrate:auto

echo "==> Done"
