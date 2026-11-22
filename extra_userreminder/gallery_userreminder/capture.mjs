/**
 * User Reminder - gallery + docs screenshot capture.
 *
 * Shoots the administrator screens of the dev site (http://usereminder.test) after `php seed-demo.php`.
 * Joomla's own chrome around the component (main menu sidebar, header buttons, debug bar) is hidden,
 * so every picture shows User Reminder's UI. Light theme, 2x scale.
 *
 *   kind 'page'    -> 1600x900 crop from the top of the page (16:9 gallery shot), shorter if the page is
 *   kind 'element' -> just one area, via locator.screenshot()
 *
 * The sample email is a real "Send Test Email" from the Active User Reminders screen, read back from
 * Mailpit (http://127.0.0.1:8025), which the dev site uses as its SMTP server.
 *
 * Run:  node capture.mjs            (all)       UR_ONLY=dashboard node capture.mjs   (subset)
 * Out:  ./output/*.png   and   ../docs_userreminder/images/*.png
 */

import { chromium, adminLogin } from './lib.mjs';
import { mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { createHash } from 'crypto';

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = join(HERE, 'output');
const DOCS_OUT = join(HERE, '..', 'docs_userreminder', 'images');
const BASE = process.env.UR_BASE || 'http://usereminder.test';
const MAILPIT = 'http://127.0.0.1:8025';
const ONLY = process.env.UR_ONLY || '';
const W = 1600;
const H = 900;

const CHROME = `
  *, *::before, *::after { animation: none !important; transition: none !important; caret-color: transparent !important; }
  #sidebar-wrapper, #system-debug, .phpdebugbar, #phpdebugbar, .header-items, .tooltip, joomla-alert[type=message] { display: none !important; }
  .container-main { margin-inline-start: 0 !important; }
  #wrapper { grid-template-columns: 1fr !important; }
  .header .header-title { padding-inline-start: 24px !important; }
`;
// Site page: only the component, no template chrome.
const SITE_CHROME = `
  .container-header, .container-breadcrumbs, .container-sidebar-right, .container-sidebar-left, footer, #system-debug, .phpdebugbar { display: none !important; }
  body { background: #f4f6f9 !important; }
`;

const A = (q) => `/administrator/index.php?option=${q}`;
const L = '&list[limit]=20';
const tab = (name) => async (p) => { await p.locator(`[role=tab]:text-is("${name}"), a:text-is("${name}"), button:text-is("${name}")`).first().click(); };

const SHOTS = [
  { file: '01-dashboard', url: A('com_userreminder&view=cpanel'), refresh: true, kind: 'page' },
  { file: '02-registration-reminders', url: A('com_userreminder&view=reminders' + L), kind: 'page' },
  { file: '03-inactive-users', url: A('com_userreminder&view=activeusers' + L), kind: 'page' },
  { file: '04-email', kind: 'email' },
  { file: '05-email-templates', url: A('com_mails&view=templates&filter[extension]=com_userreminder&filter[language]='), kind: 'page' },
  { file: '06-email-template-edit', url: A('com_mails&task=template.edit&template_id=com_userreminder.userreminder.reminder_inactive&language=en-GB'), kind: 'page', close: true },
  { file: '07-opt-out-users', url: A('com_userreminder&view=optoutusers'), kind: 'page' },
  { file: '08-opt-out-groups', url: A('com_userreminder&view=optoutusers'), before: tab('Opt-out User Groups'), kind: 'page' },
  { file: '09-action-log', url: A('com_userreminder&view=log' + L), kind: 'page' },
  { file: '10-options-reminders', url: A('com_config&view=component&component=com_userreminder'), before: tab('Reminders'), kind: 'page' },
  { file: '11-options-email-container', url: A('com_config&view=component&component=com_userreminder'), before: tab('Email Container (HTML)'), kind: 'page' },
  { file: '12-scheduled-task', url: A('com_scheduler&view=tasks&filter[search]=User Reminder'), kind: 'page' },
  { file: '13-unsubscribe', url: '/index.php?option=com_userreminder&view=optout&uid=' + createHash('md5').update('Yuki Tanakaopt').digest('hex'), site: true, kind: 'element', selector: '.card' },
];

// Docs pictures: one area per screen, narrower viewport so they suit the docs column.
const DOCS = [
  { file: 'dashboard-counters', url: A('com_userreminder&view=cpanel'), selector: '.row.g-3.mb-4' },
  { file: 'dashboard-analytics', url: A('com_userreminder&view=cpanel'), selector: '.card:has(#ur-trend-canvas)', wait: 1200 },
  { file: 'dashboard-health', url: A('com_userreminder&view=cpanel'), selector: '.card:has-text("System health") >> nth=-1' },
  { file: 'registration-reminders', url: A('com_userreminder&view=reminders' + L), selector: '#adminForm' },
  { file: 'inactive-users', url: A('com_userreminder&view=activeusers' + L), selector: '#adminForm' },
  { file: 'opt-out-users', url: A('com_userreminder&view=optoutusers'), selector: '#adminForm' },
  { file: 'opt-out-groups', url: A('com_userreminder&view=optoutusers'), before: tab('Opt-out User Groups'), selector: '#adminForm' },
  { file: 'action-log', url: A('com_userreminder&view=log'), selector: '#adminForm' },
  { file: 'email-templates', url: A('com_mails&view=templates&filter[extension]=com_userreminder&filter[language]='), selector: '#adminForm table' },
  { file: 'scheduled-task', url: A('com_scheduler&view=tasks&filter[search]=User Reminder'), selector: '#adminForm table' },
  { file: 'options-reminders', url: A('com_config&view=component&component=com_userreminder'), before: tab('Reminders'), selector: 'joomla-tab' },
];

async function open(page, s) {
  const resp = await page.goto(BASE + s.url, { waitUntil: 'networkidle', timeout: 60000 });
  if (!resp || !resp.ok()) throw new Error(`${s.file}: HTTP ${resp?.status()} ${s.url}`);
  const text = await page.innerText('body');
  if (/(Warning|Notice|Deprecated|Fatal error):/.test(text)) throw new Error(`${s.file}: PHP message on page`);
  if (s.refresh) {
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Refresh' }).click()]);
    await page.waitForLoadState('networkidle');
  }
  await page.addStyleTag({ content: s.site ? SITE_CHROME : CHROME });
  if (s.before) {
    await s.before(page);
    await page.waitForLoadState('networkidle');
    // A tab may load a new page: put the chrome CSS back.
    await page.addStyleTag({ content: s.site ? SITE_CHROME : CHROME });
  }
  await page.waitForTimeout(s.wait ?? 800);
}

/** Send a real test email and return the newest Mailpit message id. */
async function testEmail(page) {
  const before = await (await fetch(`${MAILPIT}/api/v1/messages?limit=1`)).json();
  await page.goto(BASE + A('com_userreminder&view=activeusers'), { waitUntil: 'networkidle' });
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Send Test Email' }).click()]);
  for (let i = 0; i < 20; i++) {
    const now = await (await fetch(`${MAILPIT}/api/v1/messages?limit=1`)).json();
    if (now.messages[0] && now.messages[0].ID !== before.messages[0]?.ID) return now.messages[0].ID;
    await page.waitForTimeout(500);
  }
  throw new Error('test email did not arrive in Mailpit');
}

mkdirSync(OUT, { recursive: true });
mkdirSync(DOCS_OUT, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 2, reducedMotion: 'reduce', locale: 'en-GB', timezoneId: 'UTC', colorScheme: 'light' });
const page = await ctx.newPage();
await adminLogin(page, BASE);

for (const s of SHOTS) {
  if (ONLY && !s.file.includes(ONLY)) continue;
  const path = join(OUT, `${s.file}.png`);
  if (s.kind === 'email') {
    const id = await testEmail(page);
    const mail = await ctx.newPage();
    await mail.setViewportSize({ width: 760, height: 900 });
    await mail.goto(`${MAILPIT}/view/${id}.html`, { waitUntil: 'networkidle' });
    const html = await mail.content();
    if (/https?:\/\/[^\s"<]+https?:\/\//.test(html)) throw new Error('email contains a doubled URL');
    // Stop under the footer band instead of showing the empty grey page below it.
    const bottom = await mail.evaluate(() => {
      const el = [...document.querySelectorAll('td, div, p')].filter((e) => /Sent by/.test(e.textContent)).pop();
      return Math.ceil((el.closest('table') || el).getBoundingClientRect().bottom) + 30;
    });
    await mail.screenshot({ path, clip: { x: 0, y: 0, width: 760, height: bottom } });
    await mail.close();
    console.log('ok', s.file);
    continue;
  }
  await open(page, s);
  if (s.kind === 'element') {
    await page.locator(s.selector).first().screenshot({ path });
  } else {
    // Short pages: stop under the content instead of showing an empty band.
    const bottom = await page.evaluate(() => {
      const el = document.querySelector('#content') || document.querySelector('main');
      return Math.ceil(el.getBoundingClientRect().bottom + window.scrollY) + 24;
    });
    await page.screenshot({ path, fullPage: true, clip: { x: 0, y: 0, width: W, height: Math.min(H, bottom) } });
  }
  if (s.close) await page.getByRole('button', { name: 'Close' }).first().click().catch(() => {});
  console.log('ok', s.file);
}

const docsCtx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1.5, reducedMotion: 'reduce', locale: 'en-GB', timezoneId: 'UTC', colorScheme: 'light' });
const docsPage = await docsCtx.newPage();
await adminLogin(docsPage, BASE);
for (const s of DOCS) {
  if (ONLY && !('docs-' + s.file).includes(ONLY)) continue;
  await open(docsPage, s);
  await docsPage.locator(s.selector).first().screenshot({ path: join(DOCS_OUT, `${s.file}.png`) });
  console.log('ok docs', s.file);
}
await browser.close();
