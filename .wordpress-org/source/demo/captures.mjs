// Captures du parcours WooCommerce local (http://localhost:8088) pour la fiche WordPress.org.
import { createHmac } from "node:crypto";
import { execSync } from "node:child_process";
import { pathToFileURL } from "node:url";
import { join } from "node:path";

const [pw, out, adminPass] = process.argv.slice(2);
const { chromium } = await import(pathToFileURL(join(pw, "index.mjs")).href);
const base = "http://localhost:8088";

const browser = await chromium.launch({ channel: "msedge" });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1, locale: "fr-FR" });
const page = await ctx.newPage();

// 1. Panier de 84 € puis passage en caisse.
await page.goto(`${base}/?add-to-cart=11&quantity=2`);
await page.goto(`${base}/?add-to-cart=10`);
await page.goto(`${base}/`);
await page.goto(`${base}/checkout/`);
await page.waitForSelector(".wc-block-checkout", { timeout: 30000 });
const fill = async (label, value) => {
  const f = page.getByLabel(label, { exact: true }).first();
  if (await f.count()) await f.fill(value);
};
await fill("Adresse e-mail", "camille.martin@example.fr");
await fill("Prénom", "Camille");
await fill("Nom", "Martin");
await fill("Adresse", "8 rue des Potiers");
await fill("Code postal", "69001");
await fill("Ville", "Lyon");
await page.getByText("Virement instantané", { exact: true }).first().click();
await page.mouse.wheel(0, 400);
await page.waitForTimeout(3000);
console.log(await page.evaluate(() =>
  [...document.querySelectorAll(".wp-block-woocommerce-checkout-order-summary-block *")]
    .filter((e) => /skeleton|placeholder|loading/.test(e.className)).map((e) => e.className).slice(0, 5)));
await page.addStyleTag({ content: ".wc-block-components-notices, .wc-block-store-notices { display: none !important; }" });
await page.waitForTimeout(1500);
await page.screenshot({ path: join(out, "checkout-full.png"), fullPage: true });

// 2. Validation : le mock renvoie vers « commande reçue ».
await page.getByRole("button", { name: /Commander|Passer la commande/ }).click();
await page.waitForURL(/order-received/, { timeout: 30000 });
const orderId = page.url().match(/order-received\/(\d+)/)[1];
console.log("commande", orderId);

// 3. Webhook signé payment.succeeded, comme l'enverrait Virevo.
const body = JSON.stringify({
  type: "payment.succeeded",
  data: { payment: { id: `pay_demo_${orderId}`, reference: orderId, amount_cents: 8400, currency: "EUR" } },
});
const t = Math.floor(Date.now() / 1000);
const sig = createHmac("sha256", "whsec_test123").update(`${t}.${body}`).digest("hex");
const r = await fetch(`${base}/wp-json/virevo/v1/webhook`, {
  method: "POST",
  headers: { "Content-Type": "application/json", "Virevo-Signature": `t=${t},v1=${sig}` },
  body,
});
console.log("webhook", r.status, await r.text());
await page.reload();
await page.screenshot({ path: join(out, "order-received.png"), fullPage: true });

// 4. Administration : commande et réglages.
await page.goto(`${base}/wp-login.php`);
await page.fill("#user_login", "admin");
await page.fill("#user_pass", adminPass);
await page.click("#wp-submit");
await page.waitForLoadState("networkidle");
await page.setViewportSize({ width: 1280, height: 1300 });
await page.goto(`${base}/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`);
await page.waitForLoadState("networkidle");
await page.screenshot({ path: join(out, "admin-order.png"), clip: { x: 0, y: 0, width: 1280, height: 1300 } });
await page.setViewportSize({ width: 1280, height: 900 });
// L'URL du faux serveur ne doit pas apparaître sur la capture des réglages.
execSync('docker exec wp-cli-1 wp option patch update woocommerce_virevo_settings api_base ""');
await page.goto(`${base}/wp-admin/admin.php?page=wc-settings&tab=checkout&section=virevo`);
await page.waitForLoadState("networkidle");
await page.screenshot({ path: join(out, "admin-settings.png"), fullPage: true });
await page.goto(`${base}/wp-admin/admin.php?page=wc-settings&tab=checkout`);
await page.waitForLoadState("networkidle");
await page.screenshot({ path: join(out, "admin-gateways.png"), fullPage: true });

await browser.close();
