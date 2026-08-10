#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const rootDir = path.resolve(__dirname, '..');
const release = process.env.THP_RELEASE || '0.4.0';
const baseUrl = process.env.THP_BASE_URL || 'https://thai-land.co.il/';
const chromePath = process.env.THP_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const outputDir = path.join(rootDir, 'output', 'playwright');
const outputPrefix = `homepage-live-${release}`;
const expectedTitle = 'תאילנד: טיולים, מעבר, נדל״ן ועסקים | Thai-Land.co.il';
const expectedDescription = 'תאילנד בעברית: יעדים, מסלולים, מגורים, נדל״ן ועסקים לישראלים בבנגקוק, פוקט, קוסמוי וצ׳יאנג מאי.';
const expectedSocialImagePath = '/assets/homepage/images/homepage-hero-thailand-system-v1-1713.webp';
const guideContract = JSON.parse(fs.readFileSync(path.join(rootDir, 'data', 'content', 'priority-guides.json'), 'utf8'));
const expectedGuideHubs = guideContract.routes
  .filter((route) => route.kind === 'collection')
  .map((route) => ({
    route_id: route.route_id,
    path: route.path,
    anchor: route.ownership.primary_keyword,
  }));

function presentationPhrases() {
  const source = fs.readFileSync(path.join(rootDir, 'tests', 'run.php'), 'utf8');
  const start = source.indexOf('$presentation_phrases = array(');
  const end = source.indexOf(');', start);

  if (start < 0 || end < 0) {
    throw new Error('Could not find the presentation-language boundary in tests/run.php.');
  }

  return [...source.slice(start, end).matchAll(/'([^']*)'/g)].map((match) => match[1]);
}

function pageUrl(label) {
  const url = new URL(baseUrl);
  url.searchParams.set('qa', `${release}-${label}-${Date.now()}`);
  return url.toString();
}

async function run() {
  fs.mkdirSync(outputDir, { recursive: true });

  if (!fs.existsSync(chromePath)) {
    throw new Error(`Installed Chrome was not found at ${chromePath}.`);
  }

  const forbidden = presentationPhrases();
  const browser = await chromium.launch({
    headless: true,
    executablePath: chromePath,
  });
  const report = {
    generated_at: new Date().toISOString(),
    url: baseUrl,
    release,
    forbidden_phrase_count: forbidden.length,
    desktop: {},
    mobile: {},
  };

  try {
    const desktopContext = await browser.newContext({
      viewport: { width: 1440, height: 1000 },
      locale: 'he-IL',
      colorScheme: 'light',
      reducedMotion: 'reduce',
    });
    const desktop = await desktopContext.newPage();
    const desktopConsoleErrors = [];
    const desktopRequestFailures = [];
    const desktopBadSameOrigin = [];

    desktop.on('console', (message) => {
      if (message.type() === 'error') desktopConsoleErrors.push(message.text());
    });
    desktop.on('requestfailed', (request) => {
      if (request.url().startsWith(baseUrl)) {
        desktopRequestFailures.push({
          url: request.url(),
          error: request.failure()?.errorText || '',
        });
      }
    });
    desktop.on('response', (response) => {
      if (response.url().startsWith(baseUrl) && response.status() >= 400) {
        desktopBadSameOrigin.push({ url: response.url(), status: response.status() });
      }
    });

    const desktopResponse = await desktop.goto(pageUrl('desktop'), {
      waitUntil: 'domcontentloaded',
      timeout: 45000,
    });
    await desktop.waitForSelector('body.thailand-platform-home', { timeout: 15000 });
    await desktop.waitForTimeout(1800);
    await desktop.screenshot({
      path: path.join(outputDir, `${outputPrefix}-desktop-1440.png`),
      fullPage: true,
      animations: 'disabled',
    });

    report.desktop.initial = await desktop.evaluate((phrases) => {
      const bodyText = document.body.innerText;
      const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
      const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
      const schemaTypes = [...document.querySelectorAll('script[type="application/ld+json"]')].flatMap((script) => {
        try {
          const value = JSON.parse(script.textContent);
          const nodes = Array.isArray(value)
            ? value
            : (Array.isArray(value?.['@graph']) ? value['@graph'] : [value]);
          return nodes.flatMap((node) => Array.isArray(node?.['@type']) ? node['@type'] : [node?.['@type']]).filter(Boolean);
        } catch {
          return ['INVALID_JSON'];
        }
      });
      const unnamedButtons = [...document.querySelectorAll('button')].filter((element) => (
        !element.innerText.trim()
        && !element.getAttribute('aria-label')
        && !element.getAttribute('title')
      )).length;
      const unnamedLinks = [...document.querySelectorAll('a')].filter((element) => (
        !element.innerText.trim()
        && !element.getAttribute('aria-label')
        && !element.getAttribute('title')
      )).length;
      const missingAltImages = [...document.images].filter((image) => !image.hasAttribute('alt')).length;
      const brokenImages = [...document.images]
        .filter((image) => !image.complete || image.naturalWidth === 0)
        .map((image) => image.currentSrc || image.src);

      return {
        title: document.title,
        h1_count: document.querySelectorAll('h1').length,
        h1: document.querySelector('h1')?.innerText.trim() || null,
        description: document.querySelector('meta[name="description"]')?.content || null,
        open_graph_description: document.querySelector('meta[property="og:description"]')?.content || null,
        open_graph_image: document.querySelector('meta[property="og:image"]')?.content || null,
        open_graph_image_width: document.querySelector('meta[property="og:image:width"]')?.content || null,
        open_graph_image_height: document.querySelector('meta[property="og:image:height"]')?.content || null,
        twitter_description: document.querySelector('meta[name="twitter:description"]')?.content || null,
        twitter_image: document.querySelector('meta[name="twitter:image"]')?.content || null,
        canonical: document.querySelector('link[rel="canonical"]')?.href || null,
        robots: document.querySelector('meta[name="robots"]')?.content || null,
        lang: document.documentElement.lang,
        dir: document.documentElement.dir,
        body_class: document.body.className,
        platform_live: document.body.classList.contains('thailand-platform-home')
          && !document.body.classList.contains('thailand-platform-canary'),
        admin_bar: Boolean(document.querySelector('#wpadminbar')),
        overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        long_dash_count: (bodyText.match(/[\u2013\u2014]/g) || []).length,
        forbidden_hits: phrases.filter((phrase) => bodyText.includes(phrase)),
        schema_types: [...new Set(schemaTypes)],
        duplicate_ids: duplicateIds,
        unnamed_buttons: unnamedButtons,
        unnamed_links: unnamedLinks,
        missing_alt_images: missingAltImages,
        broken_images: brokenImages,
        internal_links: [...new Set(
          [...document.querySelectorAll('a[href]')]
            .map((anchor) => anchor.href)
            .filter((href) => href.startsWith(location.origin)),
        )].length,
      };
    }, forbidden);
    report.desktop.initial.versioned_asset = await desktop.locator(`link[href*="ver=${release}"],script[src*="ver=${release}"]`).count() > 0;
    report.desktop.guide_hierarchy = await desktop.evaluate((hubs) => {
      const surfaces = ['desktop', 'mobile', 'footer'];
      return hubs.map((hub) => ({
        ...hub,
        surfaces: surfaces.map((surface) => {
          const container = document.querySelector(`[data-thp-guides-home-nav="${surface}"]`);
          const links = container
            ? [...container.querySelectorAll(`[data-thp-guides-home-link="${hub.route_id}"]`)]
            : [];
          return {
            surface,
            count: links.length,
            href: links[0]?.href || '',
            text: (links[0]?.textContent || '').replace(/\s+/g, ' ').trim(),
          };
        }),
      }));
    }, expectedGuideHubs);
    report.desktop.guide_target_statuses = [];
    for (const hub of expectedGuideHubs) {
      const target = new URL(hub.path, baseUrl).toString();
      const response = await desktopContext.request.get(target, { failOnStatusCode: false, timeout: 45000 });
      report.desktop.guide_target_statuses.push({ route_id: hub.route_id, url: response.url(), status: response.status() });
      await response.dispose();
    }

    await desktop.locator('[data-global-search] input').fill('פוקט');
    await desktop.waitForTimeout(200);
    const searchOptions = await desktop.locator('[role="option"]').allInnerTexts();
    await desktop.locator('[data-atlas] [data-place="phuket"]').click();
    await desktop.waitForTimeout(100);
    const selectedPlace = (await desktop.locator('[data-place-name]').innerText()).trim();
    await desktop.locator('.atlas-panel [data-save]').click();
    await desktop.waitForTimeout(100);
    const savedCount = (await desktop.locator('[data-saved-count]').first().innerText()).trim();
    await desktop.locator('[data-atlas-view="list"]').click();
    const listPressed = await desktop.locator('[data-atlas-view="list"]').getAttribute('aria-pressed');

    report.desktop.interactions = {
      search_options: searchOptions,
      selected_place: selectedPlace,
      saved_count: savedCount,
      list_pressed: listPressed,
    };
    report.desktop.network = {
      console_errors: desktopConsoleErrors,
      request_failures: desktopRequestFailures,
      bad_same_origin: desktopBadSameOrigin,
    };
    report.desktop.http_status = desktopResponse?.status() || null;
    await desktopContext.close();

    const mobileContext = await browser.newContext({
      viewport: { width: 390, height: 844 },
      isMobile: true,
      hasTouch: true,
      locale: 'he-IL',
      colorScheme: 'light',
      reducedMotion: 'reduce',
    });
    const mobile = await mobileContext.newPage();
    const mobileConsoleErrors = [];
    const mobileBadSameOrigin = [];

    mobile.on('console', (message) => {
      if (message.type() === 'error') mobileConsoleErrors.push(message.text());
    });
    mobile.on('response', (response) => {
      if (response.url().startsWith(baseUrl) && response.status() >= 400) {
        mobileBadSameOrigin.push({ url: response.url(), status: response.status() });
      }
    });

    const mobileResponse = await mobile.goto(pageUrl('mobile'), {
      waitUntil: 'domcontentloaded',
      timeout: 45000,
    });
    await mobile.waitForSelector('body.thailand-platform-home', { timeout: 15000 });
    await mobile.waitForTimeout(1400);
    await mobile.screenshot({
      path: path.join(outputDir, `${outputPrefix}-mobile-390.png`),
      fullPage: true,
      animations: 'disabled',
    });

    report.mobile.initial = await mobile.evaluate(() => ({
      ready_state: document.readyState,
      h1_count: document.querySelectorAll('h1').length,
      h1: document.querySelector('h1')?.innerText.trim() || null,
      width: document.documentElement.clientWidth,
      scroll_width: document.documentElement.scrollWidth,
      overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      menu_visible: getComputedStyle(document.querySelector('.menu-toggle')).display !== 'none',
      body_class: document.body.className,
      long_dash_count: (document.body.innerText.match(/[\u2013\u2014]/g) || []).length,
    }));
    report.mobile.external_a11y_before = await mobile.evaluate(() => (
      [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      }))
    ));

    await mobile.locator('.menu-toggle').click();
    await mobile.waitForTimeout(150);
    report.mobile.menu_open = await mobile.evaluate(() => ({
      expanded: document.querySelector('.menu-toggle')?.getAttribute('aria-expanded'),
      drawer_hidden: document.querySelector('#mobile-drawer')?.hasAttribute('hidden'),
      drawer_visible: Boolean(document.querySelector('#mobile-drawer'))
        && getComputedStyle(document.querySelector('#mobile-drawer')).display !== 'none',
      dialog_count: document.querySelectorAll('#mobile-drawer [role="dialog"]').length,
      links: document.querySelectorAll('#mobile-drawer a').length,
      focused_inside: Boolean(document.activeElement?.closest('#mobile-drawer')),
      background_inert: [...document.querySelector('.thp-home').children]
        .filter((element) => element.id !== 'mobile-drawer')
        .every((element) => element.inert === true && element.getAttribute('aria-hidden') === 'true'),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
      overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    }));
    await mobile.screenshot({
      path: path.join(outputDir, `${outputPrefix}-mobile-menu-390.png`),
      fullPage: false,
      animations: 'disabled',
    });
    await mobile.keyboard.press('Escape');
    await mobile.waitForTimeout(100);
    report.mobile.menu_closed = await mobile.evaluate(() => ({
      expanded: document.querySelector('.menu-toggle')?.getAttribute('aria-expanded'),
      drawer_hidden: document.querySelector('#mobile-drawer')?.hasAttribute('hidden'),
      body_drawer_open: document.body.classList.contains('drawer-open'),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    await mobile.locator('.menu-toggle').click();
    await mobile.waitForTimeout(100);
    await mobile.setViewportSize({ width: 1231, height: 900 });
    await mobile.waitForTimeout(150);
    report.mobile.desktop_breakpoint_close = await mobile.evaluate(() => ({
      expanded: document.querySelector('.menu-toggle')?.getAttribute('aria-expanded'),
      drawer_hidden: document.querySelector('#mobile-drawer')?.hasAttribute('hidden'),
      body_drawer_open: document.body.classList.contains('drawer-open'),
      menu_display: getComputedStyle(document.querySelector('.menu-toggle')).display,
      focused_in_hidden_drawer: Boolean(document.activeElement?.closest('#mobile-drawer')),
      focused_visible_header: Boolean(document.activeElement?.closest('.site-header')),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    report.mobile.network = {
      console_errors: mobileConsoleErrors,
      bad_same_origin: mobileBadSameOrigin,
    };
    report.mobile.http_status = mobileResponse?.status() || null;
    await mobileContext.close();
  } finally {
    await browser.close();
  }

  const checks = [
    ['desktop HTTP status is 200', report.desktop.http_status === 200],
    ['desktop title is current', report.desktop.initial.title === expectedTitle],
    ['desktop has one H1', report.desktop.initial.h1_count === 1],
    ['desktop description is current', report.desktop.initial.description === expectedDescription],
    ['Open Graph description is current', report.desktop.initial.open_graph_description === expectedDescription],
    ['X description is current', report.desktop.initial.twitter_description === expectedDescription],
    ['Open Graph image is current', report.desktop.initial.open_graph_image?.endsWith(expectedSocialImagePath)],
    ['X image is current', report.desktop.initial.twitter_image?.endsWith(expectedSocialImagePath)],
    ['Open Graph image width is correct', report.desktop.initial.open_graph_image_width === '1713'],
    ['Open Graph image height is correct', report.desktop.initial.open_graph_image_height === '918'],
    ['canonical points to the homepage', report.desktop.initial.canonical === new URL('/', baseUrl).toString()],
    ['document language is Hebrew', report.desktop.initial.lang === 'he-IL'],
    ['document direction is RTL', report.desktop.initial.dir === 'rtl'],
    ['public homepage is live', report.desktop.initial.platform_live === true],
    ['public homepage has no admin bar', report.desktop.initial.admin_bar === false],
    ['desktop has no horizontal overflow', report.desktop.initial.overflow_pixels === 0],
    ['desktop has no long dashes', report.desktop.initial.long_dash_count === 0],
    ['desktop has no presentation phrases', report.desktop.initial.forbidden_hits.length === 0],
    ['desktop has no duplicate IDs', report.desktop.initial.duplicate_ids.length === 0],
    ['desktop has no unnamed buttons', report.desktop.initial.unnamed_buttons === 0],
    ['desktop has no unnamed links', report.desktop.initial.unnamed_links === 0],
    ['desktop images all have alt attributes', report.desktop.initial.missing_alt_images === 0],
    ['desktop images load', report.desktop.initial.broken_images.length === 0],
    ['WebSite schema exists', report.desktop.initial.schema_types.includes('WebSite')],
    ['Organization schema exists', report.desktop.initial.schema_types.includes('Organization')],
    ['release asset version is live', report.desktop.initial.versioned_asset === true],
    ['published Guide hubs appear once in desktop, mobile, and footer navigation', report.desktop.guide_hierarchy.length === 2
      && report.desktop.guide_hierarchy.every((hub) => hub.surfaces.length === 3
        && hub.surfaces.every((surface) => surface.count === 1
          && surface.text === hub.anchor
          && new URL(surface.href).toString() === new URL(hub.path, baseUrl).toString()))],
    ['published Guide hub targets return 200', report.desktop.guide_target_statuses.length === 2
      && report.desktop.guide_target_statuses.every((target) => target.status === 200)],
    ['search suggestions work', report.desktop.interactions.search_options.length > 0],
    ['map selection works', Boolean(report.desktop.interactions.selected_place)],
    ['save control works', report.desktop.interactions.saved_count === '1'],
    ['list view works', report.desktop.interactions.list_pressed === 'true'],
    ['desktop same-origin requests load', report.desktop.network.request_failures.length === 0],
    ['desktop has no same-origin HTTP errors', report.desktop.network.bad_same_origin.length === 0],
    ['mobile HTTP status is 200', report.mobile.http_status === 200],
    ['mobile has one H1', report.mobile.initial.h1_count === 1],
    ['mobile has no horizontal overflow', report.mobile.initial.overflow_pixels === 0],
    ['mobile menu control is visible', report.mobile.initial.menu_visible === true],
    ['mobile has no long dashes', report.mobile.initial.long_dash_count === 0],
    ['mobile menu expands', report.mobile.menu_open.expanded === 'true'],
    ['mobile drawer is exposed', report.mobile.menu_open.drawer_hidden === false && report.mobile.menu_open.drawer_visible === true],
    ['mobile drawer has one dialog', report.mobile.menu_open.dialog_count === 1],
    ['mobile drawer contains navigation', report.mobile.menu_open.links > 0],
    ['mobile drawer receives focus', report.mobile.menu_open.focused_inside === true],
    ['mobile background is inert', report.mobile.menu_open.background_inert === true],
    ['mobile drawer has no horizontal overflow', report.mobile.menu_open.overflow_pixels === 0],
    ['both external accessibility controls are present', JSON.stringify(report.mobile.external_a11y_before.map((control) => control.id).sort()) === JSON.stringify(['pojo-a11y-skip-content', 'pojo-a11y-toolbar'])],
    ['external accessibility control is suppressed while the drawer is open', report.mobile.menu_open.external_a11y_controls.every((control) => (
      control.inert === true
      && control.aria_hidden === 'true'
      && control.visibility === 'hidden'
      && control.pointer_events === 'none'
    ))],
    ['Escape closes the mobile drawer', report.mobile.menu_closed.expanded === 'false' && report.mobile.menu_closed.drawer_hidden === true && report.mobile.menu_closed.body_drawer_open === false],
    ['Escape restores external accessibility controls', JSON.stringify(report.mobile.menu_closed.external_a11y_controls) === JSON.stringify(report.mobile.external_a11y_before)],
    ['desktop breakpoint closes the mobile drawer', report.mobile.desktop_breakpoint_close.expanded === 'false'
      && report.mobile.desktop_breakpoint_close.drawer_hidden === true
      && report.mobile.desktop_breakpoint_close.body_drawer_open === false
      && report.mobile.desktop_breakpoint_close.menu_display === 'none'
      && report.mobile.desktop_breakpoint_close.focused_in_hidden_drawer === false
      && report.mobile.desktop_breakpoint_close.focused_visible_header === true],
    ['desktop breakpoint restores external accessibility controls', JSON.stringify(report.mobile.desktop_breakpoint_close.external_a11y_controls) === JSON.stringify(report.mobile.external_a11y_before)],
    ['mobile has no same-origin HTTP errors', report.mobile.network.bad_same_origin.length === 0],
  ];
  report.acceptance = {
    passed: checks.every(([, passed]) => passed),
    checks: checks.map(([name, passed]) => ({ name, passed })),
    failures: checks.filter(([, passed]) => !passed).map(([name]) => name),
  };

  const reportPath = path.join(outputDir, `${outputPrefix}-acceptance.json`);
  fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);

  if (!report.acceptance.passed) {
    process.exitCode = 1;
  }
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
