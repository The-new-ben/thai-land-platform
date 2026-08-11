#!/usr/bin/env node

'use strict';

/*
 * Live usage (PowerShell):
 *   $env:THP_BASE_URL = 'https://thai-land.co.il/'
 *   $env:THP_DIGITAL_ISLAND_PAGE_ID = '<ID copied from the WP option>'
 *   node scripts/live_digital_island_acceptance.cjs
 *
 * The page ID is deliberately required and has no source-code default.
 * Use --self-test or --source-only for non-network release checks.
 */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const EXPECTED_PUBLIC_ENTITY_COUNT = 49;
const PAGE_ID_ENV = 'THP_DIGITAL_ISLAND_PAGE_ID';
const DEFAULT_BASE_URL = 'https://thai-land.co.il/';
const ISLAND_ID = 'geo:th:island:ko-pha-ngan';
const REST_PREFIX = `/wp-json/thailand-platform/v1/digital-islands/${ISLAND_ID}`;
const MAX_RESPONSE_BYTES = 12 * 1024 * 1024;

const FILES = {
  source: path.join(ROOT, 'data', 'digital-islands', 'koh-phangan.json'),
  schema: path.join(ROOT, 'data', 'digital-islands', 'island-world.schema.json'),
  manifest: path.join(ROOT, 'resources', 'digital-islands', 'manifest.json'),
  registry: path.join(ROOT, 'resources', 'digital-islands', 'registry.php'),
  context: path.join(ROOT, 'src', 'DigitalIslands', 'Context.php'),
  publicView: path.join(ROOT, 'src', 'DigitalIslands', 'PublicView.php'),
  restController: path.join(ROOT, 'src', 'DigitalIslands', 'RestController.php'),
  template: path.join(ROOT, 'templates', 'digital-islands', 'koh-phangan.php'),
  styles: path.join(ROOT, 'assets', 'digital-islands', 'digital-islands.css'),
  client: path.join(ROOT, 'assets', 'digital-islands', 'digital-islands.js'),
  settings: path.join(ROOT, 'src', 'DigitalIslands', 'Settings.php'),
  module: path.join(ROOT, 'src', 'DigitalIslands', 'Module.php'),
  seoRegistry: path.join(ROOT, 'data', 'seo', 'ownership-registry.json')
};

function invariant(condition, message) {
  if (!condition) throw new Error(message);
}

function readBytes(filename) {
  invariant(fs.existsSync(filename), `Required local file is missing: ${path.relative(ROOT, filename)}`);
  return fs.readFileSync(filename);
}

function readUtf8(filename) {
  return readBytes(filename).toString('utf8');
}

function readJson(filename) {
  const raw = readUtf8(filename);
  try {
    return JSON.parse(raw);
  } catch (error) {
    throw new Error(`Invalid JSON in ${path.relative(ROOT, filename)}: ${error.message}`);
  }
}

function sha256(value) {
  return crypto.createHash('sha256').update(value).digest('hex');
}

function fileReceipt(filename) {
  const bytes = readBytes(filename);
  return {
    path: path.relative(ROOT, filename).replace(/\\/g, '/'),
    bytes: bytes.length,
    sha256: sha256(bytes)
  };
}

const REVIEWED_ASSETS = Object.freeze({
  css: Object.freeze({
    filename: FILES.styles,
    pathnameSuffix: '/assets/digital-islands/digital-islands.css'
  }),
  javascript: Object.freeze({
    filename: FILES.client,
    pathnameSuffix: '/assets/digital-islands/digital-islands.js'
  })
});

function reviewedAssetReceipts() {
  return Object.fromEntries(Object.entries(REVIEWED_ASSETS).map(([assetType, definition]) => [
    assetType,
    {
      ...fileReceipt(definition.filename),
      pathname_suffix: definition.pathnameSuffix
    }
  ]));
}

function verifyReviewedAssetBytes(assetType, liveBytes) {
  const definition = REVIEWED_ASSETS[assetType];
  invariant(definition, `Unknown reviewed Digital Islands asset type: ${assetType}`);
  invariant(Buffer.isBuffer(liveBytes), `Live ${assetType} asset body is not a byte buffer`);
  const localBytes = readBytes(definition.filename);
  const localSha256 = sha256(localBytes);
  const liveSha256 = sha256(liveBytes);
  const exactBytesMatch = liveBytes.equals(localBytes);
  const sha256Match = liveSha256 === localSha256;
  invariant(exactBytesMatch, `Live ${assetType} bytes do not match the reviewed local asset`);
  invariant(sha256Match, `Live ${assetType} SHA-256 does not match the reviewed local asset`);
  return {
    asset_type: assetType,
    local_path: path.relative(ROOT, definition.filename).replace(/\\/g, '/'),
    reviewed_bytes: localBytes.length,
    reviewed_sha256: localSha256,
    live_bytes: liveBytes.length,
    live_sha256: liveSha256,
    exact_bytes_match: true,
    sha256_match: true
  };
}

function parsePageId(value) {
  invariant(typeof value === 'string' && /^[1-9][0-9]*$/.test(value), `${PAGE_ID_ENV} must be an explicit positive WordPress page ID; there is no fallback ID`);
  const pageId = Number(value);
  invariant(Number.isSafeInteger(pageId) && pageId > 0, `${PAGE_ID_ENV} is outside the safe integer range`);
  return pageId;
}

function configuredBaseUrl(value = process.env.THP_BASE_URL || DEFAULT_BASE_URL) {
  const baseUrl = new URL(value);
  invariant(['http:', 'https:'].includes(baseUrl.protocol), 'THP_BASE_URL must use HTTP or HTTPS');
  invariant(!baseUrl.username && !baseUrl.password, 'THP_BASE_URL must not contain credentials');
  baseUrl.pathname = '/';
  baseUrl.search = '';
  baseUrl.hash = '';
  return baseUrl;
}

function decodedPath(value) {
  const url = value instanceof URL ? value : new URL(value, DEFAULT_BASE_URL);
  try {
    return decodeURIComponent(url.pathname).normalize('NFC');
  } catch {
    return '';
  }
}

function sameCanonical(left, right) {
  try {
    const a = new URL(left);
    const b = new URL(right);
    return a.origin === b.origin && decodedPath(a) === decodedPath(b);
  } catch {
    return false;
  }
}

function sameCleanUrl(left, right) {
  try {
    const url = new URL(left);
    return sameCanonical(url, right) && url.search === '' && url.hash === '';
  } catch {
    return false;
  }
}

function cacheBusted(url, nonce, label) {
  const result = new URL(url);
  result.searchParams.set('thp_di_acceptance', `${nonce}-${label}`);
  return result;
}

function sourceContract() {
  const source = readJson(FILES.source);
  invariant(source.contract_id === 'thailand-digital-island-world-v1', 'Digital Islands source contract ID changed');
  invariant(source.canonical && typeof source.canonical.canonical_path === 'string', 'Digital Islands canonical path is missing');
  invariant(source.canonical.owner_id === 'koh-phangan-map', 'Digital Islands canonical owner changed');
  invariant(source.canonical.parent_owner_id === 'thailand-map', 'Digital Islands parent owner changed');
  return source;
}

function parentContract() {
  const registry = readJson(FILES.seoRegistry);
  const owners = Array.isArray(registry.intent_owners) ? registry.intent_owners : [];
  const owner = owners.find((candidate) => candidate.owner_id === 'thailand-map');
  invariant(owner, 'The central SEO registry is missing the thailand-map parent owner');
  invariant(owner.lifecycle === 'planned', 'The thailand-map parent must remain planned until its own reviewed release');
  invariant(typeof owner.canonical_url === 'string' && owner.canonical_url.startsWith('/'), 'The planned thailand-map canonical path is missing');
  return owner;
}

function structuralSourceGates(options = {}) {
  const requireLive = options.requireLive === true;
  const source = sourceContract();
  const manifest = readJson(FILES.manifest);
  const schemaReceipt = fileReceipt(FILES.schema);
  const sourceReceipt = fileReceipt(FILES.source);
  const manifestReceipt = fileReceipt(FILES.manifest);
  const registryReceipt = fileReceipt(FILES.registry);
  const entities = Array.isArray(source.entities) ? source.entities : [];
  const mapOnly = entities.filter((entity) => entity && entity.public_state === 'map_only');
  const mapOnlyIds = mapOnly.map((entity) => entity.entity_id);

  invariant(manifest.contract_id === source.contract_id, 'Manifest and source contract IDs disagree');
  invariant(manifest.dataset_version === source.dataset_version, 'Manifest and source dataset versions disagree');
  invariant(manifest.publication_state === source.publication_state, 'Manifest and source publication states disagree');
  invariant(manifest.counts && manifest.counts.canary_map_entities === EXPECTED_PUBLIC_ENTITY_COUNT, `Canary projection must contain exactly ${EXPECTED_PUBLIC_ENTITY_COUNT} entities`);
  invariant(mapOnly.length === EXPECTED_PUBLIC_ENTITY_COUNT, `Source map-only allowlist must contain exactly ${EXPECTED_PUBLIC_ENTITY_COUNT} entities`);
  invariant(new Set(mapOnlyIds).size === EXPECTED_PUBLIC_ENTITY_COUNT, 'Source map-only entity IDs are not unique');
  invariant(schemaReceipt.sha256 === manifest.schema_sha256, 'Schema SHA-256 does not match the manifest');
  invariant(manifest.artifacts && manifest.artifacts['resources/digital-islands/registry.php'], 'Manifest registry receipt is missing');
  invariant(registryReceipt.bytes === manifest.artifacts['resources/digital-islands/registry.php'].bytes, 'Registry byte count does not match the manifest');
  invariant(registryReceipt.sha256 === manifest.artifacts['resources/digital-islands/registry.php'].sha256, 'Registry SHA-256 does not match the manifest');

  if (source.publication_state === 'private_review') {
    invariant(manifest.counts.public_map_entities === 0, 'A private-review artifact must have no public projection');
  } else if (source.publication_state === 'live') {
    invariant(manifest.counts.public_map_entities === EXPECTED_PUBLIC_ENTITY_COUNT, `A Live artifact must expose exactly ${EXPECTED_PUBLIC_ENTITY_COUNT} public entities`);
  } else {
    throw new Error(`Unsupported Digital Islands publication state: ${source.publication_state}`);
  }
  if (requireLive) {
    invariant(source.publication_state === 'live', 'Live acceptance refuses a local artifact that is not publication_state=live');
    invariant(manifest.counts.public_map_entities === manifest.counts.canary_map_entities, 'Live public and Canary projection counts must be identical');
    const seoRegistry = readJson(FILES.seoRegistry);
    const owners = Array.isArray(seoRegistry.intent_owners) ? seoRegistry.intent_owners : [];
    const routes = Array.isArray(seoRegistry.routes) ? seoRegistry.routes : [];
    const mapOwner = owners.find((owner) => owner.owner_id === 'koh-phangan-map');
    const parentOwner = owners.find((owner) => owner.owner_id === 'thailand-map');
    const homeOwner = owners.find((owner) => owner.owner_id === 'home');
    const mapRoute = routes.find((route) => route.assignment && route.assignment.kind === 'canonical_owner' && route.assignment.owner_id === 'koh-phangan-map');
    invariant(mapOwner && mapOwner.lifecycle === 'live' && mapOwner.canonical_url === source.canonical.canonical_path, 'Central SEO registry lacks the Live Koh Phangan canonical owner');
    invariant(mapOwner.parent_owner_id === 'thailand-map', 'Central SEO owner hierarchy lost the planned Thailand Map parent');
    invariant(parentOwner && parentOwner.lifecycle === 'planned', 'Central SEO parent owner must remain planned');
    invariant(mapRoute && mapRoute.lifecycle === 'live' && mapRoute.indexing_policy === 'index' && mapRoute.url === source.canonical.canonical_path, 'Central SEO route is not the one Live indexable Koh Phangan owner');
    invariant(Array.isArray(mapOwner.internal_link_requirements) && mapOwner.internal_link_requirements.some((edge) => edge.target_owner_id === 'home' && edge.relationship === 'parent_hub'), 'Koh Phangan SEO owner lacks its direct Live Home edge');
    invariant(homeOwner && Array.isArray(homeOwner.internal_link_requirements) && homeOwner.internal_link_requirements.some((edge) => edge.target_owner_id === 'koh-phangan-map' && edge.relationship === 'child_spoke'), 'Home SEO owner lacks its reciprocal Koh Phangan child edge');
  }

  const context = readUtf8(FILES.context);
  const publicView = readUtf8(FILES.publicView);
  const restController = readUtf8(FILES.restController);
  const template = readUtf8(FILES.template);
  const client = readUtf8(FILES.client);
  const settings = readUtf8(FILES.settings);
  const module = readUtf8(FILES.module);

  invariant(context.includes('FeatureFlag::page_id() !== $post_id') || context.includes('FeatureFlag::page_id() === $post_id'), 'Live request identity is not bound to the configured WordPress page ID');
  invariant(context.includes('get_post_type( $post_id )') && context.includes("'page'"), 'Live request identity does not require post type page');
  invariant(context.includes('get_post_status( $post_id )') && context.includes("'publish'"), 'Live request identity does not require publish status');
  invariant(context.includes("get_post_field( 'post_password', $post_id )"), 'Live request identity does not inspect the raw stored password');
  invariant(publicView.includes('? Repository::public_entities()'), 'The public representation is not sourced from the compiled public projection');
  invariant(publicView.includes('Repository::public_ready()'), 'The public representation lacks its repository readiness guard');
  invariant(template.includes('PublicView::REPRESENTATION_CANARY === $representation ? wp_create_nonce'), 'The template nonce is not Canary-only');
  invariant(template.includes("if ( '' !== $rest_nonce )"), 'The public template cannot omit the REST nonce attribute');
  invariant(client.includes("credentials: nonce ? 'same-origin' : 'omit'"), 'The public client does not explicitly omit credentials');
  invariant(client.includes("if (nonce) headers['X-WP-Nonce'] = nonce"), 'The client nonce header is not conditional');
  invariant(restController.includes("'Cache-Control', 'public, max-age=300, stale-while-revalidate=60'"), 'The public REST cache contract is missing');
  invariant(settings.includes('EXPECTED_PUBLIC_ENTITY_COUNT = 49'), 'Administrator Live readiness is not bound to the exact 49-item release');
  invariant(settings.includes('check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME )'), 'Administrator settings lack their dedicated nonce gate');
  invariant(settings.includes("current_user_can( 'manage_options' )"), 'Administrator settings lack their capability gate');
  invariant(settings.includes("'thailand_platform_homepage_cache_purge_requested'"), 'Digital Islands option changes do not invalidate the homepage cache');
  invariant(settings.includes("add_action( 'add_option_' . $option") && settings.includes("add_action( 'update_option_' . $option"), 'Mode/page changes do not automatically request cache invalidation');
  invariant(module.includes('new Settings()'), 'Digital Islands Module does not register its administrator settings surface');
  invariant(context.includes("current_user_can( 'manage_options' )"), 'Canary source is not capability-bound to administrators');
  invariant(!context.includes('thp_di_canary=') && !context.includes('digital_islands_canary='), 'A query parameter must never select Canary state');

  if (requireLive) {
    for (const required of ['Seo.php', 'Schema.php', 'HomepageNavigation.php']) {
      invariant(fs.existsSync(path.join(ROOT, 'src', 'DigitalIslands', required)), `Live source is missing DigitalIslands/${required}`);
    }
  }

  return {
    publication_state: source.publication_state,
    dataset_version: source.dataset_version,
    canonical_path: source.canonical.canonical_path,
    canonical_owner_id: source.canonical.owner_id,
    parent_owner_id: source.canonical.parent_owner_id,
    source_entity_count: entities.length,
    canary_map_entity_count: manifest.counts.canary_map_entities,
    public_map_entity_count: manifest.counts.public_map_entities,
    receipts: [sourceReceipt, schemaReceipt, manifestReceipt, registryReceipt],
    reviewed_asset_receipts: reviewedAssetReceipts(),
    gates: {
      configured_page_id_identity: true,
      raw_password_identity: true,
      public_projection_only: true,
      public_credentials_omitted: true,
      canary_nonce_only: true,
      canary_query_switch_absent: true,
      administrator_nonce_and_capability: settings.includes("current_user_can( 'manage_options' )")
    }
  };
}

function decodeEntities(value) {
  const named = { amp: '&', apos: "'", gt: '>', lt: '<', nbsp: ' ', quot: '"' };
  return String(value).replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (match, entity) => {
    const lowered = entity.toLowerCase();
    if (lowered.startsWith('#x')) return String.fromCodePoint(Number.parseInt(lowered.slice(2), 16));
    if (lowered.startsWith('#')) return String.fromCodePoint(Number.parseInt(lowered.slice(1), 10));
    return Object.prototype.hasOwnProperty.call(named, lowered) ? named[lowered] : match;
  });
}

function parseAttributes(tag) {
  const attributes = {};
  const body = tag.replace(/^<\/?[^\s>]+/, '').replace(/\/?\s*>$/, '');
  const pattern = /([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
  let match;
  while ((match = pattern.exec(body)) !== null) {
    attributes[match[1].toLowerCase()] = decodeEntities(match[2] ?? match[3] ?? match[4] ?? '');
  }
  return attributes;
}

function tags(html, name) {
  return String(html).match(new RegExp(`<${name}\\b[^>]*>`, 'gi')) || [];
}

function textContent(value) {
  return decodeEntities(String(value)
    .replace(/<script\b[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style\b[\s\S]*?<\/style>/gi, ' ')
    .replace(/<[^>]+>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();
}

function tagBlocks(html, name) {
  return [...String(html).matchAll(new RegExp(`<${name}\\b([^>]*)>([\\s\\S]*?)<\\/${name}>`, 'gi'))]
    .map((match) => ({ opening: `<${name}${match[1]}>`, attributes: parseAttributes(`<${name}${match[1]}>`), body: match[2] }));
}

function metaValues(html, selectorName, selectorValue, targetAttribute = 'content') {
  return tags(html, 'meta')
    .map(parseAttributes)
    .filter((attributes) => (attributes[selectorName] || '').toLowerCase() === selectorValue.toLowerCase())
    .map((attributes) => attributes[targetAttribute] || '');
}

function canonicalLinks(html) {
  return tags(html, 'link')
    .map(parseAttributes)
    .filter((attributes) => (attributes.rel || '').toLowerCase().split(/\s+/).includes('canonical'))
    .map((attributes) => attributes.href || '');
}

function jsonLdPayloads(html) {
  const result = [];
  for (const script of tagBlocks(html, 'script')) {
    if ((script.attributes.type || '').toLowerCase() !== 'application/ld+json') continue;
    try {
      result.push(JSON.parse(script.body));
    } catch (error) {
      throw new Error(`Invalid JSON-LD in the live map document: ${error.message}`);
    }
  }
  return result;
}

function graphNodes(value, result = []) {
  if (Array.isArray(value)) {
    value.forEach((item) => graphNodes(item, result));
    return result;
  }
  if (!value || typeof value !== 'object') return result;
  if (Array.isArray(value['@graph'])) {
    value['@graph'].forEach((item) => graphNodes(item, result));
  } else {
    result.push(value);
  }
  return result;
}

function hasType(node, type) {
  const types = Array.isArray(node && node['@type']) ? node['@type'] : [node && node['@type']];
  return types.includes(type);
}

function schemaTarget(value) {
  if (typeof value === 'string') return value;
  if (!value || typeof value !== 'object') return '';
  return value.url || value['@id'] || '';
}

function recursiveForbiddenKeys(value, forbidden, trail = '$', violations = []) {
  if (Array.isArray(value)) {
    value.forEach((item, index) => recursiveForbiddenKeys(item, forbidden, `${trail}[${index}]`, violations));
    return violations;
  }
  if (!value || typeof value !== 'object') return violations;
  for (const [key, child] of Object.entries(value)) {
    if (forbidden.has(key)) violations.push(`${trail}.${key}`);
    recursiveForbiddenKeys(child, forbidden, `${trail}.${key}`, violations);
  }
  return violations;
}

function inspectDocument(html) {
  const htmlTag = tags(html, 'html')[0] || '';
  const bodyTag = tags(html, 'body')[0] || '';
  const title = tagBlocks(html, 'title')[0];
  const h1s = tagBlocks(html, 'h1');
  const mains = tagBlocks(html, 'main');
  const breadcrumbs = tagBlocks(html, 'nav').filter((block) => (block.attributes.class || '').split(/\s+/).includes('thp-di-breadcrumb'));
  const entityIds = tags(html, 'li')
    .map(parseAttributes)
    .filter((attributes) => Object.prototype.hasOwnProperty.call(attributes, 'data-entity-id'))
    .map((attributes) => attributes['data-entity-id']);
  const allLinks = tags(html, 'a').map(parseAttributes).filter((attributes) => attributes.href);
  return {
    html: parseAttributes(htmlTag),
    body: parseAttributes(bodyTag),
    title: title ? textContent(title.body) : '',
    h1s: h1s.map((block) => textContent(block.body)),
    mains,
    canonical: canonicalLinks(html),
    descriptions: metaValues(html, 'name', 'description'),
    robots: metaValues(html, 'name', 'robots').flatMap((value) => value.toLowerCase().split(/[\s,]+/).filter(Boolean)),
    ogUrls: metaValues(html, 'property', 'og:url'),
    breadcrumbs,
    entityIds,
    allLinks,
    schemas: jsonLdPayloads(html),
    hasApp: /\bdata-digital-island-app(?:\s|=|>)/i.test(html),
    hasRestNonce: /\bdata-rest-nonce(?:\s|=)/i.test(html),
    osmAttribution: allLinks.some((attributes) => attributes.href === 'https://www.openstreetmap.org/copyright')
  };
}

function validateBreadcrumb(document, canonicalUrl, baseUrl) {
  invariant(document.breadcrumbs.length === 1, `Expected one visible Digital Islands breadcrumb, found ${document.breadcrumbs.length}`);
  const items = tagBlocks(document.breadcrumbs[0].body, 'li');
  invariant(items.length === 3, `Visible breadcrumb must contain exactly three items, found ${items.length}`);
  const homeLinks = tags(items[0].body, 'a').map(parseAttributes);
  invariant(homeLinks.length === 1 && sameCanonical(new URL(homeLinks[0].href, canonicalUrl), new URL('/', baseUrl)), 'Visible breadcrumb Home item is not linked to the homepage');
  invariant(tags(items[1].body, 'a').length === 0, 'The planned Thailand Map breadcrumb parent must be plain text');
  invariant(textContent(items[1].body).includes('מפת תאילנד'), 'The planned Thailand Map breadcrumb label is missing');
  invariant((items[2].attributes['aria-current'] || '').toLowerCase() === 'page', 'Current breadcrumb item lacks aria-current=page');
  invariant(tags(items[2].body, 'a').length === 0, 'Current breadcrumb item must not link to itself');
}

function validateSchema(document, canonicalUrl, baseUrl) {
  invariant(document.schemas.length === 1, `Expected one Digital Islands JSON-LD graph, found ${document.schemas.length}`);
  const nodes = document.schemas.flatMap((payload) => graphNodes(payload));
  const webPages = nodes.filter((node) => hasType(node, 'WebPage') || hasType(node, 'CollectionPage'));
  const datasets = nodes.filter((node) => hasType(node, 'Dataset'));
  const breadcrumbs = nodes.filter((node) => hasType(node, 'BreadcrumbList'));
  invariant(webPages.length >= 1, 'JSON-LD WebPage/CollectionPage schema is missing');
  invariant(webPages.some((node) => sameCanonical(node.url || node['@id'] || '', canonicalUrl)), 'JSON-LD page entity does not own the canonical URL');
  invariant(datasets.length >= 1, 'JSON-LD Dataset schema is missing');
  invariant(breadcrumbs.length === 1, `Expected one JSON-LD BreadcrumbList, found ${breadcrumbs.length}`);
  const items = breadcrumbs[0].itemListElement;
  invariant(Array.isArray(items) && items.length === 3, 'JSON-LD breadcrumb must contain exactly three ListItems');
  invariant(items.every((item, index) => Number(item.position) === index + 1), 'JSON-LD breadcrumb positions are not 1, 2, 3');
  invariant(sameCanonical(schemaTarget(items[0].item), new URL('/', baseUrl)), 'JSON-LD Home breadcrumb target is wrong');
  invariant(schemaTarget(items[1].item) === '', 'Planned Thailand Map breadcrumb parent must omit an item URL');
  invariant(sameCanonical(schemaTarget(items[2].item), canonicalUrl), 'JSON-LD current breadcrumb target is not self-canonical');
  return { node_count: nodes.length, types: [...new Set(nodes.flatMap((node) => Array.isArray(node['@type']) ? node['@type'] : [node['@type']]).filter(Boolean))] };
}

async function fetchBytes(url, options = {}) {
  invariant(typeof fetch === 'function', 'Node.js 18 or newer is required');
  const timeoutMs = options.timeoutMs || 45000;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, {
      method: 'GET',
      redirect: options.redirect || 'manual',
      credentials: 'omit',
      cache: 'no-store',
      headers: {
        Accept: options.accept || '*/*',
        'Cache-Control': 'no-cache',
        'User-Agent': 'ThailandPlatformDigitalIslandAcceptance/1'
      },
      signal: controller.signal
    });
    const bytes = Buffer.from(await response.arrayBuffer());
    invariant(bytes.length <= MAX_RESPONSE_BYTES, `Response exceeded ${MAX_RESPONSE_BYTES} bytes: ${url}`);
    const headers = {};
    for (const [key, value] of response.headers.entries()) headers[key.toLowerCase()] = value;
    return {
      requested_url: String(url),
      final_url: response.url,
      status: response.status,
      headers,
      bytes,
      text: bytes.toString('utf8'),
      sha256: sha256(bytes)
    };
  } finally {
    clearTimeout(timer);
  }
}

function publicCacheHeaders(response, label) {
  const cacheControl = (response.headers['cache-control'] || '').toLowerCase();
  invariant(cacheControl.includes('public') && cacheControl.includes('max-age='), `${label} lacks its public cache contract`);
  invariant(!cacheControl.includes('private') && !cacheControl.includes('no-store'), `${label} emits a private/no-store cache contract`);
}

function parseJsonResponse(response, label) {
  invariant(response.status === 200, `${label} expected HTTP 200, received ${response.status}`);
  invariant((response.headers['content-type'] || '').toLowerCase().includes('json'), `${label} is not JSON`);
  try {
    return JSON.parse(response.text);
  } catch (error) {
    throw new Error(`${label} returned invalid JSON: ${error.message}`);
  }
}

function validatePublicPayload(payload, label) {
  invariant(payload && payload.representation_state === 'public_live', `${label} is not the public_live representation`);
  const forbidden = new Set(['holds', 'conflicts', 'source_id', 'source_ids', 'reviewer_notes', 'private_notes']);
  const violations = recursiveForbiddenKeys(payload, forbidden);
  invariant(violations.length === 0, `${label} leaked private/source fields: ${violations.slice(0, 5).join(', ')}`);
  const raw = JSON.stringify(payload);
  invariant(!raw.includes('private_review') && !raw.includes('private_canary'), `${label} leaked a private representation marker`);
}

async function validateRest(baseUrl, nonce, timeoutMs) {
  const endpoints = {
    island: REST_PREFIX,
    layers: `${REST_PREFIX}/layers`,
    entities: `${REST_PREFIX}/entities`
  };
  const results = {};
  let entitiesPayload = null;
  for (const [label, endpoint] of Object.entries(endpoints)) {
    const response = await fetchBytes(new URL(endpoint, baseUrl), {
      timeoutMs,
      accept: 'application/json'
    });
    const payload = parseJsonResponse(response, `REST ${label}`);
    publicCacheHeaders(response, `REST ${label}`);
    validatePublicPayload(payload, `REST ${label}`);
    if (label === 'entities') entitiesPayload = payload;
    results[label] = {
      status: response.status,
      url: response.final_url,
      content_type: response.headers['content-type'] || '',
      cache_control: response.headers['cache-control'] || '',
      x_robots_tag: response.headers['x-robots-tag'] || '',
      bytes: response.bytes.length,
      sha256: response.sha256,
      contract_id: payload.contract_id,
      representation_state: payload.representation_state
    };
  }
  invariant(entitiesPayload.entity_count === EXPECTED_PUBLIC_ENTITY_COUNT, `REST entities count must be exactly ${EXPECTED_PUBLIC_ENTITY_COUNT}`);
  invariant(Array.isArray(entitiesPayload.entities) && entitiesPayload.entities.length === EXPECTED_PUBLIC_ENTITY_COUNT, 'REST entity_count disagrees with the entity collection');
  const ids = entitiesPayload.entities.map((entity) => entity.entity_id);
  invariant(new Set(ids).size === EXPECTED_PUBLIC_ENTITY_COUNT, 'REST public entity IDs are not unique');
  results.entities.entity_count = entitiesPayload.entity_count;
  results.entities.entity_ids_sha256 = sha256(Buffer.from([...ids].sort().join('\n'), 'utf8'));
  return results;
}

async function validateWordPressPage(baseUrl, pageId, canonicalUrl, nonce, timeoutMs) {
  const endpoint = new URL(`/wp-json/wp/v2/pages/${pageId}`, baseUrl);
  endpoint.searchParams.set('_fields', 'id,type,status,link,slug,content');
  endpoint.searchParams.set('thp_di_acceptance', `${nonce}-page-object`);
  const response = await fetchBytes(endpoint, { timeoutMs, accept: 'application/json' });
  const page = parseJsonResponse(response, 'WordPress page identity');
  invariant(page.id === pageId, `WordPress REST returned page ID ${page.id}, expected configured ID ${pageId}`);
  invariant(page.type === 'page', 'Configured WordPress object is not type page');
  invariant(page.status === 'publish', 'Configured WordPress page is not published');
  invariant(sameCleanUrl(page.link, canonicalUrl), 'Configured WordPress page link is not the exact map canonical');
  invariant(!page.content || page.content.protected !== true, 'Configured WordPress page is password protected');
  return {
    configured_page_id: pageId,
    returned_page_id: page.id,
    type: page.type,
    status: page.status,
    link: page.link,
    protected: page.content ? page.content.protected === true : null,
    bytes: response.bytes.length,
    sha256: response.sha256
  };
}

async function validateMapPage(baseUrl, pageId, canonicalUrl, nonce, timeoutMs) {
  const response = await fetchBytes(cacheBusted(canonicalUrl, nonce, 'document-query-state'), {
    timeoutMs,
    accept: 'text/html,application/xhtml+xml'
  });
  invariant(response.status === 200, `Map page expected HTTP 200, received ${response.status}`);
  invariant((response.headers['content-type'] || '').toLowerCase().includes('text/html'), 'Map page is not HTML');
  invariant(decodedPath(response.final_url) === decodedPath(canonicalUrl), 'Map page final path is not the reviewed canonical path');
  const document = inspectDocument(response.text);
  invariant(document.html.lang && document.html.lang.toLowerCase().startsWith('he'), 'Map document language is not Hebrew');
  invariant((document.html.dir || '').toLowerCase() === 'rtl', 'Map document direction is not RTL');
  invariant(document.hasApp, 'Digital Islands application marker is missing');
  invariant(!document.hasRestNonce, 'Public map HTML leaked a REST nonce attribute');
  invariant(document.mains.length === 1, `Expected one main landmark, found ${document.mains.length}`);
  invariant(document.h1s.length === 1 && document.h1s[0].length > 0, `Expected one non-empty H1, found ${document.h1s.length}`);
  invariant(document.title.length > 0, 'Map document title is empty');
  invariant(document.descriptions.length === 1 && document.descriptions[0].length >= 70, 'Map document must have one useful meta description');
  invariant(document.canonical.length === 1 && sameCleanUrl(document.canonical[0], canonicalUrl), 'Map document must have one clean self-canonical link');
  invariant(document.ogUrls.length === 1 && sameCleanUrl(document.ogUrls[0], canonicalUrl), 'Map document Open Graph URL is missing or not canonical');
  const directives = new Set([
    ...document.robots,
    ...(response.headers['x-robots-tag'] || '').toLowerCase().split(/[\s,]+/).filter(Boolean)
  ]);
  invariant(directives.has('index') && directives.has('follow'), 'Live map must explicitly emit index,follow');
  invariant(!directives.has('noindex') && !directives.has('nofollow'), 'Live map emitted a private robots directive');
  invariant((document.body.class || '').split(/\s+/).includes(`page-id-${pageId}`), `Live HTML body is not bound to configured page ID ${pageId}`);
  invariant(document.entityIds.length === EXPECTED_PUBLIC_ENTITY_COUNT, `Server-rendered map must contain exactly ${EXPECTED_PUBLIC_ENTITY_COUNT} entity cards`);
  invariant(new Set(document.entityIds).size === EXPECTED_PUBLIC_ENTITY_COUNT, 'Server-rendered map entity IDs are not unique');
  invariant(!response.text.includes('private_review') && !response.text.includes('private_canary'), 'Public map HTML leaked a private representation marker');
  invariant(document.osmAttribution, 'Persistent OpenStreetMap copyright attribution link is missing');
  validateBreadcrumb(document, canonicalUrl, baseUrl);
  const schema = validateSchema(document, canonicalUrl, baseUrl);
  const homeLinks = document.allLinks.filter((attributes) => sameCanonical(new URL(attributes.href, canonicalUrl), new URL('/', baseUrl)));
  invariant(homeLinks.length >= 1, 'Live map lacks its reciprocal Home edge');
  return {
    response: {
      status: response.status,
      requested_url: response.requested_url,
      final_url: response.final_url,
      content_type: response.headers['content-type'] || '',
      x_robots_tag: response.headers['x-robots-tag'] || '',
      bytes: response.bytes.length,
      sha256: response.sha256
    },
    identity: {
      configured_page_id: pageId,
      body_page_id_match: true,
      title: document.title,
      h1: document.h1s[0],
      canonical: document.canonical[0],
      meta_description_length: document.descriptions[0].length,
      robots: [...directives],
      entity_count: document.entityIds.length,
      entity_ids_sha256: sha256(Buffer.from([...document.entityIds].sort().join('\n'), 'utf8')),
      public_rest_nonce_absent: true,
      private_markers_absent: true,
      osm_attribution_present: true
    },
    schema,
    assetUrls: [
      ...tags(response.text, 'link').map(parseAttributes).map((attributes) => attributes.href),
      ...tags(response.text, 'script').map(parseAttributes).map((attributes) => attributes.src)
    ].filter((value) => typeof value === 'string' && value.includes('digital-islands'))
  };
}

async function validateAssets(assetUrls, canonicalUrl, baseUrl, nonce, timeoutMs) {
  const unique = [...new Set(assetUrls.map((value) => new URL(value, canonicalUrl).toString()))];
  invariant(unique.length >= 2, 'Expected both Digital Islands CSS and JavaScript asset URLs');
  const results = [];
  const observedTypes = new Set();
  for (const [index, url] of unique.entries()) {
    const cleanPath = decodedPath(new URL(url));
    const assetType = Object.keys(REVIEWED_ASSETS).find((candidate) => cleanPath.endsWith(REVIEWED_ASSETS[candidate].pathnameSuffix));
    invariant(assetType, `Digital Islands document referenced an unreviewed asset path: ${url}`);
    invariant(!observedTypes.has(assetType), `Digital Islands document referenced the ${assetType} asset more than once`);
    observedTypes.add(assetType);
    const assetUrl = cacheBusted(new URL(url), nonce, `asset-${index}`);
    invariant(assetUrl.origin === baseUrl.origin, `Digital Islands asset is not same-origin: ${url}`);
    const response = await fetchBytes(assetUrl, { timeoutMs, accept: '*/*' });
    invariant(response.status === 200, `Digital Islands asset returned HTTP ${response.status}: ${url}`);
    invariant(response.bytes.length > 0, `Digital Islands asset is empty: ${url}`);
    const integrity = verifyReviewedAssetBytes(assetType, response.bytes);
    results.push({
      url,
      status: response.status,
      content_type: response.headers['content-type'] || '',
      bytes: response.bytes.length,
      sha256: response.sha256,
      ...integrity
    });
  }
  invariant(observedTypes.has('css'), 'Digital Islands CSS asset was not verified');
  invariant(observedTypes.has('javascript'), 'Digital Islands JavaScript asset was not verified');
  invariant(observedTypes.size === Object.keys(REVIEWED_ASSETS).length, 'Digital Islands live assets do not match the complete reviewed local set');
  return {
    reviewed_local: reviewedAssetReceipts(),
    live: results,
    exact_bytes_match: results.every((item) => item.exact_bytes_match),
    sha256_match: results.every((item) => item.sha256_match)
  };
}

async function validateHomepage(baseUrl, canonicalUrl, nonce, timeoutMs) {
  const response = await fetchBytes(cacheBusted(new URL('/', baseUrl), nonce, 'homepage'), {
    timeoutMs,
    accept: 'text/html,application/xhtml+xml'
  });
  invariant(response.status === 200, `Homepage expected HTTP 200, received ${response.status}`);
  const links = tags(response.text, 'a').map(parseAttributes).filter((attributes) => attributes.href);
  const matches = links.filter((attributes) => sameCleanUrl(new URL(attributes.href, baseUrl), canonicalUrl));
  const managedMatches = matches.filter((attributes) => attributes['data-thp-digital-island-home-link'] === 'koh-phangan-map');
  invariant(managedMatches.length === 1, `Homepage must have one managed direct live link to the Koh Phangan map, found ${managedMatches.length}`);
  return {
    status: response.status,
    final_url: response.final_url,
    direct_link_count: matches.length,
    managed_direct_link_count: managedMatches.length,
    anchors: managedMatches.map((attributes) => textContent(attributes['aria-label'] || attributes.title || attributes.href)),
    bytes: response.bytes.length,
    sha256: response.sha256
  };
}

function xmlLocations(xml) {
  return [...String(xml).matchAll(/<loc\b[^>]*>([\s\S]*?)<\/loc>/gi)]
    .map((match) => decodeEntities(match[1]).trim())
    .filter(Boolean);
}

async function validateSitemaps(baseUrl, canonicalUrl, parentCanonicalUrl, nonce, timeoutMs) {
  const indexUrl = cacheBusted(new URL('/sitemap_index.xml', baseUrl), nonce, 'sitemap-index');
  const index = await fetchBytes(indexUrl, { timeoutMs, accept: 'application/xml,text/xml,*/*' });
  invariant(index.status === 200, `Sitemap index expected HTTP 200, received ${index.status}`);
  invariant(/<(?:sitemapindex|urlset)\b/i.test(index.text), 'Sitemap index is not sitemap XML');
  const receipts = [{ url: index.final_url, status: index.status, bytes: index.bytes.length, sha256: index.sha256 }];
  const documents = [index.text];
  if (/<sitemapindex\b/i.test(index.text)) {
    const childLocations = xmlLocations(index.text);
    invariant(childLocations.length > 0 && childLocations.length <= 80, 'Sitemap index child count is empty or unbounded');
    for (const [position, location] of childLocations.entries()) {
      const url = new URL(location, baseUrl);
      invariant(url.origin === baseUrl.origin, `Sitemap child is not same-origin: ${location}`);
      const response = await fetchBytes(cacheBusted(url, nonce, `sitemap-${position}`), { timeoutMs, accept: 'application/xml,text/xml,*/*' });
      invariant(response.status === 200, `Sitemap child returned HTTP ${response.status}: ${location}`);
      invariant(/<urlset\b/i.test(response.text), `Sitemap child is not a URL set: ${location}`);
      documents.push(response.text);
      receipts.push({ url: response.final_url, status: response.status, bytes: response.bytes.length, sha256: response.sha256 });
    }
  }

  const listed = documents.flatMap(xmlLocations).filter((location) => {
    try {
      return new URL(location, baseUrl).origin === baseUrl.origin;
    } catch {
      return false;
    }
  });
  const childMatches = listed.filter((location) => sameCleanUrl(new URL(location, baseUrl), canonicalUrl));
  const parentMatches = listed.filter((location) => sameCanonical(new URL(location, baseUrl), parentCanonicalUrl));
  invariant(childMatches.length === 1, `Koh Phangan canonical must appear exactly once in sitemaps, found ${childMatches.length}`);
  invariant(parentMatches.length === 0, 'Planned Thailand Map parent must not appear in the sitemap');
  return {
    child_occurrences: childMatches.length,
    planned_parent_occurrences: parentMatches.length,
    sitemap_document_count: receipts.length,
    receipts
  };
}

function buildContract(options = {}) {
  const source = sourceContract();
  const parent = parentContract();
  const baseUrl = configuredBaseUrl(options.baseUrl);
  const rawPageId = options.pageId === undefined ? process.env[PAGE_ID_ENV] : options.pageId;
  const pageId = rawPageId === undefined || rawPageId === '' ? null : parsePageId(String(rawPageId));
  const canonicalUrl = new URL(source.canonical.canonical_path, baseUrl);
  return {
    schema_version: 1,
    acceptance_id: 'thailand-platform-koh-phangan-live-v1',
    origin: baseUrl.origin,
    configured_page_id_env: PAGE_ID_ENV,
    configured_page_id: pageId,
    configured_page_id_has_fallback: false,
    canonical_url: canonicalUrl.toString(),
    owner_id: source.canonical.owner_id,
    parent_owner: {
      owner_id: parent.owner_id,
      lifecycle: parent.lifecycle,
      canonical_url: new URL(parent.canonical_url, baseUrl).toString()
    },
    expected_public_entity_count: EXPECTED_PUBLIC_ENTITY_COUNT,
    request_mode: 'anonymous-no-cookie-no-rest-nonce',
    live_gates: [
      'configured WordPress page ID, type, publish status, permalink and password state',
      'exact canonical HTML identity with UTM-safe path ownership',
      '49-item compiled public projection and private-field absence',
      'public REST representation and cache headers',
      'title, description, index/follow, self-canonical and Open Graph URL',
      'visible Home > planned Thailand Map > Koh Phangan breadcrumb',
      'WebPage, Dataset and BreadcrumbList JSON-LD',
      'direct homepage edge and reciprocal Home edge',
      'child-only sitemap inclusion with planned parent omitted',
      'same-origin Digital Islands CSS and JavaScript exact-byte equality with reviewed local SHA-256 receipts'
    ]
  };
}

async function runLive(options = {}) {
  const contract = buildContract(options);
  invariant(contract.configured_page_id !== null, `${PAGE_ID_ENV} is required for Live acceptance; copy the exact positive ID from the WordPress Digital Islands setting`);
  const timeoutMs = Number.parseInt(process.env.THP_LIVE_TIMEOUT_MS || '45000', 10);
  invariant(Number.isInteger(timeoutMs) && timeoutMs >= 1000 && timeoutMs <= 120000, 'THP_LIVE_TIMEOUT_MS must be between 1000 and 120000');
  const nonce = process.env.THP_DIGITAL_ISLAND_ACCEPTANCE_NONCE || `${Date.now().toString(36)}-${crypto.randomBytes(8).toString('hex')}`;
  invariant(/^[A-Za-z0-9._-]{8,160}$/.test(nonce), 'THP_DIGITAL_ISLAND_ACCEPTANCE_NONCE has an unsafe shape');
  const baseUrl = new URL(contract.origin);
  const canonicalUrl = new URL(contract.canonical_url);
  const parentCanonicalUrl = new URL(contract.parent_owner.canonical_url);
  const local = structuralSourceGates({ requireLive: true });

  const pageObject = await validateWordPressPage(baseUrl, contract.configured_page_id, canonicalUrl, nonce, timeoutMs);
  const mapPage = await validateMapPage(baseUrl, contract.configured_page_id, canonicalUrl, nonce, timeoutMs);
  const rest = await validateRest(baseUrl, nonce, timeoutMs);
  invariant(mapPage.identity.entity_ids_sha256 === rest.entities.entity_ids_sha256, 'Server HTML and REST public entity identities disagree');
  const assets = await validateAssets(mapPage.assetUrls, canonicalUrl, baseUrl, nonce, timeoutMs);
  const homepage = await validateHomepage(baseUrl, canonicalUrl, nonce, timeoutMs);
  const sitemaps = await validateSitemaps(baseUrl, canonicalUrl, parentCanonicalUrl, nonce, timeoutMs);

  const report = {
    ...contract,
    observed_at: new Date().toISOString(),
    cache_bust_nonce: nonce,
    passed: true,
    local_structural_gates: local,
    wordpress_page_identity: pageObject,
    public_document: mapPage.response,
    public_identity: mapPage.identity,
    structured_data: mapPage.schema,
    public_rest: rest,
    homepage,
    sitemaps,
    assets
  };
  const outputPath = process.env.THP_DIGITAL_ISLAND_ACCEPTANCE_OUTPUT
    ? path.resolve(process.env.THP_DIGITAL_ISLAND_ACCEPTANCE_OUTPUT)
    : path.join(ROOT, 'output', 'acceptance', `digital-island-live-${local.dataset_version}.json`);
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  return { report, outputPath };
}

function runSelfTest() {
  invariant(parsePageId('731') === 731, 'Self-test page ID parsing failed');
  let missingRejected = false;
  try { parsePageId(''); } catch { missingRejected = true; }
  invariant(missingRejected, 'Self-test missing page ID was not rejected');
  const forbidden = recursiveForbiddenKeys({ entities: [{ entity_id: 'x', holds: ['private'] }] }, new Set(['holds']));
  invariant(forbidden.length === 1 && forbidden[0].endsWith('.holds'), 'Self-test private-field scanner failed');
  const contract = buildContract({ pageId: '' });
  invariant(contract.configured_page_id === null && contract.configured_page_id_has_fallback === false, 'Acceptance contract introduced a fallback page ID');
  const local = structuralSourceGates({ requireLive: false });
  invariant(local.canary_map_entity_count === EXPECTED_PUBLIC_ENTITY_COUNT, 'Self-test source projection count failed');
  return { contract, local };
}

async function main() {
  const args = process.argv.slice(2);
  invariant(args.length <= 1, 'Usage: node scripts/live_digital_island_acceptance.cjs [--contract-only|--source-only|--self-test]');
  if (args[0] === '--contract-only') {
    process.stdout.write(`${JSON.stringify(buildContract(), null, 2)}\n`);
    return;
  }
  if (args[0] === '--source-only') {
    const result = structuralSourceGates({ requireLive: false });
    process.stdout.write(`PASS: Digital Islands source gates; ${result.canary_map_entity_count} Canary entities, ${result.public_map_entity_count} public entities, state ${result.publication_state}.\n`);
    return;
  }
  if (args[0] === '--self-test') {
    const result = runSelfTest();
    process.stdout.write(`PASS: Digital Islands live acceptance self-test; no fallback page ID and ${result.local.canary_map_entity_count} reviewed Canary entities.\n`);
    return;
  }
  invariant(args.length === 0, 'Unknown acceptance argument');
  const { report, outputPath } = await runLive();
  process.stdout.write(`PASS: Digital Islands Live acceptance for configured page ${report.configured_page_id}, ${report.public_rest.entities.entity_count} public entities. Report: ${outputPath}\n`);
}

module.exports = {
  EXPECTED_PUBLIC_ENTITY_COUNT,
  PAGE_ID_ENV,
  buildContract,
  decodeEntities,
  graphNodes,
  inspectDocument,
  parseAttributes,
  parsePageId,
  recursiveForbiddenKeys,
  reviewedAssetReceipts,
  runSelfTest,
  sameCanonical,
  structuralSourceGates,
  validatePublicPayload,
  verifyReviewedAssetBytes,
  xmlLocations
};

if (require.main === module) {
  main().catch((error) => {
    process.stderr.write(`FAIL: ${error.message}\n`);
    process.exitCode = 1;
  });
}
