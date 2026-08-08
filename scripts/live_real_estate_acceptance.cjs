#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const rootDir = path.resolve(__dirname, '..');
const release = process.env.THP_RELEASE || '0.3.0';
const baseUrl = new URL(process.env.THP_BASE_URL || 'https://thai-land.co.il/');
const chromePath = process.env.THP_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const timeout = Number.parseInt(process.env.THP_LIVE_TIMEOUT_MS || '45000', 10);
const outputDir = path.join(rootDir, 'output', 'playwright');
const outputPrefix = `real-estate-live-${release}`;
const contract = JSON.parse(fs.readFileSync(path.join(rootDir, 'data', 'content', 'real-estate.json'), 'utf8'));
const socialImagePath = '/assets/content/images/real-estate-thailand-atlas-v1-1717.webp';

function presentationPhrases() {
  const source = fs.readFileSync(path.join(rootDir, 'tests', 'run.php'), 'utf8');
  const start = source.indexOf('$presentation_phrases = array(');
  const end = source.indexOf(');', start);
  if (start < 0 || end < 0) throw new Error('Presentation-language boundary was not found.');
  return [...source.slice(start, end).matchAll(/'([^']*)'/g)].map((match) => match[1]);
}

function routeUrl(route, label) {
  const url = new URL(route.path, baseUrl);
  url.searchParams.set('qa', `${release}-${label}-${Date.now()}`);
  return url.toString();
}

function canonicalFor(route) {
  return new URL(route.path, baseUrl).toString();
}

function socialTitle(route) {
  return `${route.public.seo_title} | Thai-Land.co.il`;
}

function normalizeRobots(value) {
  return new Set(String(value || '').split(',').map((item) => item.trim().toLowerCase()).filter(Boolean));
}

function listenForErrors(page) {
  const state = { console_errors: [], request_failures: [], bad_same_origin: [] };
  page.on('console', (message) => {
    if (message.type() === 'error') state.console_errors.push(message.text());
  });
  page.on('requestfailed', (request) => {
    if (request.url().startsWith(baseUrl.origin)) {
      state.request_failures.push({ url: request.url(), error: request.failure()?.errorText || '' });
    }
  });
  page.on('response', (response) => {
    if (response.url().startsWith(baseUrl.origin) && response.status() >= 400) {
      state.bad_same_origin.push({ url: response.url(), status: response.status() });
    }
  });
  return state;
}

async function inspectRoute(page, route, forbidden) {
  return page.evaluate(({ expected, phrases, version }) => {
    const bodyText = document.body.innerText;
    const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
    const robots = document.querySelector('meta[name="robots"]')?.content || '';
    const targetOwners = [...document.querySelectorAll('[data-thp-target-owner]')]
      .map((element) => element.getAttribute('data-thp-target-owner'))
      .filter(Boolean);
    const contentH2s = document.querySelectorAll('[data-thp-preserved-body] h2').length;
    const toc = document.querySelector('.thp-toc');
    const externalLinks = [...document.querySelectorAll('.thp-source-panel a[href]')];
    const expectedContinuationOwners = expected.continuations.map((item) => item.target_route_id).sort();
    const renderedContinuationOwners = [...document.querySelectorAll('[data-thp-relationship="sibling"]')]
      .map((element) => element.getAttribute('data-thp-target-owner'))
      .sort();

    return {
      ready_state: document.readyState,
      title: document.title,
      description: document.querySelector('meta[name="description"]')?.content || null,
      canonical: document.querySelector('link[rel="canonical"]')?.href || null,
      og_title: document.querySelector('meta[property="og:title"]')?.content || null,
      og_description: document.querySelector('meta[property="og:description"]')?.content || null,
      og_url: document.querySelector('meta[property="og:url"]')?.content || null,
      og_image: document.querySelector('meta[property="og:image"]')?.content || null,
      og_width: document.querySelector('meta[property="og:image:width"]')?.content || null,
      og_height: document.querySelector('meta[property="og:image:height"]')?.content || null,
      twitter_title: document.querySelector('meta[name="twitter:title"]')?.content || null,
      twitter_description: document.querySelector('meta[name="twitter:description"]')?.content || null,
      twitter_image: document.querySelector('meta[name="twitter:image"]')?.content || null,
      robots,
      lang: document.documentElement.lang,
      dir: document.documentElement.dir,
      body_class: document.body.className,
      admin_bar: Boolean(document.querySelector('#wpadminbar')),
      main_count: document.querySelectorAll('main').length,
      owner_main_count: document.querySelectorAll(`main[data-thp-owner-id="${expected.route_id}"]`).length,
      h1_count: document.querySelectorAll('h1').length,
      h1: document.querySelector('h1')?.innerText.trim() || null,
      preserved_body_count: document.querySelectorAll('[data-thp-preserved-body]').length,
      breadcrumb_count: document.querySelectorAll('[data-thp-breadcrumbs]').length,
      breadcrumb_items: document.querySelectorAll('[data-thp-breadcrumbs] li').length,
      breadcrumb_current: document.querySelector('[data-thp-breadcrumbs] [aria-current="page"]')?.innerText.trim() || null,
      source_panel_count: document.querySelectorAll('.thp-source-panel').length,
      source_count: externalLinks.length,
      unsafe_source_rel_count: externalLinks.filter((element) => !String(element.rel).split(/\s+/).includes('noopener')).length,
      hero_picture_sources: document.querySelectorAll('.thp-hero-art source').length,
      hero_image_count: document.querySelectorAll('.thp-hero-art img').length,
      release_asset_count: document.querySelectorAll(`link[href*="ver=${version}"],script[src*="ver=${version}"]`).length,
      overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      long_dash_count: (bodyText.match(/[\u2013\u2014]/g) || []).length,
      forbidden_hits: phrases.filter((phrase) => bodyText.includes(phrase)),
      duplicate_ids: [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))],
      unnamed_buttons: [...document.querySelectorAll('button')].filter((element) => !element.innerText.trim() && !element.getAttribute('aria-label') && !element.getAttribute('title')).length,
      unnamed_links: [...document.querySelectorAll('a')].filter((element) => !element.innerText.trim() && !element.getAttribute('aria-label') && !element.getAttribute('title')).length,
      missing_alt_images: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
      broken_images: [...document.images].filter((image) => !image.complete || image.naturalWidth === 0).map((image) => image.currentSrc || image.src),
      target_owners: [...new Set(targetOwners)].sort(),
      content_h2_count: contentH2s,
      toc_item_count: document.querySelectorAll('[data-thp-toc] li').length,
      toc_hidden: toc ? toc.hasAttribute('hidden') : null,
      parent_link_count: document.querySelectorAll('[data-thp-relationship="parent_hub"]').length,
      rendered_continuation_owners: renderedContinuationOwners,
      expected_continuation_owners: expectedContinuationOwners,
      hub_decision_cards: document.querySelectorAll('.thp-decision-card').length,
      hub_decision_links: document.querySelectorAll('[data-thp-relationship="decision"]').length,
      hub_guide_groups: document.querySelectorAll('.thp-guide-group').length,
      hub_guide_cards: document.querySelectorAll('.thp-guide-card').length,
      hub_child_owners: [...document.querySelectorAll('[data-thp-relationship="child_spoke"]')].map((element) => element.getAttribute('data-thp-target-owner')).sort(),
    };
  }, { expected: route, phrases: forbidden, version: release });
}

async function run() {
  fs.mkdirSync(outputDir, { recursive: true });
  if (!Number.isFinite(timeout) || timeout < 1000) throw new Error('THP_LIVE_TIMEOUT_MS must be at least 1000.');
  if (!fs.existsSync(chromePath)) throw new Error(`Installed Chrome was not found at ${chromePath}.`);

  const forbidden = presentationPhrases();
  const browser = await chromium.launch({ headless: true, executablePath: chromePath });
  const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl.toString(),
    release,
    contract_id: contract.contract_id,
    route_count: contract.routes.length,
    forbidden_phrase_count: forbidden.length,
    routes: {},
    responsive: {},
  };

  try {
    for (const route of contract.routes) {
      report.routes[route.route_id] = {};
      for (const profile of [
        { name: 'desktop', viewport: { width: 1440, height: 1000 }, isMobile: false },
        { name: 'mobile', viewport: { width: 390, height: 844 }, isMobile: true },
      ]) {
        const context = await browser.newContext({
          viewport: profile.viewport,
          isMobile: profile.isMobile,
          hasTouch: profile.isMobile,
          locale: 'he-IL',
          colorScheme: 'light',
          reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        const network = listenForErrors(page);
        const response = await page.goto(routeUrl(route, `${route.route_id}-${profile.name}`), {
          waitUntil: 'domcontentloaded',
          timeout,
        });
        await page.waitForSelector(`main[data-thp-owner-id="${route.route_id}"]`, { timeout: 15000 });
        await page.waitForTimeout(900);
        const inspection = await inspectRoute(page, route, forbidden);
        await page.screenshot({
          path: path.join(outputDir, `${outputPrefix}-${route.route_id}-${profile.name}-${profile.viewport.width}.png`),
          fullPage: true,
          animations: 'disabled',
        });
        report.routes[route.route_id][profile.name] = {
          http_status: response?.status() || null,
          final_url: page.url(),
          inspection,
          network,
        };
        await context.close();
      }
    }

    const hub = contract.routes.find((route) => route.route_id === contract.hub_route_id);
    if (!hub) throw new Error('The hub route is missing from the content contract.');
    const responsiveContext = await browser.newContext({
      viewport: { width: 320, height: 740 },
      isMobile: true,
      hasTouch: true,
      locale: 'he-IL',
      colorScheme: 'light',
      reducedMotion: 'reduce',
    });
    const page = await responsiveContext.newPage();
    const responsiveNetwork = listenForErrors(page);
    await page.goto(routeUrl(hub, 'responsive'), { waitUntil: 'domcontentloaded', timeout });
    await page.waitForSelector(`main[data-thp-owner-id="${hub.route_id}"]`, { timeout: 15000 });
    await page.waitForTimeout(600);

    for (const width of [320, 768, 1230]) {
      await page.setViewportSize({ width, height: width === 320 ? 740 : 900 });
      await page.waitForTimeout(120);
      report.responsive[String(width)] = await page.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scroll_width: document.documentElement.scrollWidth,
        overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        menu_display: getComputedStyle(document.querySelector('.thp-menu-toggle')).display,
        desktop_nav_display: getComputedStyle(document.querySelector('.thp-primary-nav')).display,
      }));
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(120);
    report.responsive.external_a11y_before = await page.evaluate(() => (
      [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      }))
    ));
    await page.locator('.thp-menu-toggle').click();
    await page.waitForTimeout(120);
    const firstFocusable = page.locator('.thp-mobile-nav-panel a[href], .thp-mobile-nav-panel button:not([disabled])').first();
    const lastFocusable = page.locator('.thp-mobile-nav-panel a[href], .thp-mobile-nav-panel button:not([disabled])').last();
    const firstIdentity = await firstFocusable.evaluate((element) => ({ tag: element.tagName, text: element.innerText.trim(), label: element.getAttribute('aria-label') }));
    await firstFocusable.focus();
    await page.keyboard.press('Shift+Tab');
    const shiftTabWrapped = await lastFocusable.evaluate((element) => element === document.activeElement);
    await page.keyboard.press('Tab');
    const tabWrapped = await firstFocusable.evaluate((element) => element === document.activeElement);
    report.responsive.menu_open = await page.evaluate(() => ({
      expanded: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded'),
      label: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-label'),
      drawer_hidden: document.querySelector('#thp-mobile-nav')?.hasAttribute('hidden'),
      dialog_count: document.querySelectorAll('#thp-mobile-nav [role="dialog"][aria-modal="true"]').length,
      focused_inside: Boolean(document.activeElement?.closest('#thp-mobile-nav')),
      body_open: document.body.classList.contains('thp-content-menu-open'),
      body_overflow: document.body.style.overflow,
      page_isolated: [
        document.querySelector('.thp-skip-link'),
        document.querySelector('.thp-header-inner'),
        document.querySelector('main'),
        document.querySelector('.thp-site-footer'),
      ].filter(Boolean).every((element) => element.inert === true && element.getAttribute('aria-hidden') === 'true'),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    report.responsive.menu_open.first_focusable = firstIdentity;
    report.responsive.menu_open.shift_tab_wrapped = shiftTabWrapped;
    report.responsive.menu_open.tab_wrapped = tabWrapped;
    await page.screenshot({
      path: path.join(outputDir, `${outputPrefix}-hub-mobile-menu-390.png`),
      fullPage: false,
      animations: 'disabled',
    });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(120);
    report.responsive.menu_closed = await page.evaluate(() => ({
      expanded: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded'),
      drawer_hidden: document.querySelector('#thp-mobile-nav')?.hasAttribute('hidden'),
      body_open: document.body.classList.contains('thp-content-menu-open'),
      body_overflow: document.body.style.overflow,
      focused_toggle: document.activeElement === document.querySelector('.thp-menu-toggle'),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    await page.locator('.thp-menu-toggle').click();
    await page.waitForTimeout(80);
    await page.setViewportSize({ width: 1231, height: 900 });
    await page.waitForTimeout(160);
    report.responsive['1231'] = await page.evaluate(() => ({
      width: document.documentElement.clientWidth,
      scroll_width: document.documentElement.scrollWidth,
      overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      menu_display: getComputedStyle(document.querySelector('.thp-menu-toggle')).display,
      desktop_nav_display: getComputedStyle(document.querySelector('.thp-primary-nav')).display,
      expanded: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded'),
      drawer_hidden: document.querySelector('#thp-mobile-nav')?.hasAttribute('hidden'),
      body_open: document.body.classList.contains('thp-content-menu-open'),
      focused_in_hidden_drawer: Boolean(document.activeElement?.closest('#thp-mobile-nav')),
      focused_visible_header: Boolean(document.activeElement?.closest('.thp-site-header')),
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    report.responsive.network = responsiveNetwork;
    await responsiveContext.close();
  } finally {
    await browser.close();
  }

  const checks = [];
  const add = (name, passed, detail = null) => checks.push({ name, passed: Boolean(passed), detail });
  const spokeIds = contract.routes.filter((route) => route.kind === 'spoke').map((route) => route.route_id).sort();
  for (const route of contract.routes) {
    for (const profile of ['desktop', 'mobile']) {
      const result = report.routes[route.route_id][profile];
      const value = result.inspection;
      const robots = normalizeRobots(value.robots);
      const prefix = `${route.route_id} ${profile}`;
      add(`${prefix}: HTTP 200`, result.http_status === 200, result.http_status);
      add(`${prefix}: exact canonical URL`, value.canonical === canonicalFor(route), value.canonical);
      add(`${prefix}: exact title`, value.title === socialTitle(route), value.title);
      add(`${prefix}: exact description`, value.description === route.public.meta_description, value.description);
      add(`${prefix}: exact Open Graph metadata`, value.og_title === socialTitle(route) && value.og_description === route.public.meta_description && value.og_url === canonicalFor(route));
      add(`${prefix}: exact X metadata`, value.twitter_title === socialTitle(route) && value.twitter_description === route.public.meta_description);
      add(`${prefix}: social image`, value.og_image?.endsWith(socialImagePath) && value.twitter_image?.endsWith(socialImagePath) && value.og_width === '1717' && value.og_height === '916');
      add(`${prefix}: robots index contract`, robots.has('index') && robots.has('follow') && robots.has('max-image-preview:large'), value.robots);
      add(`${prefix}: Hebrew RTL`, value.lang === 'he-IL' && value.dir === 'rtl');
      add(`${prefix}: one owned main and H1`, value.main_count === 1 && value.owner_main_count === 1 && value.h1_count === 1 && value.h1 === route.public.h1);
      add(`${prefix}: preserved body and breadcrumb`, value.preserved_body_count === 1 && value.breadcrumb_count === 1 && value.breadcrumb_items === route.breadcrumbs.length && value.breadcrumb_current === route.breadcrumbs.at(-1).label);
      add(`${prefix}: sources and hero`, value.source_panel_count === 1 && value.source_count === route.source_ids.length && value.unsafe_source_rel_count === 0 && value.hero_picture_sources === 2 && value.hero_image_count === 1);
      add(`${prefix}: release assets`, value.release_asset_count >= 2, value.release_asset_count);
      add(`${prefix}: clean public surface`, value.admin_bar === false && value.long_dash_count === 0 && value.forbidden_hits.length === 0 && value.duplicate_ids.length === 0 && value.unnamed_buttons === 0 && value.unnamed_links === 0 && value.missing_alt_images === 0 && value.broken_images.length === 0);
      add(`${prefix}: no horizontal overflow`, value.overflow_pixels === 0, value.overflow_pixels);
      add(`${prefix}: network clean`, result.network.console_errors.length === 0 && result.network.request_failures.length === 0 && result.network.bad_same_origin.length === 0, result.network);
      if (route.kind === 'hub') {
        add(`${prefix}: hub decision and guide structure`, value.hub_decision_cards === 3 && value.hub_decision_links === 9 && value.hub_guide_groups === 3 && value.hub_guide_cards === 7 && JSON.stringify(value.hub_child_owners) === JSON.stringify(spokeIds));
      } else {
        add(`${prefix}: spoke hierarchy`, value.parent_link_count === 2 && JSON.stringify(value.rendered_continuation_owners) === JSON.stringify(value.expected_continuation_owners));
        add(`${prefix}: generated article navigation`, value.toc_item_count === value.content_h2_count && value.toc_hidden === (value.content_h2_count === 0));
      }
    }
  }

  for (const width of ['320', '768', '1230']) {
    add(`${width}px: mobile navigation and no overflow`, report.responsive[width].overflow_pixels === 0 && report.responsive[width].menu_display !== 'none' && report.responsive[width].desktop_nav_display === 'none', report.responsive[width]);
  }
  const before = report.responsive.external_a11y_before;
  const open = report.responsive.menu_open;
  const closed = report.responsive.menu_closed;
  const desktop = report.responsive['1231'];
  add('mobile drawer opens as an isolated dialog', open.expanded === 'true' && open.label === 'סגירת תפריט' && open.drawer_hidden === false && open.dialog_count === 1 && open.focused_inside === true && open.body_open === true && open.body_overflow === 'hidden' && open.page_isolated === true);
  add('mobile focus trap wraps in both directions', open.shift_tab_wrapped === true && open.tab_wrapped === true);
  add('mobile drawer suppresses external accessibility controls', before.length === 2 && open.external_a11y_controls.every((control) => control.inert === true && control.aria_hidden === 'true' && control.visibility === 'hidden' && control.pointer_events === 'none'));
  add('Escape closes and restores the mobile page', closed.expanded === 'false' && closed.drawer_hidden === true && closed.body_open === false && closed.body_overflow === '' && closed.focused_toggle === true && JSON.stringify(closed.external_a11y_controls) === JSON.stringify(before));
  add('1231px closes the drawer and restores visible desktop focus', desktop.overflow_pixels === 0 && desktop.menu_display === 'none' && desktop.desktop_nav_display !== 'none' && desktop.expanded === 'false' && desktop.drawer_hidden === true && desktop.body_open === false && desktop.focused_in_hidden_drawer === false && desktop.focused_visible_header === true && JSON.stringify(desktop.external_a11y_controls) === JSON.stringify(before));
  add('responsive network is clean', report.responsive.network.console_errors.length === 0 && report.responsive.network.request_failures.length === 0 && report.responsive.network.bad_same_origin.length === 0, report.responsive.network);

  report.acceptance = {
    passed: checks.every((check) => check.passed),
    total: checks.length,
    passed_count: checks.filter((check) => check.passed).length,
    failed_count: checks.filter((check) => !check.passed).length,
    checks,
    failures: checks.filter((check) => !check.passed),
  };

  const reportPath = path.join(outputDir, `${outputPrefix}-acceptance.json`);
  fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  process.stdout.write(`${JSON.stringify({
    report: reportPath,
    passed: report.acceptance.passed,
    total: report.acceptance.total,
    passed_count: report.acceptance.passed_count,
    failed_count: report.acceptance.failed_count,
    failures: report.acceptance.failures,
  }, null, 2)}\n`);
  if (!report.acceptance.passed) process.exitCode = 1;
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
