// Portable User Reminder E2E suite for the .testbed stacks (Joomla 4.4 / 5 / 6.2 x MySQL / MariaDB).
// UR_BASE=http://127.0.0.1:8811 npx playwright test -c playwright.matrix.config.cjs
// Specs run in file order (01-, 02-, ...) and share records through test-results/matrix-state-<port>.json.
const { defineConfig, devices } = require('@playwright/test');
const port = new URL(process.env.UR_BASE || 'http://127.0.0.1:8811').port;

module.exports = defineConfig({
  testDir: './e2e/matrix',
  globalSetup: require.resolve('./e2e/matrix/global-setup.js'),
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60000,
  expect: { timeout: 10000 },
  outputDir: `test-results/matrix-${port}`,
  reporter: [['list'], ['json', { outputFile: `test-results/matrix-${port}.json` }]],
  use: {
    headless: true,
    viewport: { width: 1400, height: 900 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1400, height: 900 } } }],
});
