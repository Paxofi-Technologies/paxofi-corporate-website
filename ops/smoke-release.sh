#!/usr/bin/env bash
# Checks a release package before it is sent for deployment:
#   1. database: the previous release's install file + this upgrade, applied
#      twice (it must be safe to import again), gives the same columns and
#      foreign keys as this release's fresh install file;
#   2. runtime: the API zip (php -S) and the website zip (node app.js) start
#      against that database, the main pages answer 200, the staff API refuses
#      a request without sign-in, and a redirect (D-025) answers 301.
#
#   bash ops/smoke-release.sh dist/release26 dist/release25
#
# The previous release folder is optional (without it only the fresh install
# is checked). Needs a local MariaDB/MySQL, PHP 8.3+, Node 22, curl and unzip.
# Database access: MYSQL_HOST (127.0.0.1), MYSQL_PORT (3306), MYSQL_USER
# (root) and MYSQL_PWD (read by the mysql client). Test databases
# smoke_upgrade and smoke_fresh are created and dropped again; no real data or
# secrets are used.
set -euo pipefail

NEW="$(cd "${1:?usage: smoke-release.sh <release folder> [previous release folder]}" && pwd)"
PREV="${2:+$(cd "$2" && pwd)}"
HOST="${MYSQL_HOST:-127.0.0.1}"; PORT="${MYSQL_PORT:-3306}"; USER_="${MYSQL_USER:-root}"
API_PORT="${SMOKE_API_PORT:-8930}"; WEB_PORT="${SMOKE_WEB_PORT:-3930}"
mysql_() { mysql -h"$HOST" -P"$PORT" -u"$USER_" "$@"; }
one() { ls "$1"/$2 2>/dev/null | head -1; }

UPGRADE="$(one "$NEW" 'database-upgrade-*.sql')"; INSTALL="$(one "$NEW" 'database-install-*.sql')"
API_ZIP="$(one "$NEW" 'paxofi-api-runtime-*.zip')"; WEB_ZIP="$(one "$NEW" 'paxofi-corporate-website-*.zip')"
for f in "$UPGRADE" "$INSTALL" "$API_ZIP" "$WEB_ZIP"; do [ -n "$f" ] || { echo "Release files missing in $NEW: run package-release.sh first" >&2; exit 1; }; done

WORK="$(mktemp -d)"; PIDS=()
cleanup() {
  for pid in "${PIDS[@]:-}"; do [ -n "$pid" ] && kill "$pid" 2>/dev/null || true; done
  mysql_ -e 'DROP DATABASE IF EXISTS smoke_upgrade; DROP DATABASE IF EXISTS smoke_fresh' 2>/dev/null || true
  rm -rf "$WORK"
}
trap cleanup EXIT
FAILED=0
pass() { echo "  ok   $*"; }
fail() { echo "  FAIL $*"; FAILED=1; }

echo "== Database"
for db in smoke_upgrade smoke_fresh; do mysql_ -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; done
if [ -n "$PREV" ]; then
  PREV_INSTALL="$(one "$PREV" 'database-install-*.sql')"
  [ -n "$PREV_INSTALL" ] || { echo "No database-install-*.sql in $PREV" >&2; exit 1; }
  mysql_ smoke_upgrade < "$PREV_INSTALL"
else
  mysql_ smoke_upgrade < "$INSTALL"
fi
mysql_ smoke_upgrade < "$UPGRADE" && mysql_ smoke_upgrade < "$UPGRADE" && pass "upgrade applied twice${PREV:+ on top of $(basename "$PREV")}"
mysql_ smoke_fresh < "$INSTALL" && pass "fresh install"
columns="SELECT table_name, column_name, column_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = '%s' ORDER BY 1, 2"
keys="SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '%s'"
a="$(mysql_ -N -e "$(printf "$columns" smoke_upgrade)")"; b="$(mysql_ -N -e "$(printf "$columns" smoke_fresh)")"
[ -n "$a" ] && [ "$a" = "$b" ] && pass "upgraded and fresh schemas match ($(echo "$a" | wc -l) columns)" || fail "upgraded and fresh schemas differ"
ka="$(mysql_ -N -e "$(printf "$keys" smoke_upgrade)")"; kb="$(mysql_ -N -e "$(printf "$keys" smoke_fresh)")"
[ "$ka" = "$kb" ] && pass "foreign keys match ($ka)" || fail "foreign keys differ ($ka upgraded, $kb fresh)"

echo "== Runtime"
unzip -q "$API_ZIP" -d "$WORK/api"; unzip -q "$WEB_ZIP" -d "$WORK/web"
API_DIR="$WORK/api/paxofi-api-runtime/backend"; WEB_DIR="$WORK/web/paxofi-corporate-website"
cat > "$API_DIR/.env" <<ENV
APP_ENV=production
APP_DEBUG=false
DB_HOST=$HOST
DB_PORT=$PORT
DB_DATABASE=smoke_upgrade
DB_USERNAME=$USER_
DB_PASSWORD=${MYSQL_PWD:-}
CORS_ALLOWED_ORIGINS=http://127.0.0.1:$WEB_PORT
ENV
mysql_ smoke_upgrade -e "INSERT INTO redirects (id, from_path, to_path, note) VALUES ('5a0e0000-0000-4000-8000-000000000001', '/smoke-old-page', '/about', 'smoke test')"
(cd "$API_DIR" && php -S "127.0.0.1:$API_PORT" -t public > "$WORK/api.log" 2>&1) & PIDS+=($!)
(cd "$WEB_DIR" && PORT="$WEB_PORT" HOSTNAME=127.0.0.1 API_BASE_URL="http://127.0.0.1:$API_PORT/api/v1" node app.js > "$WORK/web.log" 2>&1) & PIDS+=($!)
for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$WEB_PORT/" && break; sleep 1; done

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
curl -s "http://127.0.0.1:$API_PORT/api/v1/health" | grep -q '"status":"ok"' && pass "API health" || fail "API health"
c="$(code "http://127.0.0.1:$API_PORT/api/v1/admin/session")"; [ "$c" = 401 ] && pass "staff API refuses without sign-in (401)" || fail "staff API answered $c without sign-in"
for path in / /about /services /products /industries /insights /resources /careers /contact /privacy /admin/login /sitemap.xml; do
  c="$(code "http://127.0.0.1:$WEB_PORT$path")"; [ "$c" = 200 ] && pass "$path 200" || fail "$path answered $c"
done
r="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "http://127.0.0.1:$WEB_PORT/smoke-old-page?utm_source=smoke")"
[[ "$r" == "301 "*"/about?utm_source=smoke" ]] && pass "redirect /smoke-old-page -> /about (301, tags kept)" || fail "redirect answered: $r"

if [ "$FAILED" -ne 0 ]; then
  echo "== Smoke test FAILED. Last lines of the logs:"; tail -n 20 "$WORK/api.log" "$WORK/web.log"; exit 1
fi
echo "== Smoke test passed: $(basename "$NEW")"
