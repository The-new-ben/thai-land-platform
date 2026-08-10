#!/usr/bin/env node

'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DEFAULT_RELEASE = '0.4.1';
const EXPECTED_PROTECTED_SURFACES = 43;
const INVENTORY_PATHS = Object.freeze([
  'data/seo/inventory/current-public-url-metadata.2026-08-08.csv',
  'data/seo/inventory/indexable-category-surfaces.2026-08-08.csv'
]);

function invariant(condition, message) {
  if (!condition) throw new Error(message);
}

function readUtf8(relativePath) {
  return fs.readFileSync(path.join(ROOT, ...relativePath.split('/')), 'utf8');
}

function sha256Lf(value) {
  return crypto.createHash('sha256').update(value.replace(/\r\n?/g, '\n'), 'utf8').digest('hex');
}

function parseCsv(value) {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;

  for (let index = 0; index < value.length; index += 1) {
    const character = value[index];
    if (quoted) {
      if (character === '"' && value[index + 1] === '"') {
        field += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        field += character;
      }
    } else if (character === '"') {
      quoted = true;
    } else if (character === ',') {
      row.push(field);
      field = '';
    } else if (character === '\n') {
      row.push(field.replace(/\r$/, ''));
      rows.push(row);
      row = [];
      field = '';
    } else {
      field += character;
    }
  }

  invariant(!quoted, 'CSV contains an unterminated quoted field');
  if (field !== '' || row.length > 0) {
    row.push(field.replace(/\r$/, ''));
    rows.push(row);
  }
  invariant(rows.length > 1, 'CSV must contain a header and at least one row');

  const headers = rows.shift().map((header, index) => index === 0 ? header.replace(/^\uFEFF/, '') : header);
  invariant(new Set(headers).size === headers.length, 'CSV headers must be unique');
  return rows.filter((values) => values.some((item) => item !== '')).map((values, rowIndex) => {
    invariant(values.length === headers.length, `CSV row ${rowIndex + 2} has ${values.length} fields, expected ${headers.length}`);
    return Object.fromEntries(headers.map((header, index) => [header, values[index]]));
  });
}

function decodePath(value) {
  let decoded = value;
  try {
    decoded = decodeURIComponent(value);
  } catch {
    throw new Error(`Path is not valid percent-encoding: ${value}`);
  }
  decoded = decoded.normalize('NFC').replace(/\/{2,}/g, '/');
  if (!decoded.startsWith('/')) decoded = `/${decoded}`;
  if (decoded !== '/' && !decoded.endsWith('/')) decoded += '/';
  return decoded;
}

function pathFromUrl(value) {
  return decodePath(new URL(value).pathname);
}

function urlIdentity(value) {
  const parsed = new URL(value);
  const port = (parsed.protocol === 'https:' && parsed.port === '443') || (parsed.protocol === 'http:' && parsed.port === '80')
    ? ''
    : parsed.port;
  return `${parsed.protocol.toLowerCase()}//${parsed.hostname.toLowerCase()}${port ? `:${port}` : ''}${decodePath(parsed.pathname)}${parsed.search}`;
}

function sameUrl(left, right) {
  try {
    return urlIdentity(left) === urlIdentity(right);
  } catch {
    return false;
  }
}

function buildUnrelated404Path(release) {
  const releaseToken = release.replace(/[^0-9A-Za-z]+/g, '-').replace(/^-|-$/g, '').toLowerCase();
  const nonce = crypto.createHash('sha256').update(`thailand-platform:${release}:sitewide-unrelated-404`).digest('hex').slice(0, 16);
  return `/__thp-sitewide-${releaseToken}-${nonce}/`;
}

function cacheBustedUrl(value, release, identity, cacheNonce) {
  const target = new URL(value);
  const nonce = crypto.createHash('sha256').update(`${release}:${identity}:${cacheNonce}:anonymous-get`).digest('hex').slice(0, 12);
  target.searchParams.set('thp_sitewide_acceptance', `${release.replace(/\./g, '-')}-${nonce}`);
  return target.toString();
}

function buildContract(options = {}) {
  const release = options.release || process.env.THP_RELEASE || DEFAULT_RELEASE;
  const cacheNonce = options.cacheNonce || process.env.THP_SITEWIDE_ACCEPTANCE_NONCE || 'contract';
  invariant(/^\d+(?:\.\d+)+$/.test(release), `Invalid release: ${release}`);
  invariant(/^[0-9A-Za-z._-]{1,128}$/.test(cacheNonce), 'Acceptance cache nonce is invalid');

  const registry = JSON.parse(readUtf8('data/seo/ownership-registry.json'));
  const baseUrl = new URL(options.baseUrl || process.env.THP_BASE_URL || registry.site.base_url);
  invariant(baseUrl.protocol === 'https:' || baseUrl.protocol === 'http:', 'Base URL must use HTTP or HTTPS');
  invariant(!baseUrl.username && !baseUrl.password, 'Base URL must not contain credentials');

  const snapshotsByPath = new Map(registry.inventory_snapshots.map((snapshot) => [snapshot.path, snapshot]));
  const ownersById = new Map(registry.intent_owners.map((owner) => [owner.owner_id, owner]));
  const exactRoutesByPath = new Map();
  for (const route of registry.routes) {
    if (route.route_kind !== 'exact') continue;
    const normalized = decodePath(route.url);
    const routes = exactRoutesByPath.get(normalized) || [];
    routes.push(route);
    exactRoutesByPath.set(normalized, routes);
  }

  const snapshots = [];
  const surfaces = [];
  for (const relativePath of INVENTORY_PATHS) {
    const snapshot = snapshotsByPath.get(relativePath);
    invariant(snapshot, `SEO registry does not declare frozen inventory ${relativePath}`);
    invariant(snapshot.digest_algorithm === 'sha256-lf-v1', `Unexpected digest algorithm for ${relativePath}`);

    const csvText = readUtf8(relativePath);
    invariant(sha256Lf(csvText) === snapshot.content_sha256, `Frozen inventory digest mismatch: ${relativePath}`);
    const rows = parseCsv(csvText);
    invariant(rows.length === snapshot.row_count, `Frozen inventory row count mismatch: ${relativePath}`);
    invariant(rows.length === snapshot.protected_url_count, `Frozen protected URL count mismatch: ${relativePath}`);
    snapshots.push({
      snapshot_id: snapshot.snapshot_id,
      path: relativePath,
      row_count: rows.length,
      content_sha256: snapshot.content_sha256
    });

    for (const row of rows) {
      const inventoryUrl = new URL(row.Url);
      const decodedPath = decodePath(row.DecodedPath);
      invariant(pathFromUrl(inventoryUrl) === decodedPath, `Inventory URL and decoded path disagree: ${row.Url}`);
      invariant(inventoryUrl.origin === new URL(snapshot.origin).origin, `Inventory origin mismatch: ${row.Url}`);

      const matchingRoutes = (exactRoutesByPath.get(decodedPath) || []).filter(
        (route) => Array.isArray(route.observed_in) && route.observed_in.includes(snapshot.snapshot_id)
      );
      invariant(matchingRoutes.length === 1, `Protected path must resolve to exactly one observed SEO route: ${decodedPath}`);
      const route = matchingRoutes[0];
      invariant(route.lifecycle === 'live', `Protected route is not live: ${route.route_id}`);
      invariant(route.assignment && ['canonical_owner', 'migration_gate'].includes(route.assignment.kind), `Protected route lacks a current owner: ${route.route_id}`);
      const ownerId = route.assignment.kind === 'canonical_owner'
        ? route.assignment.owner_id
        : route.assignment.current_owner_id;
      invariant(
        route.assignment.kind !== 'migration_gate'
          || (route.assignment.state === 'evidence_pending' && route.assignment.release_blocked === true),
        `Migration gate does not preserve its current owner: ${route.route_id}`
      );
      const owner = ownersById.get(ownerId);
      invariant(owner && owner.lifecycle === 'live', `Protected route owner is not live: ${route.route_id}`);
      invariant(decodePath(owner.canonical_url) === decodedPath, `Owner canonical path mismatch: ${route.route_id}`);
      invariant(route.indexing_policy === 'index' || route.indexing_policy === 'noindex', `Unsupported protected indexing policy: ${route.route_id}`);

      const expectedStatus = Number.parseInt(row.Status, 10);
      invariant(expectedStatus === 200, `Protected frozen surface must remain a 200 document: ${decodedPath}`);
      surfaces.push({
        snapshot_id: snapshot.snapshot_id,
        sitemap_kind: row.SitemapKind,
        path: decodedPath,
        requested_url: new URL(decodedPath, baseUrl).toString(),
        probe_url: cacheBustedUrl(new URL(decodedPath, baseUrl), release, route.route_id, cacheNonce),
        expected_status: expectedStatus,
        route_id: route.route_id,
        owner_id: owner.owner_id,
        canonical_path: decodePath(owner.canonical_url),
        indexing_policy: route.indexing_policy,
        expected_html_lang: row.HtmlLang,
        expected_html_dir: row.HtmlDir
      });
    }
  }

  const uniquePaths = new Set(surfaces.map((surface) => surface.path));
  const uniqueRoutes = new Set(surfaces.map((surface) => surface.route_id));
  const uniqueOwners = new Set(surfaces.map((surface) => surface.owner_id));
  invariant(surfaces.length === EXPECTED_PROTECTED_SURFACES, `Expected ${EXPECTED_PROTECTED_SURFACES} protected surfaces, found ${surfaces.length}`);
  invariant(uniquePaths.size === EXPECTED_PROTECTED_SURFACES, 'Protected inventory paths are not unique');
  invariant(uniqueRoutes.size === EXPECTED_PROTECTED_SURFACES, 'Protected inventory routes are not one-to-one');
  invariant(uniqueOwners.size === EXPECTED_PROTECTED_SURFACES, 'Protected inventory canonical owners are not one-to-one');

  const observedRoutes = registry.routes.filter((route) =>
    Array.isArray(route.observed_in) && route.observed_in.some((snapshotId) => snapshots.some((snapshot) => snapshot.snapshot_id === snapshotId))
  );
  invariant(observedRoutes.length === EXPECTED_PROTECTED_SURFACES, 'SEO registry observed-route inventory is not exactly 43');

  const indexableCount = surfaces.filter((surface) => surface.indexing_policy === 'index').length;
  const noindexSurfaces = surfaces.filter((surface) => surface.indexing_policy === 'noindex');
  invariant(indexableCount === 42, `Expected 42 indexable protected surfaces, found ${indexableCount}`);
  invariant(noindexSurfaces.length === 1, `Expected one noindex protected surface, found ${noindexSurfaces.length}`);
  invariant(noindexSurfaces[0].owner_id === 'thailand-entry-april-2022', 'The reviewed historical entry page must be the sole noindex protected surface');

  const canaryProbes = [
    {
      probe_id: 'published-guides-canary-anonymous',
      url: cacheBustedUrl(new URL('/hello-world/?thp_guides_canary=1', baseUrl), release, 'published-guides-canary', cacheNonce),
      expected_status: 404
    },
    {
      probe_id: 'draft-guides-canary-anonymous',
      url: cacheBustedUrl(new URL('/?page_id=846&preview=true&thp_guides_canary=1', baseUrl), release, 'draft-guides-canary', cacheNonce),
      expected_status: 404
    }
  ];

  const unrelatedPath = buildUnrelated404Path(release);
  invariant(!uniquePaths.has(unrelatedPath), 'Release-unique 404 collides with a protected surface');
  invariant(!(exactRoutesByPath.get(unrelatedPath) || []).length, 'Release-unique 404 collides with an SEO route');

  return {
    schema_version: 1,
    release,
    origin: baseUrl.origin,
    request_mode: 'anonymous',
    cache_bust: {
      query_parameter: 'thp_sitewide_acceptance',
      run_nonce: cacheNonce
    },
    inventory: {
      snapshots,
      protected_surface_count: surfaces.length,
      unique_path_count: uniquePaths.size,
      unique_route_count: uniqueRoutes.size,
      unique_owner_count: uniqueOwners.size
    },
    seo_contract: {
      indexable_surface_count: indexableCount,
      noindex_surface_count: noindexSurfaces.length,
      sole_noindex_owner_id: noindexSurfaces[0].owner_id
    },
    html_identity_basis: [
      'exact response URL',
      'self canonical',
      'HTML language and direction',
      'one main landmark with one non-empty primary heading',
      'canonical owner marker when a managed marker is present'
    ],
    surfaces,
    canary_probes: canaryProbes,
    unrelated_404: {
      probe_id: `unrelated-release-${release}-404`,
      path: unrelatedPath,
      url: cacheBustedUrl(new URL(unrelatedPath, baseUrl), release, 'unrelated-404', cacheNonce),
      expected_status: 404
    }
  };
}

function decodeEntities(value) {
  const named = { amp: '&', apos: "'", gt: '>', lt: '<', nbsp: ' ', quot: '"' };
  return value.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (match, entity) => {
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
  return html.match(new RegExp(`<${name}\\b[^>]*>`, 'gi')) || [];
}

function textContent(value) {
  return decodeEntities(value.replace(/<script\b[\s\S]*?<\/script>/gi, ' ').replace(/<style\b[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();
}

function elementText(html, name) {
  const match = html.match(new RegExp(`<${name}\\b[^>]*>([\\s\\S]*?)<\\/${name}>`, 'i'));
  return match ? textContent(match[1]) : '';
}

function inspectHtml(html) {
  const htmlTag = tags(html, 'html')[0] || '';
  const htmlAttributes = htmlTag ? parseAttributes(htmlTag) : {};
  const mainMatches = [...html.matchAll(/<main\b[^>]*>([\s\S]*?)<\/main>/gi)];
  const mainBody = mainMatches.length === 1 ? mainMatches[0][1] : '';
  const mainHeadings = mainBody.match(/<h1\b[^>]*>[\s\S]*?<\/h1>/gi) || [];
  const canonicalLinks = tags(html, 'link')
    .map(parseAttributes)
    .filter((attributes) => (attributes.rel || '').toLowerCase().split(/\s+/).includes('canonical'))
    .map((attributes) => attributes.href || '');
  const robotDirectives = tags(html, 'meta')
    .map(parseAttributes)
    .filter((attributes) => (attributes.name || '').toLowerCase() === 'robots')
    .flatMap((attributes) => (attributes.content || '').toLowerCase().split(/[\s,]+/).filter(Boolean));
  const ownerMarkers = [];
  const routeMarkers = [];
  for (const tag of html.match(/<[^>]+>/g) || []) {
    if (!/data-thp-(?:guide-(?:owner|route)|owner-id|route-id)\s*=/i.test(tag)) continue;
    const attributes = parseAttributes(tag);
    for (const key of ['data-thp-guide-owner', 'data-thp-owner-id']) {
      if (attributes[key]) ownerMarkers.push(attributes[key]);
    }
    for (const key of ['data-thp-guide-route', 'data-thp-route-id']) {
      if (attributes[key]) routeMarkers.push(attributes[key]);
    }
  }
  return {
    has_html: Boolean(htmlTag),
    html_lang: htmlAttributes.lang || '',
    html_dir: htmlAttributes.dir || '',
    title: elementText(html, 'title'),
    main_count: mainMatches.length,
    main_h1_count: mainHeadings.length,
    main_h1: mainHeadings.length ? textContent(mainHeadings[0]) : '',
    canonical_links: canonicalLinks,
    robots: [...new Set(robotDirectives)],
    owner_markers: [...new Set(ownerMarkers)],
    route_markers: [...new Set(routeMarkers)]
  };
}

function validateProtectedResponse(surface, response) {
  invariant(response.status === surface.expected_status, `${surface.path}: expected HTTP ${surface.expected_status}, received ${response.status}`);
  invariant(sameUrl(response.url, surface.probe_url), `${surface.path}: response URL identity mismatch`);
  invariant((response.content_type || '').toLowerCase().includes('text/html'), `${surface.path}: response is not HTML`);
  const identity = inspectHtml(response.body);
  invariant(identity.has_html, `${surface.path}: HTML root is missing`);
  invariant(identity.title.length > 0, `${surface.path}: document title is empty`);
  invariant(identity.main_count === 1, `${surface.path}: expected one main landmark, found ${identity.main_count}`);
  invariant(identity.main_h1_count === 1 && identity.main_h1.length > 0, `${surface.path}: primary heading identity is invalid`);
  invariant(identity.html_lang.toLowerCase() === surface.expected_html_lang.toLowerCase(), `${surface.path}: HTML language mismatch`);
  invariant(identity.html_dir.toLowerCase() === surface.expected_html_dir.toLowerCase(), `${surface.path}: HTML direction mismatch`);
  invariant(identity.canonical_links.length === 1, `${surface.path}: expected one canonical link, found ${identity.canonical_links.length}`);
  const expectedCanonical = new URL(surface.canonical_path, surface.requested_url).toString();
  invariant(sameUrl(identity.canonical_links[0], expectedCanonical), `${surface.path}: canonical URL mismatch`);
  if (identity.owner_markers.length > 0) {
    invariant(identity.owner_markers.every((ownerId) => ownerId === surface.owner_id), `${surface.path}: managed owner marker mismatch`);
  }

  const directives = new Set([
    ...identity.robots,
    ...(response.x_robots_tag || '').toLowerCase().split(/[\s,]+/).filter(Boolean)
  ]);
  if (surface.indexing_policy === 'noindex') {
    invariant(directives.has('noindex'), `${surface.path}: reviewed noindex directive is missing`);
    invariant(directives.has('follow') && !directives.has('nofollow'), `${surface.path}: reviewed noindex surface must remain followable`);
  } else {
    invariant(!directives.has('noindex'), `${surface.path}: indexable surface emits noindex`);
    invariant(!directives.has('nofollow'), `${surface.path}: indexable surface emits nofollow`);
  }

  return {
    path: surface.path,
    route_id: surface.route_id,
    owner_id: surface.owner_id,
    status: response.status,
    canonical: identity.canonical_links[0],
    indexing_policy: surface.indexing_policy,
    html_identity: {
      final_url: response.url,
      html_lang: identity.html_lang,
      html_dir: identity.html_dir,
      title: identity.title,
      main_h1: identity.main_h1,
      main_count: identity.main_count,
      main_h1_count: identity.main_h1_count,
      owner_markers: identity.owner_markers,
      route_markers: identity.route_markers
    }
  };
}

function validate404Response(probe, response, options = {}) {
  invariant(response.status === probe.expected_status, `${probe.probe_id}: expected HTTP ${probe.expected_status}, received ${response.status}`);
  invariant(sameUrl(response.url, probe.url), `${probe.probe_id}: response URL identity mismatch`);
  invariant((response.content_type || '').toLowerCase().includes('text/html'), `${probe.probe_id}: response is not HTML`);
  const identity = inspectHtml(response.body);
  invariant(identity.has_html && identity.title.length > 0, `${probe.probe_id}: 404 HTML identity is missing`);
  invariant(identity.owner_markers.length === 0 && identity.route_markers.length === 0, `${probe.probe_id}: managed content leaked into an anonymous 404`);
  if (options.unrelated) {
    invariant(pathFromUrl(response.url) === probe.path, `${probe.probe_id}: unrelated 404 path changed`);
  }
  return {
    probe_id: probe.probe_id,
    url: probe.url,
    status: response.status,
    title: identity.title,
    managed_marker_count: identity.owner_markers.length + identity.route_markers.length
  };
}

async function fetchAnonymous(url, timeoutMs) {
  invariant(typeof fetch === 'function', 'Node.js 18 or newer is required for the built-in fetch API');
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, {
      method: 'GET',
      redirect: 'manual',
      credentials: 'omit',
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Cache-Control': 'no-cache',
        'User-Agent': `ThailandPlatformSitewideAcceptance/${DEFAULT_RELEASE}`
      },
      signal: controller.signal
    });
    return {
      status: response.status,
      url: response.url,
      content_type: response.headers.get('content-type') || '',
      x_robots_tag: response.headers.get('x-robots-tag') || '',
      body: await response.text()
    };
  } finally {
    clearTimeout(timer);
  }
}

async function mapLimit(values, limit, callback) {
  const results = new Array(values.length);
  let cursor = 0;
  async function worker() {
    while (cursor < values.length) {
      const index = cursor;
      cursor += 1;
      results[index] = await callback(values[index], index);
    }
  }
  await Promise.all(Array.from({ length: Math.min(limit, values.length) }, worker));
  return results;
}

async function runLive(options = {}) {
  const cacheNonce = options.cacheNonce
    || process.env.THP_SITEWIDE_ACCEPTANCE_NONCE
    || `${Date.now().toString(36)}-${crypto.randomBytes(8).toString('hex')}`;
  const contract = buildContract({ ...options, cacheNonce });
  const timeoutMs = Number.parseInt(process.env.THP_LIVE_TIMEOUT_MS || '45000', 10);
  const concurrency = Number.parseInt(process.env.THP_LIVE_CONCURRENCY || '6', 10);
  invariant(Number.isInteger(timeoutMs) && timeoutMs >= 1000, 'THP_LIVE_TIMEOUT_MS must be at least 1000');
  invariant(Number.isInteger(concurrency) && concurrency >= 1 && concurrency <= 12, 'THP_LIVE_CONCURRENCY must be between 1 and 12');

  const protectedResults = await mapLimit(contract.surfaces, concurrency, async (surface) =>
    validateProtectedResponse(surface, await fetchAnonymous(surface.probe_url, timeoutMs))
  );
  const canaryResults = [];
  for (const probe of contract.canary_probes) {
    canaryResults.push(validate404Response(probe, await fetchAnonymous(probe.url, timeoutMs)));
  }
  const unrelatedResult = validate404Response(
    contract.unrelated_404,
    await fetchAnonymous(contract.unrelated_404.url, timeoutMs),
    { unrelated: true }
  );

  const report = {
    schema_version: 1,
    acceptance_id: `thailand-platform-sitewide-${contract.release}`,
    release: contract.release,
    origin: contract.origin,
    observed_at: new Date().toISOString(),
    request_mode: contract.request_mode,
    cache_bust: contract.cache_bust,
    passed: true,
    inventory: contract.inventory,
    seo_contract: contract.seo_contract,
    html_identity_basis: contract.html_identity_basis,
    protected_surfaces: protectedResults,
    anonymous_guides_canary_404_probes: canaryResults,
    unrelated_404: unrelatedResult
  };

  const configuredOutput = process.env.THP_SITEWIDE_ACCEPTANCE_OUTPUT;
  const outputPath = configuredOutput
    ? path.resolve(configuredOutput)
    : path.join(ROOT, 'output', 'acceptance', `sitewide-live-${contract.release}.json`);
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  return { report, outputPath };
}

async function main() {
  if (process.argv.includes('--contract-only')) {
    process.stdout.write(`${JSON.stringify(buildContract(), null, 2)}\n`);
    return;
  }
  invariant(process.argv.length === 2, 'Usage: node scripts/live_sitewide_acceptance.cjs [--contract-only]');
  const { report, outputPath } = await runLive();
  process.stdout.write(
    `PASS: sitewide live acceptance ${report.inventory.protected_surface_count} protected surfaces, 2 anonymous Guides Canary 404 probes, and 1 unrelated 404 for ${report.release}. Report: ${outputPath}\n`
  );
}

module.exports = {
  DEFAULT_RELEASE,
  EXPECTED_PROTECTED_SURFACES,
  INVENTORY_PATHS,
  buildContract,
  buildUnrelated404Path,
  inspectHtml,
  parseCsv,
  validate404Response,
  validateProtectedResponse
};

if (require.main === module) {
  main().catch((error) => {
    process.stderr.write(`FAIL: ${error.message}\n`);
    process.exitCode = 1;
  });
}
