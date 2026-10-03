#!/usr/bin/env bash
# Paxofi Corporate Website — build cPanel upload packages (CW-OPS-004)
#
# Produces, in $OUT_DIR (default: dist/<version>):
#   paxofi-api-runtime-<version>.zip        API with vendor/ (PCF included): no Composer on cPanel
#   paxofi-corporate-website-<version>.zip  prebuilt Next.js app: no npm install/build on cPanel
#   database-upgrade-<version>.sql          phpMyAdmin import: migrations + migration history
#   database-install-<version>.sql          phpMyAdmin import into a NEW, EMPTY database (staging, D-013)
#   DEPLOYMENT-GUIDE.md                     step-by-step upload guide
#   SHA256SUMS
#
# Usage (from the repository root, on a machine with PHP 8.4+, Composer with
# read access to the private PCF repository, Node.js 22, zip):
#   SITE_URL=https://corporate.paxofi.com API_URL=https://api.paxofi.com/api/v1 ops/package-release.sh
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE_URL="${SITE_URL:-https://corporate.paxofi.com}"
API_URL="${API_URL:-https://api.paxofi.com/api/v1}"
VERSION="${VERSION:-$(date -u +%Y%m%d)-$(git -C "$ROOT" rev-parse --short HEAD)}"
OUT_DIR="${OUT_DIR:-$ROOT/dist/$VERSION}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
# Extra flags, e.g. --ignore-platform-req=php when packaging on an older local PHP.
COMPOSER_FLAGS="${COMPOSER_FLAGS:-}"
# The production database was built by hand with 001–003 (see database/README.md).
BASELINE="${BASELINE:-003}"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nPACKAGING FAILED: %s\n' "$*" >&2; exit 1; }

[[ -z "$(git -C "$ROOT" status --porcelain)" ]] || [[ "${ALLOW_DIRTY:-0}" == 1 ]] || fail "working tree has uncommitted changes (set ALLOW_DIRTY=1 to override)"
COMMIT="$(git -C "$ROOT" rev-parse HEAD)"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR"/*.zip "$OUT_DIR"/*.sql "$OUT_DIR"/SHA256SUMS

# Export the committed tree only (never local or ignored files such as .env).
git -C "$ROOT" archive "$COMMIT" | tar -x -C "$WORK"
mkdir -p "$WORK/src" && mv "$WORK"/backend "$WORK"/frontend "$WORK"/database "$WORK"/ops "$WORK/src/"

step "API package (composer install --no-dev from composer.lock)"
API="$WORK/pkg-api/paxofi-api-runtime"
mkdir -p "$API"
cp -a "$WORK/src/backend" "$API/backend"
cp -a "$WORK/src/database" "$API/database"
rm -rf "$API/backend/tests" "$API/backend/phpunit.xml"
cp "$WORK/src/ops/api.env.example" "$API/backend/.env.example"
printf '%s\n' "$VERSION ($COMMIT)" > "$API/RELEASE.txt"
# shellcheck disable=SC2086
(cd "$API/backend" && "$COMPOSER_BIN" install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader $COMPOSER_FLAGS)
[[ -d "$API/backend/vendor/paxofi-technologies/paxofi-core-framework" ]] || fail "PCF missing from vendor/"
# Composer falls back to git checkouts when it cannot download archives; never
# ship repository metadata to the server.
find "$API/backend/vendor" -name .git -prune -exec rm -rf {} +
[[ ! -e "$API/backend/.env" ]] || fail "refusing to package a .env file"
(cd "$WORK/pkg-api" && zip -qr -X "$OUT_DIR/paxofi-api-runtime-$VERSION.zip" paxofi-api-runtime)

step "Frontend package (Next.js standalone build)"
FE_SRC="$WORK/src/frontend"
(cd "$FE_SRC" && npm ci --no-audit --no-fund >/dev/null \
    && NEXT_TELEMETRY_DISABLED=1 NEXT_OUTPUT_STANDALONE=1 NEXT_PUBLIC_SITE_URL="$SITE_URL" NEXT_PUBLIC_API_URL="$API_URL" npm run build >/dev/null)
FE="$WORK/pkg-fe/paxofi-corporate-website"
mkdir -p "$FE"
# CloudLinux's Node.js selector forbids a real node_modules/ in the application
# root (it manages that path as a symlink), so the self-contained server and its
# traced dependencies live in standalone/ and app.js starts it.
cp -a "$FE_SRC/.next/standalone" "$FE/standalone"
cp -a "$FE_SRC/.next/static" "$FE/standalone/.next/static"
if [[ -d "$FE_SRC/public" ]]; then cp -a "$FE_SRC/public" "$FE/standalone/public"; fi
# Served at /release.txt so anyone can confirm which release is live.
mkdir -p "$FE/standalone/public"
printf '%s\n' "$VERSION ($COMMIT)" > "$FE/standalone/public/release.txt"
cat > "$FE/app.js" <<'EOF'
// Paxofi Corporate Website — cPanel Application Manager startup file.
// Starts the prebuilt Next.js standalone server. Runtime settings:
//   API_BASE_URL  API base used by the contact form, e.g. https://api.paxofi.com/api/v1
process.env.HOSTNAME = process.env.HOSTNAME || "0.0.0.0";
process.env.NODE_ENV = "production";
require("./standalone/server.js");
EOF
cat > "$FE/package.json" <<EOF
{
  "name": "paxofi-corporate-website",
  "version": "$VERSION",
  "private": true,
  "description": "Prebuilt package: do not run npm install. Start with app.js.",
  "scripts": { "start": "node app.js" }
}
EOF
printf '%s\n' "$VERSION ($COMMIT)" "SITE_URL=$SITE_URL" "API_URL (default)=$API_URL" > "$FE/RELEASE.txt"
(cd "$WORK/pkg-fe" && zip -qr -X -y "$OUT_DIR/paxofi-corporate-website-$VERSION.zip" paxofi-corporate-website)

step "phpMyAdmin database upgrade script"
SQL="$OUT_DIR/database-upgrade-$VERSION.sql"
{
    echo "-- Paxofi Corporate Website — database upgrade $VERSION ($COMMIT)"
    echo "-- Import in phpMyAdmin with the website database selected."
    echo "-- Safe to import more than once. Take an Export (backup) first."
    echo "-- Records migration history in schema_migrations, so the database stays"
    echo "-- compatible with backend/bin/migrate.php."
    echo
    echo "CREATE TABLE IF NOT EXISTS schema_migrations ("
    echo "    version VARCHAR(191) NOT NULL PRIMARY KEY,"
    echo "    checksum CHAR(64) NOT NULL,"
    echo "    baseline TINYINT(1) NOT NULL DEFAULT 0,"
    echo "    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    for file in "$WORK"/src/database/[0-9][0-9][0-9]_*.sql; do
        version="$(basename "$file" .sql)"
        checksum="$(sha256sum "$file" | cut -d' ' -f1)"
        if [[ "${version:0:3}" > "$BASELINE" ]]; then
            echo
            echo "-- ---------------------------------------------------------------- $version"
            cat "$file"
            echo
            echo "INSERT IGNORE INTO schema_migrations (version, checksum, baseline) VALUES ('$version', '$checksum', 0);"
        else
            echo
            echo "-- $version: already applied in production before migrations were tracked (baseline)."
            echo "INSERT IGNORE INTO schema_migrations (version, checksum, baseline) VALUES ('$version', '$checksum', 1);"
        fi
    done
    echo
    echo "-- End of upgrade $VERSION."
} > "$SQL"

step "phpMyAdmin install script for a new, empty database (staging copy, D-013)"
INSTALL="$OUT_DIR/database-install-$VERSION.sql"
{
    echo "-- Paxofi Corporate Website — database install $VERSION ($COMMIT)"
    echo "-- ONLY for a new, EMPTY database (the staging copy). Import it once, in"
    echo "-- phpMyAdmin with that database selected. For the live database use"
    echo "-- database-upgrade-$VERSION.sql instead."
    echo
    echo "SET SESSION default_storage_engine = InnoDB;"
    echo "CREATE TABLE IF NOT EXISTS schema_migrations ("
    echo "    version VARCHAR(191) NOT NULL PRIMARY KEY,"
    echo "    checksum CHAR(64) NOT NULL,"
    echo "    baseline TINYINT(1) NOT NULL DEFAULT 0,"
    echo "    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    for file in "$WORK"/src/database/[0-9][0-9][0-9]_*.sql; do
        version="$(basename "$file" .sql)"
        checksum="$(sha256sum "$file" | cut -d' ' -f1)"
        echo
        echo "-- ---------------------------------------------------------------- $version"
        cat "$file"
        echo
        echo "INSERT INTO schema_migrations (version, checksum, baseline) VALUES ('$version', '$checksum', 0);"
    done
    echo
    echo "-- End of install $VERSION. Later releases: import their database-upgrade file."
} > "$INSTALL"

step "Guide and checksums"
sed -e "s|{{VERSION}}|$VERSION|g" -e "s|{{SITE_URL}}|$SITE_URL|g" -e "s|{{API_URL}}|$API_URL|g" \
    "$ROOT/ops/DEPLOYMENT-GUIDE.template.md" > "$OUT_DIR/DEPLOYMENT-GUIDE.md"
(cd "$OUT_DIR" && sha256sum ./*.zip ./*.sql > SHA256SUMS)
ls -lh "$OUT_DIR"
printf '\nPackaged %s (%s) into %s\n' "$VERSION" "$COMMIT" "$OUT_DIR"
