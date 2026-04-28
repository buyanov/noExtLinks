const { defineConfig } = require('@playwright/test');

const targets = {
  joomla5: 8055,
  joomla6: 8056
};

const target = process.env.E2E_TARGET || 'joomla5';
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${targets[target] || targets.joomla5}`;

module.exports = defineConfig({
  testDir: './Tests/e2e/playwright',
  globalSetup: require.resolve('./Tests/e2e/playwright/global-setup.cjs'),
  globalTeardown: require.resolve('./Tests/e2e/playwright/global-teardown.cjs'),
  timeout: 90_000,
  expect: {
    timeout: 10_000
  },
  fullyParallel: false,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: [
    {
      name: process.env.E2E_TARGET || 'joomla5',
      use: {
        browserName: 'chromium'
      }
    }
  ]
});
