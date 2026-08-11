'use strict';

const fs = require('fs');
const path = require('path');

const {
  EXPECTED_PUBLIC_ENTITY_COUNT,
  buildContract,
  inspectDocument,
  parsePageId,
  recursiveForbiddenKeys,
  reviewedAssetReceipts,
  structuralSourceGates,
  validatePublicPayload,
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

const reviewedAssets = reviewedAssetReceipts();
assert(reviewedAssets.css.path === 'assets/digital-islands/digital-islands.css', 'reviewed CSS path is missing');
assert(reviewedAssets.javascript.path === 'assets/digital-islands/digital-islands.js', 'reviewed JavaScript path is missing');
assert(reviewedAssets.css.bytes > 0 && reviewedAssets.javascript.bytes > 0, 'reviewed local asset is empty');
assert(/^[a-f0-9]{64}$/.test(reviewedAssets.css.sha256) && /^[a-f0-9]{64}$/.test(reviewedAssets.javascript.sha256), 'reviewed local asset SHA-256 is invalid');
const verifiedCss = verifyReviewedAssetBytes('css', fs.readFileSync(path.join(__dirname, '..', reviewedAssets.css.path)));
assert(verifiedCss.exact_bytes_match && verifiedCss.sha256_match, 'exact reviewed CSS bytes were rejected');
const verifiedJavascript = verifyReviewedAssetBytes('javascript', fs.readFileSync(path.join(__dirname, '..', reviewedAssets.javascript.path)));
assert(verifiedJavascript.exact_bytes_match && verifiedJavascript.sha256_match, 'exact reviewed JavaScript bytes were rejected');
let tamperedAssetRejected = false;
try {
  verifyReviewedAssetBytes('javascript', Buffer.from('tampered-live-asset', 'utf8'));
} catch {
  tamperedAssetRejected = true;
}
assert(tamperedAssetRejected, 'tampered live asset bytes were accepted');

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
