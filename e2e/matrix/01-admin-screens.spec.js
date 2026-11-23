// Every User Reminder admin screen loads without PHP / Joomla / JS problems, and Options saves.
const { test, expect, admin, adminLogin, BASE } = require('./helpers');

test.describe.configure({ mode: 'serial' });

const screens = [
  ['Dashboard', 'view=cpanel'],
  ['Reminders', 'view=reminders'],
  ['Active users', 'view=activeusers'],
  ['Opt-out users', 'view=optoutusers'],
  ['Log', 'view=log'],
  ['Help', 'view=help'],
];

for (const [name, q] of screens) {
  test(`admin screen loads: ${name}`, async ({ page }) => {
    await adminLogin(page);
    await page.goto(admin(q));
    await expect(page.locator('#mod-login-username')).toHaveCount(0);
    await expect(page.locator('#subhead-container, .subhead, #toolbar').first()).toBeVisible();
  });
}

test('the mail templates screen lists the User Reminder templates', async ({ page }) => {
  await adminLogin(page);
  await page.goto(`${BASE}/administrator/index.php?option=com_mails&view=templates&filter[extension]=com_userreminder`);
  await expect(page.locator('#j-main-container')).toContainText(/User Reminder|userreminder/i);
});

test('Options open on every tab and save', async ({ page }) => {
  await adminLogin(page);
  await page.goto(`${BASE}/administrator/index.php?option=com_config&view=component&component=com_userreminder`);
  const tabs = page.locator('joomla-tab button[role=tab], [role=tablist] [role=tab]');
  const n = await tabs.count();
  expect(n).toBeGreaterThan(1);
  for (let i = 0; i < n; i++) await tabs.nth(i).click();
  await Promise.all([page.waitForLoadState('load'), page.locator('#toolbar button', { hasText: /^\s*Save\s*$/ }).first().click()]);
  await expect(page.locator('#system-message-container')).toContainText(/saved/i);
});
