'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class TestElement {
	constructor(tagName, ownerDocument) {
		this.tagName = tagName.toUpperCase();
		this.ownerDocument = ownerDocument;
		this.children = [];
		this.parentNode = null;
		this.attributes = {};
		this.dataset = {};
		this.hidden = false;
		this.listeners = {};
		this.style = {
			values: {},
			setProperty: (name, value) => { this.style.values[name] = value; },
		};
	}

	appendChild(child) {
		child.parentNode = this;
		this.children.push(child);
		return child;
	}

	removeChild(child) {
		this.children = this.children.filter((candidate) => candidate !== child);
		child.parentNode = null;
		return child;
	}

	setAttribute(name, value) {
		this.attributes[name] = String(value);
	}

	addEventListener(name, callback) {
		this.listeners[name] = callback;
	}

	removeEventListener(name) {
		delete this.listeners[name];
	}

	focus() {
		this.focused = true;
	}
}

const document = {
	createElement(tagName) {
		return new TestElement(tagName, document);
	},
	querySelectorAll() {
		return [];
	},
};

const window = {};
const sandbox = {
	console,
	document,
	window,
	URL,
	Promise,
	Map,
	Set,
	Object,
	Array,
	Number,
	Math,
};
window.location = { href: 'https://thai-land.co.il/מפת-קופנגן/', origin: 'https://thai-land.co.il' };
window.devicePixelRatio = 3;
window.setTimeout = setTimeout;
window.clearTimeout = clearTimeout;
const source = fs.readFileSync(
	path.join(__dirname, '..', 'assets', 'digital-islands', 'digital-islands.js'),
	'utf8',
);
const stylesheet = fs.readFileSync(
	path.join(__dirname, '..', 'assets', 'digital-islands', 'digital-islands.css'),
	'utf8',
);
vm.runInNewContext(source, sandbox, { filename: 'digital-islands.js' });

assert.equal(typeof window.ThailandDigitalIslandsAdapters.maplibre.mount, 'function');
assert.equal(source.includes('Cesium'), false);
assert.equal(/https?:\/\//i.test(source), false);
assert.equal(source.includes('Ion.defaultAccessToken'), false);
assert.equal(source.includes('innerHTML'), false);
assert.equal(source.includes("classList.add('is-enhanced')"), true);
assert.equal(source.includes('measurement_supported: false'), true);
assert.equal(source.includes('class OrientationSceneAdapter'), true);
assert.equal(source.includes('if (this.instance !== instance)'), true);
assert.equal(source.includes('אי־ודאות מיקומית מתועדת'), true);
assert.equal(/generation !== viewGeneration[\s\S]{0,80}candidate\.destroy\(\)/.test(source), false);
assert.equal(source.includes("shell.scrollIntoView({ block: 'center'"), true);
assert.equal(source.includes("stage.focus({ preventScroll: true })"), true);
assert.equal(stylesheet.includes('outline: 3px solid #fff;'), true);
assert.equal(stylesheet.includes('box-shadow: 0 0 0 6px #12343b;'), true);
assert.equal(stylesheet.includes('@media (forced-colors: active)'), true);

const island = {
	geo_id: 'geo:th:island:ko-pha-ngan',
	bounds: { south: 9.66, north: 9.81, west: 99.95, east: 100.09 },
};
const cameraPresets = [{
	preset_id: 'koh-phangan-orbit',
	position: { latitude: 9.735, longitude: 100.03 },
	height_m: 42000,
	heading_deg: 25,
	pitch_deg: -55,
	roll_deg: 0,
}];
const safeEntity = {
	entity_id: 'settlement:th:84:840501:01',
	entity_type: 'settlement',
	names: { he: 'טונג סאלה', en: 'Thong Sala', th: null },
	coordinates: { latitude: 9.71, longitude: 99.985, accuracy_m: 100 },
	layer_ids: ['layer:settlements'],
};
const outsideEntity = {
	...safeEntity,
	entity_id: 'settlement:th:84:840501:02',
	coordinates: { latitude: 10.1, longitude: 99.985 },
};
const invalidEntity = {
	...safeEntity,
	entity_id: 'unsafe',
};

let selected = '';
let mapLibreMap;
const mapLibreMarkers = [];
class MapLibreMap {
	constructor(options) {
		this.options = options;
		this.easeTos = [];
		this.jumpTos = [];
		this.controls = [];
		this.removed = false;
		this.canvas = new TestElement('canvas', document);
		mapLibreMap = this;
	}

	once(name, callback) {
		if (name === 'load') callback();
	}

	on(name, callback) {
		this.listeners = this.listeners || {};
		this.listeners[name] = callback;
	}

	off(name) {
		if (this.listeners) delete this.listeners[name];
	}

	setProjection(value) {
		this.projection = value;
	}

	setTerrain(value) {
		this.terrain = value;
	}

	getCanvas() {
		return this.canvas;
	}

	addControl(control, position) {
		this.controls.push({ control, position });
	}

	easeTo(value) {
		this.easeTos.push(value);
	}

	jumpTo(value) {
		this.jumpTos.push(value);
	}

	getZoom() {
		return 12;
	}

	remove() {
		this.removed = true;
	}
}
class Marker {
	constructor(options) {
		this.options = options;
		this.element = options.element;
		this.removed = false;
		mapLibreMarkers.push(this);
	}

	setLngLat(value) {
		this.lngLat = value;
		return this;
	}

	addTo(map) {
		this.map = map;
		return this;
	}

	remove() {
		this.removed = true;
	}
}
let protocolAdds = 0;
window.pmtiles = {
	Protocol: class Protocol {
		tile() {}
	},
};
window.maplibregl = {
	Map: MapLibreMap,
	Marker,
	NavigationControl: class NavigationControl {},
	ScaleControl: class ScaleControl {},
	addProtocol(name, handler) {
		assert.equal(name, 'pmtiles');
		assert.equal(typeof handler, 'function');
		protocolAdds += 1;
	},
};

window.ThailandDigitalIslandsConfig = {
	reviewed: true,
	contractId: 'thailand-digital-islands-renderer-v1',
	islandGeoId: island.geo_id,
	maplibre: {
		vectorPmtilesUrl: 'https://thai-land.co.il/wp-content/plugins/thailand-platform-live-051/assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles',
		terrainUrlTemplate: 'https://thai-land.co.il/wp-content/plugins/thailand-platform-live-051/assets/digital-islands/terrain/20260811/{z}/{x}/{y}.png',
		terrainMinZoom: 8,
		terrainMaxZoom: 13,
		satelliteUrl: 'https://thai-land.co.il/wp-content/plugins/thailand-platform-live-051/assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp',
		satelliteBounds: { south: 9.63, north: 9.84, west: 99.92, east: 100.12 },
		satelliteAttribution: 'Contains modified Copernicus Sentinel data 2026',
		basemapAttribution: 'Protomaps © OpenStreetMap contributors',
		terrainAttribution: 'Mapzen terrain · USGS · NOAA/NCEI',
	},
};

selected = '';
const mapLibreContainer = document.createElement('div');
const mapLibreInstance = window.ThailandDigitalIslandsAdapters.maplibre.mount({
	container: mapLibreContainer,
	island,
	cameraPresets,
	entities: [safeEntity, outsideEntity, invalidEntity],
	visibleLayerIds: ['layer:settlements'],
	reducedMotion: false,
	selectEntity: (entityId) => { selected = entityId; },
});
assert.ok(mapLibreInstance);
assert.equal(mapLibreMap.options.style.version, 8);
assert.deepEqual(Object.keys(mapLibreMap.options.style.sources), ['basemap']);
assert.match(mapLibreMap.options.style.sources.basemap.url, /^pmtiles:\/\//);
assert.equal(mapLibreMap.options.pixelRatio, 2);
assert.equal(mapLibreMap.options.minZoom, 8);
assert.equal(mapLibreMap.options.maxZoom, 16);
assert.equal(mapLibreMap.options.maxPitch, 60);
assert.equal(protocolAdds, 1);
assert.equal(mapLibreMarkers.length, 1);
mapLibreMarkers[0].element.listeners.click({ preventDefault() {}, stopPropagation() {} });
assert.equal(selected, safeEntity.entity_id);
mapLibreInstance.setVisibleLayers([]);
assert.equal(mapLibreMarkers[0].element.hidden, true);
mapLibreInstance.setVisibleLayers(['layer:settlements']);
assert.equal(mapLibreMarkers[0].element.hidden, false);
assert.equal(mapLibreInstance.focusEntity(safeEntity.entity_id, { animate: true }), true);
assert.equal(mapLibreMap.easeTos.length, 1);
assert.equal(mapLibreInstance.capabilities.measurement_supported, false);
mapLibreInstance.destroy();
assert.equal(mapLibreMap.removed, true);
assert.equal(mapLibreMarkers[0].removed, true);
assert.equal(mapLibreContainer.children.length, 0);

const configuredContainer = document.createElement('div');
const rendererFailures = [];
const configuredInstance = window.ThailandDigitalIslandsAdapters.maplibre.mount({
	container: configuredContainer,
	island,
	cameraPresets,
	entities: [],
	visibleLayerIds: [],
	reducedMotion: true,
	rendererMode: '3d',
	onFailure: (reason) => rendererFailures.push(reason),
});
assert.ok(configuredInstance);
assert.equal(mapLibreMap.options.style.projection.type, 'globe');
assert.deepEqual(Object.keys(mapLibreMap.options.style.sources), ['basemap', 'satellite', 'terrain']);
assert.equal(mapLibreMap.options.style.sources.terrain.encoding, 'terrarium');
assert.equal(
	JSON.stringify(mapLibreMap.options.style.sources.terrain.bounds),
	JSON.stringify([99.92, 9.63, 100.12, 9.84]),
);
assert.equal(mapLibreMap.options.style.sources.satellite.coordinates.length, 4);
assert.equal(mapLibreMap.options.customAttribution, 'Contains modified Copernicus Sentinel data 2026');
assert.equal(mapLibreMap.options.style.projection.type, 'globe');
assert.equal(mapLibreMap.options.style.terrain.source, 'terrain');
assert.equal(mapLibreMap.options.style.terrain.exaggeration, 1.28);
assert.equal(
	JSON.stringify(mapLibreMap.options.style.layers.find((layer) => layer.id === 'buildings-extruded-reviewed-height').filter),
	JSON.stringify(['all', ['in', 'kind', 'building', 'building_part'], ['has', 'height'], ['>', 'height', 0]]),
);
assert.equal(configuredInstance.capabilities.globe_supported, true);
assert.equal(configuredInstance.capabilities.terrain_supported, true);
assert.equal(protocolAdds, 1);
let contextLossPrevented = false;
mapLibreMap.canvas.listeners.webglcontextlost({ preventDefault: () => { contextLossPrevented = true; } });
assert.equal(contextLossPrevented, true);
assert.deepEqual(rendererFailures, ['webgl_context_lost']);
mapLibreMap.listeners.error();
assert.deepEqual(rendererFailures, ['webgl_context_lost', 'map_source_error']);
configuredInstance.destroy();

window.ThailandDigitalIslandsConfig = {
	reviewed: false,
	islandGeoId: island.geo_id,
	maplibre: { style: 'unreviewed-map-style' },
};
const unreviewedContainer = document.createElement('div');
const unreviewedInstance = window.ThailandDigitalIslandsAdapters.maplibre.mount({
	container: unreviewedContainer,
	island,
	cameraPresets,
	entities: [],
	visibleLayerIds: [],
	reducedMotion: true,
});
assert.equal(unreviewedInstance, null);

window.ThailandDigitalIslandsConfig = {
	reviewed: true,
	contractId: 'thailand-digital-islands-renderer-v1',
	islandGeoId: island.geo_id,
	maplibre: {
		vectorPmtilesUrl: 'https://tiles.example.test/koh-phangan.pmtiles',
	},
};
const externalAssetInstance = window.ThailandDigitalIslandsAdapters.maplibre.mount({
	container: document.createElement('div'),
	island,
	cameraPresets,
	entities: [],
	visibleLayerIds: [],
	reducedMotion: true,
});
assert.equal(externalAssetInstance, null);

process.stdout.write('PASS: Digital Islands browser adapters\n');
