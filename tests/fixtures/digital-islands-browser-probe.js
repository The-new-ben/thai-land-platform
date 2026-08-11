async (page) => {
	'use strict';

	const invariant = (condition, message) => {
		if (!condition) throw new Error(`Digital Islands browser acceptance: ${message}`);
	};
	const scenarioMatch = /[?&]scenario=([^&#]+)/.exec(page.url());
	const scenario = scenarioMatch ? decodeURIComponent(scenarioMatch[1]) : 'desktop-3d';
	const expectedOrigin = await page.evaluate(() => window.location.origin);
	const requests = [];
	const failedRequests = [];
	const consoleErrors = [];
	const onRequest = (request) => requests.push(request);
	const onRequestFailed = (request) => failedRequests.push({
		url: request.url(),
		error: request.failure() ? request.failure().errorText : 'request-failed',
		range: request.headers().range || '',
	});
	const onConsole = (message) => {
		if (message.type() === 'error') consoleErrors.push(message.text());
	};
	page.on('request', onRequest);
	page.on('requestfailed', onRequestFailed);
	page.on('console', onConsole);

	try {
		if (scenario === 'reduced-motion') await page.emulateMedia({ reducedMotion: 'reduce' });
		await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
		await page.waitForFunction(() => {
			const root = document.querySelector('[data-digital-island-app]');
			return root && root.dataset.enhanced === 'true' && Boolean(root.dataset.activeRenderer);
		}, null, { timeout: 30000 });
		await page.waitForTimeout(1200);
		if (scenario === 'desktop-3d') {
			await page.evaluate(() => {
				const host = document.querySelector('.thp-di-map-canvas-maplibre');
				if (host) host.dataset.acceptancePreviousHost = 'true';
			});
			const threeDimensionalButton = page.locator('[data-view-mode="3d"]');
			await threeDimensionalButton.click();
			await threeDimensionalButton.click();
			await page.waitForFunction(() => {
				const root = document.querySelector('[data-digital-island-app]');
				const host = document.querySelector('.thp-di-map-canvas-maplibre[data-renderer-mode="3d"]');
				const poster = document.querySelector('[data-list-poster]');
				const browserEvidence = window.__thpBrowserEvidence || { maps: [] };
				const activeRecord = browserEvidence.maps.reduceRight((found, record) => (
					found || record.removed ? found : record
				), null);
				return root
					&& root.dataset.activeRenderer === '3d'
					&& host
					&& host.dataset.acceptancePreviousHost !== 'true'
					&& poster
					&& poster.hidden
					&& activeRecord
					&& activeRecord.loaded === true
					&& document.querySelectorAll('.thp-di-map-marker').length === 27;
			}, null, { timeout: 45000 });
		}

		if (scenario === 'desktop-3d' || scenario === 'desktop-2d' || scenario === 'mobile-2d') {
			const marker = page.locator('.thp-di-map-marker').first();
			await marker.waitFor({ state: 'visible', timeout: 15000 });
			await marker.click();
			await page.locator('.thp-di-detail-drawer:not([hidden])').waitFor({ state: 'visible', timeout: 10000 });
			await page.waitForTimeout(1000);
		}

		const evidence = await page.evaluate(() => {
			const root = document.querySelector('[data-digital-island-app]');
			const fixture = window.__thpFixture || {};
			const browserEvidence = window.__thpBrowserEvidence || { maps: [], instances: [] };
			const activeIndex = browserEvidence.maps.reduceRight((found, record, index) => (
				found >= 0 || record.removed ? found : index
			), -1);
			const activeRecord = activeIndex >= 0 ? browserEvidence.maps[activeIndex] : null;
			const map = activeIndex >= 0 ? browserEvidence.instances[activeIndex] : null;
			let style = null;
			let projection = null;
			let terrain = null;
			let mapLoaded = false;
			let styleLoaded = false;
			let canvas = null;
			let webgl2 = false;
			if (map) {
				try { style = typeof map.getStyle === 'function' ? map.getStyle() : null; } catch (error) { style = null; }
				try { projection = typeof map.getProjection === 'function' ? map.getProjection() : null; } catch (error) { projection = null; }
				try { terrain = typeof map.getTerrain === 'function' ? map.getTerrain() : null; } catch (error) { terrain = null; }
				try { mapLoaded = typeof map.loaded === 'function' && map.loaded(); } catch (error) { mapLoaded = false; }
				try { styleLoaded = typeof map.isStyleLoaded === 'function' && map.isStyleLoaded(); } catch (error) { styleLoaded = false; }
				try {
					canvas = typeof map.getCanvas === 'function' ? map.getCanvas() : null;
					webgl2 = Boolean(canvas && canvas.getContext('webgl2'));
				} catch (error) { webgl2 = false; }
			}
			const drawer = document.querySelector('.thp-di-detail-drawer:not([hidden])');
			return {
				active_renderer: root ? root.dataset.activeRenderer || '' : '',
				maplibre_attribution_text: document.querySelector('.maplibregl-ctrl-attrib') ? document.querySelector('.maplibregl-ctrl-attrib').textContent.replace(/\s+/g, ' ').trim() : '',
				canvas: canvas ? { height: canvas.height, width: canvas.width } : null,
				coordinate_entity_count: fixture.coordinateEntityCount || 0,
				csp_violations: browserEvidence.cspViolations || [],
				data_saver: Boolean(navigator.connection && navigator.connection.saveData),
				drawer: drawer ? { text: drawer.textContent.replace(/\s+/g, ' ').trim(), visible: true } : { text: '', visible: false },
				entity_count: fixture.entityCount || 0,
				hash: window.location.hash,
				map_count: browserEvidence.maps.length,
				map_errors: browserEvidence.maps.flatMap((record) => record.errors || []),
				map_idle_event: Boolean(activeRecord && activeRecord.idle),
				map_load_event: Boolean(activeRecord && activeRecord.loaded),
				map_loaded: mapLoaded,
				maplibre_asset_url: (() => {
					const script = document.querySelector('script[src*="/maplibre-gl/5.18.0/maplibre-gl.js"]');
					return script ? script.src : '';
				})(),
				maplibre_constructor: Boolean(window.maplibregl && typeof window.maplibregl.Map === 'function'),
				maplibre_runtime_version: window.maplibregl && typeof window.maplibregl.version === 'string' ? window.maplibregl.version : null,
				marker_count: document.querySelectorAll('.thp-di-map-marker').length,
				orientation_canvas: Boolean(document.querySelector('.thp-di-orientation-scene')),
				poster_hidden: Boolean(document.querySelector('[data-list-poster]') && document.querySelector('[data-list-poster]').hidden),
				projection: projection && projection.type ? projection.type : (style && style.projection ? style.projection.type : ''),
				reduced_motion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
				scenario: fixture.scenario || '',
				selected_card_count: document.querySelectorAll('[data-entity-card].is-selected').length,
				status: document.querySelector('[data-renderer-status]') ? document.querySelector('[data-renderer-status]').textContent.trim() : '',
				style_layers: style && Array.isArray(style.layers) ? style.layers.map((layer) => layer.id) : [],
				style_loaded: styleLoaded,
				style_sources: style && style.sources ? Object.keys(style.sources) : [],
				terrain,
				visible_attribution_text: document.querySelector('[data-reviewed-attributions]') ? document.querySelector('[data-reviewed-attributions]').textContent.replace(/\s+/g, ' ').trim() : '',
				webgl2,
				window_errors: browserEvidence.windowErrors || [],
				promise_rejections: browserEvidence.promiseRejections || [],
			};
		});

		const requestEvidence = requests.map((request) => {
			const headers = request.headers();
			return {
				method: request.method(),
				range: headers.range || '',
				resource_type: request.resourceType(),
				url: request.url(),
			};
		});
		const httpRequests = requestEvidence.filter((request) => /^https?:/i.test(request.url));
		const thirdPartyRequests = httpRequests.filter((request) => !request.url.startsWith(`${expectedOrigin}/`));
		const pmtilesRequests = httpRequests.filter((request) => request.url.includes('koh-phangan-basemap-20260811.pmtiles'));
		const terrainRequests = httpRequests.filter((request) => request.url.includes('/terrain/20260811/'));
		const satelliteRequests = httpRequests.filter((request) => request.url.includes('koh-phangan-sentinel2-20260326.webp'));
		const brokenAssetRequests = httpRequests.filter((request) => request.url.includes('/__acceptance_missing__/'));
		const restRequests = httpRequests.filter((request) => request.url.includes('/wp-json/thailand-platform/v1/digital-islands/'));
		const expectedCameraAbortions = failedRequests.filter((request) => (
			request.error === 'net::ERR_ABORTED'
			&& request.url.startsWith(`${expectedOrigin}/wp-content/plugins/thailand-platform/assets/digital-islands/`)
			&& (
				(request.url.includes('/terrain/20260811/') && /\.png(?:[?#]|$)/.test(request.url))
				|| (request.url.includes('koh-phangan-basemap-20260811.pmtiles') && /^bytes=\d+-/i.test(request.range))
			)
		));
		const unexpectedFailedRequests = failedRequests.filter((request) => !expectedCameraAbortions.includes(request));
		const requestBudgets = {
			'desktop-3d': 100,
			'desktop-2d': 45,
			'mobile-2d': 40,
			'reduced-motion': 30,
			'data-saver': 20,
			'no-webgl': 20,
			'asset-failure': 25,
		};

		invariant(evidence.scenario === scenario, 'fixture scenario identity changed');
		invariant(evidence.entity_count === 49, 'fixture did not expose the 49 reviewed entities');
		invariant(evidence.coordinate_entity_count === 27, 'fixture did not expose the 27 reviewed coordinates');
		invariant(evidence.maplibre_constructor, 'MapLibre runtime constructor is missing');
		invariant(evidence.maplibre_asset_url.endsWith('/maplibre-gl/5.18.0/maplibre-gl.js'), `unexpected MapLibre asset ${evidence.maplibre_asset_url || '(missing)'}`);
		invariant(thirdPartyRequests.length === 0, `third-party request attempted: ${thirdPartyRequests.map((request) => request.url).join(', ')}`);
		invariant(evidence.csp_violations.length === 0, 'content security policy violation detected');
		invariant(restRequests.length >= 3, 'reviewed REST fixture endpoints were not requested');
		invariant(httpRequests.length <= requestBudgets[scenario], `${scenario} exceeded its ${requestBudgets[scenario]}-request HTTP budget (${httpRequests.length})`);
		const successfulRendererScenario = ['desktop-3d', 'desktop-2d', 'mobile-2d', 'reduced-motion'].includes(scenario);
		if (successfulRendererScenario) {
			invariant(evidence.map_errors.length === 0, `MapLibre emitted errors: ${evidence.map_errors.join(' | ')}`);
			invariant(evidence.window_errors.length === 0, `window errors detected: ${evidence.window_errors.join(' | ')}`);
			invariant(evidence.promise_rejections.length === 0, `unhandled promise rejections detected: ${evidence.promise_rejections.join(' | ')}`);
			invariant(consoleErrors.length === 0, `browser console errors detected: ${consoleErrors.join(' | ')}`);
			invariant(unexpectedFailedRequests.length === 0, `browser requests failed: ${unexpectedFailedRequests.map((request) => `${request.url} (${request.error})`).join(' | ')}`);
			invariant(pmtilesRequests.length >= 1 && pmtilesRequests.every((request) => /^bytes=\d+-/i.test(request.range)), 'every PMTiles request must use an HTTP Range header');
		}

		if (scenario === 'desktop-3d') {
			invariant(evidence.active_renderer === '3d', `expected 3d renderer, received ${evidence.active_renderer}`);
			invariant(evidence.map_count >= 3, 'rapid repeated 3D activation did not construct replacement MapLibre candidates');
			invariant(evidence.webgl2, 'MapLibre canvas does not have a WebGL2 context');
			invariant(evidence.canvas && evidence.canvas.width > 0 && evidence.canvas.height > 0, 'MapLibre canvas has no rendered pixels');
			invariant(evidence.map_load_event, 'MapLibre load lifecycle event was not observed');
			invariant(evidence.projection === 'globe', `expected globe projection, received ${evidence.projection}`);
			invariant(evidence.style_sources.includes('basemap') && evidence.style_sources.includes('satellite') && evidence.style_sources.includes('terrain'), '3D style sources are incomplete');
			invariant(evidence.style_layers.includes('satellite-orientation-20260326'), 'Sentinel orientation layer is missing');
			invariant(evidence.style_layers.includes('terrain-hillshade'), 'terrain hillshade layer is missing');
			invariant(evidence.style_layers.includes('buildings-extruded-reviewed-height'), 'height-only building extrusion layer is missing');
			invariant(evidence.terrain && evidence.terrain.source === 'terrain' && evidence.terrain.exaggeration === 1.28, 'reviewed Terrarium terrain is not active');
			invariant(evidence.visible_attribution_text.includes('Protomaps © OpenStreetMap contributors'), 'visible vector attribution is not rendered');
			invariant(evidence.visible_attribution_text.includes('Mapzen Terrain Tiles') && evidence.visible_attribution_text.includes('Copernicus Sentinel data 2026'), 'visible 3D terrain or Sentinel attribution is not rendered');
			invariant(evidence.visible_attribution_text.includes('Image observed 26.03.2026'), 'dated Sentinel observation label is not rendered');
			invariant(evidence.visible_attribution_text.includes('not parcel, title, planning, or buildability evidence'), 'dated Sentinel orientation caveat is not rendered');
			invariant(satelliteRequests.length >= 1, 'local Sentinel orientation image was not requested');
			invariant(terrainRequests.length >= 1, 'local Terrarium tile was not requested');
			invariant(evidence.marker_count === 27, `expected 27 coordinate markers, received ${evidence.marker_count}`);
			invariant(evidence.drawer.visible && evidence.selected_card_count === 1 && /entity=/.test(evidence.hash), 'map marker interaction did not select an entity and open the drawer');
			invariant(/אי־ודאות מיקומית מתועדת:\s*עד\s*300\s*מטר/.test(evidence.drawer.text), 'selected Ban Thong Sala pin does not expose the reviewed 300m location uncertainty');
		}

		if (scenario === 'desktop-2d' || scenario === 'mobile-2d') {
			invariant(evidence.active_renderer === '2d', `expected 2d renderer, received ${evidence.active_renderer}`);
			invariant(evidence.map_count >= 1 && evidence.webgl2, 'real 2D MapLibre execution was not observed');
			invariant(evidence.projection === 'mercator', `expected mercator projection, received ${evidence.projection}`);
			invariant(evidence.style_sources.length === 1 && evidence.style_sources[0] === 'basemap', '2D mode loaded non-vector sources');
			invariant(!evidence.terrain, '2D mode unexpectedly enabled terrain');
			invariant(evidence.visible_attribution_text.includes('Protomaps © OpenStreetMap contributors'), 'visible 2D vector attribution is not rendered');
			invariant(!evidence.maplibre_attribution_text.includes('Mapzen Terrain Tiles') && !evidence.maplibre_attribution_text.includes('Copernicus Sentinel data 2026'), '2D engine rendered 3D-only attribution');
			invariant(satelliteRequests.length === 0 && terrainRequests.length === 0, '2D mode requested 3D raster assets');
			invariant(evidence.marker_count === 27, `expected 27 coordinate markers, received ${evidence.marker_count}`);
			invariant(evidence.drawer.visible && evidence.selected_card_count === 1, '2D marker interaction did not open reviewed entity details');
		}

		if (scenario === 'mobile-2d') {
			const viewport = page.viewportSize();
			invariant(viewport && viewport.width <= 600, `mobile viewport was not applied (${viewport ? viewport.width : 'unknown'}px)`);
		}

		if (scenario === 'reduced-motion') {
			invariant(evidence.reduced_motion, 'browser reduced-motion preference was not active');
			invariant(evidence.active_renderer === '2d', `reduced motion did not default to 2d (${evidence.active_renderer})`);
			invariant(evidence.projection === 'mercator', 'reduced-motion 2D fallback is not mercator');
			invariant(satelliteRequests.length === 0 && terrainRequests.length === 0, 'reduced-motion fallback requested 3D raster assets');
		}

		if (scenario === 'data-saver') {
			invariant(evidence.data_saver, 'saveData was not active');
			invariant(evidence.active_renderer === 'list', `data saver did not select list mode (${evidence.active_renderer})`);
			invariant(evidence.map_count === 0 && evidence.marker_count === 0, 'data saver constructed a graphical map');
			invariant(!evidence.poster_hidden, 'data saver hid the accessible poster/list fallback');
			invariant(pmtilesRequests.length === 0 && satelliteRequests.length === 0 && terrainRequests.length === 0, 'data saver requested map assets');
		}

		if (scenario === 'no-webgl') {
			invariant(evidence.active_renderer === 'preview' || evidence.active_renderer === 'list', `no-WebGL state did not fail closed (${evidence.active_renderer})`);
			invariant(evidence.map_count === 0 && evidence.marker_count === 0, 'no-WebGL state constructed MapLibre');
			invariant(pmtilesRequests.length === 0 && satelliteRequests.length === 0 && terrainRequests.length === 0, 'no-WebGL state requested map assets');
			invariant(evidence.active_renderer !== 'preview' || evidence.orientation_canvas, 'orientation fallback canvas is missing');
		}

		if (scenario === 'asset-failure') {
			invariant(evidence.active_renderer === 'preview' || evidence.active_renderer === 'list', `asset failure did not fail closed (${evidence.active_renderer})`);
			invariant(brokenAssetRequests.length >= 1, 'intentional same-origin asset failure was not exercised');
			invariant(evidence.active_renderer !== '3d' && evidence.active_renderer !== '2d', 'failed source remained active as a map');
			invariant(evidence.active_renderer !== 'preview' || evidence.orientation_canvas, 'asset failure orientation fallback is missing');
		}

		return {
			assertions: {
				asset_failure_fail_closed: scenario !== 'asset-failure' || ['preview', 'list'].includes(evidence.active_renderer),
				data_saver_list_only: scenario !== 'data-saver' || evidence.active_renderer === 'list',
				entity_interaction: !['desktop-3d', 'desktop-2d', 'mobile-2d'].includes(scenario) || evidence.drawer.visible,
				no_third_party_requests: thirdPartyRequests.length === 0,
				real_maplibre_execution: !['desktop-3d', 'desktop-2d', 'mobile-2d', 'reduced-motion'].includes(scenario) || evidence.map_count >= 1,
			},
			browser: {
				user_agent: await page.evaluate(() => navigator.userAgent),
				viewport: page.viewportSize(),
			},
			console_errors: consoleErrors,
			evidence,
			network: {
				camera_transition_abortions: expectedCameraAbortions,
				broken_asset_requests: brokenAssetRequests.length,
				unexpected_failed_requests: unexpectedFailedRequests,
				pmtiles_requests: pmtilesRequests.length,
				request_budget: requestBudgets[scenario],
				pmtiles_range_requests: pmtilesRequests.filter((request) => request.range).length,
				request_count: httpRequests.length,
				rest_requests: restRequests.length,
				satellite_requests: satelliteRequests.length,
				terrain_requests: terrainRequests.length,
				third_party_requests: thirdPartyRequests,
			},
			passed: true,
			scenario,
		};
	} finally {
		page.off('request', onRequest);
		page.off('requestfailed', onRequestFailed);
		page.off('console', onConsole);
	}
}
