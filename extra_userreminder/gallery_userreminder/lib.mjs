import { chromium } from 'file:///E:/laragon/www/nextfilemanager/extra_nextfilemanager/gallery_nextfilemanager/node_modules/playwright/index.mjs';
export { chromium };
/** Administrator login (dev sites use the standard dev admin login). */
export async function adminLogin(p, base, user = 'admin', pass = 'system123zz@@') {
  await p.goto(base + '/administrator/index.php');
  await p.locator('#mod-login-username').fill(user);
  await p.locator('#mod-login-password').fill(pass);
  await Promise.all([p.waitForNavigation(), p.locator('#btn-login-submit').click()]);
  if (await p.locator('#mod-login-username').count()) throw new Error('admin login failed');
}
