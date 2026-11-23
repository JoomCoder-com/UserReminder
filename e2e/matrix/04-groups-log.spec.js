// Opt-out user groups (saveGroup) and the action log (clear).
const { test, expect, admin, adminLogin, toolbar, message } = require('./helpers');

test.describe.configure({ mode: 'serial' });

test('a user group is opted out and the choice is kept', async ({ page }) => {
  await adminLogin(page);
  await page.goto(admin('view=optoutusers&layout=usergroup'));
  const row = page.locator('#adminForm tbody tr', { hasText: 'Guest' }).first();
  await expect(row).toBeVisible();
  const box = row.locator('input[type=checkbox]');
  await box.check();
  await toolbar(page, 'Save & Close');
  await expect(message(page)).toBeVisible();
  await expect(page.locator('#system-message-container')).not.toContainText(/fail|error/i);
  await page.goto(admin('view=optoutusers&layout=usergroup'));
  await expect(page.locator('#adminForm tbody tr', { hasText: 'Guest' }).first().locator('input[type=checkbox]')).toBeChecked();

  // Undo, so the group does not hide users in later runs on this site
  await page.locator('#adminForm tbody tr', { hasText: 'Guest' }).first().locator('input[type=checkbox]').uncheck();
  await toolbar(page, 'Save & Close');
  await page.goto(admin('view=optoutusers&layout=usergroup'));
  await expect(page.locator('#adminForm tbody tr', { hasText: 'Guest' }).first().locator('input[type=checkbox]')).not.toBeChecked();
});

test('Clear log empties the action log', async ({ page }) => {
  await adminLogin(page);
  await page.goto(admin('view=log'));
  // A confirm button: the browser's confirm() or, on newer Joomla, a joomla-dialog.
  page.once('dialog', d => d.accept());
  await page.locator('#toolbar button', { hasText: 'Clear log' }).first().click();
  const ok = page.locator('joomla-dialog button', { hasText: /^(Yes|OK)$/i }).first();
  // isVisible() does not wait; give the dialog a moment to render.
  if (await ok.waitFor({ state: 'visible', timeout: 3000 }).then(() => true, () => false)) await ok.click();
  await page.waitForLoadState('load');
  await expect(message(page)).toContainText(/cleared/i);
  await expect(page.locator('#logList tbody tr')).toHaveCount(0);
});
