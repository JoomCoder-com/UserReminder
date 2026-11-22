import { chromium, adminLogin } from './lib.mjs';
const B = 'http://usereminder.test';
const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1600, height: 900 } })).newPage();
await adminLogin(p, B);
const views = { cpanel: 'cpanel&refresh=1', reminders: 'reminders', activeusers: 'activeusers', optoutusers: 'optoutusers', log: 'log' };
for (const [k, v] of Object.entries(views)) {
  await p.goto(`${B}/administrator/index.php?option=com_userreminder&view=${v}`, { waitUntil: 'networkidle' });
  const t = await p.innerText('body'); if (/(Warning|Notice|Deprecated|Fatal error):/.test(t)) console.log('PHP MSG', k);
  await p.screenshot({ path: `probe/${k}.png`, fullPage: true }); console.log(k, await p.title());
}
await b.close();
