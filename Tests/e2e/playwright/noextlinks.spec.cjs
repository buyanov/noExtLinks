const { test, expect } = require('@playwright/test');
const {
  defaultParams,
  applyParams,
  seedArticle,
  homeMenuItem,
  installFromAdmin,
  openPluginForm,
  fetchArticle,
  collectDiagnostics
} = require('./helpers.cjs');

test.describe.serial('NoExternalLinks Joomla E2E', () => {
  let extensionId;

  test.afterEach(async ({}, testInfo) => {
    if (testInfo.status !== testInfo.expectedStatus) {
      console.error(collectDiagnostics());
    }
  });

  test('installs from Joomla administrator UI and opens plugin form', async ({ page }) => {
    const state = await installFromAdmin(page);
    extensionId = state.extensionId;

    expect(state.installed).toBe(true);
    expect(extensionId).toBeGreaterThan(0);

    await openPluginForm(page, extensionId);
  });

  test('applies default SEO output only to external links', async ({ page }) => {
    const article = seedArticle(
      'default',
      defaultParams(),
      '<p><a href="https://google.com">google</a> <a href="/local">local</a> <a href="#top">top</a> <a href="tel:123">tel</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<!--noindex--><a href="https://google.com"');
    expect(html).toContain('target="_blank"');
    expect(html).toContain('rel="nofollow"');
    expect(html).toContain('class="external-link --set-title"');
    expect(html).toContain('<a href="/local">local</a>');
    expect(html).toContain('<a href="#top">top</a>');
    expect(html).toContain('<a href="tel:123">tel</a>');
  });

  test('honors disabled noindex, nofollow and target flags', async ({ page }) => {
    const article = seedArticle(
      'disabled-flags',
      defaultParams({ noindex: '0', nofollow: '0', blank: '0' }),
      '<p><a href="https://google.com">google</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<a href="https://google.com" title="google" class="external-link --set-title">google</a>');
    expect(html).not.toContain('rel="nofollow"');
    expect(html).not.toContain('target="_blank"');
    expect(html).not.toContain('<!--noindex-->');
  });

  test('renders JavaScript replacement mode', async ({ page }) => {
    const article = seedArticle(
      'usejs',
      defaultParams({ usejs: '1' }),
      '<p><a href="https://google.com">google</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<span data-href="https://google.com"');
    expect(html).toContain('class="external-link --set-title js-modify"');
    expect(html).toContain('span.external-link');
  });

  test('replaces anchors with full URL and host-only URL', async ({ page }) => {
    let article = seedArticle(
      'replace-anchor-url',
      defaultParams({ replace_anchor: '1' }),
      '<p><a href="https://google.com/path">google</a></p>'
    );
    let html = await fetchArticle(page, article.articleId);

    expect(html).toContain('--href-replaced');
    expect(html).toContain('>https://google.com/path</a>');

    article = seedArticle(
      'replace-anchor-host',
      defaultParams({ replace_anchor: '1', replace_anchor_host: '1' }),
      '<p><a href="https://google.com/path">google</a></p>'
    );
    html = await fetchArticle(page, article.articleId);

    expect(html).toContain('--href-replaced');
    expect(html).toContain('>google.com</a>');
  });

  test('absolutizes relative links without treating them as external', async ({ page }) => {
    const article = seedArticle(
      'absolutize',
      defaultParams({ absolutize: '1' }),
      '<p><a href="/local">local</a> <a href="https://google.com">google</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain(`<a href="${new URL('/local', page.url()).origin}/local">local</a>`);
    expect(html).toContain('<!--noindex--><a href="https://google.com"');
    expect(html).toContain('class="external-link --set-title"');
  });

  test('preserves existing link attributes while adding external markers', async ({ page }) => {
    const article = seedArticle(
      'preserve-attributes',
      defaultParams(),
      '<p><a href="https://google.com" id="source-link" title="Existing title" class="custom primary">google</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('id="source-link"');
    expect(html).toContain('title="Existing title"');
    expect(html).toContain('class="custom primary external-link"');
    expect(html).not.toContain('--set-title');
  });

  test('honors excluded domain masks and legacy whitelist', async ({ page }) => {
    let article = seedArticle(
      'excluded-domain',
      defaultParams({ excluded_domains: '{"scheme":["https"],"host":["google.com"],"path":["/docs/*"]}' }),
      '<p><a href="https://google.com/docs/page">google docs</a> <a href="https://google.com/other">google other</a> <a href="https://example.com">example</a></p>'
    );
    let html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<a href="https://google.com/docs/page">google docs</a>');
    expect(html).toContain('<!--noindex--><a href="https://google.com/other"');
    expect(html).toContain('<!--noindex--><a href="https://example.com"');

    article = seedArticle(
      'legacy-whitelist',
      defaultParams({ whitelist: 'https://google.com/*' }),
      '<p><a href="https://google.com/docs/page">google docs</a></p>'
    );
    html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<a href="https://google.com/docs/page">google docs</a>');
    expect(html).not.toContain('external-link');
  });

  test('removes configured domains', async ({ page }) => {
    const article = seedArticle(
      'removed-domain',
      defaultParams({ removed_domains: '{"host":["google.com"]}' }),
      '<p>before <a href="https://google.com/path">google</a> after <a href="https://example.com">example</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('before  after');
    expect(html).not.toContain('https://google.com');
    expect(html).toContain('https://example.com');
  });

  test('routes external links through configured redirect page', async ({ page }) => {
    const menuItem = homeMenuItem();
    const article = seedArticle(
      'redirect-page',
      defaultParams({ use_redirect_page: '1', redirect_page: String(menuItem.id) }),
      '<p><a href="https://google.com/path">google</a></p>'
    );

    const html = await fetchArticle(page, article.articleId);

    expect(html).toContain('--internal-redirect');
    expect(html).toContain('url=https%3A%2F%2Fgoogle.com%2Fpath');
    expect(html).not.toContain('<!--noindex-->');
    expect(html).not.toContain('rel="nofollow"');
  });

  test('skips excluded articles and categories', async ({ page }) => {
    let article = seedArticle(
      'excluded-article',
      defaultParams(),
      '<p><a href="https://google.com">google</a></p>'
    );

    applyParams(defaultParams({ excluded_articles: String(article.articleId) }));
    let html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<a href="https://google.com">google</a>');
    expect(html).not.toContain('external-link');

    article = seedArticle(
      'excluded-category',
      defaultParams({ excluded_category_list: [article.categoryId] }),
      '<p><a href="https://google.com">google</a></p>'
    );
    html = await fetchArticle(page, article.articleId);

    expect(html).toContain('<a href="https://google.com">google</a>');
    expect(html).not.toContain('external-link');
  });

  test('skips excluded current menu items and legacy menu item ids', async ({ page }) => {
    const menuItem = homeMenuItem();
    let article = seedArticle(
      'excluded-menu',
      defaultParams({ excluded_menu: [menuItem.id] }),
      '<p><a href="https://google.com">google</a></p>'
    );

    let html = await fetchArticle(page, article.articleId, { itemId: menuItem.id });

    expect(html).toContain('<a href="https://google.com">google</a>');
    expect(html).not.toContain('external-link');

    article = seedArticle(
      'legacy-excluded-menu',
      defaultParams({ excluded_menu_items: String(menuItem.id) }),
      '<p><a href="https://google.com">google</a></p>'
    );

    html = await fetchArticle(page, article.articleId, { itemId: menuItem.id });

    expect(html).toContain('<a href="https://google.com">google</a>');
    expect(html).not.toContain('external-link');
  });

  test('does not modify administrator output', async ({ page }) => {
    const response = await page.goto('/administrator/index.php');
    const html = await page.content();

    expect(response.status()).toBe(200);
    expect(html).not.toContain('class="external-link');
    expect(html).not.toContain('<!--noindex-->');
  });
});
