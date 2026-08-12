#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const DATA_PATH = path.join(ROOT, 'data', 'digital-islands', 'koh-phangan.json');
const PLUGIN_PREFIX = '/wp-content/plugins/thailand-platform/';
const ISLAND_ID = 'geo:th:island:ko-pha-ngan';
const REST_BASE = `/wp-json/thailand-platform/v1/digital-islands/${ISLAND_ID}`;
const VALID_SCENARIOS = new Set([
	'asset-failure',
	'data-saver',
	'desktop-2d',
	'desktop-3d',
	'mobile-2d',
	'no-webgl',
	'reduced-motion',
]);

const requiredFiles = [
	'assets/digital-islands/digital-islands.css',
	'assets/digital-islands/digital-islands.js',
	'assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles',
	'assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp',
	'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css',
	'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js',
	'assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js',
];

function invariant(condition, message) {
	if (!condition) throw new Error(message);
}

function readJson(filename) {
	return JSON.parse(fs.readFileSync(filename, 'utf8').replace(/^\uFEFF/, ''));
}

requiredFiles.forEach((relativePath) => {
	invariant(fs.existsSync(path.join(ROOT, relativePath)), `Required browser fixture asset is missing: ${relativePath}`);
});

const dataset = readJson(DATA_PATH);
invariant(dataset.island && dataset.island.geo_id === ISLAND_ID, 'Unexpected Digital Islands fixture geography');
const entities = dataset.entities.filter((entity) => entity && entity.public_state === 'map_only');
invariant(entities.length === 49, `Expected 49 reviewed map-only entities, received ${entities.length}`);
const coordinateEntities = entities.filter((entity) => entity.coordinates);
invariant(coordinateEntities.length === 27, `Expected 27 reviewed coordinate entities, received ${coordinateEntities.length}`);

const labelsByLayer = new Map(
	(dataset.layer_catalog || []).map((layer) => [layer.layer_id, layer.label_he || layer.layer_id]),
);
const layerIds = Array.from(new Set(entities.flatMap((entity) => entity.layer_ids || []))).sort();
const layers = layerIds.map((layerId, index) => ({
	layer_id: layerId,
	label_he: labelsByLayer.get(layerId) || layerId,
	priority: index + 1,
}));

function parseArguments(argv) {
	const values = { host: '127.0.0.1', port: 0 };
	for (let index = 0; index < argv.length; index += 1) {
		const argument = argv[index];
		if (argument === '--host') values.host = argv[++index];
		else if (argument === '--port') values.port = Number.parseInt(argv[++index], 10);
		else throw new Error(`Unknown argument: ${argument}`);
	}
	invariant(typeof values.host === 'string' && values.host.length > 0, '--host is required');
	invariant(Number.isInteger(values.port) && values.port >= 0 && values.port <= 65535, '--port must be 0..65535');
	return values;
}

function escapeHtml(value) {
	return String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function jsonForScript(value) {
	return JSON.stringify(value)
		.replace(/</g, '\\u003c')
		.replace(/>/g, '\\u003e')
		.replace(/&/g, '\\u0026')
		.replace(/\u2028/g, '\\u2028')
		.replace(/\u2029/g, '\\u2029');
}

function fixtureConfig(scenario) {
	const broken = scenario === 'asset-failure';
	const prefix = broken
		? `${PLUGIN_PREFIX}__acceptance_missing__/`
		: `${PLUGIN_PREFIX}assets/digital-islands/`;
	return {
		reviewed: true,
		contractId: 'thailand-digital-islands-renderer-v1',
		islandGeoId: ISLAND_ID,
		maplibre: {
			vectorPmtilesUrl: `${prefix}${broken ? 'basemap.pmtiles' : 'data/koh-phangan-basemap-20260811.pmtiles'}`,
			terrainUrlTemplate: `${prefix}${broken ? 'terrain/{z}/{x}/{y}.png' : 'terrain/20260811/{z}/{x}/{y}.png'}`,
			terrainMinZoom: 8,
			terrainMaxZoom: 13,
			satelliteUrl: `${prefix}${broken ? 'satellite.webp' : 'imagery/koh-phangan-sentinel2-20260326.webp'}`,
			satelliteBounds: { south: 9.63, north: 9.84, west: 99.92, east: 100.12 },
			satelliteAttribution: 'Contains modified Copernicus Sentinel data 2026',
			basemapAttribution: 'Protomaps © OpenStreetMap contributors',
			terrainAttribution: 'Mapzen Terrain Tiles; USGS; NOAA/NCEI. Not for navigation.',
		},
	};
}

function fixtureHtml(scenario) {
	const config = fixtureConfig(scenario);
	const connectionOverride = scenario === 'data-saver'
		? `<script>Object.defineProperty(navigator, 'connection', { configurable: true, value: Object.freeze({ saveData: true }) });</script>`
		: '';
	const webglOverride = scenario === 'no-webgl'
		? `<script>(() => { const nativeGetContext = HTMLCanvasElement.prototype.getContext; HTMLCanvasElement.prototype.getContext = function getContext(kind, ...args) { return /^webgl/i.test(String(kind)) ? null : nativeGetContext.call(this, kind, ...args); }; })();</script>`
		: '';
	const layerControls = layers.map((layer) => `
		<label><input type="checkbox" value="${escapeHtml(layer.layer_id)}" data-layer-filter checked> <span>${escapeHtml(layer.label_he)}</span></label>`).join('');
	const cards = entities.map((entity) => {
		const coordinates = entity.coordinates
			? ` data-entity-lat="${escapeHtml(entity.coordinates.latitude)}" data-entity-lng="${escapeHtml(entity.coordinates.longitude)}"`
			: '';
		const button = entity.coordinates
			? `<button type="button" data-focus-entity="${escapeHtml(entity.entity_id)}">Focus on map</button>`
			: '<span>No reviewed coordinate</span>';
		return `<li class="thp-di-card" data-entity-card data-entity-id="${escapeHtml(entity.entity_id)}" data-entity-layers="${escapeHtml((entity.layer_ids || []).join(','))}"${coordinates}>
			<h4>${escapeHtml(entity.names.he || entity.names.en || entity.entity_id)}</h4>
			<p>${escapeHtml(entity.names.en || '')}</p>
			${button}
		</li>`;
	}).join('\n');

	return `<!doctype html>
<html lang="he" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<link rel="icon" href="data:,">
	<title>Koh Phangan MapLibre 0.5.2 browser fixture</title>
	<link rel="stylesheet" href="${PLUGIN_PREFIX}assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css">
	<link rel="stylesheet" href="${PLUGIN_PREFIX}assets/digital-islands/digital-islands.css">
	<style>
		.thp-di-fixture-banner { background: #102f36; color: #fff; direction: ltr; font: 600 13px/1.4 system-ui, sans-serif; padding: 8px 16px; text-align: left; }
		.thp-di-fixture-banner code { color: #d8f3e8; }
		.thp-di-fixture-entities { max-height: 16rem; overflow: auto; }
	</style>
</head>
<body class="thp-di-document">
	<div class="thp-di-fixture-banner">LOCAL QA · release <code>0.5.2</code> · scenario <code>${escapeHtml(scenario)}</code> · real vendored MapLibre</div>
	<main class="thp-di-main" data-digital-island-app data-rest-base="${REST_BASE}" data-island-id="${ISLAND_ID}" data-island-center-lat="${dataset.island.center.latitude}" data-island-center-lng="${dataset.island.center.longitude}">
		<section class="thp-di-intro"><div class="thp-di-frame"><p class="thp-di-kicker">Local browser acceptance</p><h1>Koh Phangan interactive map</h1><p>Same-origin PMTiles, Sentinel orientation imagery, and Terrarium terrain.</p></div></section>
		<div class="thp-di-frame thp-di-workspace" data-acceptance-capture>
			<aside class="thp-di-controls" aria-label="Map controls">
				<fieldset class="thp-di-view-switcher"><legend>View</legend><div class="thp-di-segmented">
					<button type="button" data-view-mode="3d" aria-pressed="false">3D world</button>
					<button type="button" data-view-mode="2d" aria-pressed="false">2D map</button>
					<button type="button" data-view-mode="list" aria-pressed="true">List</button>
				</div></fieldset>
				<form class="thp-di-search" role="search" data-island-search><label for="fixture-search">Search</label><div><input id="fixture-search" type="search" minlength="2" maxlength="80"><button type="submit">Search</button></div></form>
				<fieldset class="thp-di-layer-filter"><legend>Layers</legend><div class="thp-di-layer-options">${layerControls}</div></fieldset>
			</aside>
			<section class="thp-di-experience" aria-labelledby="fixture-map-title">
				<div class="thp-di-experience-heading"><div><p class="thp-di-eyebrow">Real renderer</p><h2 id="fixture-map-title">Koh Phangan</h2></div><p data-renderer-status role="status" aria-live="polite">Accessible list active</p></div>
				<div class="thp-di-map-shell" data-renderer-shell>
					<div class="thp-di-renderer-stage" data-renderer-stage role="region" aria-label="Koh Phangan interactive map" tabindex="0"></div>
					<div class="thp-di-map-poster" data-list-poster><div><h3>Accessible list fallback</h3><p>The reviewed entity list remains usable without the graphical renderer.</p></div></div>
				</div>
				<aside class="thp-di-attribution" data-reviewed-attributions aria-label="Reviewed map sources and licences">
					<p>Protomaps © OpenStreetMap contributors</p>
					<p>Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological Survey; ETOPO1 courtesy of NOAA/NCEI. Not for navigation.</p>
					<p>Contains modified Copernicus Sentinel data 2026. Image observed <time datetime="2026-03-26T03:55:36.171000Z">26.03.2026</time>. Dated orientation imagery only; not parcel, title, planning, or buildability evidence.</p>
				</aside>
			</section>
		</div>
		<section class="thp-di-places thp-di-fixture-entities"><div class="thp-di-frame"><p><span data-visible-count>${entities.length}</span> reviewed entities</p><div data-entity-groups><section data-entity-group="fixture"><ul class="thp-di-cards">${cards}</ul></section></div><p data-no-results hidden>No results.</p></div></section>
	</main>
	${connectionOverride}
	${webglOverride}
	<script>window.ThailandDigitalIslandsConfig = ${jsonForScript(config)}; window.__thpFixture = Object.freeze({ scenario: ${jsonForScript(scenario)}, entityCount: ${entities.length}, coordinateEntityCount: ${coordinateEntities.length} });</script>
	<script src="${PLUGIN_PREFIX}assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js"></script>
	<script src="${PLUGIN_PREFIX}assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js"></script>
	<script>
	(() => {
		'use strict';
		const evidence = { maps: [], instances: [], windowErrors: [], promiseRejections: [], cspViolations: [] };
		window.__thpBrowserEvidence = evidence;
		window.addEventListener('error', (event) => evidence.windowErrors.push(String(event.message || 'window-error')));
		window.addEventListener('unhandledrejection', (event) => evidence.promiseRejections.push(String(event.reason && event.reason.message ? event.reason.message : event.reason)));
		document.addEventListener('securitypolicyviolation', (event) => evidence.cspViolations.push({ blockedURI: event.blockedURI, violatedDirective: event.violatedDirective }));
		if (!window.maplibregl || typeof window.maplibregl.Map !== 'function') return;
		const OriginalMap = window.maplibregl.Map;
		class ObservedMap extends OriginalMap {
			constructor(options) {
				super(options);
				const record = { createdAt: Date.now(), errors: [], idle: false, loaded: false, removed: false };
				evidence.maps.push(record);
				evidence.instances.push(this);
				this.on('load', () => { record.loaded = true; });
				this.on('idle', () => { record.idle = true; });
				this.on('error', (event) => { record.errors.push(String(event && event.error && event.error.message ? event.error.message : 'map-error')); });
				this.on('remove', () => { record.removed = true; });
			}
		}
		window.maplibregl.Map = ObservedMap;
	})();
	</script>
	<script src="${PLUGIN_PREFIX}assets/digital-islands/digital-islands.js"></script>
</body>
</html>`;
}

function sendJson(response, value, statusCode = 200) {
	const body = Buffer.from(`${JSON.stringify(value)}\n`, 'utf8');
	response.writeHead(statusCode, {
		'Cache-Control': 'no-store',
		'Content-Length': body.length,
		'Content-Type': 'application/json; charset=utf-8',
	});
	response.end(body);
}

function sendText(response, value, statusCode, contentType = 'text/plain; charset=utf-8') {
	const body = Buffer.from(value, 'utf8');
	response.writeHead(statusCode, {
		'Cache-Control': 'no-store',
		'Content-Length': body.length,
		'Content-Type': contentType,
	});
	response.end(body);
}

function contentType(filename) {
	return ({
		'.css': 'text/css; charset=utf-8',
		'.js': 'text/javascript; charset=utf-8',
		'.png': 'image/png',
		'.pmtiles': 'application/vnd.pmtiles',
		'.txt': 'text/plain; charset=utf-8',
		'.webp': 'image/webp',
	})[path.extname(filename).toLowerCase()] || 'application/octet-stream';
}

function parseRange(value, size) {
	if (!value) return null;
	const match = /^bytes=(\d+)-(\d*)$/.exec(value.trim());
	if (!match) return false;
	const start = Number.parseInt(match[1], 10);
	const end = match[2] ? Number.parseInt(match[2], 10) : size - 1;
	if (!Number.isSafeInteger(start) || !Number.isSafeInteger(end) || start < 0 || end < start || start >= size) return false;
	return { start, end: Math.min(end, size - 1) };
}

function serveFile(request, response, filename) {
	const stats = fs.statSync(filename);
	const range = parseRange(request.headers.range, stats.size);
	if (range === false) {
		response.writeHead(416, { 'Content-Range': `bytes */${stats.size}` });
		response.end();
		return;
	}
	const headers = {
		'Accept-Ranges': 'bytes',
		'Cache-Control': 'no-store',
		'Content-Type': contentType(filename),
	};
	if (range) {
		headers['Content-Length'] = range.end - range.start + 1;
		headers['Content-Range'] = `bytes ${range.start}-${range.end}/${stats.size}`;
		response.writeHead(206, headers);
		if (request.method === 'HEAD') response.end();
		else fs.createReadStream(filename, { start: range.start, end: range.end }).pipe(response);
		return;
	}
	headers['Content-Length'] = stats.size;
	response.writeHead(200, headers);
	if (request.method === 'HEAD') response.end();
	else fs.createReadStream(filename).pipe(response);
}

function staticFilename(pathname) {
	if (!pathname.startsWith(PLUGIN_PREFIX)) return null;
	const relative = decodeURIComponent(pathname.slice(PLUGIN_PREFIX.length)).replace(/\\/g, '/');
	if (!relative || relative.startsWith('/') || relative.split('/').includes('..')) return null;
	const filename = path.resolve(ROOT, relative);
	return filename.startsWith(`${ROOT}${path.sep}`) ? filename : null;
}

function createServer() {
	return http.createServer((request, response) => {
		try {
			if (!['GET', 'HEAD'].includes(request.method || '')) {
				sendText(response, 'Method not allowed\n', 405);
				return;
			}
			const url = new URL(request.url || '/', 'http://fixture.invalid');
			if (url.pathname === '/__health') {
				sendJson(response, {
					contract_id: 'thp-digital-islands-maplibre-browser-v1',
					coordinate_entity_count: coordinateEntities.length,
					entity_count: entities.length,
					island_id: ISLAND_ID,
					playwright_cli_package: '@playwright/cli@0.1.18',
					status: 'ready',
				});
				return;
			}
			if (url.pathname === '/fixture') {
				const scenario = url.searchParams.get('scenario') || 'desktop-3d';
				if (!VALID_SCENARIOS.has(scenario)) {
					sendText(response, 'Unknown fixture scenario\n', 400);
					return;
				}
				const body = Buffer.from(fixtureHtml(scenario), 'utf8');
				response.writeHead(200, {
					'Cache-Control': 'no-store',
					'Content-Length': body.length,
					'Content-Security-Policy': "default-src 'self'; base-uri 'none'; connect-src 'self'; font-src 'none'; frame-ancestors 'none'; img-src 'self' data: blob:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; worker-src 'self' blob:",
					'Content-Type': 'text/html; charset=utf-8',
					'Cross-Origin-Resource-Policy': 'same-origin',
					'Referrer-Policy': 'no-referrer',
					'X-Content-Type-Options': 'nosniff',
					'X-Frame-Options': 'DENY',
				});
				response.end(body);
				return;
			}
			if (url.pathname === REST_BASE) {
				sendJson(response, { island: dataset.island, camera_presets: dataset.camera_presets });
				return;
			}
			if (url.pathname === `${REST_BASE}/layers`) {
				sendJson(response, { layers });
				return;
			}
			if (url.pathname === `${REST_BASE}/entities`) {
				sendJson(response, { entities });
				return;
			}
			if (url.pathname.startsWith(`${REST_BASE}/search/`)) {
				const term = decodeURIComponent(url.pathname.slice(`${REST_BASE}/search/`.length)).toLocaleLowerCase('he');
				const results = entities.filter((entity) => Object.values(entity.names || {}).some((name) => typeof name === 'string' && name.toLocaleLowerCase('he').includes(term)));
				sendJson(response, { results });
				return;
			}
			if (url.pathname.startsWith(`${PLUGIN_PREFIX}__acceptance_missing__/`)) {
				sendText(response, 'Intentional browser acceptance asset failure\n', 503);
				return;
			}
			const filename = staticFilename(url.pathname);
			if (filename && fs.existsSync(filename) && fs.statSync(filename).isFile()) {
				serveFile(request, response, filename);
				return;
			}
			sendText(response, 'Not found\n', 404);
		} catch (error) {
			sendText(response, `Fixture server error: ${error.message}\n`, 500);
		}
	});
}

if (require.main === module) {
	const options = parseArguments(process.argv.slice(2));
	const server = createServer();
	server.listen(options.port, options.host, () => {
		const address = server.address();
		process.stdout.write(`THP_DI_FIXTURE_READY ${JSON.stringify({ host: options.host, port: address.port })}\n`);
	});
	const shutdown = () => server.close(() => process.exit(0));
	process.on('SIGINT', shutdown);
	process.on('SIGTERM', shutdown);
}

module.exports = { createServer, fixtureHtml };
