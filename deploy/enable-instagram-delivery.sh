#!/usr/bin/env bash
# WABA only — uses this app's .env (META_AD_ACCOUNT_ID, META_PAGE_ID, META_INSTAGRAM_USER_ID).
# Do not copy credentials from xanderbot; they use separate Meta ad accounts.
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan config:clear
php artisan optimize:clear

php artisan meta:verify-instagram || exit 1
php artisan meta:enable-instagram
