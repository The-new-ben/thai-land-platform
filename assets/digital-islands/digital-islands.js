(() => {
	'use strict';

	const ENTITY_ID_PATTERN = /^[a-z_]+:th:[a-z0-9:._-]+$/;
	const LAYER_ID_PATTERN = /^layer:[a-z0-9-]+$/;
	const isObject = (value) => Boolean(value) && typeof value === 'object' && !Array.isArray(value);
	const isFiniteNumber = (value) => typeof value === 'number' && Number.isFinite(value);
	const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));
	const safeName = (entity) => {
		if (!isObject(entity) || !isObject(entity.names)) return '';
		return ['he', 'en', 'th'].reduce((name, locale) => {
			if (name) return name;
			const candidate = entity.names[locale];
			return typeof candidate === 'string' ? candidate.trim().slice(0, 240) : '';
		}, '');
	};
	const safeBounds = (island) => {
		const bounds = isObject(island) && isObject(island.bounds) ? island.bounds : null;
		if (!bounds) return null;
		const { south, north, west, east } = bounds;
		if (![south, north, west, east].every(isFiniteNumber)) return null;
		if (south < -90 || north > 90 || west < -180 || east > 180 || south >= north || west >= east) return null;
		return { south, north, west, east };
	};
	const inBounds = (latitude, longitude, bounds) => (
		latitude >= bounds.south
		&& latitude <= bounds.north
		&& longitude >= bounds.west
		&& longitude <= bounds.east
	);
	const safeEntities = (entities, bounds) => {
		if (!Array.isArray(entities) || !bounds) return [];
		return entities.reduce((safe, entity) => {
			const coordinates = isObject(entity) ? entity.coordinates : null;
			const entityId = isObject(entity) ? entity.entity_id : null;
			if (!ENTITY_ID_PATTERN.test(entityId || '') || !isObject(coordinates)) return safe;
			const latitude = coordinates.latitude;
			const longitude = coordinates.longitude;
			if (!isFiniteNumber(latitude) || !isFiniteNumber(longitude) || !inBounds(latitude, longitude, bounds)) return safe;
			const layerIds = Array.isArray(entity.layer_ids)
				? entity.layer_ids.filter((layerId) => typeof layerId === 'string' && LAYER_ID_PATTERN.test(layerId))
				: [];
			if (!layerIds.length) return safe;
			safe.push({
				entityId,
				entityType: typeof entity.entity_type === 'string' ? entity.entity_type : '',
				name: safeName(entity) || entityId,
				latitude,
				longitude,
				accuracyM: Number.isInteger(coordinates.accuracy_m) && coordinates.accuracy_m >= 0
					? Math.min(coordinates.accuracy_m, 5000)
					: null,
				layerIds,
			});
			return safe;
		}, []);
	};
	const safePresets = (cameraPresets) => {
		if (!Array.isArray(cameraPresets)) return [];
		return cameraPresets.reduce((safe, preset) => {
			const position = isObject(preset) ? preset.position : null;
			if (!isObject(position) || typeof preset.preset_id !== 'string' || !/^[a-z][a-z0-9-]{0,79}$/.test(preset.preset_id)) return safe;
			if (!isFiniteNumber(position.latitude) || !isFiniteNumber(position.longitude)) return safe;
			if (position.latitude < -90 || position.latitude > 90 || position.longitude < -180 || position.longitude > 180) return safe;
			safe.push({
				id: preset.preset_id,
				latitude: position.latitude,
				longitude: position.longitude,
				height: isFiniteNumber(preset.height_m) ? clamp(preset.height_m, 50, 10000000) : 42000,
				heading: isFiniteNumber(preset.heading_deg) ? preset.heading_deg : 0,
				pitch: isFiniteNumber(preset.pitch_deg) ? clamp(preset.pitch_deg, -90, 0) : -55,
				roll: isFiniteNumber(preset.roll_deg) ? preset.roll_deg : 0,
			});
			return safe;
		}, []);
	};
	const preferredIslandPreset = (presets, bounds) => (
		presets.find((preset) => preset.id === 'koh-phangan-orbit')
		|| presets.find((preset) => inBounds(preset.latitude, preset.longitude, bounds))
		|| null
	);
	const requestedLayers = (layerIds) => {
		if (!Array.isArray(layerIds)) return null;
		return new Set(layerIds.filter((layerId) => typeof layerId === 'string' && LAYER_ID_PATTERN.test(layerId)));
	};
	const recordIsVisible = (record, visibleLayers) => (
		visibleLayers === null || record.layerIds.some((layerId) => visibleLayers.has(layerId))
	);
	const reviewedConfig = (adapterId, island) => {
		const config = window.ThailandDigitalIslandsConfig;
		if (!isObject(config) || config.reviewed !== true) return null;
		if (typeof config.islandGeoId === 'string' && config.islandGeoId !== island.geo_id) return null;
		return isObject(config[adapterId]) ? config[adapterId] : null;
	};
	const markerColor = (entityType) => ({
		banking: '#c28a14',
		education: '#4b6f9f',
		government: '#275c4f',
		health: '#b63f51',
		landmark: '#7a6a22',
		postal: '#8a5d3c',
		property_project: '#dc7a20',
		road: '#6d5a46',
		settlement: '#2f7d62',
		telecom: '#3f6aa8',
		transport: '#276fbf',
		utility: '#6553a6',
	}[entityType] || '#0b6f75');
	const makeHost = (container, className, label) => {
		if (!container || typeof container.appendChild !== 'function' || !container.ownerDocument) return null;
		const host = container.ownerDocument.createElement('div');
		host.className = `thp-di-map-canvas ${className}`;
		host.dir = 'ltr';
		host.setAttribute('role', 'application');
		host.setAttribute('aria-label', label);
		container.appendChild(host);
		return host;
	};

	const cesiumFactory = Object.freeze({
		mount(context) {
			const Cesium = window.Cesium;
			const bounds = safeBounds(context && context.island);
			if (!Cesium || typeof Cesium.Viewer !== 'function' || !Cesium.Cartesian3 || !bounds) return null;

			const records = safeEntities(context.entities, bounds);
			const presets = safePresets(context.cameraPresets);
			const initialPreset = preferredIslandPreset(presets, bounds);
			const config = reviewedConfig('cesium', context.island);
			const host = makeHost(context.container, 'thp-di-map-canvas-cesium', 'מפת התמצאות תלת ממדית של קופנגן');
			if (!host) return null;

			let viewer = null;
			let clickHandler = null;
			let destroyed = false;
			const mapEntities = new Map();
			const primitives = [];
			try {
				const options = {
					animation: false,
					baseLayer: false,
					baseLayerPicker: false,
					fullscreenButton: false,
					geocoder: false,
					homeButton: false,
					imageryProvider: false,
					infoBox: false,
					navigationHelpButton: false,
					requestRenderMode: true,
					sceneModePicker: false,
					selectionIndicator: false,
					timeline: false,
				};
				if (typeof Cesium.EllipsoidTerrainProvider === 'function') {
					options.terrainProvider = new Cesium.EllipsoidTerrainProvider();
				}
				if (config && isObject(config.baseLayer)) options.baseLayer = config.baseLayer;
				if (config && isObject(config.imageryProvider)) options.imageryProvider = config.imageryProvider;
				if (config && isObject(config.terrainProvider) && typeof config.terrainProvider.then !== 'function') {
					options.terrainProvider = config.terrainProvider;
				}

				viewer = new Cesium.Viewer(host, options);
				if (!viewer || !viewer.entities || !viewer.camera || !viewer.scene) throw new Error('cesium-viewer-incomplete');

				if (config && config.terrainProvider && typeof config.terrainProvider.then === 'function') {
					Promise.resolve(config.terrainProvider).then((provider) => {
						if (!destroyed && isObject(provider)) {
							viewer.terrainProvider = provider;
							if (viewer.scene && typeof viewer.scene.requestRender === 'function') viewer.scene.requestRender();
						}
					}).catch(() => {});
				}
				if (config && Array.isArray(config.tilesets) && viewer.scene.primitives && typeof viewer.scene.primitives.add === 'function') {
					config.tilesets.forEach((tileset) => {
						if (isObject(tileset) && typeof tileset.then !== 'function') primitives.push(viewer.scene.primitives.add(tileset));
					});
				}

				let visibleLayers = requestedLayers(context.visibleLayerIds);
				records.forEach((record) => {
					const color = Cesium.Color && typeof Cesium.Color.fromCssColorString === 'function'
						? Cesium.Color.fromCssColorString(markerColor(record.entityType))
						: (Cesium.Color ? Cesium.Color.WHITE : undefined);
					const outlineColor = Cesium.Color ? Cesium.Color.WHITE : undefined;
					const entityOptions = {
						id: record.entityId,
						name: record.name,
						position: Cesium.Cartesian3.fromDegrees(record.longitude, record.latitude, 12),
						point: {
							color,
							outlineColor,
							outlineWidth: 2,
							pixelSize: record.entityType === 'property_project' ? 15 : 11,
						},
						label: {
							fillColor: outlineColor,
							font: '600 14px Arial, sans-serif',
							outlineColor: Cesium.Color ? Cesium.Color.BLACK : undefined,
							outlineWidth: 3,
							pixelOffset: Cesium.Cartesian2 ? new Cesium.Cartesian2(0, -24) : undefined,
							showBackground: true,
							style: Cesium.LabelStyle ? Cesium.LabelStyle.FILL_AND_OUTLINE : undefined,
							text: record.name,
						},
						show: recordIsVisible(record, visibleLayers),
					};
					if (record.accuracyM && color && typeof color.withAlpha === 'function') {
						entityOptions.ellipse = {
							material: color.withAlpha(0.16),
							semiMajorAxis: record.accuracyM,
							semiMinorAxis: record.accuracyM,
						};
					}
					const mapEntity = viewer.entities.add(entityOptions);
					mapEntities.set(record.entityId, { mapEntity, record });
				});

				const setCameraPreset = (presetId, animate) => {
					const preset = presets.find((candidate) => candidate.id === presetId);
					if (!preset) return false;
					const destination = Cesium.Cartesian3.fromDegrees(preset.longitude, preset.latitude, preset.height);
					const orientation = Cesium.Math && typeof Cesium.Math.toRadians === 'function'
						? {
							heading: Cesium.Math.toRadians(preset.heading),
							pitch: Cesium.Math.toRadians(preset.pitch),
							roll: Cesium.Math.toRadians(preset.roll),
						}
						: undefined;
					const movement = { destination, orientation };
					if (animate && !context.reducedMotion && typeof viewer.camera.flyTo === 'function') {
						viewer.camera.flyTo({ ...movement, duration: 1.8 });
					} else if (typeof viewer.camera.setView === 'function') {
						viewer.camera.setView(movement);
					}
					return true;
				};

				if (initialPreset) {
					setCameraPreset(initialPreset.id, false);
				} else if (Cesium.Rectangle && typeof Cesium.Rectangle.fromDegrees === 'function') {
					const rectangle = Cesium.Rectangle.fromDegrees(bounds.west, bounds.south, bounds.east, bounds.north);
					if (typeof viewer.camera.setView === 'function') viewer.camera.setView({ destination: rectangle });
				}

				if (
					Cesium.ScreenSpaceEventHandler
					&& Cesium.ScreenSpaceEventType
					&& viewer.scene.canvas
					&& typeof viewer.scene.pick === 'function'
				) {
					clickHandler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
					clickHandler.setInputAction((movement) => {
						const picked = viewer.scene.pick(movement && movement.position);
						const pickedEntity = picked && picked.id;
						const entityId = typeof pickedEntity === 'string'
							? pickedEntity
							: (pickedEntity && typeof pickedEntity.id === 'string' ? pickedEntity.id : '');
						if (mapEntities.has(entityId) && typeof context.selectEntity === 'function') context.selectEntity(entityId);
					}, Cesium.ScreenSpaceEventType.LEFT_CLICK);
				}

				return {
					capabilities: Object.freeze({ measurement_supported: false }),
					focusEntity(entityId, options = {}) {
						const item = mapEntities.get(entityId);
						if (!item || destroyed) return false;
						const destination = Cesium.Cartesian3.fromDegrees(item.record.longitude, item.record.latitude, 1900);
						const movement = { destination };
						if (options.animate && !context.reducedMotion && typeof viewer.camera.flyTo === 'function') {
							viewer.camera.flyTo({ ...movement, duration: 0.9 });
						} else if (typeof viewer.camera.setView === 'function') {
							viewer.camera.setView(movement);
						}
						return true;
					},
					setCameraPreset,
					setVisibleLayers(layerIds) {
						visibleLayers = requestedLayers(layerIds);
						mapEntities.forEach((item) => {
							item.mapEntity.show = recordIsVisible(item.record, visibleLayers);
						});
						if (viewer.scene && typeof viewer.scene.requestRender === 'function') viewer.scene.requestRender();
					},
					destroy() {
						if (destroyed) return;
						destroyed = true;
						if (clickHandler && typeof clickHandler.destroy === 'function') clickHandler.destroy();
						if (viewer && typeof viewer.isDestroyed === 'function' && !viewer.isDestroyed() && typeof viewer.destroy === 'function') viewer.destroy();
						else if (viewer && typeof viewer.destroy === 'function') viewer.destroy();
						primitives.length = 0;
						mapEntities.clear();
						if (host.parentNode) host.parentNode.removeChild(host);
					},
				};
			} catch (error) {
				if (clickHandler && typeof clickHandler.destroy === 'function') clickHandler.destroy();
				if (viewer && typeof viewer.destroy === 'function') {
					try { viewer.destroy(); } catch (destroyError) { /* Fail closed. */ }
				}
				if (host.parentNode) host.parentNode.removeChild(host);
				return null;
			}
		},
	});

	const offlineMapLibreStyle = () => ({
		version: 8,
		sources: {},
		layers: [{
			id: 'private-orientation-background',
			type: 'background',
			paint: { 'background-color': '#cfe7e2' },
		}],
	});
	const heightToZoom = (height, latitude) => {
		const worldMeters = 40075016.686 * Math.max(0.2, Math.cos(latitude * Math.PI / 180));
		return clamp(Math.log2(worldMeters / Math.max(250, height * 1.4)), 8, 16);
	};

	const mapLibreFactory = Object.freeze({
		mount(context) {
			const maplibregl = window.maplibregl;
			const bounds = safeBounds(context && context.island);
			if (!maplibregl || typeof maplibregl.Map !== 'function' || typeof maplibregl.Marker !== 'function' || !bounds) return null;
			const records = safeEntities(context.entities, bounds);
			const presets = safePresets(context.cameraPresets);
			const initialPreset = preferredIslandPreset(presets, bounds);
			const config = reviewedConfig('maplibre', context.island);
			const host = makeHost(context.container, 'thp-di-map-canvas-maplibre', 'מפת התמצאות שימושית של קופנגן');
			if (!host) return null;

			let map = null;
			let destroyed = false;
			const markers = new Map();
			try {
				const style = config && (typeof config.style === 'string' || isObject(config.style))
					? config.style
					: offlineMapLibreStyle();
				const center = initialPreset
					? [initialPreset.longitude, initialPreset.latitude]
					: [(bounds.west + bounds.east) / 2, (bounds.south + bounds.north) / 2];
				map = new maplibregl.Map({
					attributionControl: true,
					bearing: initialPreset ? initialPreset.heading : 0,
					center,
					container: host,
					fadeDuration: context.reducedMotion ? 0 : 300,
					maxBounds: [[bounds.west, bounds.south], [bounds.east, bounds.north]],
					pitch: context.reducedMotion || !initialPreset ? 0 : clamp(Math.abs(initialPreset.pitch), 0, 70),
					renderWorldCopies: false,
					style,
					zoom: initialPreset ? heightToZoom(initialPreset.height, initialPreset.latitude) : 11,
				});
				if (!map || typeof map.remove !== 'function') throw new Error('maplibre-map-incomplete');

				if (typeof maplibregl.NavigationControl === 'function' && typeof map.addControl === 'function') {
					map.addControl(new maplibregl.NavigationControl({ showCompass: true, showZoom: true }), 'top-left');
				}
				if (typeof maplibregl.ScaleControl === 'function' && typeof map.addControl === 'function') {
					map.addControl(new maplibregl.ScaleControl({ maxWidth: 120, unit: 'metric' }), 'bottom-left');
				}

				let visibleLayers = requestedLayers(context.visibleLayerIds);
				records.forEach((record) => {
					const element = host.ownerDocument.createElement('button');
					element.type = 'button';
					element.className = 'thp-di-map-marker';
					element.style.setProperty('--thp-di-marker-color', markerColor(record.entityType));
					element.dataset.label = record.name;
					element.setAttribute('aria-label', `הצג פרטים על ${record.name}`);
					element.hidden = !recordIsVisible(record, visibleLayers);
					element.addEventListener('click', (event) => {
						event.preventDefault();
						event.stopPropagation();
						if (typeof context.selectEntity === 'function') context.selectEntity(record.entityId);
					});
					const marker = new maplibregl.Marker({ anchor: 'center', element })
						.setLngLat([record.longitude, record.latitude])
						.addTo(map);
					markers.set(record.entityId, { element, marker, record });
				});

				const setCameraPreset = (presetId, animate) => {
					const preset = presets.find((candidate) => candidate.id === presetId && inBounds(candidate.latitude, candidate.longitude, bounds));
					if (!preset || destroyed) return false;
					const camera = {
						bearing: preset.heading,
						center: [preset.longitude, preset.latitude],
						pitch: context.reducedMotion ? 0 : clamp(Math.abs(preset.pitch), 0, 70),
						zoom: heightToZoom(preset.height, preset.latitude),
					};
					if (animate && !context.reducedMotion && typeof map.easeTo === 'function') map.easeTo({ ...camera, duration: 900 });
					else if (typeof map.jumpTo === 'function') map.jumpTo(camera);
					return true;
				};

				return {
					capabilities: Object.freeze({ measurement_supported: false }),
					focusEntity(entityId, options = {}) {
						const item = markers.get(entityId);
						if (!item || destroyed) return false;
						const camera = {
							center: [item.record.longitude, item.record.latitude],
							pitch: context.reducedMotion ? 0 : 48,
							zoom: Math.max(typeof map.getZoom === 'function' ? map.getZoom() : 13, 14),
						};
						if (options.animate && !context.reducedMotion && typeof map.easeTo === 'function') map.easeTo({ ...camera, duration: 700 });
						else if (typeof map.jumpTo === 'function') map.jumpTo(camera);
						if (typeof item.element.focus === 'function') item.element.focus({ preventScroll: true });
						return true;
					},
					setCameraPreset,
					setVisibleLayers(layerIds) {
						visibleLayers = requestedLayers(layerIds);
						markers.forEach((item) => {
							item.element.hidden = !recordIsVisible(item.record, visibleLayers);
						});
					},
					destroy() {
						if (destroyed) return;
						destroyed = true;
						markers.forEach((item) => {
							if (item.marker && typeof item.marker.remove === 'function') item.marker.remove();
						});
						markers.clear();
						map.remove();
						if (host.parentNode) host.parentNode.removeChild(host);
					},
				};
			} catch (error) {
				markers.forEach((item) => {
					if (item.marker && typeof item.marker.remove === 'function') item.marker.remove();
				});
				if (map && typeof map.remove === 'function') {
					try { map.remove(); } catch (removeError) { /* Fail closed. */ }
				}
				if (host.parentNode) host.parentNode.removeChild(host);
				return null;
			}
		},
	});

	const adapters = isObject(window.ThailandDigitalIslandsAdapters)
		? window.ThailandDigitalIslandsAdapters
		: {};
	if (!isObject(adapters.cesium) || typeof adapters.cesium.mount !== 'function') adapters.cesium = cesiumFactory;
	if (!isObject(adapters.maplibre) || typeof adapters.maplibre.mount !== 'function') adapters.maplibre = mapLibreFactory;
	window.ThailandDigitalIslandsAdapters = adapters;
})();

(() => {
	'use strict';

	const roots = document.querySelectorAll('[data-digital-island-app]');
	if (!roots.length) return;

	const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const dataSaver = () => Boolean(navigator.connection && navigator.connection.saveData);
	const validEntityId = (value) => typeof value === 'string' && /^[a-z_]+:th:[a-z0-9:._-]+$/.test(value);
	const markerColor = (entityType) => ({
		banking: '#c28a14',
		government: '#275c4f',
		health: '#b63f51',
		postal: '#8a5d3c',
		property_project: '#dc7a20',
		settlement: '#2f7d62',
		transport: '#276fbf',
		utility: '#6553a6',
	}[entityType] || '#0b6f75');
	const supportsWebGL = () => {
		try {
			const canvas = document.createElement('canvas');
			return Boolean(canvas.getContext('webgl2') || canvas.getContext('webgl'));
		} catch (error) {
			return false;
		}
	};

	const readFragment = () => new URLSearchParams(window.location.hash.replace(/^#/, ''));
	const writeFragment = (changes) => {
		const state = readFragment();
		Object.entries(changes).forEach(([key, value]) => {
			if (value === null || value === undefined || value === '') state.delete(key);
			else state.set(key, String(value));
		});
		const fragment = state.toString();
		window.history.replaceState(null, '', `${window.location.pathname}${fragment ? `#${fragment}` : '#'}`);
	};

	class AccessibleListAdapter {
		constructor() {
			this.id = 'list';
		}

		available() {
			return true;
		}

		mount(context) {
			context.stage.replaceChildren();
			context.poster.hidden = false;
			return true;
		}

		focusEntity(entityId, context) {
			if (!validEntityId(entityId)) return;
			const card = context.root.querySelector(`[data-entity-id="${entityId}"]`);
			if (card) card.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
		}

		setVisibleLayers() {}

		measurementSupported() {
			return false;
		}

		destroy() {}
	}

	class ExternalMapAdapter {
		constructor(id, factoryKey) {
			this.id = id;
			this.factoryKey = factoryKey;
			this.instance = null;
		}

		factory() {
			const adapters = window.ThailandDigitalIslandsAdapters;
			const factory = adapters && adapters[this.factoryKey];
			return factory && typeof factory.mount === 'function' ? factory : null;
		}

		available() {
			const libraryReady = this.factoryKey === 'cesium'
				? Boolean(window.Cesium)
				: Boolean(window.maplibregl);
			return supportsWebGL() && libraryReady && Boolean(this.factory());
		}

		mount(context) {
			const factory = this.factory();
			if (!factory) return false;
			this.destroy();
			context.stage.replaceChildren();
			try {
				this.instance = factory.mount({
					container: context.stage,
					island: context.island,
					layers: context.layers,
					entities: context.entities,
					cameraPresets: context.cameraPresets,
					visibleLayerIds: context.visibleLayerIds,
					reducedMotion: reducedMotion(),
					selectEntity: context.selectEntity,
				});
			} catch (error) {
				this.instance = null;
			}
			context.poster.hidden = Boolean(this.instance);
			return Boolean(this.instance);
		}

		focusEntity(entityId) {
			if (this.instance && typeof this.instance.focusEntity === 'function') {
				this.instance.focusEntity(entityId, { animate: !reducedMotion() });
			}
		}

		setVisibleLayers(layerIds) {
			if (this.instance && typeof this.instance.setVisibleLayers === 'function') {
				this.instance.setVisibleLayers(layerIds);
			}
		}

		measurementSupported() {
			return Boolean(
				this.instance
				&& this.instance.capabilities
				&& this.instance.capabilities.measurement_supported === true,
			);
		}

		destroy() {
			if (this.instance && typeof this.instance.destroy === 'function') this.instance.destroy();
			this.instance = null;
		}
	}

	class CesiumAdapter extends ExternalMapAdapter {
		constructor() {
			super('3d', 'cesium');
		}
	}

	class MapLibreAdapter extends ExternalMapAdapter {
		constructor() {
			super('2d', 'maplibre');
		}
	}

	class OrientationSceneAdapter {
		constructor() {
			this.id = 'preview';
			this.canvas = null;
			this.context = null;
			this.bounds = null;
			this.records = [];
			this.visibleLayers = null;
			this.selectedEntityId = '';
			this.frame = 0;
			this.cleanup = [];
			this.state = { bearing: 25, pitch: 54, zoom: 1, panX: 0, panY: 0 };
			this.viewport = { width: 0, height: 0, dpr: 1 };
		}

		available() {
			try {
				const canvas = document.createElement('canvas');
				return Boolean(canvas.getContext('2d'));
			} catch (error) {
				return false;
			}
		}

		mount(context) {
			this.destroy();
			const bounds = context && context.island && context.island.bounds;
			if (
				!bounds
				|| ![bounds.south, bounds.north, bounds.west, bounds.east].every((value) => typeof value === 'number' && Number.isFinite(value))
				|| bounds.south >= bounds.north
				|| bounds.west >= bounds.east
			) return false;

			this.bounds = bounds;
			this.context = context;
			this.visibleLayers = new Set(Array.isArray(context.visibleLayerIds) ? context.visibleLayerIds : []);
			this.records = (Array.isArray(context.entities) ? context.entities : []).reduce((records, entity) => {
				const coordinates = entity && entity.coordinates;
				const layerIds = entity && Array.isArray(entity.layer_ids) ? entity.layer_ids : [];
				if (
					!validEntityId(entity && entity.entity_id)
					|| !coordinates
					|| typeof coordinates.latitude !== 'number'
					|| typeof coordinates.longitude !== 'number'
					|| !Number.isFinite(coordinates.latitude)
					|| !Number.isFinite(coordinates.longitude)
					|| coordinates.latitude < bounds.south
					|| coordinates.latitude > bounds.north
					|| coordinates.longitude < bounds.west
					|| coordinates.longitude > bounds.east
					|| !layerIds.length
				) return records;
				const names = entity.names && typeof entity.names === 'object' ? entity.names : {};
				records.push({
					entityId: entity.entity_id,
					entityType: typeof entity.entity_type === 'string' ? entity.entity_type : '',
					name: [names.he, names.en, names.th].find((name) => typeof name === 'string' && name.trim()) || entity.entity_id,
					latitude: coordinates.latitude,
					longitude: coordinates.longitude,
					layerIds: layerIds.filter((layerId) => typeof layerId === 'string' && /^layer:[a-z0-9-]+$/.test(layerId)),
				});
				return records;
			}, []).filter((record) => record.layerIds.length);

			context.stage.replaceChildren();
			const canvas = context.stage.ownerDocument.createElement('canvas');
			canvas.className = 'thp-di-orientation-scene';
			canvas.dir = 'rtl';
			canvas.tabIndex = 0;
			canvas.setAttribute('role', 'application');
			canvas.setAttribute('aria-label', 'סצנת התמצאות מקומית של קופנגן. זו אינה מפת תבליט או תצלום. גרירה מסובבת, שיפט וגרירה מזיזים, וגלגלת משנה מרחק.');
			context.stage.appendChild(canvas);
			context.poster.hidden = true;
			this.canvas = canvas;
			this.state = { bearing: 25, pitch: 54, zoom: 1, panX: 0, panY: 0 };

			const resize = () => {
				if (!this.canvas) return;
				const rectangle = this.canvas.getBoundingClientRect();
				const width = Math.max(320, Math.round(rectangle.width || context.stage.clientWidth || 900));
				const height = Math.max(420, Math.round(rectangle.height || context.stage.clientHeight || 660));
				const dpr = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
				this.viewport = { width, height, dpr };
				if (this.canvas.width !== Math.round(width * dpr)) this.canvas.width = Math.round(width * dpr);
				if (this.canvas.height !== Math.round(height * dpr)) this.canvas.height = Math.round(height * dpr);
				this.render();
			};
			resize();

			if (typeof window.ResizeObserver === 'function') {
				const observer = new window.ResizeObserver(resize);
				observer.observe(canvas);
				this.cleanup.push(() => observer.disconnect());
			} else {
				window.addEventListener('resize', resize);
				this.cleanup.push(() => window.removeEventListener('resize', resize));
			}

			let pointer = null;
			const pointerDown = (event) => {
				pointer = { id: event.pointerId, x: event.clientX, y: event.clientY, moved: 0, shift: event.shiftKey };
				if (typeof canvas.setPointerCapture === 'function') canvas.setPointerCapture(event.pointerId);
			};
			const pointerMove = (event) => {
				if (!pointer || pointer.id !== event.pointerId) return;
				const deltaX = event.clientX - pointer.x;
				const deltaY = event.clientY - pointer.y;
				pointer.x = event.clientX;
				pointer.y = event.clientY;
				pointer.moved += Math.abs(deltaX) + Math.abs(deltaY);
				if (pointer.shift || event.shiftKey) {
					this.state.panX += deltaX;
					this.state.panY += deltaY;
				} else {
					this.state.bearing = (this.state.bearing + deltaX * 0.35 + 360) % 360;
					this.state.pitch = Math.min(72, Math.max(24, this.state.pitch - deltaY * 0.22));
				}
				this.render();
			};
			const pointerUp = (event) => {
				if (!pointer || pointer.id !== event.pointerId) return;
				if (pointer.moved < 8) this.selectAt(event.clientX, event.clientY);
				pointer = null;
			};
			const wheel = (event) => {
				event.preventDefault();
				this.state.zoom = Math.min(4, Math.max(0.65, this.state.zoom * Math.exp(-event.deltaY * 0.0012)));
				this.render();
			};
			const keydown = (event) => {
				let handled = true;
				switch (event.key) {
					case 'ArrowLeft': this.state.bearing = (this.state.bearing - 6 + 360) % 360; break;
					case 'ArrowRight': this.state.bearing = (this.state.bearing + 6) % 360; break;
					case 'ArrowUp': this.state.pitch = Math.min(72, this.state.pitch + 3); break;
					case 'ArrowDown': this.state.pitch = Math.max(24, this.state.pitch - 3); break;
					case '+':
					case '=': this.state.zoom = Math.min(4, this.state.zoom * 1.12); break;
					case '-':
					case '_': this.state.zoom = Math.max(0.65, this.state.zoom / 1.12); break;
					case 'w':
					case 'W': this.state.panY += 18; break;
					case 's':
					case 'S': this.state.panY -= 18; break;
					case 'a':
					case 'A': this.state.panX += 18; break;
					case 'd':
					case 'D': this.state.panX -= 18; break;
					case 'Home': this.state = { bearing: 25, pitch: 54, zoom: 1, panX: 0, panY: 0 }; break;
					case 'Enter': this.selectNearestCenter(); break;
					default: handled = false;
				}
				if (handled) {
					event.preventDefault();
					this.render();
				}
			};

			canvas.addEventListener('pointerdown', pointerDown);
			canvas.addEventListener('pointermove', pointerMove);
			canvas.addEventListener('pointerup', pointerUp);
			canvas.addEventListener('pointercancel', pointerUp);
			canvas.addEventListener('wheel', wheel, { passive: false });
			canvas.addEventListener('keydown', keydown);
			this.cleanup.push(() => canvas.removeEventListener('pointerdown', pointerDown));
			this.cleanup.push(() => canvas.removeEventListener('pointermove', pointerMove));
			this.cleanup.push(() => canvas.removeEventListener('pointerup', pointerUp));
			this.cleanup.push(() => canvas.removeEventListener('pointercancel', pointerUp));
			this.cleanup.push(() => canvas.removeEventListener('wheel', wheel));
			this.cleanup.push(() => canvas.removeEventListener('keydown', keydown));
			return true;
		}

		visible(record) {
			return !this.visibleLayers || record.layerIds.some((layerId) => this.visibleLayers.has(layerId));
		}

		project(record, includePan = true) {
			if (!this.bounds) return null;
			const { width, height } = this.viewport;
			const unit = Math.min(width, height) * 0.72;
			const normalizedX = (record.longitude - this.bounds.west) / (this.bounds.east - this.bounds.west) - 0.5;
			const normalizedY = 0.5 - (record.latitude - this.bounds.south) / (this.bounds.north - this.bounds.south);
			const angle = this.state.bearing * Math.PI / 180;
			const rotatedX = normalizedX * Math.cos(angle) - normalizedY * Math.sin(angle);
			const rotatedY = normalizedX * Math.sin(angle) + normalizedY * Math.cos(angle);
			const pitchScale = 0.35 + (72 - this.state.pitch) / 96;
			const radius = Math.min(1, Math.sqrt(normalizedX ** 2 + normalizedY ** 2) * 1.55);
			const visualLift = (1 - radius) * Math.sin(this.state.pitch * Math.PI / 180) * 28;
			return {
				x: width / 2 + (includePan ? this.state.panX : 0) + rotatedX * unit * this.state.zoom,
				y: height / 2 + (includePan ? this.state.panY : 0) + rotatedY * unit * pitchScale * this.state.zoom - visualLift * this.state.zoom,
			};
		}

		render() {
			if (!this.canvas || !this.bounds || this.frame) return;
			this.frame = window.requestAnimationFrame(() => {
				this.frame = 0;
				if (!this.canvas) return;
				const drawing = this.canvas.getContext('2d');
				if (!drawing) return;
				const { width, height, dpr } = this.viewport;
				drawing.setTransform(dpr, 0, 0, dpr, 0, 0);
				drawing.clearRect(0, 0, width, height);
				const sea = drawing.createLinearGradient(0, 0, width, height);
				sea.addColorStop(0, '#b8ddd9');
				sea.addColorStop(1, '#0d6670');
				drawing.fillStyle = sea;
				drawing.fillRect(0, 0, width, height);

				drawing.save();
				drawing.translate(width / 2 + this.state.panX, height / 2 + this.state.panY + 22);
				drawing.rotate(this.state.bearing * Math.PI / 180);
				const islandWidth = Math.min(width, height) * 0.67 * this.state.zoom;
				const pitchScale = 0.35 + (72 - this.state.pitch) / 96;
				drawing.scale(1, pitchScale);
				drawing.shadowBlur = 36;
				drawing.shadowColor = 'rgba(4, 39, 45, 0.38)';
				drawing.shadowOffsetY = 24;
				const land = drawing.createRadialGradient(-islandWidth * 0.12, -islandWidth * 0.12, 8, 0, 0, islandWidth * 0.55);
				land.addColorStop(0, '#dce8b7');
				land.addColorStop(0.52, '#7da06d');
				land.addColorStop(1, '#315e4b');
				drawing.fillStyle = land;
				drawing.beginPath();
				drawing.ellipse(0, 0, islandWidth * 0.47, islandWidth * 0.52, -0.13, 0, Math.PI * 2);
				drawing.fill();
				drawing.shadowColor = 'transparent';
				drawing.strokeStyle = 'rgba(255, 255, 255, 0.28)';
				drawing.lineWidth = 1.2 / Math.max(0.7, this.state.zoom);
				[0.22, 0.31, 0.4].forEach((radius) => {
					drawing.beginPath();
					drawing.ellipse(0, 0, islandWidth * radius, islandWidth * (radius + 0.035), -0.13, 0, Math.PI * 2);
					drawing.stroke();
				});
				drawing.restore();

				this.records.filter((record) => this.visible(record)).forEach((record) => {
					const point = this.project(record);
					if (!point || point.x < -30 || point.x > width + 30 || point.y < -30 || point.y > height + 30) return;
					const selected = record.entityId === this.selectedEntityId;
					drawing.beginPath();
					drawing.arc(point.x, point.y, selected ? 9 : 6, 0, Math.PI * 2);
					drawing.fillStyle = markerColor(record.entityType);
					drawing.fill();
					drawing.lineWidth = selected ? 4 : 2;
					drawing.strokeStyle = '#ffffff';
					drawing.stroke();
					if (selected) {
						drawing.direction = 'rtl';
						drawing.font = '700 14px Arial, sans-serif';
						drawing.textAlign = 'center';
						drawing.textBaseline = 'bottom';
						drawing.lineWidth = 4;
						drawing.strokeStyle = 'rgba(19, 45, 53, 0.92)';
						drawing.strokeText(record.name, point.x, point.y - 14);
						drawing.fillStyle = '#ffffff';
						drawing.fillText(record.name, point.x, point.y - 14);
					}
				});

				drawing.direction = 'rtl';
				drawing.font = '700 13px Arial, sans-serif';
				drawing.textAlign = 'right';
				drawing.textBaseline = 'top';
				drawing.fillStyle = 'rgba(255, 255, 255, 0.92)';
				drawing.fillText('סצנת התמצאות מקומית, לא תבליט או תצלום', width - 18, 18);
			});
		}

		selectAt(clientX, clientY) {
			if (!this.canvas) return;
			const rectangle = this.canvas.getBoundingClientRect();
			const x = clientX - rectangle.left;
			const y = clientY - rectangle.top;
			let closest = null;
			this.records.filter((record) => this.visible(record)).forEach((record) => {
				const point = this.project(record);
				if (!point) return;
				const distance = Math.hypot(point.x - x, point.y - y);
				if (distance <= 18 && (!closest || distance < closest.distance)) closest = { record, distance };
			});
			if (closest && this.context && typeof this.context.selectEntity === 'function') {
				this.context.selectEntity(closest.record.entityId);
			}
		}

		selectNearestCenter() {
			let closest = null;
			this.records.filter((record) => this.visible(record)).forEach((record) => {
				const point = this.project(record);
				if (!point) return;
				const distance = Math.hypot(point.x - this.viewport.width / 2, point.y - this.viewport.height / 2);
				if (!closest || distance < closest.distance) closest = { record, distance };
			});
			if (closest && this.context && typeof this.context.selectEntity === 'function') this.context.selectEntity(closest.record.entityId);
		}

		focusEntity(entityId) {
			const record = this.records.find((candidate) => candidate.entityId === entityId);
			if (!record) return false;
			this.selectedEntityId = entityId;
			this.state.zoom = Math.max(1.7, this.state.zoom);
			const point = this.project(record, false);
			if (point) {
				this.state.panX = this.viewport.width / 2 - point.x;
				this.state.panY = this.viewport.height / 2 - point.y;
			}
			this.render();
			if (this.canvas && typeof this.canvas.focus === 'function') this.canvas.focus({ preventScroll: true });
			return true;
		}

		setVisibleLayers(layerIds) {
			this.visibleLayers = new Set(Array.isArray(layerIds) ? layerIds : []);
			if (this.selectedEntityId) {
				const selected = this.records.find((record) => record.entityId === this.selectedEntityId);
				if (!selected || !this.visible(selected)) this.selectedEntityId = '';
			}
			this.render();
		}

		measurementSupported() {
			return false;
		}

		destroy() {
			if (this.frame) window.cancelAnimationFrame(this.frame);
			this.frame = 0;
			this.cleanup.splice(0).forEach((remove) => {
				try { remove(); } catch (error) { /* Local preview cleanup is best effort. */ }
			});
			if (this.canvas && this.canvas.parentNode) this.canvas.parentNode.removeChild(this.canvas);
			this.canvas = null;
			this.context = null;
			this.bounds = null;
			this.records = [];
			this.visibleLayers = null;
			this.selectedEntityId = '';
		}
	}

	const fetchJson = async (url, nonce) => {
		const headers = { Accept: 'application/json' };
		if (nonce) headers['X-WP-Nonce'] = nonce;
		const response = await window.fetch(url, {
			method: 'GET',
			credentials: nonce ? 'same-origin' : 'omit',
			cache: nonce ? 'no-store' : 'default',
			redirect: 'error',
			headers,
		});
		if (!response.ok) throw new Error('island-response-unavailable');
		return response.json();
	};

	const adapterCandidates = (requested, adapters) => {
		if (requested === 'list' || dataSaver() || reducedMotion()) return [adapters.list];
		const candidates = [];
		if (supportsWebGL() && requested === '3d' && adapters.cesium.available()) candidates.push(adapters.cesium);
		if (supportsWebGL() && adapters.maplibre.available()) candidates.push(adapters.maplibre);
		if (requested === '3d' && adapters.orientation.available()) candidates.push(adapters.orientation);
		candidates.push(adapters.list);
		return candidates;
	};

	const start = async (root) => {
		const stage = root.querySelector('[data-renderer-stage]');
		const poster = root.querySelector('[data-list-poster]');
		const status = root.querySelector('[data-renderer-status]');
		const restBase = root.dataset.restBase;
		const nonce = root.dataset.restNonce || '';
		if (!stage || !poster || !status || !restBase) return;

		const adapters = {
			cesium: new CesiumAdapter(),
			maplibre: new MapLibreAdapter(),
			orientation: new OrientationSceneAdapter(),
			list: new AccessibleListAdapter(),
		};
		let activeAdapter = adapters.list;
		let payload = { island: null, cameraPresets: [], layers: [], entities: [] };
		let drawer = null;

		const selectedLayerIds = () => Array.from(
			root.querySelectorAll('[data-layer-filter]:checked'),
			(input) => input.value,
		);
		const entityTypeLabel = (entityType) => ({
			banking: 'בנקאות',
			education: 'חינוך',
			government: 'שירות ממשלתי',
			health: 'בריאות',
			landmark: 'נקודת התמצאות',
			postal: 'דואר',
			professional_service: 'שירות מקצועי',
			property_project: 'פרויקט נדל״ן במעקב',
			road: 'ציר דרך להתמצאות',
			settlement: 'יישוב',
			telecom: 'תקשורת',
			transport: 'תחבורה',
			utility: 'תשתית',
		}[entityType] || 'נקודת התמצאות');

		const ensureDrawer = () => {
			if (drawer) return drawer;
			const shell = root.querySelector('[data-renderer-shell]');
			if (!shell || !shell.ownerDocument) return null;
			const panel = shell.ownerDocument.createElement('aside');
			panel.className = 'thp-di-detail-drawer';
			panel.hidden = true;
			panel.dir = 'rtl';
			panel.setAttribute('role', 'region');
			panel.setAttribute('aria-live', 'polite');
			const titleId = `thp-di-detail-title-${(root.dataset.islandId || 'island').replace(/[^a-z0-9_-]/gi, '-')}`;
			panel.setAttribute('aria-labelledby', titleId);
			const close = shell.ownerDocument.createElement('button');
			close.type = 'button';
			close.className = 'thp-di-detail-close';
			close.setAttribute('aria-label', 'סגירת פרטי המקום');
			close.textContent = '×';
			const eyebrow = shell.ownerDocument.createElement('p');
			eyebrow.className = 'thp-di-detail-type';
			const title = shell.ownerDocument.createElement('h3');
			title.id = titleId;
			const location = shell.ownerDocument.createElement('p');
			location.className = 'thp-di-detail-location';
			const facts = shell.ownerDocument.createElement('dl');
			facts.className = 'thp-di-detail-facts';
			const caveat = shell.ownerDocument.createElement('p');
			caveat.className = 'thp-di-detail-caveat';
			const openCard = shell.ownerDocument.createElement('button');
			openCard.type = 'button';
			openCard.className = 'thp-di-detail-card-link';
			openCard.textContent = 'מעבר לכרטיס המלא ברשימה';
			panel.append(close, eyebrow, title, location, facts, caveat, openCard);
			shell.appendChild(panel);
			close.addEventListener('click', () => {
				panel.hidden = true;
				if (typeof stage.focus === 'function') stage.focus({ preventScroll: true });
			});
			panel.addEventListener('keydown', (event) => {
				if (event.key !== 'Escape') return;
				panel.hidden = true;
				if (typeof stage.focus === 'function') stage.focus({ preventScroll: true });
			});
			drawer = { panel, eyebrow, title, location, facts, caveat, openCard, entityId: '' };
			return drawer;
		};

		const renderDrawer = (entityId) => {
			const detail = ensureDrawer();
			const entity = payload.entities.find((candidate) => candidate.entity_id === entityId);
			if (!detail || !entity) {
				if (detail) detail.panel.hidden = true;
				return;
			}
			const names = entity.names && typeof entity.names === 'object' ? entity.names : {};
			const name = [names.he, names.en, names.th].find((candidate) => typeof candidate === 'string' && candidate.trim()) || entityId;
			detail.entityId = entityId;
			detail.eyebrow.textContent = entityTypeLabel(entity.entity_type);
			detail.title.textContent = name;
			detail.location.textContent = typeof entity.location_label_he === 'string' && entity.location_label_he
				? entity.location_label_he
				: 'מיקום כללי בקופנגן';
			detail.facts.replaceChildren();
			(Array.isArray(entity.facts) ? entity.facts : []).forEach((fact) => {
				if (!fact || typeof fact.label_he !== 'string' || typeof fact.value_he !== 'string') return;
				const row = detail.facts.ownerDocument.createElement('div');
				const term = detail.facts.ownerDocument.createElement('dt');
				const value = detail.facts.ownerDocument.createElement('dd');
				term.textContent = fact.label_he;
				value.textContent = fact.value_he;
				row.append(term, value);
				detail.facts.appendChild(row);
			});
			const coordinates = entity.coordinates;
			const basis = coordinates && typeof coordinates.basis_label === 'string' ? coordinates.basis_label.trim() : '';
			detail.caveat.textContent = basis
				? `בסיס הנקודה: ${basis}. הנקודה מיועדת להתמצאות ואינה אימות של חלקה, בעלות או זכויות בנייה.`
				: 'הנקודה מיועדת להתמצאות בלבד ואינה אימות של חלקה, בעלות או זכויות בנייה.';
			detail.openCard.onclick = () => {
				const card = root.querySelector(`[data-entity-card][data-entity-id="${entityId}"]`);
				if (!card) return;
				detail.panel.hidden = true;
				card.hidden = false;
				const group = card.closest('[data-entity-group]');
				if (group) group.hidden = false;
				card.tabIndex = -1;
				card.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
				card.focus({ preventScroll: true });
			};
			detail.panel.hidden = false;
		};

		const context = () => ({
			root,
			stage,
			poster,
			island: payload.island,
			cameraPresets: payload.cameraPresets,
			layers: payload.layers,
			entities: payload.entities,
			visibleLayerIds: selectedLayerIds(),
			selectEntity: (entityId) => selectEntity(entityId),
		});

		const activateView = (requested) => {
			activeAdapter.destroy();
			const candidates = adapterCandidates(requested, adapters);
			activeAdapter = adapters.list;
			for (const candidate of candidates) {
				if (candidate.mount(context())) {
					activeAdapter = candidate;
					break;
				}
			}
			root.dataset.activeRenderer = activeAdapter.id;
			root.dataset.measurementSupported = String(activeAdapter.measurementSupported());
			const pressedMode = activeAdapter.id === 'preview' ? '3d' : activeAdapter.id;
			root.querySelectorAll('[data-view-mode]').forEach((button) => {
				button.setAttribute('aria-pressed', String(button.dataset.viewMode === pressedMode));
			});
			const labels = {
				'3d': 'תצוגת העולם התלת ממדי פעילה',
				'2d': 'המפה השימושית פעילה',
				preview: 'סצנת התמצאות מקומית פעילה. זו אינה מפת תבליט או תצלום',
				list: 'הרשימה הנגישה פעילה',
			};
			const measurement = activeAdapter.measurementSupported() ? '' : '. כלי מדידה אינם זמינים בפיילוט';
			status.textContent = `${labels[activeAdapter.id]}${measurement}`;
		};

		const selectEntity = (entityId) => {
			if (!validEntityId(entityId)) return;
			writeFragment({ entity: entityId });
			renderDrawer(entityId);
			activeAdapter.focusEntity(entityId, context());
			root.querySelectorAll('[data-entity-card]').forEach((card) => {
				card.classList.toggle('is-selected', card.dataset.entityId === entityId);
			});
		};

		const applyFilters = (allowedIds = null) => {
			const state = readFragment();
			const query = (state.get('q') || '').trim().toLocaleLowerCase('he');
			const selectedLayers = new Set(
				Array.from(root.querySelectorAll('[data-layer-filter]:checked'), (input) => input.value),
			);
			let visible = 0;
			root.querySelectorAll('[data-entity-card]').forEach((card) => {
				const layers = card.dataset.entityLayers.split(',');
				const layerMatch = layers.some((layerId) => selectedLayers.has(layerId));
				const queryMatch = !query || card.textContent.toLocaleLowerCase('he').includes(query);
				const remoteMatch = !allowedIds || allowedIds.has(card.dataset.entityId);
				card.hidden = !(layerMatch && queryMatch && remoteMatch);
				if (!card.hidden) visible += 1;
			});
			root.querySelectorAll('[data-entity-group]').forEach((group) => {
				group.hidden = !group.querySelector('[data-entity-card]:not([hidden])');
			});
			const count = root.querySelector('[data-visible-count]');
			const empty = root.querySelector('[data-no-results]');
			if (count) count.textContent = String(visible);
			if (empty) empty.hidden = visible !== 0;
			activeAdapter.setVisibleLayers(Array.from(selectedLayers));
		};

		root.querySelectorAll('[data-view-mode]').forEach((button) => {
			button.addEventListener('click', () => {
				writeFragment({ view: button.dataset.viewMode });
				activateView(button.dataset.viewMode);
			});
		});

		root.querySelectorAll('[data-layer-filter]').forEach((input) => {
			input.addEventListener('change', () => {
				const layers = Array.from(root.querySelectorAll('[data-layer-filter]:checked'), (item) => item.value);
				writeFragment({ layers: layers.join(',') });
				applyFilters();
			});
		});

		root.querySelectorAll('[data-focus-entity]').forEach((button) => {
			button.addEventListener('click', () => {
				selectEntity(button.dataset.focusEntity);
				const shell = root.querySelector('[data-renderer-shell]');
				if (shell && typeof shell.scrollIntoView === 'function') {
					shell.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
				}
				if (typeof stage.focus === 'function') stage.focus({ preventScroll: true });
			});
		});

		const searchForm = root.querySelector('[data-island-search]');
		const searchInput = searchForm && searchForm.querySelector('input[type="search"]');
		if (searchForm && searchInput) {
			searchForm.addEventListener('submit', async (event) => {
				event.preventDefault();
				const term = searchInput.value.trim();
				writeFragment({ q: term });
				if (term.length < 2) {
					applyFilters();
					return;
				}
				try {
					const result = await fetchJson(`${restBase}/search/${encodeURIComponent(term)}`, nonce);
					applyFilters(new Set(result.results.map((entity) => entity.entity_id)));
				} catch (error) {
					applyFilters();
				}
			});
		}

		try {
			const [islandResponse, layersResponse, entitiesResponse] = await Promise.all([
				fetchJson(restBase, nonce),
				fetchJson(`${restBase}/layers`, nonce),
				fetchJson(`${restBase}/entities`, nonce),
			]);
			if (
				!islandResponse
				|| !islandResponse.island
				|| !layersResponse
				|| !Array.isArray(layersResponse.layers)
				|| !entitiesResponse
				|| !Array.isArray(entitiesResponse.entities)
			) throw new Error('island-contract-invalid');
			payload = {
				island: islandResponse.island,
				cameraPresets: Array.isArray(islandResponse.camera_presets) ? islandResponse.camera_presets : [],
				layers: layersResponse.layers,
				entities: entitiesResponse.entities,
			};
			root.dataset.enhanced = 'true';
			root.classList.add('is-enhanced');
		} catch (error) {
			status.textContent = 'הרשימה הנגישה פעילה';
			activeAdapter = adapters.list;
			activeAdapter.mount(context());
			return;
		}

		const initial = readFragment();
		const requestedLayers = new Set((initial.get('layers') || '').split(',').filter(Boolean));
		if (requestedLayers.size) {
			root.querySelectorAll('[data-layer-filter]').forEach((input) => {
				input.checked = requestedLayers.has(input.value);
			});
		}
		if (searchInput) searchInput.value = initial.get('q') || '';
		activateView(initial.get('view') || '3d');
		applyFilters();
		selectEntity(initial.get('entity'));

		window.addEventListener('hashchange', () => {
			const state = readFragment();
			activateView(state.get('view') || '3d');
			applyFilters();
			if (validEntityId(state.get('entity'))) activeAdapter.focusEntity(state.get('entity'), context());
		});

		window.addEventListener('pagehide', () => activeAdapter.destroy(), { once: true });
	};

	roots.forEach((root) => start(root));
})();
