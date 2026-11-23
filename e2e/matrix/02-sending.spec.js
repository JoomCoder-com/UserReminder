// Sending: switch the registration reminders on in Options, send from the lists, check the mails
// in Mailpit and the action log, and send a test email.
const { test, expect, admin, adminLogin, BASE, MAILPIT, seedUsers, mails, toolbar, message, readState } = require('./helpers');

test.describe.configure({ mode: 'serial' });

// A Joomla switcher field: only the current state's label is shown (the other one sits under it),
// the radio inputs take the click.
async function setSwitch(page, name, value) {
  const radio = page.locator(`input[name="jform[${name}]"][value="${value}"]`);
  await radio.check();
  await expect(radio).toBeChecked();
}

test('activation and login reminders are switched on in Options', async ({ page }) => {
  seedUsers();
  await adminLogin(page);
  await page.goto(`${BASE}/administrator/index.php?option=com_config&view=component&component=com_userreminder`);
  await page.locator('[role=tab]', { hasText: /Reminders/i }).first().click();
  await setSwitch(page, 'enableActivateReminder', 1);
  await setSwitch(page, 'enableLoginReminder', 1);
  await Promise.all([page.waitForLoadState('load'), page.locator('#toolbar button', { hasText: /^\s*Save\s*$/ }).first().click()]);
  await expect(page.locator('#system-message-container')).toContainText(/saved/i);
});

test('the Reminders list shows the users due a registration reminder', async ({ page }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=reminders&filter[search]=' + encodeURIComponent(users.domain)));
  const table = page.locator('#remindersList');
  await expect(table).toContainText(`act@${users.domain}`);
  await expect(table).toContainText(`never@${users.domain}`);
  await expect(table).not.toContainText(`member@${users.domain}`);
});

test('Send Reminders mails the not-activated and never-logged-in users', async ({ page, request }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=reminders'));
  await toolbar(page, 'Send Reminders');
  await expect(message(page)).toContainText(/Sent [1-9]\d* reminder/);
  await expect.poll(async () => (await mails(request, `to:act@${users.domain}`)).length).toBe(1);
  await expect.poll(async () => (await mails(request, `to:never@${users.domain}`)).length).toBe(1);
  expect((await mails(request, `to:member@${users.domain}`)).length).toBe(0);
});

test('the Active users list sends the inactive user a reminder', async ({ page, request }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=activeusers&filter[search]=' + encodeURIComponent(users.domain)));
  await expect(page.locator('#activeusersList')).toContainText(`inact@${users.domain}`);
  await toolbar(page, 'Send Reminders');
  await expect(message(page)).toContainText(/Sent [1-9]\d* reminder/);
  await expect.poll(async () => (await mails(request, `to:inact@${users.domain}`)).length).toBe(1);
});

test('the action log lists the three sends', async ({ page }) => {
  const { run } = readState();
  await adminLogin(page);
  await page.goto(admin('view=log&filter[search]=' + encodeURIComponent(`ui-${run}-`)));
  for (const k of ['act', 'never', 'inact']) {
    await expect(page.locator('#logList')).toContainText(`ui-${run}-${k}`);
  }
});

test('a second run inside the cool-down sends nothing again', async ({ page, request }) => {
  const { users } = readState();
  await adminLogin(page);
  await page.goto(admin('view=cpanel'));
  await toolbar(page, 'Send now');
  await expect(message(page)).toBeVisible();
  await page.waitForTimeout(1000);
  expect((await mails(request, `to:act@${users.domain}`)).length).toBe(1);
  expect((await mails(request, `to:never@${users.domain}`)).length).toBe(1);
});

test('the dashboard buttons answer: inactive send, refresh, prune', async ({ page }) => {
  await adminLogin(page);
  for (const b of ['Send inactive reminders', 'Refresh', 'Prune old logs']) {
    await page.goto(admin('view=cpanel'));
    await toolbar(page, b);
    await expect(page.locator('#system-message-container')).not.toContainText(/error|warning/i);
  }
});

test('Send test email reaches the admin', async ({ page, request }) => {
  await adminLogin(page);
  await page.goto(admin('view=reminders'));
  const t0 = Date.now() - 2000;
  await toolbar(page, /Test/);
  await expect(message(page)).toContainText(/Test email sent/i);
  await expect.poll(async () => {
    const r = await request.get(`${MAILPIT}/api/v1/messages?limit=20`);
    return ((await r.json()).messages || []).some(m => Date.parse(m.Created) >= t0 && (m.To || []).some(t => /@/.test(t.Address)));
  }).toBe(true);
});
