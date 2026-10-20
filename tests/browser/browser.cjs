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
    // Keep WordPress core avatars/widgets from making external requests during review.
    await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
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
      for (const tab of ['Discoveries', 'Sources', 'Settings']) {
        await page.getByRole('navigation', {name:'Editorial Scout sections'}).getByRole('link', {name:tab,exact:true}).click();
        await page.getByRole('heading', {name: {Discoveries:'Discovery queue', Sources:'Source Manager', Settings:'Source categories'}[tab], exact: true}).waitFor();
        check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `${tab}: no page-wide overflow at ${width}px`);
        const scan = await new AxeBuilder({page}).include('.editorial-scout').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        check(scan.violations.length === 0, `${tab}: scoped axe A/AA at ${width}px: ${JSON.stringify(scan.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})))}`);
      }
    }
    await page.getByLabel('New category', {exact:true}).fill('Research');
    await page.getByRole('button', {name:'Save category',exact:true}).click();
    await page.getByLabel('Category name', {exact:true}).waitFor();
    check(await page.getByLabel('Category name', {exact:true}).inputValue() === 'Research', 'Browser category creation succeeds');
    await page.getByRole('navigation', {name:'Editorial Scout sections'}).getByRole('link', {name:'Sources',exact:true}).click();
    await page.getByRole('link', {name:/Edit.*Browser source/}).click();
    check(await page.getByLabel('Name', {exact:true}).inputValue() === 'Browser source', 'Browser edit navigation populates the source form');
    await page.getByLabel('Name', {exact:true}).fill('Browser edited');
    await page.locator('#source-category').selectOption({label:'Research'});
    await page.getByRole('button', {name:'Save source',exact:true}).click();
    await page.getByText('Changes saved.', {exact:true}).waitFor();
    check(await page.getByRole('link', {name:/Edit.*Browser edited/}).count() === 1, 'Browser POST saves source and category');
    await page.getByRole('button', {name:'Check Now: Browser edited',exact:true}).click();
    await page.getByRole('status').filter({hasText:'Check complete:'}).waitFor();
    check(true, 'Browser Check Now performs the real pinned TLS feed request');
    await page.getByRole('button', {name:'Check Now: Browser edited',exact:true}).click();
    await page.getByRole('status').filter({hasText:'Wait five minutes'}).waitFor();
    check(true, 'Second browser Check Now shows server-side cooldown');
    await page.getByRole('button', {name:'Disable: Browser edited',exact:true}).click();
    await page.getByRole('button', {name:'Enable: Browser edited',exact:true}).waitFor();
    check(await page.getByRole('button', {name:'Check Now: Browser edited',exact:true}).count() === 0, 'Disabled source offers no Check Now action');
    await page.getByRole('navigation', {name:'Editorial Scout sections'}).getByRole('link', {name:'Discoveries',exact:true}).click();
    check(await page.locator('.editorial-scout script').count() === 0, 'Untrusted article title never creates a script element');
    const story = page.getByRole('row').filter({hasText:'Original research'});
    await story.locator('select[name="status"]').selectOption('shortlisted');
    await story.getByRole('button', {name:/Update status/}).click();
    await page.getByText('Changes saved.', {exact:true}).waitFor();
    check(await story.locator('select[name="status"]').inputValue() === 'shortlisted', 'Browser shortlist updates the shared story decision');
    await page.locator('#filter-status').selectOption('dismissed');
    await page.getByRole('button', {name:'Filter',exact:true}).click();
    await page.getByText('No discoveries match these filters.', {exact:true}).waitFor();
    check(true, 'Browser status filter excludes shortlisted stories');
    await page.getByRole('link', {name:'Clear filters',exact:true}).click();
    await story.getByRole('link', {name:/View coverage/}).click();
    await page.getByRole('heading', {name:'Source coverage',exact:true}).waitFor();
    check(true, 'Source coverage is accessible independently of story grouping');
    await page.getByRole('navigation', {name:'Editorial Scout sections'}).getByRole('link', {name:'Settings',exact:true}).click();
    await page.getByRole('heading', {name:'Cron Health Monitor',exact:true}).waitFor();
    check(await page.locator('#scout-coverage-warning').innerText().then(t => t.includes('discard older items') && t.includes('84 hours')), 'Infrequent feed coverage warning explains loss and Twice Weekly interval');
    for (const mode of ['daily', 'twice', 'weekly', 'manual', 'hourly', 'manual']) {
      await page.getByLabel('Automatic feed checks', {exact:true}).selectOption(mode);
      await page.getByRole('button', {name:'Save feed schedule',exact:true}).click();
      await page.getByText('Changes saved.', {exact:true}).waitFor();
      check(await page.getByLabel('Automatic feed checks', {exact:true}).inputValue() === mode, 'Schedule POST persists: ' + mode);
    }
    check(await page.getByRole('status').filter({hasText:'automatic feed checks are off'}).count() === 1, 'Health monitor recognizes Manual Only');
    const diagnostic = await page.getByLabel('Sanitized diagnostic report', {exact:true}).inputValue();
    check(diagnostic.includes('Feed schedule: manual') && !/https?:|feeds\.example|93\.184|DB_PASSWORD|\/var\//i.test(diagnostic), 'Diagnostic report omits source and infrastructure identifiers');
    await page.getByRole('button', {name:'Copy Diagnostic Report',exact:true}).click();
    check(await page.locator('#scout-diagnostic').evaluate(el => el.selectionEnd - el.selectionStart === el.value.length), 'Diagnostic copy button provides selectable fallback');
    await page.getByRole('navigation', {name:'Editorial Scout sections'}).getByRole('link', {name:'Sources',exact:true}).click();
    await page.getByRole('button', {name:'Enable: Browser edited',exact:true}).click();
    await page.getByRole('button', {name:'Check All Sources Now',exact:true}).click();
    await page.getByRole('status').filter({hasText:'Attempted 1 of 2'}).waitFor();
    check(await page.getByRole('status').filter({hasText:'Skipped: 1'}).count() === 1, 'Synchronous Check All respects recent individual cooldown in Manual Only');
    await page.getByRole('button', {name:'Continue checking sources',exact:true}).click();
    await page.getByRole('status').filter({hasText:'Attempted 2 of 2'}).waitFor();
    check(await page.getByRole('status').filter({hasText:'Successful: 1'}).count() === 1, 'Explicit browser continuation performs the remaining real TLS check without cron');
    check(await page.getByRole('button', {name:'Check All Sources Now',exact:true}).count() === 1, 'Completed batch offers a new explicit administrator action');
    check(errors.length === 0, 'No browser JavaScript errors');
    console.log(`Completed ${checks} browser checks.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
