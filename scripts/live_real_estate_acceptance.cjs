#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const rootDir = path.resolve(__dirname, '..');
const release = process.env.THP_RELEASE || '0.3.4';
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

function exactUrl(value) {
  return new URL(value).href.replace(/%[0-9a-f]{2}/gi, (sequence) => sequence.toUpperCase());
}

function sameUrl(left, right) {
  try {
    return exactUrl(left) === exactUrl(right);
  } catch {
    return false;
  }
}

function normalizeRobots(value) {
  return new Set(String(value || '').split(',').map((item) => item.trim().toLowerCase()).filter(Boolean));
}

function listenForErrors(page) {
  const state = { console_errors: [], request_failures: [], bad_same_origin: [] };
  page.on('console', (message) => {
    if (message.type() === 'error') {
      state.console_errors.push({
        text: message.text(),
        url: message.location()?.url || '',
      });
    }
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

function unexpectedConsoleErrors(errors) {
  return errors.filter((error) => {
    const text = typeof error === 'string' ? error : error.text;
    const url = typeof error === 'string' ? '' : error.url;
    return !(
      text.includes('embed.tawk.to')
      || url.startsWith('https://embed.tawk.to/')
    );
  });
}

function parseColor(value) {
  const match = String(value || '').match(/^rgba?\(\s*([\d.]+)[, ]+\s*([\d.]+)[, ]+\s*([\d.]+)(?:\s*[,/]\s*([\d.]+))?\s*\)$/i);
  if (!match) return null;
  return [Number(match[1]), Number(match[2]), Number(match[3]), match[4] === undefined ? 1 : Number(match[4])];
}

function sameColor(value, expected, tolerance = 0.01) {
  const actual = parseColor(value);
  return actual !== null && actual.every((channel, index) => Math.abs(channel - expected[index]) <= tolerance);
}

function contrastRatio(foreground, background) {
  const linear = (channel) => {
    const normalized = channel / 255;
    return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
  };
  const luminance = (color) => 0.2126 * linear(color[0]) + 0.7152 * linear(color[1]) + 0.0722 * linear(color[2]);
  const first = luminance(foreground);
  const second = luminance(background);
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

async function stabilizeForCapture(page) {
  await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach((image) => { image.loading = 'eager'; });
    if (document.fonts?.ready) await document.fonts.ready;
    await Promise.all(images.map(async (image) => {
      if (!image.complete) await new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      });
      if (typeof image.decode === 'function') await image.decode().catch(() => {});
    }));
  });
  await page.waitForFunction(() => {
    const height = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    const now = performance.now();
    const state = window.__thpCaptureHeightState;
    if (!state || state.height !== height) {
      window.__thpCaptureHeightState = { height, since: now };
      return false;
    }
    return now - state.since >= 300;
  }, null, { polling: 100, timeout: 5000 });
}

async function capturePage(page, basename) {
  await stabilizeForCapture(page);
  // Keep long visual evidence continuous. Sticky controls are verified in the
  // responsive state matrix below, while route captures render the same header
  // in normal flow so it cannot replace content at a segment boundary.
  const captureHeaderStyle = await page.addStyleTag({ content: '.thp-site-header { position: relative !important; top: auto !important; } #pojo-a11y-toolbar, #pojo-a11y-skip-content { visibility: hidden !important; pointer-events: none !important; }' });
  const dimensions = await page.evaluate(() => ({
    width: document.documentElement.clientWidth,
    height: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight),
  }));
  const files = [];
  const segments = [];
  if (dimensions.height <= 7000) {
    const filename = `${basename}.png`;
    const screenshotPath = path.join(outputDir, filename);
    await page.screenshot({
      path: screenshotPath,
      fullPage: true,
      animations: 'disabled',
      scale: 'css',
    });
    const png = fs.readFileSync(screenshotPath);
    files.push(filename);
    segments.push({ filename, top: 0, height: dimensions.height, bottom: dimensions.height, overlap: 0, png_width: png.readUInt32BE(16), png_height: png.readUInt32BE(20) });
  } else {
    const segmentHeight = 6500;
    const overlap = 160;
    const step = segmentHeight - overlap;
    const originalViewport = page.viewportSize();
    await page.setViewportSize({ width: dimensions.width, height: segmentHeight });
    await page.waitForFunction((expected) => innerWidth === expected.width && innerHeight === expected.height, { width: dimensions.width, height: segmentHeight });
    const captureHeight = await page.evaluate(() => Math.max(document.body.scrollHeight, document.documentElement.scrollHeight));
    const targets = [];
    for (let top = 0; top < captureHeight; top += step) {
      const height = Math.min(segmentHeight, captureHeight - top);
      targets.push({ top, height });
      if (top + height >= captureHeight) break;
    }
    for (let index = 0; index < targets.length; index += 1) {
      const targetTop = targets[index].top;
      const targetHeight = targets[index].height;
      await page.setViewportSize({ width: dimensions.width, height: targetHeight });
      await page.waitForFunction((expected) => innerWidth === expected.width && innerHeight === expected.height, { width: dimensions.width, height: targetHeight });
      await page.evaluate(async (top) => {
        window.scrollTo(0, top);
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      }, targetTop);
      const actualTop = await page.evaluate(() => window.scrollY);
      const actualHeight = await page.evaluate(() => innerHeight);
      const filename = `${basename}-part-${String(index + 1).padStart(2, '0')}.png`;
      const screenshotPath = path.join(outputDir, filename);
      await page.screenshot({
        path: screenshotPath,
        fullPage: false,
        animations: 'disabled',
        scale: 'css',
      });
      const png = fs.readFileSync(screenshotPath);
      const previousBottom = segments.at(-1)?.bottom ?? actualTop;
      files.push(filename);
      segments.push({
        filename,
        target_top: targetTop,
        target_height: targetHeight,
        top: actualTop,
        height: actualHeight,
        bottom: actualTop + actualHeight,
        overlap: index === 0 ? 0 : previousBottom - actualTop,
        png_width: png.readUInt32BE(16),
        png_height: png.readUInt32BE(20),
      });
    }
    if (originalViewport) await page.setViewportSize(originalViewport);
  }
  const finalHeight = await page.evaluate(() => Math.max(document.body.scrollHeight, document.documentElement.scrollHeight));
  await captureHeaderStyle.evaluate((element) => element.remove());
  return {
    files,
    segments,
    width: dimensions.width,
    measured_height: dimensions.height,
    final_height: finalHeight,
    coverage_complete: segments.at(-1)?.bottom >= finalHeight,
    persistent_header_normalized: true,
    external_fixed_controls_normalized: true,
  };
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
    const inspectAction = (selector, childSelector = null) => {
      const element = document.querySelector(selector);
      const styledElement = childSelector ? element?.querySelector(childSelector) : element;
      if (!element || !styledElement) return null;
      const style = getComputedStyle(styledElement);
      const elementRect = element.getBoundingClientRect();
      const range = document.createRange();
      range.selectNodeContents(styledElement);
      const textRect = range.getBoundingClientRect();
      const containerStyle = getComputedStyle(element);
      return {
        text: element.innerText.trim(),
        color: style.color,
        text_fill_color: style.webkitTextFillColor,
        background_color: style.backgroundColor,
        background_image: containerStyle.backgroundImage,
        display: style.display,
        visibility: style.visibility,
        opacity: style.opacity,
        pointer_events: style.pointerEvents,
        element_width: elementRect.width,
        element_height: elementRect.height,
        text_width: textRect.width,
        text_height: textRect.height,
      };
    };

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
      hello_pink_link_count: [...document.querySelectorAll('.thp-content a[href]')].filter((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && style.visibility === 'visible' && style.color === 'rgb(204, 51, 102)';
      }).length,
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
      hero_action: inspectAction('.thp-content-hero .thp-button-light'),
      aside_hub_action: inspectAction('.thp-aside-hub-link'),
      continuation_action: inspectAction('.thp-continuation-card', 'strong'),
      footer_action: inspectAction('.thp-site-footer .thp-button-light'),
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
        const screenshotCapture = await capturePage(
          page,
          `${outputPrefix}-${route.route_id}-${profile.name}-${profile.viewport.width}`,
        );
        report.routes[route.route_id][profile.name] = {
          http_status: response?.status() || null,
          final_url: page.url(),
          inspection,
          network,
          screenshots: screenshotCapture.files,
          screenshot_capture: screenshotCapture,
        };
        await context.close();
      }
    }

    const hub = contract.routes.find((route) => route.route_id === contract.hub_route_id);
    if (!hub) throw new Error('The hub route is missing from the content contract.');
    const responsiveContext = await browser.newContext({
      viewport: { width: 320, height: 740 },
      // Route captures above exercise Chrome's touch-only mobile emulation.
      // Keep this state matrix pointer-capable so hover, focus, keyboard, and
      // pointer assertions test real interaction states instead of asking a
      // touch-only context to synthesize an impossible hover capability.
      isMobile: false,
      hasTouch: false,
      locale: 'he-IL',
      colorScheme: 'light',
      reducedMotion: 'reduce',
    });
    const page = await responsiveContext.newPage();
    const responsiveNetwork = listenForErrors(page);
    await page.goto(routeUrl(hub, 'responsive'), { waitUntil: 'domcontentloaded', timeout });
    await page.waitForSelector(`main[data-thp-owner-id="${hub.route_id}"]`, { timeout: 15000 });
    await page.waitForTimeout(600);

    const inspectMenuControl = () => page.locator('.thp-menu-toggle').evaluate((element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return {
        color: style.color,
        background_color: style.backgroundColor,
        border_color: style.borderColor,
        outline_color: style.outlineColor,
        outline_width: style.outlineWidth,
        outline_offset: style.outlineOffset,
        display: style.display,
        visibility: style.visibility,
        opacity: style.opacity,
        rect_count: element.getClientRects().length,
        width: rect.width,
        height: rect.height,
        hovered: element.matches(':hover'),
        focused: element.matches(':focus'),
      };
    });
    const inspectHeaderSearchControl = () => page.locator('.thp-header-search button').evaluate((element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return {
        color: style.color,
        background_color: style.backgroundColor,
        border_color: style.borderColor,
        outline_color: style.outlineColor,
        outline_width: style.outlineWidth,
        outline_offset: style.outlineOffset,
        display: style.display,
        visibility: style.visibility,
        opacity: style.opacity,
        rect_count: element.getClientRects().length,
        width: rect.width,
        height: rect.height,
        hovered: element.matches(':hover'),
        focused: element.matches(':focus'),
      };
    });

    await page.waitForSelector('#pojo-a11y-toolbar .pojo-a11y-toolbar-toggle-link', { state: 'visible', timeout: 5000 });
    const inspectAccessibilityDock = () => page.evaluate(() => {
      const rect = (element) => {
        const value = element.getBoundingClientRect();
        return { x: value.x, y: value.y, right: value.right, bottom: value.bottom, width: value.width, height: value.height };
      };
      const intersects = (first, second) => (
        Math.max(0, Math.min(first.right, second.right) - Math.max(first.x, second.x)) > 0
        && Math.max(0, Math.min(first.bottom, second.bottom) - Math.max(first.y, second.y)) > 0
      );
      const header = document.querySelector('.thp-site-header');
      const menu = document.querySelector('.thp-menu-toggle');
      const brand = document.querySelector('.thp-brand');
      const toolbar = document.querySelector('#pojo-a11y-toolbar');
      const toggle = toolbar?.querySelector('.pojo-a11y-toolbar-toggle-link');
      if (!header || !menu || !brand || !toolbar || !toggle) return null;
      const headerRect = rect(header);
      const menuRect = rect(menu);
      const brandRect = rect(brand);
      const toolbarRect = rect(toolbar);
      const toggleRect = rect(toggle);
      const toggleStyle = getComputedStyle(toggle);
      const headerStyle = getComputedStyle(header);
      const center = document.elementFromPoint(toggleRect.x + toggleRect.width / 2, toggleRect.y + toggleRect.height / 2);
      return {
        scroll_y: scrollY,
        open: toolbar.classList.contains('pojo-a11y-toolbar-open'),
        header_position: headerStyle.position,
        header_background: headerStyle.backgroundColor,
        header_rect: headerRect,
        menu_rect: menuRect,
        brand_rect: brandRect,
        toolbar_rect: toolbarRect,
        toggle_rect: toggleRect,
        toggle_visible: toggleStyle.visibility === 'visible' && toggleStyle.display !== 'none' && toggleStyle.opacity === '1',
        toggle_topmost: center === toggle || toggle.contains(center),
        toggle_inside_header: toggleRect.x >= headerRect.x && toggleRect.y >= headerRect.y && toggleRect.right <= headerRect.right && toggleRect.bottom <= headerRect.bottom,
        overlaps_menu: intersects(toggleRect, menuRect),
        overlaps_brand: intersects(toggleRect, brandRect),
      };
    });

    for (const width of [320, 768, 1230]) {
      await page.setViewportSize({ width, height: width === 320 ? 740 : 900 });
      await page.waitForFunction((expectedWidth) => document.documentElement.clientWidth === expectedWidth, width);
      await page.evaluate(() => scrollTo(0, 0));
      await page.waitForFunction(() => scrollY === 0);
      const responsiveWidthState = await page.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scroll_width: document.documentElement.scrollWidth,
        overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        menu_display: getComputedStyle(document.querySelector('.thp-menu-toggle')).display,
        desktop_nav_display: getComputedStyle(document.querySelector('.thp-primary-nav')).display,
        search_display: getComputedStyle(document.querySelector('.thp-header-search')).display,
        media_matches: matchMedia('(min-width: 1231px)').matches,
        toggle_rect_count: document.querySelector('.thp-menu-toggle')?.getClientRects().length || 0,
        toggle_width: document.querySelector('.thp-menu-toggle')?.getBoundingClientRect().width || 0,
        toggle_height: document.querySelector('.thp-menu-toggle')?.getBoundingClientRect().height || 0,
      }));
      responsiveWidthState.accessibility_dock_top = await inspectAccessibilityDock();
      await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
      await page.waitForFunction(() => Math.abs(scrollY - Math.max(0, document.documentElement.scrollHeight - innerHeight)) <= 1);
      responsiveWidthState.accessibility_dock_bottom = await inspectAccessibilityDock();
      report.responsive[String(width)] = responsiveWidthState;
    }

    await page.evaluate(() => scrollTo(0, 0));
    await page.waitForFunction(() => scrollY === 0);

    report.responsive['1230'].toggle_rest = await inspectMenuControl();
    await page.locator('.thp-menu-toggle').hover();
    report.responsive['1230'].toggle_hover = await inspectMenuControl();
    await page.mouse.move(200, 200);
    await page.locator('.thp-menu-toggle').focus();
    report.responsive['1230'].toggle_focus = await inspectMenuControl();
    await page.locator('.thp-brand').focus();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForFunction(() => document.documentElement.clientWidth === 390);
    report.responsive.accessibility_dock_390_top = await inspectAccessibilityDock();
    report.responsive.screenshots = {
      dock: `${outputPrefix}-hub-mobile-accessibility-dock-390.png`,
      panel: `${outputPrefix}-hub-mobile-accessibility-panel-390.png`,
      footer_clearance: `${outputPrefix}-hub-mobile-accessibility-footer-clearance-390.png`,
      menu: `${outputPrefix}-hub-mobile-menu-390.png`,
    };
    await page.screenshot({
      path: path.join(outputDir, report.responsive.screenshots.dock),
      fullPage: false,
      animations: 'disabled',
      scale: 'css',
    });
    report.responsive.toggle_rest = await inspectMenuControl();
    await page.locator('.thp-menu-toggle').hover();
    report.responsive.toggle_hover = await inspectMenuControl();
    await page.mouse.move(200, 200);
    await page.locator('.thp-menu-toggle').focus();
    report.responsive.toggle_focus = await inspectMenuControl();
    const inspectAccessibilityRail = () => page.evaluate(() => {
      const toolbar = document.querySelector('#pojo-a11y-toolbar');
      const toggle = toolbar?.querySelector('.pojo-a11y-toolbar-toggle-link');
      if (!toolbar || !toggle) return null;
      const toolbarRect = toolbar.getBoundingClientRect();
      const toggleRect = toggle.getBoundingClientRect();
      const toggleStyle = getComputedStyle(toggle);
      const center = document.elementFromPoint(toggleRect.x + toggleRect.width / 2, toggleRect.y + toggleRect.height / 2);
      return {
        open: toolbar.classList.contains('pojo-a11y-toolbar-open'),
        toolbar_rect: { x: toolbarRect.x, y: toolbarRect.y, right: toolbarRect.right, bottom: toolbarRect.bottom, width: toolbarRect.width, height: toolbarRect.height },
        toggle_rect: { x: toggleRect.x, y: toggleRect.y, right: toggleRect.right, bottom: toggleRect.bottom, width: toggleRect.width, height: toggleRect.height },
        toggle_visible: toggleStyle.visibility === 'visible' && toggleStyle.display !== 'none' && toggleStyle.opacity === '1',
        toggle_topmost: center === toggle || toggle.contains(center),
      };
    });
    report.responsive.accessibility_rail_closed = await inspectAccessibilityRail();
    await page.locator('#pojo-a11y-toolbar .pojo-a11y-toolbar-toggle-link').click();
    await page.waitForFunction(() => {
      const toolbar = document.querySelector('#pojo-a11y-toolbar');
      return toolbar?.classList.contains('pojo-a11y-toolbar-open') && Math.abs(toolbar.getBoundingClientRect().x - 76) < 0.05;
    });
    report.responsive.accessibility_rail_open = await inspectAccessibilityRail();
    await page.screenshot({
      path: path.join(outputDir, report.responsive.screenshots.panel),
      fullPage: false,
      animations: 'disabled',
      scale: 'css',
    });
    await page.locator('#pojo-a11y-toolbar .pojo-a11y-toolbar-toggle-link').click();
    await page.waitForFunction(() => {
      const toolbar = document.querySelector('#pojo-a11y-toolbar');
      return !toolbar?.classList.contains('pojo-a11y-toolbar-open') && Math.abs(toolbar.getBoundingClientRect().x + 180) < 0.05;
    });
    report.responsive.accessibility_rail_restored = await inspectAccessibilityRail();
    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForFunction(() => Math.abs(scrollY - Math.max(0, document.documentElement.scrollHeight - innerHeight)) <= 1);
    report.responsive.accessibility_dock_390_bottom = await inspectAccessibilityDock();
    await page.screenshot({
      path: path.join(outputDir, report.responsive.screenshots.footer_clearance),
      fullPage: false,
      animations: 'disabled',
      scale: 'css',
    });
    await page.evaluate(() => scrollTo(0, 0));
    await page.waitForFunction(() => scrollY === 0);
    report.responsive.external_a11y_before = await page.evaluate(() => (
      [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      }))
    ));
    report.responsive.external_surfaces_before = await page.evaluate(() => (
      [...document.querySelectorAll('body > :not(.thp-content):not(script):not(style):not(link)')].map((element, index) => ({
        identity: `${element.tagName}:${element.id}:${index}`,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      }))
    ));
    await page.locator('.thp-menu-toggle').click();
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === false && document.body.classList.contains('thp-content-menu-open'));
    const firstFocusable = page.locator('.thp-mobile-nav-panel a[href], .thp-mobile-nav-panel button:not([disabled])').first();
    const lastFocusable = page.locator('.thp-mobile-nav-panel a[href], .thp-mobile-nav-panel button:not([disabled])').last();
    const firstIdentity = await firstFocusable.evaluate((element) => ({ tag: element.tagName, text: element.innerText.trim(), label: element.getAttribute('aria-label') }));
    await firstFocusable.focus();
    await page.keyboard.press('Shift+Tab');
    const shiftTabWrapped = await lastFocusable.evaluate((element) => element === document.activeElement);
    await page.keyboard.press('Tab');
    const tabWrapped = await firstFocusable.evaluate((element) => element === document.activeElement);
    report.responsive.menu_open = await page.evaluate(() => {
      const backdrop = document.querySelector('.thp-mobile-nav-backdrop');
      const panel = document.querySelector('.thp-mobile-nav-panel');
      const backdropStyle = getComputedStyle(backdrop);
      const backdropRect = backdrop.getBoundingClientRect();
      const panelRect = panel.getBoundingClientRect();
      const closeButton = document.querySelector('.thp-mobile-nav-head button');
      const closeStyle = getComputedStyle(closeButton);
      return ({
      expanded: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded'),
      label: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-label'),
      drawer_hidden: document.querySelector('#thp-mobile-nav')?.hasAttribute('hidden'),
      dialog_count: document.querySelectorAll('#thp-mobile-nav [role="dialog"][aria-modal="true"]').length,
      focused_inside: Boolean(document.activeElement?.closest('#thp-mobile-nav')),
      body_open: document.body.classList.contains('thp-content-menu-open'),
      body_overflow: document.body.style.overflow,
      heading: document.querySelector('.thp-mobile-nav-head strong')?.innerText.trim() || null,
      backdrop_tag: backdrop.tagName,
      backdrop_aria_hidden: backdrop.getAttribute('aria-hidden'),
      backdrop_background: backdropStyle.backgroundColor,
      backdrop_background_image: backdropStyle.backgroundImage,
      backdrop_visibility: backdropStyle.visibility,
      backdrop_pointer_events: backdropStyle.pointerEvents,
      backdrop_opacity: backdropStyle.opacity,
      backdrop_rect: { x: backdropRect.x, y: backdropRect.y, width: backdropRect.width, height: backdropRect.height },
      backdrop_is_top_left: document.elementFromPoint(10, Math.floor(innerHeight / 2)) === backdrop,
      panel_rect: { x: panelRect.x, y: panelRect.y, right: panelRect.right, width: panelRect.width, height: panelRect.height },
      close_button: {
        color: closeStyle.color,
        background_color: closeStyle.backgroundColor,
        outline_color: closeStyle.outlineColor,
        outline_width: closeStyle.outlineWidth,
        outline_offset: closeStyle.outlineOffset,
      },
      toggle_bars: [...document.querySelectorAll('.thp-menu-toggle span')].map((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return {
          display: style.display,
          background_color: style.backgroundColor,
          opacity: style.opacity,
          x: rect.x,
          y: rect.y,
          height: rect.height,
          width: rect.width,
        };
      }),
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
      external_surfaces: [...document.querySelectorAll('body > :not(.thp-content):not(script):not(style):not(link)')].map((element, index) => ({
        identity: `${element.tagName}:${element.id}:${index}`,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    });
    });
    report.responsive.menu_open.first_focusable = firstIdentity;
    report.responsive.menu_open.shift_tab_wrapped = shiftTabWrapped;
    report.responsive.menu_open.tab_wrapped = tabWrapped;
    await page.screenshot({
      path: path.join(outputDir, report.responsive.screenshots.menu),
      fullPage: false,
      animations: 'disabled',
      scale: 'css',
    });
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === true && !document.body.classList.contains('thp-content-menu-open'));
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
      external_surfaces: [...document.querySelectorAll('body > :not(.thp-content):not(script):not(style):not(link)')].map((element, index) => ({
        identity: `${element.tagName}:${element.id}:${index}`,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    }));
    await page.locator('.thp-menu-toggle').click();
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === false);
    await page.setViewportSize({ width: 1231, height: 900 });
    await page.waitForFunction(() => matchMedia('(min-width: 1231px)').matches && document.querySelector('#thp-mobile-nav')?.hidden === true && document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded') === 'false');
    report.responsive['1231'] = await page.evaluate(() => {
      const toggle = document.querySelector('.thp-menu-toggle');
      const drawer = document.querySelector('#thp-mobile-nav');
      const brand = document.querySelector('.thp-brand');
      const brandRect = brand.getBoundingClientRect();
      const navRect = document.querySelector('.thp-primary-nav').getBoundingClientRect();
      return ({
      width: document.documentElement.clientWidth,
      scroll_width: document.documentElement.scrollWidth,
      overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      media_matches: matchMedia('(min-width: 1231px)').matches,
      menu_display: getComputedStyle(toggle).display,
      menu_rect_count: toggle.getClientRects().length,
      menu_offset_width: toggle.offsetWidth,
      menu_offset_height: toggle.offsetHeight,
      desktop_nav_display: getComputedStyle(document.querySelector('.thp-primary-nav')).display,
      desktop_nav_width: navRect.width,
      desktop_nav_height: navRect.height,
      search_display: getComputedStyle(document.querySelector('.thp-header-search')).display,
      expanded: toggle.getAttribute('aria-expanded'),
      drawer_hidden: drawer.hasAttribute('hidden'),
      drawer_display: getComputedStyle(drawer).display,
      drawer_rect_count: drawer.getClientRects().length,
      body_open: document.body.classList.contains('thp-content-menu-open'),
      focused_in_hidden_drawer: Boolean(document.activeElement?.closest('#thp-mobile-nav')),
      focused_visible_header: Boolean(document.activeElement?.closest('.thp-site-header')),
      focused_brand: document.activeElement === brand,
      brand_width: brandRect.width,
      brand_height: brandRect.height,
      external_a11y_controls: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => ({
        id: element.id,
        inert: element.inert,
        aria_hidden: element.getAttribute('aria-hidden'),
        visibility: getComputedStyle(element).visibility,
        pointer_events: getComputedStyle(element).pointerEvents,
      })),
    });
    });

    await page.mouse.move(10, 300);
    report.responsive.header_search_rest = await inspectHeaderSearchControl();
    await page.locator('.thp-header-search button').hover();
    report.responsive.header_search_hover = await inspectHeaderSearchControl();
    await page.mouse.move(10, 300);
    await page.locator('.thp-header-search button').focus();
    report.responsive.header_search_focus = await inspectHeaderSearchControl();
    await page.locator('.thp-brand').focus();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForFunction(() => !matchMedia('(min-width: 1231px)').matches && getComputedStyle(document.querySelector('.thp-menu-toggle')).display !== 'none');
    await page.locator('.thp-menu-toggle').click();
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === false);
    const backdropAtPoint = await page.evaluate(() => document.elementFromPoint(10, Math.floor(innerHeight / 2))?.classList.contains('thp-mobile-nav-backdrop') === true);
    await page.mouse.click(10, 422);
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === true);
    report.responsive.backdrop_close = await page.evaluate((wasBackdrop) => ({
      was_backdrop: wasBackdrop,
      drawer_hidden: document.querySelector('#thp-mobile-nav')?.hidden === true,
      expanded: document.querySelector('.thp-menu-toggle')?.getAttribute('aria-expanded'),
      focused_toggle: document.activeElement === document.querySelector('.thp-menu-toggle'),
      body_open: document.body.classList.contains('thp-content-menu-open'),
      body_overflow: document.body.style.overflow,
    }), backdropAtPoint);

    await page.setViewportSize({ width: 844, height: 390 });
    await page.waitForFunction(() => document.documentElement.clientWidth === 844 && getComputedStyle(document.querySelector('.thp-menu-toggle')).display !== 'none');
    report.responsive.landscape_accessibility_dock = await inspectAccessibilityDock();
    report.responsive.landscape_closed = await page.evaluate(() => {
      const toggleRect = document.querySelector('.thp-menu-toggle').getBoundingClientRect();
      const accessibilityToggle = document.querySelector('#pojo-a11y-toolbar .pojo-a11y-toolbar-toggle-link');
      const accessibilityToggleRect = accessibilityToggle.getBoundingClientRect();
      return {
        overflow_pixels: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        toggle_rect: { x: toggleRect.x, y: toggleRect.y, right: toggleRect.right, bottom: toggleRect.bottom, width: toggleRect.width, height: toggleRect.height },
        accessibility_toggle_rect: { x: accessibilityToggleRect.x, y: accessibilityToggleRect.y, right: accessibilityToggleRect.right, bottom: accessibilityToggleRect.bottom, width: accessibilityToggleRect.width, height: accessibilityToggleRect.height },
        accessibility_toggle_topmost: accessibilityToggle.contains(document.elementFromPoint(accessibilityToggleRect.x + accessibilityToggleRect.width / 2, accessibilityToggleRect.y + accessibilityToggleRect.height / 2)),
        toolbar_rects: [...document.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')].map((element) => {
          const rect = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return { id: element.id, top: rect.top, bottom: rect.bottom, height: rect.height, visibility: style.visibility };
        }),
      };
    });
    await page.locator('.thp-menu-toggle').click();
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === false);
    report.responsive.landscape_open = await page.evaluate(() => {
      const panelRect = document.querySelector('.thp-mobile-nav-panel').getBoundingClientRect();
      const backdropRect = document.querySelector('.thp-mobile-nav-backdrop').getBoundingClientRect();
      return {
        panel_rect: { x: panelRect.x, y: panelRect.y, right: panelRect.right, bottom: panelRect.bottom, width: panelRect.width, height: panelRect.height },
        panel_overflow_y: getComputedStyle(document.querySelector('.thp-mobile-nav-panel')).overflowY,
        backdrop_rect: { x: backdropRect.x, y: backdropRect.y, right: backdropRect.right, bottom: backdropRect.bottom, width: backdropRect.width, height: backdropRect.height },
      };
    });
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.querySelector('#thp-mobile-nav')?.hidden === true);
    report.responsive.network = responsiveNetwork;
    await responsiveContext.close();
  } finally {
    await browser.close();
  }

  const checks = [];
  const add = (name, passed, detail = null) => checks.push({ name, passed: Boolean(passed), detail });
  const white = [255, 255, 255, 1];
  const deepForest = [7, 47, 45, 1];
  const forest = [11, 63, 60, 1];
  const actionIsVisible = (action) => Boolean(
    action
    && action.text.length > 0
    && action.display !== 'none'
    && action.visibility === 'visible'
    && action.opacity === '1'
    && action.element_width > 0
    && action.element_height > 0
    && action.text_width > 0
    && action.text_height > 0
  );
  const exactReadableAction = (action, foreground, background) => (
    actionIsVisible(action)
    && sameColor(action.color, foreground)
    && sameColor(action.text_fill_color, foreground)
    && sameColor(action.background_color, background)
    && contrastRatio(foreground, background) >= 4.5
  );
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
      add(`${prefix}: exact Open Graph metadata`, value.og_title === socialTitle(route) && value.og_description === route.public.meta_description && sameUrl(value.og_url, canonicalFor(route)));
      add(`${prefix}: exact X metadata`, value.twitter_title === socialTitle(route) && value.twitter_description === route.public.meta_description);
      add(`${prefix}: social image`, value.og_image?.endsWith(socialImagePath) && value.twitter_image?.endsWith(socialImagePath) && value.og_width === '1717' && value.og_height === '916');
      add(`${prefix}: robots index contract`, robots.has('index') && robots.has('follow') && robots.has('max-image-preview:large'), value.robots);
      add(`${prefix}: Hebrew RTL`, value.lang === 'he-IL' && value.dir === 'rtl');
      add(`${prefix}: one owned main and H1`, value.main_count === 1 && value.owner_main_count === 1 && value.h1_count === 1 && value.h1 === route.public.h1);
      add(`${prefix}: preserved body and breadcrumb`, value.preserved_body_count === 1 && value.breadcrumb_count === 1 && value.breadcrumb_items === route.breadcrumbs.length && value.breadcrumb_current === route.breadcrumbs.at(-1).label);
      add(`${prefix}: sources and hero`, value.source_panel_count === 1 && value.source_count === route.source_ids.length && value.unsafe_source_rel_count === 0 && value.hero_picture_sources === 2 && value.hero_image_count === 1);
      add(`${prefix}: release assets`, value.release_asset_count >= 2, value.release_asset_count);
      add(`${prefix}: clean public surface`, value.admin_bar === false && value.long_dash_count === 0 && value.forbidden_hits.length === 0 && value.hello_pink_link_count === 0 && value.duplicate_ids.length === 0 && value.unnamed_buttons === 0 && value.unnamed_links === 0 && value.missing_alt_images === 0 && value.broken_images.length === 0);
      add(`${prefix}: no horizontal overflow`, value.overflow_pixels === 0, value.overflow_pixels);
      add(`${prefix}: network clean`, unexpectedConsoleErrors(result.network.console_errors).length === 0 && result.network.request_failures.length === 0 && result.network.bad_same_origin.length === 0, result.network);
      add(`${prefix}: complete screenshot coverage`, result.screenshot_capture.coverage_complete === true && result.screenshot_capture.final_height === result.screenshot_capture.measured_height && result.screenshot_capture.segments.every((segment, index) => segment.png_width === result.screenshot_capture.width && segment.png_height === segment.height && segment.top === (segment.target_top ?? 0) && (index === 0 ? segment.overlap === 0 : Math.abs(segment.overlap - 160) <= 1)), result.screenshot_capture);
      add(`${prefix}: visible footer action`, exactReadableAction(value.footer_action, deepForest, white), value.footer_action);
      if (route.kind === 'hub') {
        add(`${prefix}: hub decision and guide structure`, value.hub_decision_cards === 3 && value.hub_decision_links === 9 && value.hub_guide_groups === 3 && value.hub_guide_cards === 7 && JSON.stringify(value.hub_child_owners) === JSON.stringify(spokeIds));
      } else {
        add(`${prefix}: visible hero and hub actions`, exactReadableAction(value.hero_action, deepForest, white) && exactReadableAction(value.aside_hub_action, white, forest), { hero: value.hero_action, hub: value.aside_hub_action });
        add(`${prefix}: visible continuation action`, actionIsVisible(value.continuation_action) && sameColor(value.continuation_action.color, white) && sameColor(value.continuation_action.text_fill_color, white) && value.continuation_action.background_image.includes('rgb(11, 63, 60)') && value.continuation_action.background_image.includes('rgb(7, 47, 45)') && contrastRatio(white, forest) >= 4.5 && contrastRatio(white, deepForest) >= 4.5, value.continuation_action);
        add(`${prefix}: spoke hierarchy`, value.parent_link_count === 2 && JSON.stringify(value.rendered_continuation_owners) === JSON.stringify(value.expected_continuation_owners));
        add(`${prefix}: generated article navigation`, value.toc_item_count === value.content_h2_count && value.toc_hidden === (value.content_h2_count === 0));
      }
    }
  }

  for (const width of ['320', '768', '1230']) {
    const value = report.responsive[width];
    add(`${width}px: mobile navigation and no overflow`, value.overflow_pixels === 0 && value.media_matches === false && value.menu_display !== 'none' && value.toggle_rect_count === 1 && value.toggle_width >= 44 && value.toggle_height >= 44 && value.desktop_nav_display === 'none' && value.search_display === 'none', value);
  }
  const accessibilityDockIsClear = (state) => state && state.open === false && state.header_position === 'sticky' && state.header_rect.y === 0 && state.header_rect.height >= 68 && sameColor(state.header_background, [255, 255, 255, 0.97]) && state.toggle_visible === true && state.toggle_topmost === true && state.toggle_inside_header === true && state.overlaps_menu === false && state.overlaps_brand === false && Math.abs(state.toolbar_rect.x + 180) <= 1 && Math.abs(state.toolbar_rect.right) <= 1 && Math.abs(state.toggle_rect.x - 100) <= 1 && Math.abs(state.toggle_rect.right - 144) <= 1 && Math.abs(state.toggle_rect.y - 12) <= 1 && Math.abs(state.toggle_rect.bottom - 56) <= 1 && state.toggle_rect.width === 44 && state.toggle_rect.height === 44;
  for (const width of ['320', '768', '1230']) {
    const value = report.responsive[width];
    add(`${width}px: accessibility control stays inside its collision-free header dock`, accessibilityDockIsClear(value.accessibility_dock_top) && accessibilityDockIsClear(value.accessibility_dock_bottom) && value.accessibility_dock_top.scroll_y === 0 && value.accessibility_dock_bottom.scroll_y > 0, { top: value.accessibility_dock_top, bottom: value.accessibility_dock_bottom });
  }
  const before = report.responsive.external_a11y_before;
  const externalBefore = report.responsive.external_surfaces_before;
  const open = report.responsive.menu_open;
  const closed = report.responsive.menu_closed;
  const desktop = report.responsive['1231'];
  const backdrop = open.backdrop_rect;
  const panel = open.panel_rect;
  const barsAreDistinct = new Set(open.toggle_bars.map((bar) => bar.y)).size === 3;
  const externalSurfacesRestored = externalBefore.every((original) => {
    const restored = closed.external_surfaces.find((surface) => surface.identity === original.identity);
    return restored && restored.inert === original.inert && restored.aria_hidden === original.aria_hidden && restored.visibility === original.visibility && restored.pointer_events === original.pointer_events;
  });
  const controlStateIsVisible = (state, minimumHeight = 44) => state.display !== 'none' && state.visibility === 'visible' && state.opacity === '1' && state.rect_count === 1 && state.width >= 44 && state.height >= minimumHeight;
  const interactiveStateIsSealed = (state) => controlStateIsVisible(state) && sameColor(state.color, forest) && sameColor(state.background_color, white) && !sameColor(state.background_color, [204, 51, 102, 1]);
  const mobileToggleStates = [report.responsive.toggle_rest, report.responsive.toggle_hover, report.responsive.toggle_focus];
  const boundaryToggleStates = [report.responsive['1230'].toggle_rest, report.responsive['1230'].toggle_hover, report.responsive['1230'].toggle_focus];
  add('mobile menu control remains visible through separate rest, hover, and focus states', mobileToggleStates.every(interactiveStateIsSealed) && report.responsive.toggle_rest.hovered === false && report.responsive.toggle_rest.focused === false && report.responsive.toggle_hover.hovered === true && report.responsive.toggle_hover.focused === false && report.responsive.toggle_focus.hovered === false && report.responsive.toggle_focus.focused === true && sameColor(report.responsive.toggle_focus.outline_color, [243, 181, 76, 1]) && report.responsive.toggle_focus.outline_width === '3px' && report.responsive.toggle_focus.outline_offset === '3px', { rest: report.responsive.toggle_rest, hover: report.responsive.toggle_hover, focus: report.responsive.toggle_focus });
  add('1230px menu control remains visible through separate rest, hover, and focus states', boundaryToggleStates.every(interactiveStateIsSealed) && report.responsive['1230'].toggle_rest.hovered === false && report.responsive['1230'].toggle_rest.focused === false && report.responsive['1230'].toggle_hover.hovered === true && report.responsive['1230'].toggle_hover.focused === false && report.responsive['1230'].toggle_focus.hovered === false && report.responsive['1230'].toggle_focus.focused === true, report.responsive['1230']);
  const searchStates = [report.responsive.header_search_rest, report.responsive.header_search_hover, report.responsive.header_search_focus];
  add('desktop search action resists theme hover and focus states', searchStates.every((state) => controlStateIsVisible(state, 40) && sameColor(state.color, white) && sameColor(state.background_color, forest)) && report.responsive.header_search_rest.hovered === false && report.responsive.header_search_rest.focused === false && report.responsive.header_search_hover.hovered === true && report.responsive.header_search_hover.focused === false && report.responsive.header_search_focus.hovered === false && report.responsive.header_search_focus.focused === true && sameColor(report.responsive.header_search_focus.outline_color, [243, 181, 76, 1]) && report.responsive.header_search_focus.outline_width === '3px' && report.responsive.header_search_focus.outline_offset === '-4px', { rest: report.responsive.header_search_rest, hover: report.responsive.header_search_hover, focus: report.responsive.header_search_focus });
  const railClosed = report.responsive.accessibility_rail_closed;
  const railOpen = report.responsive.accessibility_rail_open;
  const railRestored = report.responsive.accessibility_rail_restored;
  const dock390Top = report.responsive.accessibility_dock_390_top;
  const dock390Bottom = report.responsive.accessibility_dock_390_bottom;
  add('390px accessibility control stays in its reserved header dock while scrolling', accessibilityDockIsClear(dock390Top) && accessibilityDockIsClear(dock390Bottom) && dock390Top.scroll_y === 0 && dock390Bottom.scroll_y > 0, { top: dock390Top, bottom: dock390Bottom });
  add('mobile accessibility header dock opens and restores the complete panel', railClosed && railOpen && railRestored && railClosed.open === false && railClosed.toggle_visible === true && railClosed.toggle_topmost === true && Math.abs(railClosed.toolbar_rect.x + 180) <= 1 && Math.abs(railClosed.toolbar_rect.right) <= 1 && Math.abs(railClosed.toggle_rect.x - 100) <= 1 && Math.abs(railClosed.toggle_rect.right - 144) <= 1 && railClosed.toggle_rect.width === 44 && railClosed.toggle_rect.height === 44 && Math.abs(railClosed.toggle_rect.y - 12) <= 1 && Math.abs(railClosed.toggle_rect.bottom - 56) <= 1 && railOpen.open === true && Math.abs(railOpen.toolbar_rect.x - 76) <= 1 && Math.abs(railOpen.toolbar_rect.right - 256) <= 1 && Math.abs(railOpen.toggle_rect.x - 256) <= 1 && Math.abs(railOpen.toggle_rect.right - 300) <= 1 && railOpen.toggle_topmost === true && railRestored.open === false && Math.abs(railRestored.toolbar_rect.x + 180) <= 1 && Math.abs(railRestored.toggle_rect.x - 100) <= 1 && railRestored.toggle_topmost === true, { closed: railClosed, open: railOpen, restored: railRestored });
  const responsiveScreenshotEvidence = Object.values(report.responsive.screenshots || {}).map((filename) => {
    const screenshotPath = path.join(outputDir, filename);
    if (!fs.existsSync(screenshotPath)) return { filename, exists: false, width: 0, height: 0 };
    const png = fs.readFileSync(screenshotPath);
    return { filename, exists: true, width: png.readUInt32BE(16), height: png.readUInt32BE(20) };
  });
  add('responsive accessibility and menu evidence uses exact 390px viewport captures', responsiveScreenshotEvidence.length === 4 && responsiveScreenshotEvidence.every((item) => item.exists && item.width === 390 && item.height === 844), responsiveScreenshotEvidence);
  add('mobile drawer opens as an isolated dialog', open.expanded === 'true' && open.label === 'סגירת תפריט' && open.drawer_hidden === false && open.dialog_count === 1 && open.focused_inside === true && open.body_open === true && open.body_overflow === 'hidden' && open.page_isolated === true && open.heading === 'תפריט ראשי');
  add('mobile drawer has a complete pointer-only backdrop', open.backdrop_tag === 'DIV' && open.backdrop_aria_hidden === 'true' && sameColor(open.backdrop_background, [3, 24, 23, 0.72]) && open.backdrop_background_image === 'none' && open.backdrop_visibility === 'visible' && open.backdrop_pointer_events !== 'none' && open.backdrop_opacity === '1' && backdrop.x === 0 && backdrop.y === 0 && backdrop.width === 390 && backdrop.height === 844 && open.backdrop_is_top_left === true && panel.right === 390 && panel.width > 0 && panel.width < backdrop.width && panel.height === 844, { backdrop: open, panel });
  add('mobile drawer has three visible menu bars', open.toggle_bars.length === 3 && barsAreDistinct && open.toggle_bars.every((bar) => bar.display === 'block' && sameColor(bar.background_color, forest) && bar.opacity === '1' && bar.height >= 2 && bar.width >= 20 && contrastRatio(forest, white) >= 3), open.toggle_bars);
  add('mobile drawer close control resists theme focus styles', sameColor(open.close_button.color, forest) && sameColor(open.close_button.background_color, [237, 245, 242, 1]) && sameColor(open.close_button.outline_color, [243, 181, 76, 1]) && open.close_button.outline_width === '3px' && open.close_button.outline_offset === '3px', open.close_button);
  add('mobile focus trap wraps in both directions', open.shift_tab_wrapped === true && open.tab_wrapped === true);
  add('mobile drawer suppresses external accessibility controls', before.length === 2 && open.external_a11y_controls.every((control) => control.inert === true && control.aria_hidden === 'true' && control.visibility === 'hidden' && control.pointer_events === 'none'));
  add('mobile drawer isolates every top-level external widget', open.external_surfaces.length >= externalBefore.length && open.external_surfaces.every((control) => control.inert === true && control.aria_hidden === 'true' && control.visibility === 'hidden' && control.pointer_events === 'none'), open.external_surfaces);
  add('Escape closes and restores the mobile page', closed.expanded === 'false' && closed.drawer_hidden === true && closed.body_open === false && closed.body_overflow === '' && closed.focused_toggle === true && JSON.stringify(closed.external_a11y_controls) === JSON.stringify(before) && externalSurfacesRestored === true);
  add('1231px closes the drawer and restores exact desktop focus', desktop.overflow_pixels === 0 && desktop.media_matches === true && desktop.menu_display === 'none' && desktop.menu_rect_count === 0 && desktop.menu_offset_width === 0 && desktop.menu_offset_height === 0 && desktop.desktop_nav_display !== 'none' && desktop.desktop_nav_width > 0 && desktop.desktop_nav_height > 0 && desktop.search_display !== 'none' && desktop.expanded === 'false' && desktop.drawer_hidden === true && desktop.drawer_display === 'none' && desktop.drawer_rect_count === 0 && desktop.body_open === false && desktop.focused_in_hidden_drawer === false && desktop.focused_visible_header === true && desktop.focused_brand === true && desktop.brand_width > 0 && desktop.brand_height > 0 && JSON.stringify(desktop.external_a11y_controls) === JSON.stringify(before), desktop);
  add('pointer backdrop closes and restores its opener', report.responsive.backdrop_close.was_backdrop === true && report.responsive.backdrop_close.drawer_hidden === true && report.responsive.backdrop_close.expanded === 'false' && report.responsive.backdrop_close.focused_toggle === true && report.responsive.backdrop_close.body_open === false && report.responsive.backdrop_close.body_overflow === '', report.responsive.backdrop_close);
  const landscapeClosed = report.responsive.landscape_closed;
  const landscapeOpen = report.responsive.landscape_open;
  add('short landscape keeps navigation controls inside the viewport', accessibilityDockIsClear(report.responsive.landscape_accessibility_dock) && landscapeClosed.overflow_pixels === 0 && landscapeClosed.toggle_rect.x >= 0 && landscapeClosed.toggle_rect.y >= 0 && landscapeClosed.toggle_rect.right <= 844 && landscapeClosed.toggle_rect.bottom <= 390 && landscapeClosed.toggle_rect.width >= 44 && landscapeClosed.toggle_rect.height >= 44 && landscapeClosed.accessibility_toggle_rect.x >= 0 && landscapeClosed.accessibility_toggle_rect.y >= 0 && landscapeClosed.accessibility_toggle_rect.right <= 844 && landscapeClosed.accessibility_toggle_rect.bottom <= 390 && landscapeClosed.accessibility_toggle_rect.width === 44 && landscapeClosed.accessibility_toggle_rect.height === 44 && landscapeClosed.accessibility_toggle_topmost === true && landscapeOpen.panel_rect.y === 0 && landscapeOpen.panel_rect.right === 844 && landscapeOpen.panel_rect.bottom === 390 && landscapeOpen.panel_rect.height === 390 && landscapeOpen.panel_overflow_y === 'auto' && landscapeOpen.backdrop_rect.x === 0 && landscapeOpen.backdrop_rect.y === 0 && landscapeOpen.backdrop_rect.right === 844 && landscapeOpen.backdrop_rect.bottom === 390, { dock: report.responsive.landscape_accessibility_dock, closed: landscapeClosed, open: landscapeOpen });
  add('responsive network is clean', unexpectedConsoleErrors(report.responsive.network.console_errors).length === 0 && report.responsive.network.request_failures.length === 0 && report.responsive.network.bad_same_origin.length === 0, report.responsive.network);

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
