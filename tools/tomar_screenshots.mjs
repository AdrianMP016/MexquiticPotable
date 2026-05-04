import playwright from "file:///C:/Users/Acer_V/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.js";
import path from "node:path";
import { fileURLToPath } from "node:url";
import fs from "node:fs";

// ─── CREDENCIALES ─────────────────────────────────────────────────────────────
const BASE_URL   = "http://localhost/mexquitic";
const CRED_ADMIN = { usuario: "admin", password: "admin" };
const CRED_COBRO = { usuario: "admin", password: "admin" };
const CRED_VERIF = { usuario: "admin", password: "admin" };
// ──────────────────────────────────────────────────────────────────────────────

const { chromium } = playwright;
const __filename   = fileURLToPath(import.meta.url);
const __dirname    = path.dirname(__filename);
const SHOTS_DIR    = path.join(__dirname, "screenshots");

if (!fs.existsSync(SHOTS_DIR)) fs.mkdirSync(SHOTS_DIR, { recursive: true });

async function login(page, modulo, creds) {
  const loginPage = modulo === "plataforma" ? "login-admin.php"
                  : modulo === "cobro"       ? "login-cobro.php"
                  :                            "login-verificador.php";
  await page.goto(`${BASE_URL}/${loginPage}`, { waitUntil: "domcontentloaded" });
  await page.evaluate(async ({ ajaxUrl, creds, mod }) => {
    const body = new URLSearchParams({
      accion: "auth.login", usuario: creds.usuario,
      password: creds.password, modulo: mod,
    });
    await fetch(ajaxUrl, {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });
  }, { ajaxUrl: `${BASE_URL}/ajax/peticiones.php`, creds, mod: modulo });
}

async function capturar(context, url, filename, opts = {}) {
  const page = await context.newPage();
  try {
    await page.goto(url, { waitUntil: "networkidle", timeout: 30000 });
    if (page.url().includes("login")) {
      console.warn(`  ⚠  Redirigió a login: ${url}`);
      return;
    }
    if (opts.waitSelector) {
      await page.waitForSelector(opts.waitSelector, { timeout: 8000 }).catch(() => {});
    }
    await page.waitForTimeout(1800);
    const outPath = path.join(SHOTS_DIR, filename);
    await page.screenshot({ path: outPath, fullPage: opts.fullPage ?? true, type: "png" });
    console.log(`  ✓  ${filename}`);
  } finally {
    await page.close();
  }
}

const CHROME = "C:/Program Files/Google/Chrome/Application/chrome.exe";

// ═══ BLOQUE 1: Páginas de login (sin auth) ═══════════════════════════════════
console.log("\n[1/3] Pantallas de login...");
{
  const browser = await chromium.launch({ headless: true, executablePath: CHROME });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page    = await context.newPage();

  for (const [file, url] of [
    ["01-login-admin.png",       `${BASE_URL}/login-admin.php`],
    ["02-login-cobro.png",       `${BASE_URL}/login-cobro.php`],
    ["03-login-verificador.png", `${BASE_URL}/login-verificador.php`],
  ]) {
    await page.goto(url, { waitUntil: "networkidle" });
    await page.waitForTimeout(800);
    await page.screenshot({ path: path.join(SHOTS_DIR, file), fullPage: false, type: "png" });
    console.log(`  ✓  ${file}`);
  }

  await browser.close();
}

// ═══ BLOQUE 2: Panel de administración ═══════════════════════════════════════
console.log("\n[2/3] Pantallas del panel admin...");
{
  const browser = await chromium.launch({ headless: true, executablePath: CHROME });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const lp      = await context.newPage();
  await login(lp, "plataforma", CRED_ADMIN);
  await lp.close();

  const paginas = [
    { url: `${BASE_URL}/index.php`,            file: "04-dashboard-consulta.png" },
    { url: `${BASE_URL}/alta-usuario.php`,     file: "05-alta-usuario.png" },
    { url: `${BASE_URL}/medidores-admin.php`,  file: "06-medidores.png" },
    { url: `${BASE_URL}/rutas-admin.php`,      file: "07-rutas.png" },
    { url: `${BASE_URL}/periodos-admin.php`,   file: "08-periodos.png" },
    { url: `${BASE_URL}/lecturas-recibos.php`, file: "09-lecturas-recibos.png" },
    { url: `${BASE_URL}/pagos-admin.php`,      file: "10-pagos.png" },
    { url: `${BASE_URL}/whatsapp-admin.php`,   file: "11-whatsapp.png" },
  ];

  for (const p of paginas) {
    await capturar(context, p.url, p.file, { fullPage: true });
  }

  await browser.close();
}

// ═══ BLOQUE 3: Módulos de campo (viewport móvil) ══════════════════════════════
console.log("\n[3/3] Módulos de campo (móvil)...");
{
  const browser = await chromium.launch({ headless: true, executablePath: CHROME });

  // Verificador
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const lp  = await ctx.newPage();
    await login(lp, "verificador", CRED_VERIF);
    await lp.close();
    await capturar(ctx, `${BASE_URL}/verificador.php`, "12-verificador-campo.png", { fullPage: false });
    await ctx.close();
  }

  // Cobro
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const lp  = await ctx.newPage();
    await login(lp, "cobro", CRED_COBRO);
    await lp.close();
    await capturar(ctx, `${BASE_URL}/pago-campo.php`, "13-cobro-campo.png", { fullPage: false });
    await ctx.close();
  }

  await browser.close();
}

console.log(`\nListo. Screenshots en: ${SHOTS_DIR}`);
