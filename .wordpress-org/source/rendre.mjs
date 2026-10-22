// Rend les visuels WordPress.org en PNG dans .wordpress-org/.
// Usage : node .wordpress-org/source/rendre.mjs <chemin vers le paquet playwright>
// (Playwright n'est pas une dépendance de l'extension ; on emprunte celui du
// dépôt Virevo, et le navigateur Edge installé sur le poste.)
import { pathToFileURL, fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const [pw] = process.argv.slice(2);
if (!pw) throw new Error("Chemin vers le paquet playwright attendu en argument");
const { chromium } = await import(pathToFileURL(join(pw, "index.mjs")).href);

const here = dirname(fileURLToPath(import.meta.url));
const out = join(here, "..");
const src = pathToFileURL(join(here, "visuels.html")).href;

const jobs = [
  { q: "v=icon", w: 256, h: 256, scale: 1, file: "icon-256x256.png" },
  { q: "v=icon", w: 256, h: 256, scale: 0.5, file: "icon-128x128.png" },
  { q: "v=banner", w: 772, h: 250, scale: 1, file: "banner-772x250.png" },
  { q: "v=banner", w: 772, h: 250, scale: 2, file: "banner-1544x500.png" },
  { q: "v=banner&lang=fr", w: 772, h: 250, scale: 1, file: "banner-772x250-fr_FR.png" },
  { q: "v=banner&lang=fr", w: 772, h: 250, scale: 2, file: "banner-1544x500-fr_FR.png" },
];

const browser = await chromium.launch({ channel: "msedge" });
for (const j of jobs) {
  const page = await browser.newPage({ viewport: { width: j.w, height: j.h }, deviceScaleFactor: j.scale });
  await page.goto(`${src}?${j.q}`);
  await page.waitForLoadState("networkidle");
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: join(out, j.file), clip: { x: 0, y: 0, width: j.w, height: j.h } });
  await page.close();
  console.log(j.file);
}
await browser.close();
