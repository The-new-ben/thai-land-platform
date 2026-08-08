#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const registryPath = path.join(rootDir, 'data', 'seo', 'ownership-registry.json');
const timeoutMs = Number.parseInt(process.env.THP_LIVE_TIMEOUT_MS || '30000', 10);

function invariant(condition, message) {
  if (!condition) throw new Error(message);
}

function loadAcceptanceContract() {
  const registry = JSON.parse(fs.readFileSync(registryPath, 'utf8'));
  const owners = new Map(registry.intent_owners.map((owner) => [owner.owner_id, owner]));
  const migrationRoutes = registry.routes.filter(
    (route) => route.assignment.kind === 'migration_gate',
  );

  invariant(migrationRoutes.length === 2, 'Expected exactly two migration gates.');

  const gates = new Map();
  for (const route of migrationRoutes) {
    const assignment = route.assignment;
    const currentOwner = owners.get(assignment.current_owner_id);
    invariant(route.route_kind === 'exact', `${route.route_id} must be an exact route.`);
    invariant(route.lifecycle === 'live', `${route.route_id} must remain live.`);
    invariant(assignment.release_blocked === true, `${route.route_id} must remain blocked.`);
    invariant(currentOwner, `${route.route_id} has an unknown current owner.`);

    const expectedRedirectTarget = route.indexing_policy === 'redirect'
      ? route.redirect_target
      : null;
    if (route.indexing_policy === 'redirect') {
      invariant(
        expectedRedirectTarget === currentOwner.canonical_url,
        `${route.route_id} does not target its declared current owner.`,
      );
    } else {
      invariant(route.indexing_policy === 'index', `${route.route_id} has an unsupported policy.`);
      invariant(route.url === currentOwner.canonical_url, `${route.route_id} is not self-canonical.`);
      invariant(!route.redirect_target, `${route.route_id} unexpectedly declares a redirect.`);
    }

    gates.set(route.route_id, {
      route_id: route.route_id,
      path: route.url,
      current_owner_id: assignment.current_owner_id,
      candidate: assignment.candidate_owner_id,
      state: assignment.state,
      indexing_policy: route.indexing_policy,
      expected_redirect_target: expectedRedirectTarget,
    });
  }

  return { registry, gates };
}

function decodeHtmlEntities(value) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_, digits) => String.fromCodePoint(Number.parseInt(digits, 16)))
    .replace(/&#([0-9]+);/g, (_, digits) => String.fromCodePoint(Number.parseInt(digits, 10)))
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&apos;|&#39;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>');
}

function parseAttributes(tag) {
  const attributes = {};
  const body = tag.replace(/^<\s*[^\s>]+/, '').replace(/\/?>\s*$/, '');
  const pattern = /([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
  let match;
  while ((match = pattern.exec(body)) !== null) {
    attributes[match[1].toLowerCase()] = decodeHtmlEntities(
      match[2] ?? match[3] ?? match[4] ?? '',
    );
  }
  return attributes;
}

function canonicalLinks(html) {
  return [...html.matchAll(/<link\b[^>]*>/gi)]
    .map((match) => parseAttributes(match[0]))
    .filter((attributes) => (
      (attributes.rel || '').toLowerCase().split(/\s+/).includes('canonical')
    ))
    .map((attributes) => attributes.href)
    .filter(Boolean);
}

function robotsDirectives(html, headers) {
  const values = [];
  const headerValue = headers.get('x-robots-tag');
  if (headerValue) values.push(headerValue);
  for (const match of html.matchAll(/<meta\b[^>]*>/gi)) {
    const attributes = parseAttributes(match[0]);
    const name = (attributes.name || '').toLowerCase();
    if (name === 'robots' || name === 'googlebot' || name === 'bingbot') {
      values.push(attributes.content || '');
    }
  }
  return values;
}

async function anonymousFetch(url) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, {
      method: 'GET',
      redirect: 'manual',
      credentials: 'omit',
      cache: 'no-store',
      signal: controller.signal,
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'Accept-Language': 'he-IL,he;q=0.9,en;q=0.5',
        'Cache-Control': 'no-cache',
        'User-Agent': 'Mozilla/5.0 SEO-Migration-Acceptance/1.0',
      },
    });
  } finally {
    clearTimeout(timer);
  }
}

function exactUrl(value) {
  const url = new URL(value);
  return `${url.origin}${url.pathname}${url.search}${url.hash}`
    .replace(/%[0-9a-f]{2}/gi, (sequence) => sequence.toUpperCase());
}

async function run() {
  invariant(Number.isInteger(timeoutMs) && timeoutMs > 0, 'THP_LIVE_TIMEOUT_MS must be positive.');
  const { registry, gates } = loadAcceptanceContract();
  const configuredBase = process.env.THP_BASE_URL || registry.site.base_url;
  const base = new URL(configuredBase);
  invariant(['http:', 'https:'].includes(base.protocol), 'THP_BASE_URL must use HTTP or HTTPS.');
  invariant(!base.username && !base.password, 'THP_BASE_URL cannot contain credentials.');
  const origin = base.origin;

  const cheap = gates.get('route-cheap-flight-tips-legacy');
  const business = gates.get('route-business-short-redirect');
  invariant(cheap, 'Cheap-flight tips migration gate is missing.');
  invariant(business, 'Business short-route migration gate is missing.');
  invariant(cheap.indexing_policy === 'index', 'Cheap-flight tips must remain indexable.');
  invariant(business.indexing_policy === 'redirect', 'Business short route must remain a redirect.');

  const cheapUrl = new URL(cheap.path, `${origin}/`);
  const cheapResponse = await anonymousFetch(cheapUrl);
  const cheapHtml = await cheapResponse.text();
  invariant(cheapResponse.status === 200, `Cheap-flight tips returned ${cheapResponse.status}, expected 200.`);
  invariant(
    (cheapResponse.headers.get('content-type') || '').toLowerCase().includes('text/html'),
    'Cheap-flight tips did not return HTML.',
  );
  const canonicals = canonicalLinks(cheapHtml);
  invariant(canonicals.length === 1, `Cheap-flight tips exposed ${canonicals.length} canonicals.`);
  const cheapCanonical = new URL(canonicals[0], cheapUrl);
  invariant(
    exactUrl(cheapCanonical) === exactUrl(cheapUrl),
    `Cheap-flight tips canonical is ${cheapCanonical.href}, expected ${cheapUrl.href}.`,
  );
  const robotValues = robotsDirectives(cheapHtml, cheapResponse.headers);
  invariant(
    robotValues.every((value) => !/(?:^|[\s,])noindex(?:$|[\s,])/i.test(value)),
    `Cheap-flight tips is not indexable: ${robotValues.join(' | ')}`,
  );

  const businessUrl = new URL(business.path, `${origin}/`);
  const businessResponse = await anonymousFetch(businessUrl);
  invariant(
    businessResponse.status === 301 || businessResponse.status === 302,
    `Business short route returned ${businessResponse.status}, expected 301 or 302.`,
  );
  const location = businessResponse.headers.get('location');
  invariant(location, 'Business short route did not return a Location header.');
  const actualTarget = new URL(location, businessUrl);
  const expectedTarget = new URL(business.expected_redirect_target, `${origin}/`);
  invariant(
    exactUrl(actualTarget) === exactUrl(expectedTarget),
    `Business short route targets ${actualTarget.href}, expected ${expectedTarget.href}.`,
  );

  const report = {
    checked_at: new Date().toISOString(),
    origin,
    anonymous: true,
    passed: true,
    routes: {
      cheap_flight_tips: {
        route_id: cheap.route_id,
        path: cheap.path,
        status: cheapResponse.status,
        canonical: cheapCanonical.href,
        indexable: true,
      },
      business_short_redirect: {
        route_id: business.route_id,
        path: business.path,
        status: businessResponse.status,
        location: actualTarget.href,
        current_owner_id: business.current_owner_id,
      },
    },
  };
  process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
}

run().catch((error) => {
  process.stderr.write(`SEO migration live acceptance failed: ${error.message}\n`);
  process.exitCode = 1;
});
