const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');

(async () => {
  const compose = ['compose', '-p', 'editorial-scout-tests', '-f', 'tests/compose.yaml'];
  if (process.env.SCOUT_COMPOSE_EXTRA) compose.push('-f', process.env.SCOUT_COMPOSE_EXTRA);
  const info = JSON.parse(execFileSync('docker', ['inspect', 'editorial-scout-tests-wordpress-1'], {encoding:'utf8'}))[0];
  const network = Object.entries(info.NetworkSettings.Networks).find(([name]) => name.endsWith('_database'))[1];
  const base = process.env.SCOUT_BROWSER_URL || 'http://' + network.IPAddress;
  const cookies = JSON.parse(execFileSync('docker', [...compose, 'exec', '-T', '-e', 'SCOUT_BROWSER_URL=' + base, 'wordpress', 'php', '/opt/scout-tests/browser-setup.php'], {cwd: require('node:path').resolve(__dirname, '../..'), encoding: 'utf8'}));
  const browser = await chromium.launch({executablePath: process.env.SCOUT_CHROMIUM || (require('node:fs').existsSync('/usr/bin/chromium') ? '/usr/bin/chromium' : undefined), headless: true, args: ['--no-sandbox', '--no-proxy-server', '--proxy-bypass-list=*']});
  try {
    const context = await browser.newContext();
    await context.addCookies(cookies);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let checks = 0;
    const check = (value, message) => { assert(value, message); checks++; console.log('PASS: ' + message); };
    const response = await page.goto(base + '/wp-admin/admin.php?page=editorial-scout');
    console.log('HTTP status:', response.status());
    assert.equal(response.status(), 200, 'Administrator page must respond successfully');
    console.log('Administrator page:', page.url(), await page.title());
    await page.getByRole('heading', {name: 'Editorial Scout', exact: true}).waitFor();
    for (const width of [375, 768, 1280]) {
      await page.setViewportSize({width, height: 900});
      check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `No page-wide overflow at ${width}px`);
      for (const label of ['Sources', 'Discovered articles']) {
        const region = page.getByRole('region', {name: label, exact: true});
        await region.focus();
        check(await region.evaluate(el => document.activeElement === el && el.clientWidth > 0), `${label} table region is named and keyboard focusable at ${width}px`);
      }
      const scan = await new AxeBuilder({page}).include('.editorial-scout').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      check(scan.violations.length === 0, `No scoped axe WCAG A/AA violations at ${width}px: ${JSON.stringify(scan.violations.map(v => ({id:v.id,nodes:v.nodes.map(n=>n.target)})))}`);
    }
    check(await page.locator('.editorial-scout script').count() === 0, 'Untrusted article title does not create a script element');
    check(await page.locator('input[name="_wpnonce"]').evaluateAll(nodes => nodes.every(n => !n.id)), 'Repeated source forms have no duplicate nonce IDs');
    await page.getByRole('link', {name: /Edit.*Browser source/}).click();
    check(await page.getByLabel('Name', {exact:true}).inputValue() === 'Browser source', 'Browser edit navigation populates the source form');
    await page.getByLabel('Name', {exact:true}).fill('Browser edited');
    await page.getByRole('button', {name: 'Save source', exact:true}).click();
    await page.getByText('Source updated.', {exact:true}).waitFor();
    check(await page.getByRole('link', {name: /Edit.*Browser edited/}).count() === 1, 'Browser POST saves the edited source');
    await page.getByRole('button', {name:'Disable: Browser edited',exact:true}).click();
    await page.getByRole('button', {name:'Enable: Browser edited',exact:true}).waitFor();
    check(true, 'Browser disable POST updates enabled state');
    check(errors.length === 0, 'No browser JavaScript errors');
    console.log(`Completed ${checks} browser checks.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
