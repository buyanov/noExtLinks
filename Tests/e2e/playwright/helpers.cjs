const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');

const targets = {
  joomla5: {
    image: 'joomla:5-php8.3-apache',
    port: 8055,
    project: 'noextlinks-e2e-j5'
  },
  joomla6: {
    image: 'joomla:6-php8.3-apache',
    port: 8056,
    project: 'noextlinks-e2e-j6'
  }
};

function targetName() {
  return process.env.E2E_TARGET || 'joomla5';
}

function targetConfig() {
  const name = targetName();

  if (!targets[name]) {
    throw new Error(`Unknown E2E target "${name}". Expected one of: ${Object.keys(targets).join(', ')}`);
  }

  return targets[name];
}

function baseUrl() {
  return process.env.E2E_BASE_URL || `http://127.0.0.1:${targetConfig().port}`;
}

function commandToString(command, args) {
  return [command, ...args].join(' ');
}

function run(command, args = [], options = {}) {
  const result = spawnSync(command, args, {
    cwd: root,
    env: { ...process.env, ...composeEnv(), ...(options.env || {}) },
    encoding: 'utf8',
    maxBuffer: 1024 * 1024 * 12
  });

  const output = [result.stdout, result.stderr].filter(Boolean).join('\n').trim();

  if (result.status !== 0 && !options.allowFailure) {
    throw new Error(`Command failed with exit code ${result.status}: ${commandToString(command, args)}${output ? `\n${output}` : ''}`);
  }

  return output;
}

function composeEnv() {
  const config = targetConfig();

  return {
    JOOMLA_IMAGE: config.image,
    JOOMLA_PORT: String(config.port)
  };
}

function compose(args, options = {}) {
  const config = targetConfig();

  return run('docker', [
    'compose',
    '-f',
    path.join(root, 'Tests/e2e/compose.yaml'),
    '-p',
    config.project,
    ...args
  ], options);
}

async function waitForJoomla(request) {
  const deadline = Date.now() + 240_000;
  let lastStatus = 0;
  let lastBody = '';

  while (Date.now() < deadline) {
    try {
      const response = await request.get(`${baseUrl()}/`);
      lastStatus = response.status();
      lastBody = await response.text();

      if (lastStatus === 200 && lastBody.includes('Joomla')) {
        return;
      }
    } catch (error) {
      lastBody = error.message;
    }

    await new Promise((resolve) => setTimeout(resolve, 3000));
  }

  throw new Error(`Joomla did not become ready. Last status: ${lastStatus}\n${lastBody.slice(0, 1200)}`);
}

function buildZip() {
  run('php', [path.join(root, 'tools/build.php'), 'zip']);
}

function startStand() {
  compose(['down', '-v', '--remove-orphans'], { allowFailure: true });
  compose(['up', '-d']);
}

function stopStand() {
  compose(['down', '-v', '--remove-orphans'], { allowFailure: true });
}

function containerPhp(script, args = []) {
  return compose([
    'exec',
    '-T',
    'joomla',
    'php',
    `/tests/${script}`,
    ...args
  ]);
}

function encodeJson(data) {
  return Buffer.from(JSON.stringify(data)).toString('base64');
}

function encodeText(text) {
  return Buffer.from(text).toString('base64');
}

function defaultParams(overrides = {}) {
  return {
    noindex: '1',
    nofollow: 'nofollow',
    settitle: '1',
    blank: '_blank',
    replace_anchor: '0',
    replace_anchor_host: '0',
    absolutize: '0',
    usejs: '0',
    excluded_domains: '',
    removed_domains: '',
    use_redirect_page: '0',
    redirect_timeout: '5',
    excluded_menu_items: '',
    excluded_menu: [],
    excluded_categories: '',
    excluded_category_list: [],
    excluded_articles: '',
    whitelist: '',
    ...overrides
  };
}

function pluginState() {
  return JSON.parse(containerPhp('Tests/e2e/plugin-state.php'));
}

function applyParams(params) {
  return JSON.parse(containerPhp('Tests/e2e/apply-params.php', [encodeJson(params)]));
}

function seedArticle(scenario, params, introtext) {
  const output = containerPhp('Tests/e2e/seed.php', [
    scenario,
    encodeJson(params),
    encodeText(introtext)
  ]);

  return JSON.parse(output);
}

async function adminLogin(page) {
  await page.goto(`${baseUrl()}/administrator/`);
  await page.locator('input[name="username"]').fill('admin');
  await page.locator('input[name="passwd"]').fill('admin-password-123');
  await Promise.all([
    page.waitForURL(/\/administrator\/index\.php(?:$|\?)/, { waitUntil: 'load' }),
    page.locator('button[type="submit"], input[type="submit"]').first().click()
  ]);
  await expect(page.locator('body')).not.toContainText('Username and password do not match');

  const cancelModal = page.getByRole('button', { name: 'Cancel' });
  if (await cancelModal.isVisible({ timeout: 5000 }).catch(() => false)) {
    await cancelModal.click();
  }
}

async function installFromAdmin(page) {
  const zipPath = path.join(root, 'dist/noextlinks.zip');
  const packagePath = path.join(root, 'dist/noextlinks');

  if (!fs.existsSync(zipPath) || !fs.existsSync(packagePath)) {
    throw new Error(`Missing package artifacts: ${zipPath} / ${packagePath}`);
  }

  await adminLogin(page);
  const installerUrl = `${baseUrl()}/administrator/index.php?option=com_installer&view=install`;
  const installerPage = await page.request.get(installerUrl);
  const installerHtml = await installerPage.text();
  const tokenMatch = installerHtml.match(/name="([a-f0-9]{32})"\s+value="1"/i);

  if (!tokenMatch) {
    throw new Error(`Unable to find Joomla CSRF token on installer page:\n${installerHtml.slice(0, 2000)}`);
  }

  const installResponse = await page.request.post(installerUrl, {
    form: {
      installtype: 'folder',
      install_directory: '/tests/dist/noextlinks',
      task: 'install.install',
      option: 'com_installer',
      [tokenMatch[1]]: '1'
    }
  });
  const body = await installResponse.text();

  if (!installResponse.ok() || /Unable to install extension|error has occurred|Call to undefined method/i.test(body)) {
    throw new Error(`Admin install failed:\n${body.slice(0, 2000)}`);
  }

  const state = pluginState();

  if (!state.installed) {
    throw new Error(`Admin install finished but plugin is not installed:\n${body.slice(0, 2000)}`);
  }

  return state;
}

async function openPluginForm(page, extensionId) {
  await page.goto(`${baseUrl()}/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=${extensionId}`);
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(/noextlinks|NoExternalLinks|PLG_/i);
}

async function fetchArticle(page, articleId) {
  const response = await page.goto(`${baseUrl()}/index.php?option=com_content&view=article&id=${articleId}`);
  expect(response.status()).toBe(200);

  return page.content();
}

function collectDiagnostics() {
  const diagnostics = [];

  diagnostics.push('--- docker compose ps ---');
  diagnostics.push(compose(['ps'], { allowFailure: true }));
  diagnostics.push('--- joomla logs ---');
  diagnostics.push(compose([
    'exec',
    '-T',
    'joomla',
    'sh',
    '-lc',
    'find administrator/logs logs -type f -maxdepth 2 -print -exec tail -n 160 {} \\; 2>/dev/null || true'
  ], { allowFailure: true }));
  diagnostics.push('--- joomla container logs ---');
  diagnostics.push(compose(['logs', '--no-color', '--tail', '240', 'joomla'], { allowFailure: true }));

  return diagnostics.join('\n');
}

module.exports = {
  root,
  baseUrl,
  targetName,
  buildZip,
  startStand,
  stopStand,
  waitForJoomla,
  defaultParams,
  pluginState,
  applyParams,
  seedArticle,
  installFromAdmin,
  openPluginForm,
  fetchArticle,
  collectDiagnostics
};
