// Opt-out: the unsubscribe link in a real reminder (site, no login needed), resubscribe, and the
// admin opt-out list (add through the picker, remove).
const { test, expect, admin, adminLogin, BASE, mails, mailBody, toolbar, message, readState, saveState } = require('./helpers');

test.describe.configure({ mode: 'serial' });

test('the reminder mail carries a working unsubscribe link', async ({ page, request }) => {
  const { users } = readState();
  const [msg] = await mails(request, `to:never@${users.domain}`);
  expect(msg, 'reminder to the never-logged-in user (spec 02)').toBeTruthy();
  const body = await mailBody(request, msg.ID);
  const html = `${body.HTML || ''}\n${body.Text || ''}`.replace(/&amp;/g, '&');
  // SEF: /index.php/component/userreminder/<code>; non-SEF: ...view=optout&uid=<code>
  const m = html.match(/https?:\/\/[^\s"'<>]*(?:view=optout|component\/userreminder\/)[^\s"'<>]*/i);
  expect(m, 'unsubscribe URL in the mail').toBeTruthy();
  // The link is built from the site URL the container knows; open it on this test site.
  const u = new URL(m[0]);
  const link = `${BASE}${u.pathname.replace(/^.*?(\/index\.php|\/component\/)/, '$1')}${u.search}`;
  expect(link).not.toMatch(/https?:\/\/[^/]+\/https?:/); // no doubled site URL
  saveState({ optoutLink: link });

  await page.goto(link);
  await expect(page.locator('h1')).toBeVisible();
  await Promise.all([page.waitForLoadState('load'), page.locator('form[action*="optout.unsubscribe"] button[type=submit]').click()]);
  await expect(page.locator('.alert h1, h1.alert-heading')).toBeVisible();
  await expect(page.locator('form[action*="optout.resubscribe"]')).toBeVisible();
});

test('the unsubscribed user is on the admin opt-out list', async ({ page }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=optoutusers&filter[search]=' + encodeURIComponent(users.domain)));
  await expect(page.locator('#optoutusersList')).toContainText(`never@${users.domain}`);
});

test('opening the link again offers to resubscribe, and resubscribing works', async ({ page }) => {
  const { optoutLink, users } = readState();
  await page.goto(optoutLink);
  await Promise.all([page.waitForLoadState('load'), page.locator('form[action*="optout.resubscribe"] button[type=submit]').first().click()]);
  await expect(page.locator('.alert h1, h1.alert-heading')).toBeVisible();

  await adminLogin(page);
  await page.goto(admin('view=optoutusers&filter[search]=' + encodeURIComponent(users.domain)));
  await expect(page.locator('body')).not.toContainText(`never@${users.domain}`);
});

test('a bad opt-out code shows the failure message, not an error page', async ({ page }) => {
  await page.goto(`${BASE}/index.php?option=com_userreminder&view=optout&uid=not-a-real-code`);
  await expect(page.locator('h1, h2').first()).toBeVisible();
});

test('admin adds a user to the opt-out list through the picker and removes them', async ({ page }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=optoutusers'));
  await page.locator('#toolbar button', { hasText: 'Select Users' }).first().click();
  const dialog = page.locator('#userreminder-select-users-modal, joomla-dialog, dialog[open]').filter({ visible: true }).first();
  await expect(dialog).toBeVisible();
  // com_users' own user picker runs in an iframe; a click on a name stages the user below it.
  const frame = dialog.frameLocator('iframe');
  await frame.locator('#filter_search').fill(`ui-${readState().run}-member`);
  await frame.locator('#filter_search').press('Enter');
  // Wait for the filtered list (one row): a click on the old page is lost when the frame reloads.
  await expect(frame.locator('table tbody tr')).toHaveCount(1);
  await frame.locator('a.button-select', { hasText: `UI Member ${readState().run}` }).first().click();
  await expect(dialog.locator('#userreminder-picked-users')).toContainText(`UI Member ${readState().run}`);
  await Promise.all([page.waitForLoadState('load'), dialog.locator('button', { hasText: 'Add to opt-out list' }).click()]);
  await page.goto(admin('view=optoutusers&filter[search]=' + encodeURIComponent(users.domain)));
  const row = page.locator('#optoutusersList tbody tr', { hasText: `member@${users.domain}` });
  await expect(row).toHaveCount(1);

  await row.locator('input[type=checkbox]').check();
  await toolbar(page, 'Remove from opt-out list');
  await expect(message(page)).toBeVisible();
  await expect(page.locator('#optoutusersList tbody tr', { hasText: `member@${users.domain}` })).toHaveCount(0);
});
