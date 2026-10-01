#!/usr/bin/env bash
# Paxofi Corporate Website — cPanel deployment (CW-OPS-004 / CW-UAT-008)
#
# Deploys the `main` branch from the cPanel Git clone into the two runtime
# directories, in a fixed order:
#   1. update the Git clone (fast-forward only)
#   2. stage the API (composer install) and frontend (npm ci + build)
#      in separate directories; nothing live changes yet
#   3. back up the database, then apply database migrations
#   4. promote the staged API and frontend, restart Passenger
#   5. smoke-test (health, readiness, catalogue, CORS, pages)
#
# Run from cPanel Terminal:
#   bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh
#
# First deployment onto the existing production database only (001–003 were
# applied by hand before migrations were tracked):
#   bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh --baseline 003
#
# Every path and binary can be overridden with the environment variables
# below. Live .env files, .htaccess, .user.ini and php.ini are never overwritten.
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

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nDEPLOY FAILED: %s\n' "$*" >&2; exit 1; }

# This script lives inside the clone it updates, and bash reads scripts
# incrementally. Update the clone first, then re-run the fresh copy, so a
# deployment always executes the deploy script of the commit it deploys.
if [[ "${PAXOFI_DEPLOY_UPDATED:-0}" != 1 && " $* " != *" -h "* && " $* " != *" --help "* ]]; then
    [[ -d "$REPO_DIR/.git" ]] || fail "Git clone not found at $REPO_DIR"
    step "Update Git clone ($BRANCH, fast-forward only)"
    git -C "$REPO_DIR" fetch --quiet origin "$BRANCH"
    git -C "$REPO_DIR" checkout --quiet "$BRANCH"
    git -C "$REPO_DIR" merge --ff-only --quiet "origin/$BRANCH"
    PAXOFI_DEPLOY_UPDATED=1 exec bash "$REPO_DIR/ops/deploy-cpanel.sh" "$@"
fi

main() {
BASELINE=""
SKIP_FRONTEND=0
SKIP_BACKUP=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --baseline) BASELINE="${2:?--baseline needs a migration number, e.g. 003}"; shift 2 ;;
        --skip-frontend) SKIP_FRONTEND=1; shift ;;
        --skip-backup) SKIP_BACKUP=1; shift ;;
        -h|--help) sed -n '2,/^set -euo pipefail/{/^set -euo/!p}' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done


API_BACKEND="$API_RUNTIME_DIR/backend"

step "Preflight"
[[ -d "$REPO_DIR/.git" ]] || fail "Git clone not found at $REPO_DIR"
[[ -f "$API_BACKEND/.env" ]] || fail "Missing $API_BACKEND/.env (copy .env.example from the repo and fill in production values)"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || fail "$PHP_BIN is not PHP 8.4+ (set PHP_BIN)"
"$PHP_BIN" -m | grep -qi '^pdo_mysql$' || fail "pdo_mysql extension missing for $PHP_BIN"
echo "PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')  repo: $REPO_DIR  api: $API_BACKEND  frontend: $FRONTEND_APP_DIR"

COMMIT="$(git -C "$REPO_DIR" rev-parse --short HEAD)"
echo "Deploying commit $COMMIT"

API_STAGE="$API_RUNTIME_DIR/.staging"
FRONTEND_STAGE="${FRONTEND_STAGE:-$HOME/.paxofi-frontend-staging}"
# Never synced over the live runtime: secrets, host-managed PHP/handler config, logs.
PROTECT=(--exclude .env --exclude '.env.*' --exclude .htaccess --exclude .user.ini --exclude php.ini --exclude error_log --exclude '*.log')
cleanup() { rm -f "$API_STAGE/backend/.env" "${DEFAULTS_FILE:-}"; }
trap cleanup EXIT

# Everything below is prepared in staging directories first. The live API
# and frontend only change in the "Promote" steps, after dependencies,
# backup and migrations have all succeeded.

step "Stage API (code + composer install from composer.lock, no dev)"
mkdir -p "$API_STAGE"
rsync -a --delete --exclude vendor/ --exclude tests/ --exclude phpunit.xml --exclude .phpunit.cache/ "${PROTECT[@]}" \
    "$REPO_DIR/backend/" "$API_STAGE/backend/"
rsync -a --delete "$REPO_DIR/database/" "$API_STAGE/database/"
install -m 600 "$API_BACKEND/.env" "$API_STAGE/backend/.env"
(cd "$API_STAGE/backend" && "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader)
[[ -d "$API_STAGE/backend/vendor/paxofi-technologies/paxofi-core-framework" ]] || fail "PCF not installed under vendor/"

if [[ "$SKIP_FRONTEND" -eq 0 ]]; then
    step "Stage frontend (npm ci + next build)"
    [[ -f "$FRONTEND_APP_DIR/.env.production" ]] || fail "Missing $FRONTEND_APP_DIR/.env.production with NEXT_PUBLIC_SITE_URL and NEXT_PUBLIC_API_URL (read at build time)"
    mkdir -p "$FRONTEND_STAGE"
    rsync -a --delete --exclude node_modules/ --exclude .next/ "${PROTECT[@]}" "$REPO_DIR/frontend/" "$FRONTEND_STAGE/"
    install -m 600 "$FRONTEND_APP_DIR/.env.production" "$FRONTEND_STAGE/.env.production"
    if [[ -f "$NODE_VENV" ]]; then
        # shellcheck disable=SC1090
        source "$NODE_VENV"
    fi
    (cd "$FRONTEND_STAGE" && npm ci --no-audit --no-fund && npm run build)
fi

if [[ "$SKIP_BACKUP" -eq 0 ]]; then
    step "Back up database"
    [[ -n "$MYSQLDUMP_BIN" ]] || fail "mariadb-dump/mysqldump not found (set MYSQLDUMP_BIN, or --skip-backup after taking a backup in phpMyAdmin)"
    mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
    DEFAULTS_FILE="$(mktemp)"; chmod 600 "$DEFAULTS_FILE"
    # Option-file values are written quoted and escaped by PHP, so passwords
    # containing #, ;, quotes or backslashes are passed through intact.
    DB_NAME="$(cd "$API_STAGE/backend" && "$PHP_BIN" -r '
        require "vendor/autoload.php";
        $v = (new Paxofi\Core\Configuration\EnvLoader())->load(".env");
        $q = static fn (string $s): string => "\"" . addcslashes($s, "\\\"") . "\"";
        file_put_contents($argv[1], sprintf("[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $q($v["DB_HOST"] ?? "127.0.0.1"), $q($v["DB_PORT"] ?? "3306"), $q($v["DB_USERNAME"] ?? ""), $q($v["DB_PASSWORD"] ?? "")));
        echo $v["DB_DATABASE"] ?? "";' "$DEFAULTS_FILE")"
    [[ -n "$DB_NAME" ]] || fail "DB_DATABASE is not set in $API_BACKEND/.env"
    BACKUP_FILE="$BACKUP_DIR/$DB_NAME-$(date -u +%Y%m%dT%H%M%SZ)-before-$COMMIT.sql.gz"
    "$MYSQLDUMP_BIN" --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$BACKUP_FILE"
    chmod 600 "$BACKUP_FILE"
    echo "Backup: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"
fi

step "Apply database migrations"
if [[ -n "$BASELINE" ]]; then
    (cd "$API_STAGE/backend" && "$PHP_BIN" bin/migrate.php --baseline="$BASELINE")
fi
(cd "$API_STAGE/backend" && "$PHP_BIN" bin/migrate.php)
(cd "$API_STAGE/backend" && "$PHP_BIN" bin/migrate.php --status)

step "Promote API"
mkdir -p "$API_BACKEND/public"
rsync -a --delete "${PROTECT[@]}" "$API_STAGE/backend/" "$API_BACKEND/"
rsync -a --delete "$API_STAGE/database/" "$API_RUNTIME_DIR/database/"
if [[ ! -f "$API_BACKEND/public/.htaccess" ]]; then
    install -m 644 "$REPO_DIR/backend/public/.htaccess" "$API_BACKEND/public/.htaccess"
    echo "Installed API routing .htaccess."
elif ! grep -q 'index.php' "$API_BACKEND/public/.htaccess"; then
    echo "WARNING: $API_BACKEND/public/.htaccess has no rewrite to index.php; compare with backend/public/.htaccess in the repo."
fi

if [[ "$SKIP_FRONTEND" -eq 0 ]]; then
    step "Promote frontend and restart Passenger"
    mkdir -p "$FRONTEND_APP_DIR/tmp"
    rsync -a --delete --exclude tmp/ "${PROTECT[@]}" "$FRONTEND_STAGE/" "$FRONTEND_APP_DIR/"
    touch "$FRONTEND_APP_DIR/tmp/restart.txt"
    echo "Passenger restart requested."
fi

step "Smoke tests"
env_value() { sed -n "s/^$2=//p" "$1" 2>/dev/null | tail -1 | tr -d '"'"'"; }
API_URL="${API_URL:-$(env_value "$FRONTEND_APP_DIR/.env.production" NEXT_PUBLIC_API_URL)}"
SITE_URL="${SITE_URL:-$(env_value "$FRONTEND_APP_DIR/.env.production" NEXT_PUBLIC_SITE_URL)}"
[[ -n "$API_URL" && -n "$SITE_URL" ]] || fail "Set API_URL and SITE_URL (or NEXT_PUBLIC_* in $FRONTEND_APP_DIR/.env.production) for smoke tests"
API_URL="${API_URL%/}"; SITE_URL="${SITE_URL%/}"

# Retries for up to ~60s: Passenger boots the app lazily after a restart.
check() {
    local name="$1" expected="$2"; shift 2
    local status=""
    for _ in $(seq 1 "${SMOKE_ATTEMPTS:-12}"); do
        status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$@" 2>/dev/null || true)"
        [[ "$status" == "$expected" ]] && break
        sleep 5
    done
    if [[ "$status" == "$expected" ]]; then echo "PASS $name ($status)"; else echo "FAIL $name (got ${status:-no response}, expected $expected)"; SMOKE_FAILED=1; fi
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
}

main "$@"
