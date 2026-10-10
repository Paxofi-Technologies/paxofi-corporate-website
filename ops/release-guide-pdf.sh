#!/usr/bin/env bash
# Turns a release's DEPLOYMENT-GUIDE.md into DEPLOYMENT-GUIDE-<version>.pdf
# (A4, page numbers) and adds it to SHA256SUMS. Run after package-release.sh:
#
#   bash ops/release-guide-pdf.sh dist/release25
#
# Needs: python3 with the "markdown" package (pip install markdown) and the
# frontend's Playwright (npm ci in frontend/). Chromium is Playwright's own, or
# set PLAYWRIGHT_CHROMIUM_PATH to a Chrome/Chromium binary.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIR="$(cd "${1:?usage: release-guide-pdf.sh <release folder>}" && pwd)"
GUIDE="$DIR/DEPLOYMENT-GUIDE.md"
[ -f "$GUIDE" ] || { echo "No DEPLOYMENT-GUIDE.md in $DIR" >&2; exit 1; }
UPGRADE="$(ls "$DIR"/database-upgrade-*.sql 2>/dev/null | head -1)"
[ -n "$UPGRADE" ] || { echo "No database-upgrade-*.sql in $DIR: run package-release.sh first" >&2; exit 1; }
VERSION="$(basename "$UPGRADE" .sql)"; VERSION="${VERSION#database-upgrade-}"
PDF="$DIR/DEPLOYMENT-GUIDE-$VERSION.pdf"
python3 -c 'import markdown' 2>/dev/null || { echo "Python package 'markdown' is missing: pip install markdown" >&2; exit 1; }
[ -d "$ROOT/frontend/node_modules/playwright" ] || { echo "Playwright is missing: run npm ci in frontend/" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

python3 - "$GUIDE" "$WORK/guide.html" <<'PY'
import sys, markdown
source, target = sys.argv[1], sys.argv[2]
css = """
@page{size:A4;margin:18mm 16mm 18mm 16mm}
body{font-family:'DejaVu Sans',Arial,sans-serif;font-size:10.5pt;line-height:1.5;color:#1a1a1a}
h1{font-size:19pt;color:#0b3d6e;border-bottom:3px solid #0b3d6e;padding-bottom:6px;margin-top:0}
h2{font-size:14pt;color:#0b3d6e;margin-top:22px;border-bottom:1px solid #cfd8e3;padding-bottom:3px;page-break-after:avoid}
h3{font-size:12pt;color:#0b3d6e;page-break-after:avoid}
code{font-family:'DejaVu Sans Mono',monospace;font-size:9pt;background:#eef2f7;padding:1px 4px;border-radius:3px;word-break:break-all}
pre{background:#f4f6f9;border:1px solid #d9e0e8;padding:10px;border-radius:4px;page-break-inside:avoid}
pre code{background:none;padding:0;white-space:pre-wrap;word-break:break-all}
table{border-collapse:collapse;width:100%;margin:10px 0;font-size:9.5pt;page-break-inside:avoid}
th{background:#0b3d6e;color:#fff;text-align:left}
th,td{border:1px solid #c7d0db;padding:6px 8px;vertical-align:top}
tr:nth-child(even) td{background:#f6f8fb}
li{margin:3px 0}
hr{border:none;border-top:1px solid #cfd8e3;margin:18px 0}
"""
body = markdown.markdown(open(source, encoding="utf-8").read(), extensions=["tables", "fenced_code"])
open(target, "w", encoding="utf-8").write(
    "<!doctype html><html><head><meta charset='utf-8'><title>Paxofi Deployment Guide</title><style>"
    + css + "</style></head><body>" + body + "</body></html>"
)
PY

# Run from frontend/ so Node finds its Playwright.
(cd "$ROOT/frontend" && HTML="$WORK/guide.html" PDF="$PDF" VERSION="$VERSION" node --input-type=module -e '
import { chromium } from "playwright";
const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {});
const page = await browser.newPage();
await page.goto("file://" + process.env.HTML);
await page.pdf({
  path: process.env.PDF,
  format: "A4",
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: "<span></span>",
  footerTemplate: `<div style="font-size:8px;width:100%;text-align:center;color:#777">Paxofi Corporate Website — Deployment Guide ${process.env.VERSION} — page <span class="pageNumber"></span> of <span class="totalPages"></span></div>`,
  margin: { top: "18mm", bottom: "18mm", left: "16mm", right: "16mm" },
});
await browser.close();
')

# Replace any earlier checksum line for the PDF, then add the new one.
SUMS="$DIR/SHA256SUMS"
if [ -f "$SUMS" ]; then grep -v " $(basename "$PDF")\$" "$SUMS" > "$WORK/sums" || true; cp "$WORK/sums" "$SUMS"; fi
(cd "$DIR" && sha256sum "$(basename "$PDF")" >> SHA256SUMS && sha256sum -c --quiet SHA256SUMS)
echo "Wrote $PDF ($(du -h "$PDF" | cut -f1)) and updated SHA256SUMS."
