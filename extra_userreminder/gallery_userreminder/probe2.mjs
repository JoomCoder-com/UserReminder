import { chromium, adminLogin } from './lib.mjs';
const B = 'http://usereminder.test';
const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1600, height: 900 } })).newPage();
await adminLogin(p, B);
const urls = { log: 'option=com_userreminder&view=log', mails: 'option=com_mails&view=templates&filter[extension]=com_userreminder', mailedit: 'option=com_mails&task=template.edit&template_id=com_userreminder.reminder_activation&language=en-GB', options: 'option=com_config&view=component&component=com_userreminder', tasks: 'option=com_scheduler&view=tasks', site: '' };
for (const [k, v] of Object.entries(urls)) {
  await p.goto(k==='site' ? B+'/index.php?option=com_userreminder&view=optout' : `${B}/administrator/index.php?${v}`, { waitUntil: 'networkidle' });
  await p.screenshot({ path: `probe/${k}.png`, fullPage: true }); console.log(k, p.url());
}
await b.close();
