// Shared helpers for the portable User Reminder E2E suite (e2e/matrix).
//
// Runs against the .testbed stacks (they have Mailpit on the compose network):
//   UR_BASE=http://127.0.0.1:8811 npx playwright test -c playwright.matrix.config.cjs
// Every test gets the `guard` fixture: it fails the test when a page shows a PHP error/warning/
// notice/deprecation, Joomla's error page, a 5xx response, or a JavaScript error.
const base = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const BASE = (process.env.UR_BASE || 'http://127.0.0.1:8811').replace(/\/$/, '');
const PORT = new URL(BASE).port;
const ADMIN = { user: process.env.UR_ADMIN_USER || 'admin', pass: process.env.UR_ADMIN_PASS || 'system123zz@@' };
const MAILPIT = (process.env.UR_MAILPIT || 'http://127.0.0.1:18025').replace(/\/$/, '');
const STATE_FILE = path.join(__dirname, '..', '..', 'test-results', `matrix-state-${PORT || 'x'}.json`);
// .testbed/docker-compose.yml: host port -> container
const CONTAINERS = { 8811: 'joomla-j4', 8812: 'joomla-j5', 8813: 'joomla-j6', 8814: 'joomla-j4m', 8815: 'joomla-j5m', 8816: 'joomla-j6y' };
const CONTAINER = process.env.UR_CONTAINER || `urtest-${CONTAINERS[PORT]}-1`;

function readState() {
  try { return JSON.parse(fs.readFileSync(STATE_FILE, 'utf8')); } catch { return {}; }
}
function saveState(patch) {
  const s = { ...readState(), ...patch };
  fs.mkdirSync(path.dirname(STATE_FILE), { recursive: true });
  fs.writeFileSync(STATE_FILE, JSON.stringify(s, null, 2));
  return s;
}
// A short id for this run, shared by every spec.
const RUN = readState().run || saveState({ run: Math.random().toString(36).slice(2, 6).replace(/[^a-z0-9]/g, 'x') }).run;

// Run a command in the stack's Joomla container (as www-data, from the site root).
function inContainer(cmd) {
  return execFileSync('docker', ['exec', '-u', 'www-data', CONTAINER, 'sh', '-c', `cd /var/www/html && ${cmd}`], { encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
}
// Users of this run (fixtures/seed-ui.php), created once per run.
function seedUsers() {
  const st = readState();
  if (st.users) return st.users;
  execFileSync('docker', ['cp', path.join(__dirname, 'fixtures', 'seed-ui.php'), `${CONTAINER}:/tmp/seed-ui.php`], { env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
  const out = inContainer(`php /tmp/seed-ui.php ${RUN}`);
  const users = JSON.parse(out.trim().split(/\r?\n/).pop());
  saveState({ users });
  return users;
}

// Mailpit: messages to one address / domain (newest first).
async function mails(request, query) {
  const r = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(query)}&limit=50`);
  base.expect(r.ok()).toBeTruthy();
  const list = (await r.json()).messages || [];
  // Mailpit matches "to:" as a substring (act@x also finds inact@x): keep exact addresses only.
  const to = query.match(/^to:(\S+@\S+)$/);
  return to ? list.filter(m => (m.To || []).some(t => t.Address.toLowerCase() === to[1].toLowerCase())) : list;
}
async function mailBody(request, id) {
  const r = await request.get(`${MAILPIT}/api/v1/message/${id}`);
  return r.json();
}

const PHP_ERROR = /\b(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards)\s*:\s*.{0,200}?\bon line\b\s*\d+/i;
const JOOMLA_ERROR = /An error has occurred\.|The requested page can't be found|0 - Call to|Class ".*" not found/i;

const test = base.test.extend({
  guard: [async ({ page }, use, testInfo) => {
    const problems = [];
    // Joomla 4.4 core: the end-of-support quick icon's snooze script fails on the admin home page.
    page.on('pageerror', e => /plg_quickicon_eos/.test(e.stack || '') || problems.push(`JS error on ${page.url().replace(BASE, '')}: ${e.message.split('\n')[0]} @ ${((e.stack || '').split('\n')[1] || '').trim()}`));
    page.on('console', m => {
      if (m.type() !== 'error') return;
      const t = m.text();
      // Browser noise unrelated to the product
      if (/favicon|Failed to load resource: the server responded with a status of 404/.test(t)) return;
      problems.push(`console: ${t.slice(0, 200)}`);
    });
    page.on('response', r => {
      if (!r.url().startsWith(BASE)) return;
      if (r.status() >= 500) {
        // Keep the error message (Joomla's error page, or the debug "Call Stack" page when debug is on)
        pending.push(r.text().then(t => {
          const txt = t.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g, ' ').replace(/<[^>]+>/g, ' ')
            .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/\s+/g, ' ');
          const m = txt.match(/report the error below\.\s*(.{0,300})/) || txt.match(/(?:Fatal error|Exception|Error)\s*:?\s*(.{0,300})/);
          problems.push(`HTTP ${r.status()} ${r.url().replace(BASE, '')}${m ? ' :: ' + m[1].replace(/ Call Stack.*$/, '') : ''}`);
        }).catch(() => problems.push(`HTTP ${r.status()} ${r.url().replace(BASE, '')}`)));
      } else if (r.status() >= 400 && r.request().isNavigationRequest() && !page.__allow4xx)
        problems.push(`HTTP ${r.status()} page ${r.url().replace(BASE, '')}`);
    });
    const pending = [];
    const inspect = async () => {
      try {
        const html = await page.content();
        const text = html.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ');
        const php = text.match(PHP_ERROR);
        // Joomla 4.4 core on PHP 8.1+: its CalendarField formats every date with strftime() unless the
        // field has a filterformat (Joomla 5+ does not). Core notice, hidden at default error reporting.
        if (php && !/strftime\(\) is deprecated in .*CalendarField\.php/.test(php[0])) problems.push(`PHP on ${page.url().replace(BASE, '')}: ${php[0].replace(/\s+/g, ' ')}`);
        const jerr = text.match(JOOMLA_ERROR);
        if (jerr) problems.push(`Joomla error page on ${page.url().replace(BASE, '')}: ${text.replace(/\s+/g, ' ').slice(text.replace(/\s+/g, ' ').indexOf(jerr[0]), 400)}`);
      } catch { /* page navigated away */ }
    };
    page.on('load', () => pending.push(inspect()));
    await use(problems);
    await Promise.all(pending);
    if (!page.isClosed()) await inspect();
    const found = [...new Set(problems)];
    if (found.length) {
      await testInfo.attach('problems', { body: found.join('\n'), contentType: 'text/plain' });
      base.expect(found, 'page problems (PHP / Joomla / JS / 4xx-5xx)').toEqual([]);
    }
  }, { auto: true }],
});

async function adminLogin(page) {
  await page.goto(`${BASE}/administrator/index.php`);
  if (await page.locator('#mod-login-username').count()) {
    await page.fill('#mod-login-username', ADMIN.user);
    await page.fill('#mod-login-password', ADMIN.pass);
    await Promise.all([page.waitForLoadState('load'), page.click('#btn-login-submit')]);
  }
  await base.expect(page.locator('#mod-login-username')).toHaveCount(0);
}

async function siteLogin(page, user = ADMIN.user, pass = ADMIN.pass) {
  await page.goto(`${BASE}/index.php?option=com_users&view=login`);
  if (await page.locator('#username').count()) {
    await page.fill('#username', user);
    await page.fill('#password', pass);
    await Promise.all([page.waitForLoadState('load'), page.locator('form.com-users-login__form button[type=submit], .login button[type=submit]').first().click()]);
  }
  await base.expect(page.locator('#username')).toHaveCount(0);
}

const admin = (q) => `${BASE}/administrator/index.php?option=com_userreminder${q ? '&' + q : ''}`;
const site = (q) => `${BASE}/index.php?option=com_userreminder${q ? '&' + q : ''}`;
// Click a toolbar button by its visible text and wait for the page that follows.
async function toolbar(page, text) {
  await Promise.all([
    page.waitForLoadState('load'),
    page.locator('#toolbar button, #toolbar joomla-toolbar-button button, #toolbar a', { hasText: text }).first().click(),
  ]);
}
const message = (page) => page.locator('#system-message-container joomla-alert, #system-message-container .alert').first();

module.exports = { test, expect: base.expect, BASE, ADMIN, RUN, CONTAINER, MAILPIT, admin, site, adminLogin, siteLogin, readState, saveState, inContainer, seedUsers, mails, mailBody, toolbar, message };
