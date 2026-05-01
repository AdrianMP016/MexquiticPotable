import playwright from "file:///C:/Users/Acer_V/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.js";
import path from "node:path";
import { fileURLToPath } from "node:url";

const { chromium } = playwright;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const root = path.resolve(__dirname, "..");
const htmlPath = path.join(root, "recibos", "plantillas", "opcion2-recibo.html");
const outputPath = path.join(root, "recibos", "plantillas", "recibo-opcion2-vacio.png");

const browser = await chromium.launch({
  headless: true,
  executablePath: "C:/Program Files/Google/Chrome/Application/chrome.exe"
});
const page = await browser.newPage({
  viewport: { width: 1080, height: 1920 },
  deviceScaleFactor: 1
});

await page.goto(`file:///${htmlPath.replaceAll("\\", "/")}`, { waitUntil: "networkidle" });
await page.screenshot({
  path: outputPath,
  fullPage: false,
  type: "png"
});

await browser.close();
console.log(outputPath);
