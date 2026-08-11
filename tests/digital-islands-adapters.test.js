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
	Promise,
	Map,
	Set,
	Object,
	Array,
	Number,
	Math,
};
const source = fs.readFileSync(
	path.join(__dirname, '..', 'assets', 'digital-islands', 'digital-islands.js'),
	'utf8',
);
const stylesheet = fs.readFileSync(
	path.join(__dirname, '..', 'assets', 'digital-islands', 'digital-islands.css'),
	'utf8',
);
vm.runInNewContext(source, sandbox, { filename: 'digital-islands.js' });

assert.equal(typeof window.ThailandDigitalIslandsAdapters.cesium.mount, 'function');
assert.equal(typeof window.ThailandDigitalIslandsAdapters.maplibre.mount, 'function');
assert.equal(/https?:\/\//i.test(source), false);
assert.equal(source.includes('Ion.defaultAccessToken'), false);
assert.equal(source.includes('innerHTML'), false);
assert.equal(source.includes("classList.add('is-enhanced')"), true);
assert.equal(source.includes('measurement_supported: false'), true);
assert.equal(source.includes('class OrientationSceneAdapter'), true);
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

let cesiumViewer;
let cesiumClickHandler;
class CesiumViewer {
	constructor(host, options) {
		this.host = host;
		this.options = options;
		this.added = [];
		this.entities = {
			add: (entity) => {
				const added = { ...entity, id: entity.id };
				this.added.push(added);
				return added;
			},
		};
		this.camera = {
			setViews: [],
			flyTos: [],
			setView: (value) => this.camera.setViews.push(value),
			flyTo: (value) => this.camera.flyTos.push(value),
		};
		this.scene = {
			canvas: {},
			picked: null,
			pick: () => this.scene.picked,
			primitives: { add: (primitive) => primitive },
			requestRender: () => { this.renderRequested = true; },
		};
		this.destroyed = false;
		cesiumViewer = this;
	}

	destroy() {
		this.destroyed = true;
	}

	isDestroyed() {
		return this.destroyed;
	}
}
class ScreenSpaceEventHandler {
	constructor() {
		cesiumClickHandler = this;
	}

	setInputAction(callback) {
		this.callback = callback;
	}

	destroy() {
		this.destroyed = true;
	}
}
window.Cesium = {
	Viewer: CesiumViewer,
	Cartesian2: class Cartesian2 {},
	Cartesian3: { fromDegrees: (longitude, latitude, height) => ({ longitude, latitude, height }) },
	Color: {
		BLACK: { name: 'black' },
		WHITE: { name: 'white' },
		fromCssColorString: (value) => ({ value, withAlpha: (alpha) => ({ value, alpha }) }),
	},
	EllipsoidTerrainProvider: class EllipsoidTerrainProvider {},
	LabelStyle: { FILL_AND_OUTLINE: 'fill-outline' },
	Math: { toRadians: (degrees) => degrees * Math.PI / 180 },
	Rectangle: { fromDegrees: (...values) => values },
	ScreenSpaceEventHandler,
	ScreenSpaceEventType: { LEFT_CLICK: 'left-click' },
};

let selected = '';
const cesiumContainer = document.createElement('div');
const cesiumInstance = window.ThailandDigitalIslandsAdapters.cesium.mount({
	container: cesiumContainer,
	island,
	cameraPresets,
	entities: [safeEntity, outsideEntity, invalidEntity],
	visibleLayerIds: ['layer:settlements'],
	reducedMotion: false,
	selectEntity: (entityId) => { selected = entityId; },
});
assert.ok(cesiumInstance);
assert.equal(cesiumViewer.options.baseLayer, false);
assert.equal(cesiumViewer.options.imageryProvider, false);
assert.equal(cesiumViewer.added.length, 1);
assert.equal(cesiumViewer.camera.setViews.length, 1);
assert.equal(cesiumInstance.capabilities.measurement_supported, false);
cesiumViewer.scene.picked = { id: cesiumViewer.added[0] };
cesiumClickHandler.callback({ position: {} });
assert.equal(selected, safeEntity.entity_id);
cesiumInstance.setVisibleLayers([]);
assert.equal(cesiumViewer.added[0].show, false);
cesiumInstance.setVisibleLayers(['layer:settlements']);
assert.equal(cesiumViewer.added[0].show, true);
assert.equal(cesiumInstance.focusEntity(safeEntity.entity_id, { animate: true }), true);
assert.equal(cesiumViewer.camera.flyTos.length, 1);
cesiumInstance.destroy();
assert.equal(cesiumViewer.destroyed, true);
assert.equal(cesiumContainer.children.length, 0);

let mapLibreMap;
const mapLibreMarkers = [];
class MapLibreMap {
	constructor(options) {
		this.options = options;
		this.easeTos = [];
		this.jumpTos = [];
		this.controls = [];
		this.removed = false;
		mapLibreMap = this;
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
window.maplibregl = {
	Map: MapLibreMap,
	Marker,
	NavigationControl: class NavigationControl {},
	ScaleControl: class ScaleControl {},
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
assert.deepEqual(Object.keys(mapLibreMap.options.style.sources), []);
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

window.ThailandDigitalIslandsConfig = {
	reviewed: true,
	islandGeoId: island.geo_id,
	maplibre: { style: 'reviewed-map-style' },
};
const configuredContainer = document.createElement('div');
const configuredInstance = window.ThailandDigitalIslandsAdapters.maplibre.mount({
	container: configuredContainer,
	island,
	cameraPresets,
	entities: [],
	visibleLayerIds: [],
	reducedMotion: true,
});
assert.ok(configuredInstance);
assert.equal(mapLibreMap.options.style, 'reviewed-map-style');
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
assert.ok(unreviewedInstance);
assert.equal(mapLibreMap.options.style.version, 8);
unreviewedInstance.destroy();

const WorkingViewer = window.Cesium.Viewer;
window.Cesium.Viewer = class FailingViewer {
	constructor() {
		throw new Error('expected-constructor-failure');
	}
};
const failedContainer = document.createElement('div');
const failedInstance = window.ThailandDigitalIslandsAdapters.cesium.mount({
	container: failedContainer,
	island,
	cameraPresets,
	entities: [safeEntity],
	visibleLayerIds: ['layer:settlements'],
	reducedMotion: true,
});
assert.equal(failedInstance, null);
assert.equal(failedContainer.children.length, 0);
window.Cesium.Viewer = WorkingViewer;

process.stdout.write('PASS: Digital Islands browser adapters\n');
