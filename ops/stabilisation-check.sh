#!/usr/bin/env bash
# Outside-in check of the live sites for the stabilisation and operational
# reviews (CW-OPS2-003/004, docs/STABILISATION-REVIEW.md). Read-only: it only
# sends GET requests and opens TLS connections, like a visitor would.
#
#   bash ops/stabilisation-check.sh            # live
#   CORPORATE_URL=https://staging.corporate.paxofi.com API_URL=https://api-staging.paxofi.com \
#     CAREERS_URL= bash ops/stabilisation-check.sh   # staging (needs STAGING_AUTH=user:password)
#
# Checks: both sites report the same release; API health and readiness; the
# main pages answer 200; security headers; TLS certificates valid for more than
# 14 days; homepage response time. Prints one line per check and exits 1 if
# any check fails. Needs bash, curl and openssl.
set -uo pipefail

CORPORATE="${CORPORATE_URL-https://corporate.paxofi.com}"
CAREERS="${CAREERS_URL-https://careers.paxofi.com}"
API="${API_URL-https://api.paxofi.com}"
AUTH=(); [ -n "${STAGING_AUTH:-}" ] && AUTH=(-u "$STAGING_AUTH")
CERT_WARN_DAYS="${CERT_WARN_DAYS:-14}"

FAILED=0; WARNED=0
pass() { echo "  ok   $*"; }
warn() { echo "  WARN $*"; WARNED=1; }
fail() { echo "  FAIL $*"; FAILED=1; }
get() { curl -sS -m 20 "${AUTH[@]}" "$@"; }
status() { get -o /dev/null -w '%{http_code}' "$@" 2>/dev/null || echo 000; }

echo "Stabilisation check — $(date -u '+%Y-%m-%d %H:%M UTC')"

echo "Releases"
corp_release="$(get "$CORPORATE/release.txt" 2>/dev/null | head -1)"
if [ -n "$corp_release" ] && [ "$(status "$CORPORATE/release.txt")" = 200 ]; then
  pass "corporate release: $corp_release"
else
  fail "corporate /release.txt not served (RB-3)"
fi
if [ -n "$CAREERS" ]; then
  careers_release="$(get "$CAREERS/release.txt" 2>/dev/null | head -1)"
  if [ -z "$careers_release" ]; then
    fail "careers /release.txt not served (RB-3)"
  elif [ "$careers_release" = "$corp_release" ]; then
    pass "careers release matches"
  else
    warn "careers release differs: $careers_release (both apps should run the same release, D-018)"
  fi
fi

echo "API"
health="$(get "$API/api/v1/health" 2>/dev/null)"
case "$health" in *'"status":"ok"'*) pass "health ok" ;; *) fail "health: ${health:-no answer} (RB-5)" ;; esac
readiness="$(get "$API/api/v1/readiness" 2>/dev/null)"
case "$readiness" in *'"database":true'*) pass "readiness: database true" ;; *) fail "readiness: ${readiness:-no answer} (RB-5)" ;; esac
code="$(status "$API/api/v1/admin/session")"
[ "$code" = 401 ] && pass "staff API refuses requests without sign-in (401)" || fail "staff API without sign-in answered $code, expected 401 (RB-8)"

echo "Pages"
for path in / /about /services /products /industries /insights /resources /careers /contact /privacy /sitemap.xml /robots.txt; do
  code="$(status "$CORPORATE$path")"
  [ "$code" = 200 ] && pass "corporate $path" || fail "corporate $path answered $code"
done
if [ -n "$CAREERS" ]; then
  code="$(status "$CAREERS/")"
  [ "$code" = 200 ] && pass "careers /" || fail "careers / answered $code"
fi

echo "Security headers (corporate /)"
headers="$(get -o /dev/null -D - "$CORPORATE/" 2>/dev/null | tr -d '\r' | tr '[:upper:]' '[:lower:]')"
for h in strict-transport-security content-security-policy x-content-type-options x-frame-options referrer-policy; do
  grep -q "^$h:" <<<"$headers" && pass "$h" || fail "$h missing"
done

echo "TLS certificates"
for url in "$CORPORATE" "$CAREERS" "$API"; do
  [ -n "$url" ] || continue
  host="${url#https://}"; host="${host%%/*}"
  end="$(echo | openssl s_client -connect "$host:443" -servername "$host" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)"
  if [ -z "$end" ]; then fail "$host: no certificate read (RB-6)"; continue; fi
  days=$(( ( $(date -u -d "$end" +%s) - $(date -u +%s) ) / 86400 ))
  if [ "$days" -lt 0 ]; then fail "$host: certificate expired $end (RB-6)"
  elif [ "$days" -lt "$CERT_WARN_DAYS" ]; then warn "$host: certificate expires in $days days (AutoSSL should renew; RB-6)"
  else pass "$host: certificate valid $days more days"; fi
done

echo "Response time (corporate /, 5 requests)"
total=0
for _ in 1 2 3 4 5; do
  t="$(get -o /dev/null -w '%{time_total}' "$CORPORATE/" 2>/dev/null || echo 99)"
  total="$(awk -v a="$total" -v b="$t" 'BEGIN { print a + b }')"
done
avg="$(awk -v t="$total" 'BEGIN { printf "%.2f", t / 5 }')"
if awk -v a="$avg" 'BEGIN { exit !(a <= 1.5) }'; then pass "average ${avg}s (full HTML download; LCP is measured separately)"
else warn "average ${avg}s, above 1.5 s: check the host's load and run PageSpeed Insights (D-007: LCP ≤ 2.5 s)"; fi

echo
if [ "$FAILED" = 1 ]; then echo "Result: FAIL — see the runbook named on each failing line."; exit 1; fi
if [ "$WARNED" = 1 ]; then echo "Result: passed with warnings."; else echo "Result: all checks passed."; fi
