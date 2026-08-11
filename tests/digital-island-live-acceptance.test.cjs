'use strict';

const fs = require('fs');
const path = require('path');

const {
  EXPECTED_RENDERER_INVENTORY_COUNT,
  EXPECTED_PUBLIC_ENTITY_COUNT,
  PLAYWRIGHT_CLI_PACKAGE,
  buildContract,
  inspectDocument,
  parseContentRange,
  parsePageId,
  pluginBaseFromAssetUrl,
  recursiveForbiddenKeys,
  rendererAssetReceipts,
  rendererManifestContract,
  reviewedAssetReceipts,
  staticAssetHeaderContract,
  structuralSourceGates,
  validatePublicPayload,
  verifyRendererInventoryBytes,
  verifyReviewedAssetBytes,
  xmlLocations
} = require('../scripts/live_digital_island_acceptance.cjs');

let assertions = 0;
function assert(condition, message) {
  assertions += 1;
  if (!condition) throw new Error(message);
}

assert(parsePageId('731') === 731, 'explicit page ID parsing failed');
for (const rejected of ['', '0', '-1', '731.5', 'abc']) {
  let didReject = false;
  try { parsePageId(rejected); } catch { didReject = true; }
  assert(didReject, `unsafe page ID was accepted: ${rejected}`);
}

const contract = buildContract({ pageId: '' });
assert(contract.configured_page_id === null, 'contract unexpectedly configured a page ID');
assert(contract.configured_page_id_has_fallback === false, 'contract introduced a fallback page ID');
assert(contract.expected_public_entity_count === EXPECTED_PUBLIC_ENTITY_COUNT, 'contract projection count changed');
assert(contract.parent_owner.lifecycle === 'planned', 'parent hierarchy lifecycle changed');
assert(contract.live_gates.some((gate) => gate.includes('exact-byte equality')), 'contract omits exact live/local asset integrity');
assert(contract.live_gates.some((gate) => gate.includes('all 65 reviewed')), 'contract omits complete renderer inventory verification');
assert(contract.live_gates.some((gate) => gate.includes('HTTP 206')), 'contract omits PMTiles Range transport verification');
assert(contract.live_gates.some((gate) => gate.includes('activeRenderer=3d')), 'contract omits real live 3D browser execution');
assert(contract.live_gates.some((gate) => gate.includes('immutable caching')), 'contract omits immutable static caching verification');
assert(PLAYWRIGHT_CLI_PACKAGE === '@playwright/cli@0.1.18', 'live browser dependency pin changed');

const reviewedAssets = reviewedAssetReceipts();
const expectedReviewedAssets = {
  css: 'assets/digital-islands/digital-islands.css',
  javascript: 'assets/digital-islands/digital-islands.js',
  maplibre_css: 'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css',
  maplibre_javascript: 'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js',
  pmtiles_javascript: 'assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js'
};
assert(JSON.stringify(Object.keys(reviewedAssets).sort()) === JSON.stringify(Object.keys(expectedReviewedAssets).sort()), 'reviewed document dependency inventory changed');
for (const [assetType, expectedPath] of Object.entries(expectedReviewedAssets)) {
  const receipt = reviewedAssets[assetType];
  assert(receipt.path === expectedPath, `reviewed asset path changed: ${assetType}`);
  assert(receipt.bytes > 0 && /^[a-f0-9]{64}$/.test(receipt.sha256), `reviewed asset receipt is invalid: ${assetType}`);
  const verified = verifyReviewedAssetBytes(assetType, fs.readFileSync(path.join(__dirname, '..', receipt.path)));
  assert(verified.exact_bytes_match && verified.sha256_match, `exact reviewed bytes were rejected: ${assetType}`);
}
let tamperedAssetRejected = false;
try {
  verifyReviewedAssetBytes('javascript', Buffer.from('tampered-live-asset', 'utf8'));
} catch {
  tamperedAssetRejected = true;
}
assert(tamperedAssetRejected, 'tampered live asset bytes were accepted');

const rendererManifest = rendererManifestContract();
const rendererReceipts = rendererAssetReceipts();
assert(rendererReceipts.inventory_count === EXPECTED_RENDERER_INVENTORY_COUNT, 'renderer receipt inventory count changed');
assert(Object.keys(rendererManifest.inventory).length === EXPECTED_RENDERER_INVENTORY_COUNT, 'renderer manifest inventory count changed');
assert(rendererManifest.dependencies.maplibre.version === '5.18.0' && rendererManifest.dependencies.pmtiles.version === '4.5.0', 'renderer dependency versions changed');
assert(rendererManifest.satellite.observed_at === '2026-03-26T03:55:36.171000Z', 'Sentinel observation identity changed');
const sampleRendererPath = rendererManifest.dependencies.pmtiles.script_path;
const sampleRendererBytes = fs.readFileSync(path.join(__dirname, '..', sampleRendererPath));
assert(verifyRendererInventoryBytes(sampleRendererPath, sampleRendererBytes).exact_bytes_match, 'exact renderer inventory bytes were rejected');
let tamperedRendererRejected = false;
try { verifyRendererInventoryBytes(sampleRendererPath, Buffer.from('tampered-renderer', 'utf8')); } catch { tamperedRendererRejected = true; }
assert(tamperedRendererRejected, 'tampered renderer inventory bytes were accepted');

const parsedRange = parseContentRange('bytes 0-16383/1205287');
assert(parsedRange && parsedRange.start === 0 && parsedRange.end === 16383 && parsedRange.total === 1205287, 'valid Content-Range was rejected');
assert(parseContentRange('bytes 10-20/20') === null && parseContentRange('invalid') === null, 'invalid Content-Range was accepted');
const pluginBase = pluginBaseFromAssetUrl(
  'https://thai-land.co.il/wp-content/plugins/thailand-platform/assets/digital-islands/digital-islands.js?ver=0.5.1',
  new URL('https://thai-land.co.il/')
);
assert(pluginBase.toString() === 'https://thai-land.co.il/wp-content/plugins/thailand-platform/', 'plugin base derivation changed');
const staticHeaders = staticAssetHeaderContract('assets/digital-islands/data/test.pmtiles', {
  'cache-control': 'public, max-age=31536000, immutable',
  'content-type': 'application/vnd.pmtiles',
  'x-content-type-options': 'nosniff'
});
assert(staticHeaders.max_age_seconds === 31536000 && staticHeaders.immutable && staticHeaders.nosniff, 'safe PMTiles static headers were rejected');
for (const [label, headers] of Object.entries({
  unsafe_mime: { 'cache-control': 'public, max-age=31536000, immutable', 'content-type': 'text/html', 'x-content-type-options': 'nosniff' },
  missing_nosniff: { 'cache-control': 'public, max-age=31536000, immutable', 'content-type': 'application/vnd.pmtiles' },
  private_cache: { 'cache-control': 'private, no-store', 'content-type': 'application/vnd.pmtiles', 'x-content-type-options': 'nosniff' },
  short_cache: { 'cache-control': 'public, max-age=60, immutable', 'content-type': 'application/vnd.pmtiles', 'x-content-type-options': 'nosniff' }
})) {
  let rejected = false;
  try { staticAssetHeaderContract('assets/digital-islands/data/test.pmtiles', headers); } catch { rejected = true; }
  assert(rejected, `unsafe static header contract was accepted: ${label}`);
}

const acceptanceSource = fs.readFileSync(path.join(__dirname, '..', 'scripts', 'live_digital_island_acceptance.cjs'), 'utf8');
assert(acceptanceSource.includes('await validateRendererAssets(') && acceptanceSource.includes('renderer_assets: rendererAssets'), 'renderer static assets are not called and reported by runLive');
assert(acceptanceSource.includes('await validatePmtilesRange(') && acceptanceSource.includes('pmtiles_range: pmtilesRange'), 'PMTiles Range acceptance is not called and reported by runLive');
assert(acceptanceSource.includes('await validateLiveBrowser(') && acceptanceSource.includes('live_browser: liveBrowser'), 'live browser acceptance is not called and reported by runLive');
const liveProbeSource = fs.readFileSync(path.join(__dirname, 'fixtures', 'digital-islands-live-browser-probe.js'), 'utf8');
assert(liveProbeSource.includes("root.dataset.activeRenderer === '3d'") && liveProbeSource.includes("'@playwright/cli@0.1.18'"), 'live browser probe lost its 3D/pinned CLI identity');
assert(liveProbeSource.includes('pmtilesRequests.every') && liveProbeSource.includes('request_budget'), 'live browser probe lost Range/request budget checks');

const cards = Array.from({ length: EXPECTED_PUBLIC_ENTITY_COUNT }, (_, index) =>
  `<li data-entity-id="settlement:th:84:test-${index}">Entity ${index}</li>`
).join('');
const html = `<!doctype html>
<html lang="he" dir="rtl"><head>
<title>מפת קופנגן</title>
<meta name="description" content="מפת קופנגן מפורטת עם יישובים, שירותים, תשתיות, אנשי מקצוע ופרויקטים שנבדקו לפרסום לציבור.">
<meta name="robots" content="index, follow">
<meta property="og:url" content="https://thai-land.co.il/מפת-קופנגן/">
<link rel="canonical" href="https://thai-land.co.il/מפת-קופנגן/">
<script type="application/ld+json">${JSON.stringify({
  '@context': 'https://schema.org',
  '@graph': [
    { '@type': 'WebPage', '@id': 'https://thai-land.co.il/מפת-קופנגן/', url: 'https://thai-land.co.il/מפת-קופנגן/' },
    { '@type': 'Dataset', name: 'Koh Phangan' },
    { '@type': 'BreadcrumbList', itemListElement: [
      { '@type': 'ListItem', position: 1, item: 'https://thai-land.co.il/' },
      { '@type': 'ListItem', position: 2, name: 'מפת תאילנד' },
      { '@type': 'ListItem', position: 3, item: 'https://thai-land.co.il/מפת-קופנגן/' }
    ] }
  ]
})}</script>
</head><body class="page page-id-731">
<main data-digital-island-app>
<nav class="thp-di-breadcrumb"><ol><li><a href="/">Home</a></li><li>מפת תאילנד</li><li aria-current="page">מפת קופנגן</li></ol></nav>
<h1>מפת קופנגן</h1><ul>${cards}</ul>
<a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>
</main></body></html>`;
const inspected = inspectDocument(html);
assert(inspected.hasApp, 'map application marker parser failed');
assert(!inspected.hasRestNonce, 'public fixture incorrectly contains a nonce');
assert(inspected.entityIds.length === EXPECTED_PUBLIC_ENTITY_COUNT, '49-card HTML parser failed');
assert(new Set(inspected.entityIds).size === EXPECTED_PUBLIC_ENTITY_COUNT, 'HTML entity IDs are not unique');
assert(inspected.canonical.length === 1, 'canonical parser failed');
assert(inspected.breadcrumbs.length === 1, 'visible breadcrumb parser failed');
assert(inspected.schemas.length === 1, 'JSON-LD parser failed');
assert(inspected.osmAttribution, 'OSM attribution parser failed');

const publicPayload = {
  representation_state: 'public_live',
  entity_count: EXPECTED_PUBLIC_ENTITY_COUNT,
  entities: [{ entity_id: 'settlement:th:84:test', facts: [] }]
};
validatePublicPayload(publicPayload, 'synthetic public payload');
assert(true, 'public payload validator unexpectedly failed');
let privacyRejected = false;
try {
  validatePublicPayload({ ...publicPayload, entities: [{ entity_id: 'x', holds: ['private'] }] }, 'synthetic leak');
} catch {
  privacyRejected = true;
}
assert(privacyRejected, 'private field leak was not rejected');
assert(recursiveForbiddenKeys({ item: { source_ids: ['private'] } }, new Set(['source_ids'])).length === 1, 'recursive privacy scanner failed');

const locations = xmlLocations('<urlset><url><loc>https://thai-land.co.il/a/</loc></url><url><loc>https://thai-land.co.il/b/?x=1&amp;y=2</loc></url></urlset>');
assert(locations.length === 2, 'sitemap location parser failed');
assert(locations[1].includes('&y=2'), 'sitemap entity decoding failed');

const local = structuralSourceGates({ requireLive: false });
assert(local.canary_map_entity_count === EXPECTED_PUBLIC_ENTITY_COUNT, 'local Canary projection is not exact');
assert(local.reviewed_asset_receipts.css.sha256 === reviewedAssets.css.sha256, 'source gates lost the reviewed CSS receipt');
assert(local.reviewed_asset_receipts.javascript.sha256 === reviewedAssets.javascript.sha256, 'source gates lost the reviewed JavaScript receipt');
assert(['private_review', 'live'].includes(local.publication_state), 'local publication state is unsafe');
if (local.publication_state === 'live') {
  assert(local.public_map_entity_count === EXPECTED_PUBLIC_ENTITY_COUNT, 'local Live projection is not exact');
} else {
  assert(local.public_map_entity_count === 0, 'local private artifact has a public projection');
}

process.stdout.write(`PASS: Digital Islands live acceptance contract and privacy parsers (${assertions} assertions).\n`);
