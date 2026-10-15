// Draws the two link-preview images (1200 × 630) shown when a page is shared on
// LinkedIn, WhatsApp, X or Facebook (D-034):
//   public/og-image.png    paxofi.com — corporate tagline
//   public/og-careers.png  careers.paxofi.com — PIF campaign headline
// Run after a wording change: PLAYWRIGHT_CHROMIUM_PATH=... node scripts/share-images.mjs
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { chromium } from "playwright";

const root = fileURLToPath(new URL("..", import.meta.url));
const font = readFileSync(`${root}node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2`).toString("base64");
const mark = readFileSync(`${root}app/icon.svg`, "utf8");

const images = [
  {
    file: "public/og-image.png",
    name: "Technologies LTD",
    headline: 'Innovating <span class="hl">Today.</span><br>Transforming Tomorrow.',
    line: "People | Products | Possibilities",
    address: "paxofi.com",
  },
  {
    file: "public/og-careers.png",
    name: "Innovation Fellowship",
    headline: 'Build. Learn.<br><span class="hl">Contribute.</span>',
    line: "Real projects. Real mentors. Real growth.",
    address: "careers.paxofi.com",
  },
];

const page = (image) => `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face{font-family:Inter;src:url(data:font/woff2;base64,${font}) format("woff2");font-weight:100 900}
*{margin:0;box-sizing:border-box}
body{width:1200px;height:630px;overflow:hidden;font-family:Inter,sans-serif;color:#fff;
  background:#0A1F44;position:relative}
.dots{position:absolute;right:0;top:0;width:620px;height:300px;opacity:.35;
  background-image:radial-gradient(#2b4a85 1.6px,transparent 1.6px);background-size:16px 16px;
  -webkit-mask-image:linear-gradient(to left,#000,transparent)}
.shape{position:absolute;border-radius:90px;transform:rotate(-20deg)}
.s1{width:620px;height:620px;right:-260px;bottom:-300px;background:linear-gradient(135deg,#0047D6,#0066FF)}
.s2{width:560px;height:560px;right:-340px;bottom:-190px;background:linear-gradient(135deg,#0066FF,#00B4FF);opacity:.9}
.brand{position:absolute;left:80px;top:80px;display:flex;align-items:center;gap:22px}
.logo{width:92px;height:92px;border-radius:20px;background:#fff;display:grid;place-items:center}
.logo svg{width:70px;height:70px}
.brand b{display:block;font-size:44px;font-weight:700;letter-spacing:-.5px}
.brand small{display:block;font-size:19px;color:#BFD6FF;margin-top:4px;font-weight:500}
h1{position:absolute;left:80px;top:232px;font-size:66px;line-height:1.14;font-weight:800;letter-spacing:-1.5px;white-space:nowrap}
.hl{color:#00B4FF}
.line{position:absolute;left:82px;top:438px;font-size:21px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#10B981}
.addr{position:absolute;left:80px;bottom:56px;font-size:24px;font-weight:600;color:#BFD6FF}
</style></head><body>
<div class="dots"></div><div class="shape s1"></div><div class="shape s2"></div>
<div class="brand"><div class="logo">${mark.replace(/<rect[^>]*\/>/, "")}</div><div><b>Paxofi</b><small>${image.name}</small></div></div>
<h1>${image.headline}</h1><div class="line">${image.line}</div><div class="addr">${image.address}</div>
</body></html>`;

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {});
const tab = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const image of images) {
  await tab.setContent(page(image));
  await tab.evaluate(() => document.fonts.ready);
  await tab.screenshot({ path: `${root}${image.file}` });
  console.log(`wrote ${image.file}`);
}
await browser.close();
