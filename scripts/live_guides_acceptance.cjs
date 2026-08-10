#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const rootDir = path.resolve(__dirname, '..');
const release = process.env.THP_RELEASE || '0.4.0';
const baseUrl = new URL(process.env.THP_BASE_URL || 'https://thai-land.co.il/');
const chromePath = process.env.THP_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const timeout = Number.parseInt(process.env.THP_LIVE_TIMEOUT_MS || '45000', 10);
const outputDir = path.join(rootDir, 'output', 'playwright');
const outputPrefix = `guides-live-${release}`;
const contract = JSON.parse(
  fs.readFileSync(path.join(rootDir, 'data', 'content', 'priority-guides.json'), 'utf8')
);

const expectedRouteIds = [
  'thailand-visas',
  'thailand-law-and-tax',
  'thailand-entry-requirements',
  'thailand-entry-april-2022',
  'thailand-cannabis-law',
  'thailand-tourist-visa',
  'thailand-permanent-residence'
];
const routesById = new Map(contract.routes.map((route) => [route.route_id, route]));

const staleClaimsByRoute = {
  'thailand-entry-requirements': [
    'Test & Go',
    'SHA+',
    'בדיקת PCR לפני הטיסה',
    'TDAC בתשלום'
  ],
  'thailand-cannabis-law': [
    'שימוש פנאי חוקי',
    'מותר לתיירים לקנות בחופשיות',
    'כל חלקי הצמח חוקיים',
    'מותר להעביר קנאביס בגבול'
  ],
  'thailand-tourist-visa': [
    'תשלום במזומן בשגרירות',
    '150 ש״ח',
    'שלושה ימי עסקים מובטחים'
  ],
  'thailand-permanent-residence': [
    'חלון קבוע מאוקטובר עד דצמבר',
    'אישור מובטח',
    'אזרחות אוטומטית'
  ]
};

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

function canonicalFor(route) {
  return new URL(route.path, baseUrl).toString();
}

function contextualTarget(ownerId) {
  if (ownerId === 'home') {
    return { availability: 'live', href: new URL('/', baseUrl).toString() };
  }
  const route = routesById.get(ownerId);
  if (!route) return null;
  return {
    availability: 'live',
    href: canonicalFor(route)
  };
}

function routeUrl(route, label) {
  const url = new URL(route.path, baseUrl);
  url.searchParams.set('qa', `${release}-${label}-${Date.now()}`);
  return url.toString();
}

function normalizeRobots(value) {
  return new Set(
    String(value || '')
      .split(',')
      .map((item) => item.trim().toLowerCase())
      .filter(Boolean)
  );
}

function parseColor(value) {
  const match = String(value || '').match(
    /^rgba?\(\s*([\d.]+)[, ]+\s*([\d.]+)[, ]+\s*([\d.]+)(?:\s*[,/]\s*([\d.]+))?\s*\)$/i
  );
  if (!match) return null;
  return [Number(match[1]), Number(match[2]), Number(match[3]), match[4] === undefined ? 1 : Number(match[4])];
}

function contrastRatio(foreground, background) {
  const linear = (channel) => {
    const normalized = channel / 255;
    return normalized <= 0.04045
      ? normalized / 12.92
      : ((normalized + 0.055) / 1.055) ** 2.4;
  };
  const luminance = (color) => (
    0.2126 * linear(color[0])
    + 0.7152 * linear(color[1])
    + 0.0722 * linear(color[2])
  );
  const first = luminance(foreground);
  const second = luminance(background);
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

function presentationPhrases() {
  const source = fs.readFileSync(path.join(rootDir, 'tests', 'run.php'), 'utf8');
  const start = source.indexOf('$presentation_phrases = array(');
  const end = source.indexOf(');', start);
  if (start < 0 || end < 0) return [];
  return [...source.slice(start, end).matchAll(/'([^']*)'/g)].map((match) => match[1]);
}

function errorListeners(page) {
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

function unexpectedConsoleErrors(values) {
  return values.filter((value) => !String(value).includes('embed.tawk.to'));
}

async function stabilize(page) {
  await page.evaluate(async () => {
    if (document.fonts?.ready) await document.fonts.ready;
    const images = [...document.images];
    images.forEach((image) => { image.loading = 'eager'; });
    await Promise.all(images.map(async (image) => {
      if (!image.complete) {
        await new Promise((resolve) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        });
      }
      if (typeof image.decode === 'function') await image.decode().catch(() => {});
    }));
  });
}

async function capture(page, basename) {
  await stabilize(page);
  const originalViewport = page.viewportSize();
  const dimensions = await page.evaluate(() => ({
    width: document.documentElement.clientWidth,
    height: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight)
  }));
  const files = [];
  const segments = [];
  const captureStyle = await page.addStyleTag({
    content: '.thp-guide-header{position:relative!important;inset-block-start:auto!important}#pojo-a11y-toolbar,#pojo-a11y-skip-content{visibility:hidden!important;pointer-events:none!important}'
  });

  if (dimensions.height <= 7000) {
    const filename = `${basename}.png`;
    const target = path.join(outputDir, filename);
    await page.screenshot({ path: target, fullPage: true, animations: 'disabled', scale: 'css' });
    const png = fs.readFileSync(target);
    files.push(filename);
    segments.push({
      filename,
      top: 0,
      height: dimensions.height,
      bottom: dimensions.height,
      png_width: png.readUInt32BE(16),
      png_height: png.readUInt32BE(20)
    });
  } else {
    const segmentHeight = 6500;
    const overlap = 160;
    const step = segmentHeight - overlap;
    let index = 0;
    for (let top = 0; top < dimensions.height; top += step) {
      const height = Math.min(segmentHeight, dimensions.height - top);
      await page.setViewportSize({ width: dimensions.width, height });
      await page.evaluate(async (scrollTop) => {
        window.scrollTo(0, scrollTop);
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      }, top);
      const actualTop = await page.evaluate(() => window.scrollY);
      const filename = `${basename}-part-${String(index + 1).padStart(2, '0')}.png`;
      const target = path.join(outputDir, filename);
      await page.screenshot({ path: target, fullPage: false, animations: 'disabled', scale: 'css' });
      const png = fs.readFileSync(target);
      files.push(filename);
      segments.push({
        filename,
        top: actualTop,
        height,
        bottom: actualTop + height,
        png_width: png.readUInt32BE(16),
        png_height: png.readUInt32BE(20)
      });
      index += 1;
      if (top + height >= dimensions.height) break;
    }
  }

  await captureStyle.evaluate((element) => element.remove());
  if (originalViewport) await page.setViewportSize(originalViewport);
  return { files, segments, dimensions };
}

function addCheck(report, name, pass, details = null) {
  report.checks.push({ name, pass: Boolean(pass), details });
}

function graphItems(value) {
  if (!value || typeof value !== 'object') return [];
  if (Array.isArray(value['@graph'])) return value['@graph'];
  return [value];
}

async function inspectRoute(page, route, viewportLabel) {
  const requestedUrl = routeUrl(route, viewportLabel);
  const response = await page.goto(requestedUrl, {
    waitUntil: 'networkidle',
    timeout
  });
  const canonical = canonicalFor(route);
  const expectedTitle = `${route.public.seo_title} | Thai-Land.co.il`;
  const snapshot = await page.evaluate(({ expectedRouteId, expectedOwner, expectedCanonical }) => {
    const text = document.body.textContent || '';
    const links = [...document.querySelectorAll('a[href]')];
    const buttons = [...document.querySelectorAll('button')];
    const ids = [...document.querySelectorAll('[id]')].map((node) => node.id).filter(Boolean);
    const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
    const schema = [...document.querySelectorAll('script[type="application/ld+json"]')].map((node) => {
      try { return JSON.parse(node.textContent || ''); } catch { return null; }
    }).filter(Boolean);
    const hero = document.querySelector('.thp-guide-hero-media img');
    const routeMain = document.querySelector('[data-thp-guide-route]');
    const breadcrumbItems = [...document.querySelectorAll('.thp-guide-breadcrumbs li')].map((item) => ({
      text: (item.textContent || '').replace(/\s+/g, ' ').trim().replace(/\/$/, '').trim(),
      href: item.querySelector('a')?.href || '',
      current: item.querySelector('[aria-current="page"]') !== null
    }));
    const sourceLinks = [...document.querySelectorAll('.thp-guide-sources a[href]')].map((link) => ({
      href: link.href,
      text: (link.textContent || '').replace(/\s+/g, ' ').trim(),
      rel: link.rel
    }));
    const contextualItems = [...document.querySelectorAll('[data-thp-contextual-target]')].map((paragraph) => {
      const ownerNode = paragraph.querySelector('[data-thp-contextual-owner]');
      const link = ownerNode?.matches('a[href]') ? ownerNode : null;
      return {
        target_owner_id: paragraph.getAttribute('data-thp-contextual-target') || '',
        owner_id: ownerNode?.getAttribute('data-thp-contextual-owner') || '',
        anchor_text: (ownerNode?.textContent || '').replace(/\s+/g, ' ').trim(),
        href: link?.href || '',
        linked: Boolean(link),
        unlinked: Boolean(ownerNode?.hasAttribute('data-thp-contextual-unlinked')),
        owner_tag: ownerNode?.tagName || '',
        paragraph_tag: paragraph.tagName
      };
    });
    const main = document.querySelector('main');
    const mainRect = main?.getBoundingClientRect();
    return {
      html_lang: document.documentElement.lang,
      html_dir: document.documentElement.dir,
      title: document.title,
      h1_count: document.querySelectorAll('h1').length,
      h1: (document.querySelector('h1')?.textContent || '').trim(),
      main_count: document.querySelectorAll('main').length,
      route_id: routeMain?.getAttribute('data-thp-guide-route') || '',
      owner_id: routeMain?.getAttribute('data-thp-guide-owner') || '',
      canonical_links: [...document.querySelectorAll('link[rel="canonical"]')].map((node) => node.href),
      description: document.querySelector('meta[name="description"]')?.content || '',
      robots: document.querySelector('meta[name="robots"]')?.content || '',
      og_title: document.querySelector('meta[property="og:title"]')?.content || '',
      og_description: document.querySelector('meta[property="og:description"]')?.content || '',
      og_url: document.querySelector('meta[property="og:url"]')?.content || '',
      og_image: document.querySelector('meta[property="og:image"]')?.content || '',
      og_modified_times: [...document.querySelectorAll('meta[property="article:modified_time"]')].map((node) => node.content),
      twitter_title: document.querySelector('meta[name="twitter:title"]')?.content || '',
      visible_modified_on: document.querySelector('.thp-guide-date time')?.getAttribute('datetime') || '',
      schema,
      body_text: text,
      body_classes: document.body.className,
      breadcrumb_items: breadcrumbItems,
      toc_links: document.querySelectorAll('.thp-guide-toc a[href^="#"]').length,
      section_count: document.querySelectorAll('[data-thp-guide-section]').length,
      question_count: document.querySelectorAll('.thp-guide-question-list details').length,
      related_count: document.querySelectorAll('[data-thp-related-route]').length,
      contextual_items: contextualItems,
      source_links: sourceLinks,
      hero: hero ? {
        src: hero.src,
        current_src: hero.currentSrc,
        complete: hero.complete,
        natural_width: hero.naturalWidth,
        natural_height: hero.naturalHeight,
        alt: hero.alt
      } : null,
      guide_css: [...document.styleSheets].map((sheet) => sheet.href || '').find((href) => href.includes('/assets/guides/guides.css')) || '',
      guide_js: [...document.scripts].map((script) => script.src || '').find((src) => src.includes('/assets/guides/guides.js')) || '',
      duplicate_ids: duplicateIds,
      unnamed_links: links.filter((link) => !((link.textContent || '').trim() || link.getAttribute('aria-label') || link.getAttribute('title'))).length,
      unnamed_buttons: buttons.filter((button) => !((button.textContent || '').trim() || button.getAttribute('aria-label') || button.getAttribute('title'))).length,
      viewport_width: document.documentElement.clientWidth,
      scroll_width: document.documentElement.scrollWidth,
      main_visible: Boolean(mainRect && mainRect.width > 0 && mainRect.height > 0),
      expected_route_id: expectedRouteId,
      expected_owner: expectedOwner,
      expected_canonical: expectedCanonical
    };
  }, {
    expectedRouteId: route.route_id,
    expectedOwner: route.seo_owner_id,
    expectedCanonical: canonical
  });

  const items = snapshot.schema.flatMap(graphItems);
  const types = items.flatMap((item) => Array.isArray(item['@type']) ? item['@type'] : [item['@type']]).filter(Boolean);
  return { response_status: response?.status() || 0, requested_url: requestedUrl, final_url: page.url(), expectedTitle, canonical, snapshot, schema_types: types };
}

async function validateRoute(page, route, viewportLabel, report) {
  const inspected = await inspectRoute(page, route, viewportLabel);
  const { snapshot, schema_types: types } = inspected;
  const robots = normalizeRobots(snapshot.robots);
  const expectedImageWidth = viewportLabel === 'mobile' ? 720 : 1717;
  const crumbLabels = route.breadcrumbs.map((crumb) => crumb.label);
  const actualLabels = snapshot.breadcrumb_items.map((crumb) => crumb.text);

  addCheck(report, `${route.route_id}:${viewportLabel}:status`, inspected.response_status === 200, inspected.response_status);
  addCheck(report, `${route.route_id}:${viewportLabel}:canonical-final-url`, sameUrl(new URL(inspected.final_url), new URL(inspected.requested_url)), inspected.final_url);
  addCheck(report, `${route.route_id}:${viewportLabel}:language-direction`, /^he(?:-|$)/i.test(snapshot.html_lang) && snapshot.html_dir === 'rtl', { lang: snapshot.html_lang, dir: snapshot.html_dir });
  addCheck(report, `${route.route_id}:${viewportLabel}:single-main-h1`, snapshot.main_count === 1 && snapshot.h1_count === 1 && snapshot.main_visible, { main: snapshot.main_count, h1: snapshot.h1_count });
  addCheck(report, `${route.route_id}:${viewportLabel}:route-owner`, snapshot.route_id === route.route_id && snapshot.owner_id === route.seo_owner_id, { route: snapshot.route_id, owner: snapshot.owner_id });
  addCheck(report, `${route.route_id}:${viewportLabel}:h1`, snapshot.h1 === route.public.h1, snapshot.h1);
  addCheck(report, `${route.route_id}:${viewportLabel}:title`, snapshot.title === inspected.expectedTitle, snapshot.title);
  addCheck(report, `${route.route_id}:${viewportLabel}:description`, snapshot.description === route.public.meta_description, snapshot.description);
  addCheck(report, `${route.route_id}:${viewportLabel}:canonical`, snapshot.canonical_links.length === 1 && sameUrl(snapshot.canonical_links[0], inspected.canonical), snapshot.canonical_links);

  const historical = route.indexing.policy === 'noindex';
  addCheck(report, `${route.route_id}:${viewportLabel}:robots`, historical
    ? robots.has('noindex') && robots.has('follow') && !robots.has('nofollow')
    : robots.has('index') && robots.has('follow') && !robots.has('noindex'), snapshot.robots);
  addCheck(report, `${route.route_id}:${viewportLabel}:max-image-preview`, robots.has('max-image-preview:large'), snapshot.robots);

  addCheck(report, `${route.route_id}:${viewportLabel}:social`, snapshot.og_title === inspected.expectedTitle
    && snapshot.og_description === route.public.meta_description
    && sameUrl(snapshot.og_url, inspected.canonical)
    && snapshot.og_image.includes(`/assets/guides/images/${route.asset_key}-1717.webp`), {
    title: snapshot.og_title,
    url: snapshot.og_url,
    image: snapshot.og_image
  });
  addCheck(report, `${route.route_id}:${viewportLabel}:schema-base`, types.includes('Organization') && types.includes('WebSite') && types.includes('BreadcrumbList'), types);
  addCheck(report, `${route.route_id}:${viewportLabel}:schema-kind`, route.kind === 'collection'
    ? types.includes('CollectionPage') && !types.includes('Article')
    : types.includes('WebPage') && types.includes('Article'), types);
  addCheck(report, `${route.route_id}:${viewportLabel}:no-faq-schema`, !types.includes('FAQPage'), types);
  const schemaModifiedDates = snapshot.schema
    .flatMap(graphItems)
    .map((item) => item.dateModified)
    .filter(Boolean);
  const expectedSchemaDateCount = route.kind === 'collection' ? 1 : 2;
  addCheck(report, `${route.route_id}:${viewportLabel}:freshness-alignment`,
    snapshot.og_modified_times.length === 1
      && snapshot.og_modified_times[0].slice(0, 10) === route.modified_on
      && snapshot.visible_modified_on === route.modified_on
      && schemaModifiedDates.length === expectedSchemaDateCount
      && schemaModifiedDates.every((value) => String(value).slice(0, 10) === route.modified_on), {
    modified_on: route.modified_on,
    og: snapshot.og_modified_times,
    visible: snapshot.visible_modified_on,
    schema: schemaModifiedDates
  });

  addCheck(report, `${route.route_id}:${viewportLabel}:breadcrumbs`, JSON.stringify(actualLabels) === JSON.stringify(crumbLabels)
    && snapshot.breadcrumb_items.at(-1)?.current === true, { expected: crumbLabels, actual: actualLabels });
  addCheck(report, `${route.route_id}:${viewportLabel}:content-counts`, snapshot.section_count === route.sections.length
    && snapshot.toc_links === route.sections.length
    && snapshot.question_count === route.faqs.length, {
    sections: snapshot.section_count,
    toc: snapshot.toc_links,
    questions: snapshot.question_count
  });
  const contextualStructure = snapshot.contextual_items.length === route.contextual_links.length
    && route.contextual_links.every((item, index) => {
      const actual = snapshot.contextual_items[index];
      return actual
        && actual.paragraph_tag === 'P'
        && actual.target_owner_id === item.target_owner_id
        && actual.owner_id === item.target_owner_id
        && actual.anchor_text === item.anchor_text;
    });
  addCheck(report, `${route.route_id}:${viewportLabel}:contextual-anchors-owners`, contextualStructure, {
    expected: route.contextual_links,
    actual: snapshot.contextual_items
  });
  const contextualHrefs = contextualStructure && route.contextual_links.every((item, index) => {
    const target = contextualTarget(item.target_owner_id);
    const actual = snapshot.contextual_items[index];
    if (!target) return false;
    if (target.availability === 'live') {
      return actual.linked
        && !actual.unlinked
        && actual.owner_tag === 'A'
        && sameUrl(actual.href, target.href);
    }
    return !actual.linked
      && actual.href === ''
      && actual.unlinked
      && actual.owner_tag === 'SPAN';
  });
  addCheck(report, `${route.route_id}:${viewportLabel}:contextual-hrefs`, contextualHrefs, snapshot.contextual_items);
  addCheck(report, `${route.route_id}:${viewportLabel}:no-unpublished-contextual-link`, snapshot.contextual_items.every((item) => {
    const target = contextualTarget(item.target_owner_id);
    return target?.availability === 'live' || !item.linked;
  }), snapshot.contextual_items);
  addCheck(report, `${route.route_id}:${viewportLabel}:official-sources`, snapshot.source_links.length === route.source_ids.length
    && snapshot.source_links.every((link) => /^https:\/\//.test(link.href) && link.text && link.rel.includes('noopener')), snapshot.source_links.length);
  addCheck(report, `${route.route_id}:${viewportLabel}:hero`, Boolean(snapshot.hero)
    && snapshot.hero.complete
    && snapshot.hero.natural_width === expectedImageWidth
    && snapshot.hero.natural_height > 0
    && snapshot.hero.alt === route.public.h1, snapshot.hero);
  addCheck(report, `${route.route_id}:${viewportLabel}:versioned-assets`, snapshot.guide_css.includes(`ver=${release}`) && snapshot.guide_js.includes(`ver=${release}`), { css: snapshot.guide_css, js: snapshot.guide_js });
  addCheck(report, `${route.route_id}:${viewportLabel}:clean-controls`, snapshot.duplicate_ids.length === 0 && snapshot.unnamed_links === 0 && snapshot.unnamed_buttons === 0, {
    duplicate_ids: snapshot.duplicate_ids,
    unnamed_links: snapshot.unnamed_links,
    unnamed_buttons: snapshot.unnamed_buttons
  });
  addCheck(report, `${route.route_id}:${viewportLabel}:no-overflow`, snapshot.scroll_width <= snapshot.viewport_width + 1, { viewport: snapshot.viewport_width, scroll: snapshot.scroll_width });
  addCheck(report, `${route.route_id}:${viewportLabel}:no-long-dashes`, !/[\u2013\u2014]/u.test(snapshot.body_text));
  addCheck(report, `${route.route_id}:${viewportLabel}:no-presentation-copy`, presentationPhrases().every((phrase) => !snapshot.body_text.includes(phrase)));
  addCheck(report, `${route.route_id}:${viewportLabel}:no-stale-claims`, (staleClaimsByRoute[route.route_id] || []).every((phrase) => !snapshot.body_text.includes(phrase)));

  const screenshot = await capture(page, `${outputPrefix}-${route.route_id}-${viewportLabel}`);
  report.routes.push({ route_id: route.route_id, viewport: viewportLabel, ...inspected, screenshot });
}

async function validateMobileInteraction(browser, route, report) {
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    locale: 'he-IL',
    colorScheme: 'light',
    reducedMotion: 'reduce'
  });
  const page = await context.newPage();
  await page.addInitScript(() => {
    window.__thpScrollCalls = [];
    const original = Element.prototype.scrollIntoView;
    Element.prototype.scrollIntoView = function (options) {
      window.__thpScrollCalls.push(options || null);
      return original.call(this, options);
    };
  });
  await page.goto(routeUrl(route, 'mobile-interaction'), { waitUntil: 'networkidle', timeout });

  const toggle = page.locator('[data-thp-menu-open]');
  await toggle.focus();
  const focusStyle = await toggle.evaluate((element) => {
    const style = getComputedStyle(element);
    return { outlineColor: style.outlineColor, backgroundColor: style.backgroundColor, outlineWidth: style.outlineWidth, outlineStyle: style.outlineStyle };
  });
  const outline = parseColor(focusStyle.outlineColor);
  const background = parseColor(focusStyle.backgroundColor);
  addCheck(report, 'mobile-menu:focus-visible', focusStyle.outlineStyle !== 'none'
    && Number.parseFloat(focusStyle.outlineWidth) >= 2
    && outline && background && contrastRatio(outline, background) >= 3, focusStyle);

  await page.keyboard.press('Enter');
  const opened = await page.evaluate(() => ({
    expanded: document.querySelector('[data-thp-menu-open]')?.getAttribute('aria-expanded'),
    shell_hidden: document.querySelector('[data-thp-mobile-shell]')?.hasAttribute('hidden'),
    focus_in_panel: Boolean(document.activeElement?.closest('[data-thp-mobile-panel]'))
  }));
  addCheck(report, 'mobile-menu:keyboard-open', opened.expanded === 'true' && !opened.shell_hidden && opened.focus_in_panel, opened);

  const closeIcon = await page.locator('.thp-guide-close-icon').evaluate((element) => {
    const rect = element.getBoundingClientRect();
    const before = getComputedStyle(element, '::before');
    const after = getComputedStyle(element, '::after');
    return {
      text: (element.textContent || '').trim(),
      width: rect.width,
      height: rect.height,
      before_content: before.content,
      after_content: after.content,
      before_transform: before.transform,
      after_transform: after.transform
    };
  });
  addCheck(report, 'mobile-menu:css-close-icon', closeIcon.text === ''
    && closeIcon.width >= 18
    && closeIcon.height >= 18
    && closeIcon.before_content !== 'none'
    && closeIcon.after_content !== 'none'
    && closeIcon.before_transform !== 'none'
    && closeIcon.after_transform !== 'none', closeIcon);

  await page.keyboard.press('Escape');
  const closed = await page.evaluate(() => ({
    expanded: document.querySelector('[data-thp-menu-open]')?.getAttribute('aria-expanded'),
    shell_hidden: document.querySelector('[data-thp-mobile-shell]')?.hasAttribute('hidden'),
    focus_returned: document.activeElement === document.querySelector('[data-thp-menu-open]')
  }));
  addCheck(report, 'mobile-menu:escape-close', closed.expanded === 'false' && closed.shell_hidden && closed.focus_returned, closed);

  await page.locator('.thp-guide-toc a').first().click();
  const scrollCalls = await page.evaluate(() => window.__thpScrollCalls);
  addCheck(report, 'reduced-motion:toc-scroll', scrollCalls.length > 0 && scrollCalls.at(-1)?.behavior === 'auto', scrollCalls);

  await context.close();
}

async function validateNoJs(browser, route, report) {
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    locale: 'he-IL',
    javaScriptEnabled: false
  });
  const page = await context.newPage();
  const response = await page.goto(routeUrl(route, 'no-js'), { waitUntil: 'load', timeout });
  const state = await page.evaluate(() => {
    const nav = document.querySelector('.thp-guide-desktop-nav');
    const toggle = document.querySelector('[data-thp-menu-open]');
    return {
      status_surface: document.querySelectorAll('[data-thp-guide-route]').length,
      nav_display: nav ? getComputedStyle(nav).display : '',
      nav_links: nav?.querySelectorAll('a[href]').length || 0,
      toggle_display: toggle ? getComputedStyle(toggle).display : '',
      toggle_hidden: toggle?.hasAttribute('hidden') || false,
      main_height: document.querySelector('main')?.getBoundingClientRect().height || 0,
      viewport_width: document.documentElement.clientWidth,
      scroll_width: document.documentElement.scrollWidth
    };
  });
  addCheck(report, 'no-js:status', response?.status() === 200, response?.status());
  addCheck(report, 'no-js:content-and-navigation', state.status_surface === 1
    && state.main_height > 0
    && state.nav_display !== 'none'
    && state.nav_links >= 1
    && state.toggle_display === 'none'
    && state.toggle_hidden, state);
  addCheck(report, 'no-js:no-overflow', state.scroll_width <= state.viewport_width + 1, state);
  const screenshot = await capture(page, `${outputPrefix}-${route.route_id}-no-js`);
  report.no_js = { route_id: route.route_id, state, screenshot };
  await context.close();
}

async function smokeInternalLinks(browser, report) {
  const context = await browser.newContext({ locale: 'he-IL' });
  const request = context.request;
  const paths = new Set(['/']);
  contract.routes.forEach((route) => {
    paths.add(route.path);
    route.breadcrumbs.forEach((crumb) => paths.add(crumb.path));
  });
  const results = [];
  for (const routePath of paths) {
    const response = await request.get(new URL(routePath, baseUrl).toString(), { timeout });
    results.push({ path: routePath, status: response.status() });
  }
  addCheck(report, 'internal-link-targets:status', results.every((item) => item.status >= 200 && item.status < 400), results);
  report.internal_link_targets = results;
  await context.close();
}

function decodeXmlText(value) {
  return String(value || '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .trim();
}

function sitemapLocations(xml) {
  return [...String(xml || '').matchAll(/<loc>([\s\S]*?)<\/loc>/gi)]
    .map((match) => decodeXmlText(match[1]));
}

function sitemapEntries(xml, source) {
  return [...String(xml || '').matchAll(/<url>([\s\S]*?)<\/url>/gi)].map((match) => {
    const block = match[1];
    const location = block.match(/<loc>([\s\S]*?)<\/loc>/i);
    const modified = block.match(/<lastmod>([\s\S]*?)<\/lastmod>/i);
    return {
      source,
      loc: decodeXmlText(location?.[1] || ''),
      lastmod: decodeXmlText(modified?.[1] || '')
    };
  }).filter((entry) => entry.loc);
}

async function validateYoastSitemaps(browser, report) {
  const context = await browser.newContext({ locale: 'en-US' });
  const targets = {
    index: new URL('/sitemap_index.xml', baseUrl).toString(),
    post: new URL('/post-sitemap.xml', baseUrl).toString(),
    page: new URL('/page-sitemap.xml', baseUrl).toString()
  };
  const documents = {};
  const statuses = {};
  for (const [key, url] of Object.entries(targets)) {
    try {
      const response = await context.request.get(url, {
        timeout,
        failOnStatusCode: false,
        maxRedirects: 5
      });
      statuses[key] = { requested_url: url, final_url: response.url(), status: response.status() };
      documents[key] = await response.text();
      await response.dispose();
    } catch (error) {
      statuses[key] = { requested_url: url, final_url: '', status: 0, error: String(error?.message || error) };
      documents[key] = '';
    }
  }

  addCheck(report, 'sitemaps:responses', Object.values(statuses).every((item) => item.status >= 200 && item.status < 300), statuses);
  const indexLocations = sitemapLocations(documents.index);
  addCheck(report, 'sitemaps:index-membership', ['post', 'page'].every((key) => indexLocations.some((url) => sameUrl(url, targets[key]))), {
    expected: [targets.post, targets.page],
    actual: indexLocations
  });

  const entries = [
    ...sitemapEntries(documents.post, 'post-sitemap.xml'),
    ...sitemapEntries(documents.page, 'page-sitemap.xml')
  ];
  const routeResults = contract.routes.map((route) => {
    const canonical = canonicalFor(route);
    const matches = entries.filter((entry) => sameUrl(entry.loc, canonical));
    const indexable = route.indexing.policy !== 'noindex';
    const pass = indexable
      ? matches.length === 1 && matches[0].lastmod.slice(0, 10) === route.modified_on
      : matches.length === 0;
    return {
      route_id: route.route_id,
      indexable,
      expected_modified_on: route.modified_on,
      match_count: matches.length,
      matches,
      pass
    };
  });
  addCheck(report, 'sitemaps:guide-indexability-and-freshness', routeResults.every((item) => item.pass), routeResults);
  report.sitemaps = { statuses, index_locations: indexLocations, route_results: routeResults };
  await context.close();
}

async function smokeOfficialSources(browser, report) {
  const context = await browser.newContext({
    locale: 'en-US',
    extraHTTPHeaders: {
      Accept: 'text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.8'
    }
  });
  const results = [];
  const sources = contract.source_catalog.slice();
  for (let index = 0; index < sources.length; index += 4) {
    const batch = sources.slice(index, index + 4);
    const batchResults = await Promise.all(batch.map(async (source) => {
      try {
        const response = await context.request.get(source.url, {
          timeout,
          failOnStatusCode: false,
          maxRedirects: 8
        });
        const result = {
          source_id: source.source_id,
          requested_url: source.url,
          final_url: response.url(),
          status: response.status()
        };
        await response.dispose();
        return result;
      } catch (error) {
        return {
          source_id: source.source_id,
          requested_url: source.url,
          final_url: '',
          status: 0,
          error: String(error && error.message ? error.message : error)
        };
      }
    }));
    results.push(...batchResults);
  }
  addCheck(report, 'official-source-targets:status', results.every((item) => item.status >= 200 && item.status < 400), results);
  report.official_source_targets = results;
  await context.close();
}

async function validateHomepageHierarchy(browser, report) {
  const hubs = contract.routes
    .filter((route) => route.kind === 'collection')
    .map((route) => ({
      route_id: route.route_id,
      path: route.path,
      anchor: route.ownership.primary_keyword
    }));
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    locale: 'he-IL',
    colorScheme: 'light',
    reducedMotion: 'reduce'
  });
  const page = await context.newPage();
  const homepageUrl = new URL('/', baseUrl);
  homepageUrl.searchParams.set('qa', `${release}-guide-hierarchy-${Date.now()}`);
  const response = await page.goto(homepageUrl.toString(), { waitUntil: 'domcontentloaded', timeout });
  await page.waitForSelector('body.thailand-platform-home', { timeout });
  const surfaces = await page.evaluate((expectedHubs) => expectedHubs.map((hub) => ({
    ...hub,
    surfaces: ['desktop', 'mobile', 'footer'].map((surface) => {
      const container = document.querySelector(`[data-thp-guides-home-nav="${surface}"]`);
      const links = container
        ? [...container.querySelectorAll(`[data-thp-guides-home-link="${hub.route_id}"]`)]
        : [];
      return {
        surface,
        count: links.length,
        href: links[0]?.href || '',
        text: (links[0]?.textContent || '').replace(/\s+/g, ' ').trim()
      };
    })
  })), hubs);
  const targetStatuses = [];
  for (const hub of hubs) {
    const target = canonicalFor(hub);
    const targetResponse = await context.request.get(target, { timeout, failOnStatusCode: false });
    targetStatuses.push({ route_id: hub.route_id, url: targetResponse.url(), status: targetResponse.status() });
    await targetResponse.dispose();
  }

  addCheck(report, 'homepage-hierarchy:status', response?.status() === 200, response?.status() || 0);
  addCheck(report, 'homepage-hierarchy:atomic-three-surface-links', surfaces.length === 2
    && surfaces.every((hub) => hub.surfaces.length === 3
      && hub.surfaces.every((surface) => surface.count === 1
        && surface.text === hub.anchor
        && sameUrl(surface.href, canonicalFor(hub)))), surfaces);
  addCheck(report, 'homepage-hierarchy:canonical-targets', targetStatuses.length === 2
    && targetStatuses.every((item) => item.status === 200), targetStatuses);
  report.homepage_hierarchy = { surfaces, target_statuses: targetStatuses };
  await context.close();
}

async function main() {
  fs.mkdirSync(outputDir, { recursive: true });
  const report = {
    release,
    checked_at: new Date().toISOString(),
    base_url: baseUrl.toString(),
    contract_id: contract.contract_id,
    routes: [],
    checks: []
  };

  addCheck(report, 'contract:exact-seven-routes', contract.routes.length === 7
    && JSON.stringify(contract.routes.map((route) => route.route_id)) === JSON.stringify(expectedRouteIds), contract.routes.map((route) => route.route_id));

  const browser = await chromium.launch({ executablePath: chromePath, headless: true });
  try {
    for (const viewport of [
      { label: 'desktop', width: 1440, height: 1000 },
      { label: 'mobile', width: 390, height: 844 }
    ]) {
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        locale: 'he-IL',
        colorScheme: 'light',
        reducedMotion: 'reduce'
      });
      for (const route of contract.routes) {
        const page = await context.newPage();
        const errors = errorListeners(page);
        await validateRoute(page, route, viewport.label, report);
        addCheck(report, `${route.route_id}:${viewport.label}:clean-runtime`, unexpectedConsoleErrors(errors.console_errors).length === 0
          && errors.request_failures.length === 0
          && errors.bad_same_origin.length === 0, errors);
        await page.close();
      }
      await context.close();
    }

    await validateMobileInteraction(browser, contract.routes[0], report);
    await validateNoJs(browser, contract.routes[0], report);
    await validateHomepageHierarchy(browser, report);
    await smokeInternalLinks(browser, report);
    await validateYoastSitemaps(browser, report);
    await smokeOfficialSources(browser, report);
  } finally {
    await browser.close();
  }

  report.total_checks = report.checks.length;
  report.passed_checks = report.checks.filter((check) => check.pass).length;
  report.failed_checks = report.checks.filter((check) => !check.pass);
  report.ok = report.failed_checks.length === 0;

  const reportPath = path.join(outputDir, `${outputPrefix}-acceptance.json`);
  fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  if (!report.ok) {
    process.stderr.write(`FAIL: Guides live acceptance ${report.passed_checks}/${report.total_checks}. See ${reportPath}\n`);
    process.stderr.write(`${JSON.stringify(report.failed_checks, null, 2)}\n`);
    process.exitCode = 1;
    return;
  }
  process.stdout.write(`PASS: Guides live acceptance ${report.passed_checks}/${report.total_checks}. See ${reportPath}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error}\n`);
  process.exitCode = 1;
});
