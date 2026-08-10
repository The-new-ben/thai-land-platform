#!/usr/bin/env node

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const acceptancePath = path.join(root, 'scripts', 'live_sitewide_acceptance.cjs');
const acceptance = require(acceptancePath);

const fixedNonce = 'focused-test-041';
const contract = acceptance.buildContract({ release: '0.4.1', cacheNonce: fixedNonce });
const secondNonceContract = acceptance.buildContract({ release: '0.4.1', cacheNonce: 'focused-test-041-second' });
assert.deepStrictEqual(
  acceptance.INVENTORY_PATHS,
  [
    'data/seo/inventory/current-public-url-metadata.2026-08-08.csv',
    'data/seo/inventory/indexable-category-surfaces.2026-08-08.csv'
  ]
);
assert.strictEqual(contract.inventory.snapshots.length, 2);
assert.deepStrictEqual(contract.cache_bust, {
  query_parameter: 'thp_sitewide_acceptance',
  run_nonce: fixedNonce
});
assert.deepStrictEqual(contract.inventory.snapshots.map((snapshot) => snapshot.row_count), [40, 3]);
assert.strictEqual(contract.inventory.protected_surface_count, 43);
assert.strictEqual(contract.inventory.unique_path_count, 43);
assert.strictEqual(contract.inventory.unique_route_count, 43);
assert.strictEqual(contract.inventory.unique_owner_count, 43);
assert.strictEqual(contract.surfaces.length, 43);
assert.strictEqual(new Set(contract.surfaces.map((surface) => surface.owner_id)).size, 43);
assert.strictEqual(contract.seo_contract.indexable_surface_count, 42);
assert.strictEqual(contract.seo_contract.noindex_surface_count, 1);
assert.strictEqual(contract.seo_contract.sole_noindex_owner_id, 'thailand-entry-april-2022');

for (const surface of contract.surfaces) {
  assert.strictEqual(surface.expected_status, 200);
  assert.strictEqual(surface.path, surface.canonical_path);
  assert.strictEqual(new URL(surface.requested_url).origin, contract.origin);
  assert.strictEqual(new URL(surface.probe_url).origin, contract.origin);
  assert.ok(new URL(surface.probe_url).searchParams.get('thp_sitewide_acceptance').startsWith('0-4-1-'));
}

assert.strictEqual(contract.canary_probes.length, 2);
for (const probe of contract.canary_probes) {
  const url = new URL(probe.url);
  assert.strictEqual(url.origin, contract.origin);
  assert.strictEqual(probe.expected_status, 404);
  assert.strictEqual(url.searchParams.get('thp_guides_canary'), '1');
  assert.ok(url.searchParams.get('thp_sitewide_acceptance').startsWith('0-4-1-'));
}
assert.strictEqual(new URL(contract.canary_probes[0].url).pathname, '/hello-world/');
assert.strictEqual(new URL(contract.canary_probes[1].url).searchParams.get('page_id'), '846');
assert.strictEqual(new URL(contract.canary_probes[1].url).searchParams.get('preview'), 'true');

assert.strictEqual(contract.unrelated_404.expected_status, 404);
assert.match(contract.unrelated_404.path, /^\/__thp-sitewide-0-4-1-[0-9a-f]{16}\/$/);
assert.ok(!contract.surfaces.some((surface) => surface.path === contract.unrelated_404.path));
assert.strictEqual(new URL(contract.unrelated_404.url).origin, contract.origin);
assert.ok(new URL(contract.unrelated_404.url).searchParams.get('thp_sitewide_acceptance').startsWith('0-4-1-'));
assert.notStrictEqual(contract.surfaces[0].probe_url, secondNonceContract.surfaces[0].probe_url);
assert.notStrictEqual(contract.canary_probes[0].url, secondNonceContract.canary_probes[0].url);
assert.notStrictEqual(contract.unrelated_404.url, secondNonceContract.unrelated_404.url);
assert.notStrictEqual(
  acceptance.buildUnrelated404Path('0.4.1'),
  acceptance.buildUnrelated404Path('0.4.2')
);

const indexSurface = contract.surfaces.find((surface) => surface.indexing_policy === 'index');
const indexCanonical = new URL(indexSurface.canonical_path, indexSurface.requested_url).toString();
const indexHtml = `<!doctype html><html lang="${indexSurface.expected_html_lang}" dir="${indexSurface.expected_html_dir}"><head><title>Index identity</title><meta name="robots" content="index, follow"><link href="${indexCanonical}" rel="canonical"></head><body><main data-thp-owner-id="${indexSurface.owner_id}" data-thp-route-id="fixture"><h1>Primary identity</h1></main></body></html>`;
const indexEvidence = acceptance.validateProtectedResponse(indexSurface, {
  status: 200,
  url: indexSurface.probe_url,
  content_type: 'text/html; charset=UTF-8',
  x_robots_tag: '',
  body: indexHtml
});
assert.strictEqual(indexEvidence.owner_id, indexSurface.owner_id);
assert.strictEqual(indexEvidence.html_identity.main_h1, 'Primary identity');

const noindexSurface = contract.surfaces.find((surface) => surface.indexing_policy === 'noindex');
const noindexCanonical = new URL(noindexSurface.canonical_path, noindexSurface.requested_url).toString();
const noindexHtml = `<!doctype html><html lang="${noindexSurface.expected_html_lang}" dir="${noindexSurface.expected_html_dir}"><head><title>Historical identity</title><meta content="noindex, follow, max-image-preview:large" name="robots"><link rel="canonical" href="${noindexCanonical}"></head><body><main data-thp-guide-owner="${noindexSurface.owner_id}" data-thp-guide-route="thailand-entry-april-2022"><h1>Historical entry rules</h1></main></body></html>`;
acceptance.validateProtectedResponse(noindexSurface, {
  status: 200,
  url: noindexSurface.probe_url,
  content_type: 'text/html',
  x_robots_tag: '',
  body: noindexHtml
});
assert.throws(
  () => acceptance.validateProtectedResponse(noindexSurface, {
    status: 200,
    url: noindexSurface.probe_url,
    content_type: 'text/html',
    x_robots_tag: '',
    body: noindexHtml.replace('noindex, follow, max-image-preview:large', 'index, follow')
  }),
  /reviewed noindex directive is missing/
);

const notFoundHtml = '<!doctype html><html lang="he-IL" dir="rtl"><head><title>Page not found</title></head><body><main><h1>Not found</h1></main></body></html>';
for (const probe of contract.canary_probes) {
  const evidence = acceptance.validate404Response(probe, {
    status: 404,
    url: probe.url,
    content_type: 'text/html; charset=UTF-8',
    x_robots_tag: 'noindex',
    body: notFoundHtml
  });
  assert.strictEqual(evidence.managed_marker_count, 0);
}
acceptance.validate404Response(contract.unrelated_404, {
  status: 404,
  url: contract.unrelated_404.url,
  content_type: 'text/html',
  x_robots_tag: '',
  body: notFoundHtml
}, { unrelated: true });

const packageInventory = fs.readFileSync(path.join(root, 'package-files.txt'), 'utf8').split(/\r?\n/);
assert.ok(!packageInventory.includes('scripts/live_sitewide_acceptance.cjs'));
assert.ok(!packageInventory.includes('tests/live-sitewide-acceptance.test.cjs'));

const acceptanceSource = fs.readFileSync(acceptancePath, 'utf8');
assert.ok(!acceptanceSource.includes('ContentSha256'));
assert.ok(!acceptanceSource.includes("require('playwright')"));
assert.ok(acceptanceSource.includes("credentials: 'omit'"));
assert.ok(!acceptanceSource.includes('wp-admin'));

process.stdout.write('PASS: sitewide acceptance contract\n');
