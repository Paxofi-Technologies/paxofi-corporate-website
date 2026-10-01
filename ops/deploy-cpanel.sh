#!/usr/bin/env bash
# Paxofi Corporate Website — cPanel deployment (CW-OPS-004 / CW-UAT-008)
#
# Deploys the `main` branch from the cPanel Git clone into the two runtime
# directories, in a fixed order:
#   1. update the Git clone (fast-forward only)
#   2. back up the database
#   3. sync + composer install the API, then apply database migrations
#   4. sync + npm ci + build the Next.js frontend, then restart Passenger
#   5. smoke-test the live API (health, readiness, catalogue, CORS)
#
# Run from cPanel Terminal:
#   bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh
#
# First deployment onto the existing production database only (001–003 were
# applied by hand before migrations were tracked):
#   bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh --baseline 003
#
# Every path and binary can be overridden with the environment variables
# below. Nothing here deletes backend/.env, frontend .env* files or vendor/.
set -euo pipefail

REPO_DIR="${REPO_DIR:-$HOME/repositories/paxofi-corporate-website}"
API_RUNTIME_DIR="${API_RUNTIME_DIR:-$HOME/paxofi-api-runtime}"
FRONTEND_APP_DIR="${FRONTEND_APP_DIR:-$HOME/paxofi-corporate-website}"
NODE_VENV="${NODE_VENV:-$HOME/nodevenv/paxofi-corporate-website/22/bin/activate}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/corporate-website}"
BRANCH="${BRANCH:-main}"

if [[ -z "${PHP_BIN:-}" ]]; then
    if [[ -x /opt/cpanel/ea-php84/root/usr/bin/php ]]; then PHP_BIN=/opt/cpanel/ea-php84/root/usr/bin/php; else PHP_BIN=php; fi
fi
if [[ -z "${COMPOSER_BIN:-}" ]]; then
    COMPOSER_BIN="$(command -v composer || echo /opt/cpanel/composer/bin/composer)"
fi
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-$(command -v mariadb-dump || command -v mysqldump || true)}"

BASELINE=""
SKIP_FRONTEND=0
SKIP_BACKUP=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --baseline) BASELINE="${2:?--baseline needs a migration number, e.g. 003}"; shift 2 ;;
        --skip-frontend) SKIP_FRONTEND=1; shift ;;
        --skip-backup) SKIP_BACKUP=1; shift ;;
        -h|--help) sed -n '2,23p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nDEPLOY FAILED: %s\n' "$*" >&2; exit 1; }

API_BACKEND="$API_RUNTIME_DIR/backend"

step "Preflight"
[[ -d "$REPO_DIR/.git" ]] || fail "Git clone not found at $REPO_DIR"
[[ -f "$API_BACKEND/.env" ]] || fail "Missing $API_BACKEND/.env (copy .env.example from the repo and fill in production values)"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || fail "$PHP_BIN is not PHP 8.4+ (set PHP_BIN)"
"$PHP_BIN" -m | grep -qi '^pdo_mysql$' || fail "pdo_mysql extension missing for $PHP_BIN"
echo "PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')  repo: $REPO_DIR  api: $API_BACKEND  frontend: $FRONTEND_APP_DIR"

step "Update Git clone ($BRANCH, fast-forward only)"
git -C "$REPO_DIR" fetch --quiet origin "$BRANCH"
git -C "$REPO_DIR" checkout --quiet "$BRANCH"
git -C "$REPO_DIR" merge --ff-only --quiet "origin/$BRANCH"
COMMIT="$(git -C "$REPO_DIR" rev-parse --short HEAD)"
echo "Deploying commit $COMMIT"

# Reads DB_* from backend/.env (process environment wins), via the PCF loader.
db_setting() {
    (cd "$API_BACKEND" && "$PHP_BIN" -r '
        require "vendor/autoload.php";
        $v = (new Paxofi\Core\Configuration\EnvLoader())->load(".env");
        echo $v[$argv[1]] ?? ($argv[2] ?? "");' "$1" "${2:-}")
}

if [[ "$SKIP_BACKUP" -eq 0 ]]; then
    step "Back up database"
    [[ -n "$MYSQLDUMP_BIN" ]] || fail "mariadb-dump/mysqldump not found (set MYSQLDUMP_BIN, or --skip-backup after taking a backup in phpMyAdmin)"
    [[ -f "$API_BACKEND/vendor/autoload.php" ]] || fail "No vendor/ in $API_BACKEND yet; run composer install there once, or use --skip-backup after a manual backup"
    mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
    DEFAULTS_FILE="$(mktemp)"; chmod 600 "$DEFAULTS_FILE"
    trap 'rm -f "$DEFAULTS_FILE"' EXIT
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$(db_setting DB_HOST 127.0.0.1)" "$(db_setting DB_PORT 3306)" "$(db_setting DB_USERNAME)" "$(db_setting DB_PASSWORD)" > "$DEFAULTS_FILE"
    BACKUP_FILE="$BACKUP_DIR/$(db_setting DB_DATABASE)-$(date -u +%Y%m%dT%H%M%SZ)-before-$COMMIT.sql.gz"
    "$MYSQLDUMP_BIN" --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --routines --triggers "$(db_setting DB_DATABASE)" | gzip > "$BACKUP_FILE"
    chmod 600 "$BACKUP_FILE"
    echo "Backup: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"
fi

step "Sync API code and migrations"
mkdir -p "$API_BACKEND"
for dir in bin config public src; do
    rsync -a --delete "$REPO_DIR/backend/$dir/" "$API_BACKEND/$dir/"
done
rsync -a "$REPO_DIR/backend/composer.json" "$REPO_DIR/backend/composer.lock" "$REPO_DIR/backend/README.md" "$API_BACKEND/"
rsync -a --delete "$REPO_DIR/database/" "$API_RUNTIME_DIR/database/"

step "Install API dependencies (composer.lock, no dev)"
(cd "$API_BACKEND" && "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader)
[[ -d "$API_BACKEND/vendor/paxofi-technologies/paxofi-core-framework" ]] || fail "PCF not installed under vendor/"

step "Apply database migrations"
if [[ -n "$BASELINE" ]]; then
    (cd "$API_BACKEND" && "$PHP_BIN" bin/migrate.php --baseline="$BASELINE")
fi
(cd "$API_BACKEND" && "$PHP_BIN" bin/migrate.php)
(cd "$API_BACKEND" && "$PHP_BIN" bin/migrate.php --status)

if [[ "$SKIP_FRONTEND" -eq 0 ]]; then
    step "Sync and build frontend"
    [[ -f "$FRONTEND_APP_DIR/.env.production" ]] || fail "Missing $FRONTEND_APP_DIR/.env.production with NEXT_PUBLIC_SITE_URL and NEXT_PUBLIC_API_URL (read at build time)"
    mkdir -p "$FRONTEND_APP_DIR"
    rsync -a --delete \
        --exclude node_modules/ --exclude .next/ --exclude tmp/ --exclude '.env*' --exclude '*.log' --exclude .htaccess \
        "$REPO_DIR/frontend/" "$FRONTEND_APP_DIR/"
    if [[ -f "$NODE_VENV" ]]; then
        # shellcheck disable=SC1090
        source "$NODE_VENV"
    fi
    (cd "$FRONTEND_APP_DIR" && npm ci --no-audit --no-fund && npm run build)
    mkdir -p "$FRONTEND_APP_DIR/tmp" && touch "$FRONTEND_APP_DIR/tmp/restart.txt"
    echo "Passenger restart requested."
fi

step "Smoke tests"
env_value() { sed -n "s/^$2=//p" "$1" 2>/dev/null | tail -1 | tr -d '"'"'"; }
API_URL="${API_URL:-$(env_value "$FRONTEND_APP_DIR/.env.production" NEXT_PUBLIC_API_URL)}"
SITE_URL="${SITE_URL:-$(env_value "$FRONTEND_APP_DIR/.env.production" NEXT_PUBLIC_SITE_URL)}"
[[ -n "$API_URL" && -n "$SITE_URL" ]] || fail "Set API_URL and SITE_URL (or NEXT_PUBLIC_* in $FRONTEND_APP_DIR/.env.production) for smoke tests"
API_URL="${API_URL%/}"; SITE_URL="${SITE_URL%/}"

check() {
    local name="$1" expected="$2"; shift 2
    local status
    status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$@" || true)"
    if [[ "$status" == "$expected" ]]; then echo "PASS $name ($status)"; else echo "FAIL $name (got $status, expected $expected)"; SMOKE_FAILED=1; fi
}
SMOKE_FAILED=0
check "API health" 200 "$API_URL/health"
check "API readiness (database)" 200 "$API_URL/readiness"
check "Products from database" 200 "$API_URL/products"
check "CORS preflight from $SITE_URL" 204 -X OPTIONS "$API_URL/forms/contact/submit" \
    -H "Origin: $SITE_URL" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type"
check "Frontend home" 200 "$SITE_URL/"
check "Frontend contact" 200 "$SITE_URL/contact"

[[ "$SMOKE_FAILED" -eq 0 ]] || fail "smoke tests failed for commit $COMMIT (backup: ${BACKUP_FILE:-none})"

printf '\nDeployed %s successfully.\nFinal manual check: submit the live contact form and confirm a new row in `enquiries`\nand a matching `enquiry.submitted` row in `audit_events` (same request_id).\n' "$COMMIT"
