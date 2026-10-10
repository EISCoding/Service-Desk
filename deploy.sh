#!/usr/bin/env bash
#
# Deployment script for Plesk (Git -> "Additional deployment actions").
#
#   bash deploy.sh
#
# Plesk ships several PHP versions. Point the script at the right binaries
# via environment variables if the defaults are not correct, e.g.:
#
#   PHP_BIN=/opt/plesk/php/8.3/bin/php bash deploy.sh
#
# Variables:
#   PHP_BIN       PHP CLI binary              (default: php)
#   COMPOSER_BIN  Composer (phar or script)   (default: composer from PATH)
#   NPM_BIN       npm binary                  (default: npm)
#   SKIP_ASSETS=1 skip the frontend build (e.g. when public/build is committed)

set -euo pipefail

cd "$(dirname "$0")"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-$(command -v composer || true)}"
NPM="${NPM_BIN:-npm}"

log() { printf '\n==> %s\n' "$1"; }

if [[ ! -f .env ]]; then
    echo "Error: .env is missing. Copy .env.example to .env and fill it in first." >&2
    exit 1
fi

if [[ -z "$COMPOSER" ]]; then
    echo "Error: Composer not found. Set COMPOSER_BIN." >&2
    exit 1
fi

# Bring the application back up even if a step fails.
log "Maintenance mode on"
"$PHP" artisan down --retry=30 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

log "Composer dependencies"
# Run Composer with the configured PHP binary so the right PHP version is used.
"$PHP" "$COMPOSER" install --no-dev -o --no-interaction --prefer-dist

if [[ "${SKIP_ASSETS:-0}" == "1" ]]; then
    log "Frontend build skipped (SKIP_ASSETS=1)"
elif command -v "$NPM" >/dev/null 2>&1; then
    log "Frontend build"
    "$NPM" ci --include=dev --no-audit --no-fund
    "$NPM" run build
elif [[ -f public/build/manifest.json ]]; then
    log "npm not found, using the committed public/build"
else
    echo "Error: npm not found and no public/build present." >&2
    exit 1
fi

log "Storage directories"
mkdir -p storage/app/private storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

log "Database migrations"
"$PHP" artisan migrate --force

# Adds new permissions and re-syncs the default roles (idempotent).
log "Roles and permissions"
"$PHP" artisan db:seed --class=RolesAndPermissionsSeeder --force

# Creates missing system templates, never overwrites edited ones.
log "System templates"
"$PHP" artisan db:seed --class=MailTemplateSeeder --force

log "Caches"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache

# A worker started by the scheduler before this deploy finishes its current job and exits.
"$PHP" artisan queue:restart

log "Maintenance mode off"
"$PHP" artisan up
trap - EXIT

log "Deployment finished"
