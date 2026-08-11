async (page) => {
	'use strict';

	const invariant = (condition, message) => {
		if (!condition) throw new Error(`Digital Islands live browser acceptance: ${message}`);
	};
	const expectedOrigin = await page.evaluate(() => window.location.origin);
	const requests = [];
	const failedRequests = [];
	const consoleErrors = [];
	const pageErrors = [];
	let phase = 'default';
	const onRequest = (request) => requests.push({ phase, request });
	const onRequestFailed = (request) => failedRequests.push({
		error: request.failure() ? request.failure().errorText : 'request-failed',
		phase,
		range: request.headers().range || '',
		url: request.url(),
	});
	const onConsole = (message) => {
		if (message.type() === 'error') consoleErrors.push({ phase, text: message.text() });
	};
	const onPageError = (error) => pageErrors.push({ phase, text: error.message });
	page.on('request', onRequest);
	page.on('requestfailed', onRequestFailed);
	page.on('console', onConsole);
	page.on('pageerror', onPageError);

	const requestReceipts = (selectedPhase) => requests
		.filter((entry) => entry.phase === selectedPhase)
		.map((entry) => {
			const headers = entry.request.headers();
			return {
				method: entry.request.method(),
				range: headers.range || '',
				resource_type: entry.request.resourceType(),
				url: entry.request.url(),
			};
		});
	const browserState = () => page.evaluate(() => {
		const root = document.querySelector('[data-digital-island-app]');
		const host = document.querySelector('.thp-di-map-canvas-maplibre');
		const canvas = document.querySelector('.maplibregl-canvas');
		let webgl2 = false;
		try { webgl2 = Boolean(canvas && canvas.getContext('webgl2')); } catch (error) { webgl2 = false; }
		const drawer = document.querySelector('.thp-di-detail-drawer:not([hidden])');
		return {
			active_renderer: root ? root.dataset.activeRenderer || '' : '',
			canvas: canvas ? { height: canvas.height, width: canvas.width } : null,
			drawer_visible: Boolean(drawer),
			hash: window.location.hash,
			host_mode: host ? host.dataset.rendererMode || '' : '',
			map_host_present: Boolean(host),
			marker_count: document.querySelectorAll('.thp-di-map-marker').length,
			orientation_canvas: Boolean(document.querySelector('.thp-di-orientation-scene')),
			poster_hidden: Boolean(document.querySelector('[data-list-poster]') && document.querySelector('[data-list-poster]').hidden),
			satellite_observed_time: (() => {
				const time = document.querySelector('.thp-di-attribution time[datetime="2026-03-26T03:55:36.171000Z"]');
				return time ? { datetime: time.dateTime, text: time.textContent.trim() } : null;
			})(),
			selected_card_count: document.querySelectorAll('[data-entity-card].is-selected').length,
			webgl2,
		};
	});

	try {
		await page.reload({ waitUntil: 'domcontentloaded', timeout: 45000 });
		await page.waitForFunction(() => {
			const root = document.querySelector('[data-digital-island-app]');
			return root && root.dataset.enhanced === 'true' && root.dataset.activeRenderer === '3d';
		}, null, { timeout: 45000 });
		await page.waitForTimeout(1600);
		const marker = page.locator('.thp-di-map-marker').first();
		await marker.waitFor({ state: 'visible', timeout: 10000 });
		await marker.click();
		await page.locator('.thp-di-detail-drawer:not([hidden])').waitFor({ state: 'visible', timeout: 5000 });
		await page.waitForTimeout(1000);
		const defaultState = await browserState();
		const defaultRequests = requestReceipts('default');
		const defaultHttp = defaultRequests.filter((request) => /^https?:/i.test(request.url));
		const defaultThirdParty = defaultHttp.filter((request) => !request.url.startsWith(`${expectedOrigin}/`));
		const defaultFailures = failedRequests.filter((request) => request.phase === 'default');
		const expectedCameraAbortions = defaultFailures.filter((request) => (
			request.error === 'net::ERR_ABORTED'
			&& request.url.startsWith(`${expectedOrigin}/wp-content/plugins/`)
			&& request.url.includes('/assets/digital-islands/')
			&& (
				(request.url.includes('/terrain/20260811/') && /\.png(?:[?#]|$)/.test(request.url))
				|| (request.url.includes('koh-phangan-basemap-20260811.pmtiles') && /^bytes=\d+-/i.test(request.range))
			)
		));
		const unexpectedDefaultFailures = defaultFailures.filter((request) => !expectedCameraAbortions.includes(request));
		const defaultConsoleErrors = consoleErrors.filter((entry) => entry.phase === 'default');
		const defaultPageErrors = pageErrors.filter((entry) => entry.phase === 'default');
		const pmtilesRequests = defaultHttp.filter((request) => request.url.includes('koh-phangan-basemap-20260811.pmtiles'));
		const terrainRequests = defaultHttp.filter((request) => request.url.includes('/terrain/20260811/'));
		const satelliteRequests = defaultHttp.filter((request) => request.url.includes('koh-phangan-sentinel2-20260326.webp'));
		const restRequests = defaultHttp.filter((request) => request.url.includes('/wp-json/thailand-platform/v1/digital-islands/'));
		const reviewedScripts = defaultHttp.filter((request) => (
			request.url.includes('/maplibre-gl/5.18.0/maplibre-gl.js')
			|| request.url.includes('/pmtiles/4.5.0/pmtiles.js')
			|| request.url.includes('/assets/digital-islands/digital-islands.js')
		));

		invariant(defaultState.active_renderer === '3d' && defaultState.host_mode === '3d', 'reviewed 3D renderer is not active');
		invariant(defaultState.map_host_present && defaultState.webgl2, 'real MapLibre WebGL2 execution was not observed');
		invariant(defaultState.canvas && defaultState.canvas.width > 0 && defaultState.canvas.height > 0, 'live MapLibre canvas has no rendered pixels');
		invariant(defaultState.marker_count === 27, `expected 27 reviewed coordinate markers, received ${defaultState.marker_count}`);
		invariant(defaultState.drawer_visible && defaultState.selected_card_count === 1 && /entity=/.test(defaultState.hash), 'live entity interaction did not open reviewed details');
		invariant(defaultState.poster_hidden, '3D renderer left the list poster visible');
		invariant(defaultState.satellite_observed_time && defaultState.satellite_observed_time.datetime === '2026-03-26T03:55:36.171000Z' && defaultState.satellite_observed_time.text === '26.03.2026', 'visible Sentinel observation date is missing or changed');
		invariant(/אי־ודאות מיקומית מתועדת:\s*עד\s*300\s*מטר/.test(await page.locator('.thp-di-detail-drawer:not([hidden])').innerText()), 'selected Ban Thong Sala pin does not expose the reviewed 300m location uncertainty');
		invariant(defaultThirdParty.length === 0, `third-party request attempted: ${defaultThirdParty.map((request) => request.url).join(', ')}`);
		invariant(unexpectedDefaultFailures.length === 0, `unexpected live request failure: ${unexpectedDefaultFailures.map((request) => `${request.url} (${request.error})`).join(', ')}`);
		invariant(defaultConsoleErrors.length === 0, `live console errors: ${defaultConsoleErrors.map((entry) => entry.text).join(' | ')}`);
		invariant(defaultPageErrors.length === 0, `live page errors: ${defaultPageErrors.map((entry) => entry.text).join(' | ')}`);
		invariant(defaultHttp.length <= 80, `live 3D exceeded its 80-request HTTP budget (${defaultHttp.length})`);
		invariant(pmtilesRequests.length >= 1 && pmtilesRequests.every((request) => /^bytes=\d+-/i.test(request.range)), 'every live PMTiles request must carry an HTTP Range header');
		invariant(terrainRequests.length >= 1, 'live browser did not request local Terrarium terrain');
		invariant(satelliteRequests.length >= 1, 'live browser did not request local Sentinel orientation imagery');
		invariant(restRequests.length >= 3, 'live browser did not request the public island REST contract');
		invariant(reviewedScripts.length === 3, `expected three reviewed renderer scripts, received ${reviewedScripts.length}`);

		phase = 'fallback';
		const blockedPmtiles = '**/koh-phangan-basemap-20260811.pmtiles*';
		const blockedSatellite = '**/koh-phangan-sentinel2-20260326.webp*';
		await page.route(blockedPmtiles, (route) => route.abort('failed'));
		await page.route(blockedSatellite, (route) => route.abort('failed'));
		await page.reload({ waitUntil: 'domcontentloaded', timeout: 45000 });
		await page.waitForFunction(() => {
			const root = document.querySelector('[data-digital-island-app]');
			return root && root.dataset.enhanced === 'true' && ['preview', 'list'].includes(root.dataset.activeRenderer);
		}, null, { timeout: 45000 });
		await page.waitForTimeout(800);
		const fallbackState = await browserState();
		const fallbackRequests = requestReceipts('fallback');
		const fallbackHttp = fallbackRequests.filter((request) => /^https?:/i.test(request.url));
		const fallbackThirdParty = fallbackHttp.filter((request) => !request.url.startsWith(`${expectedOrigin}/`));
		const blockedFailures = failedRequests.filter((request) => request.phase === 'fallback' && (
			request.url.includes('koh-phangan-basemap-20260811.pmtiles')
			|| request.url.includes('koh-phangan-sentinel2-20260326.webp')
		));
		const blockedPmtilesFailures = blockedFailures.filter((request) => request.url.includes('koh-phangan-basemap-20260811.pmtiles'));
		const blockedSatelliteFailures = blockedFailures.filter((request) => request.url.includes('koh-phangan-sentinel2-20260326.webp'));
		const unexpectedFallbackFailures = failedRequests.filter((request) => request.phase === 'fallback' && !blockedFailures.includes(request) && !(
			request.error === 'net::ERR_ABORTED'
			&& request.url.startsWith(`${expectedOrigin}/wp-content/plugins/`)
			&& request.url.includes('/assets/digital-islands/terrain/20260811/')
			&& /\.png(?:[?#]|$)/.test(request.url)
		));

		invariant(['preview', 'list'].includes(fallbackState.active_renderer), `asset failure did not fail closed (${fallbackState.active_renderer})`);
		invariant(!fallbackState.map_host_present && fallbackState.marker_count === 0, 'failed MapLibre renderer remained mounted');
		invariant(fallbackState.active_renderer !== 'preview' || fallbackState.orientation_canvas, 'orientation fallback canvas is missing');
		invariant(blockedPmtilesFailures.length >= 1 && blockedSatelliteFailures.length >= 1, 'intentional PMTiles and Sentinel failures were not both exercised');
		invariant(fallbackThirdParty.length === 0, 'failure fallback attempted a third-party request');
		invariant(fallbackHttp.length <= 45, `failure fallback exceeded its 45-request HTTP budget (${fallbackHttp.length})`);
		invariant(unexpectedFallbackFailures.length === 0, `failure fallback had unexpected request failures: ${unexpectedFallbackFailures.map((request) => request.url).join(', ')}`);

		return {
			contract_id: 'thp-digital-islands-live-browser-v1',
			default_3d: {
				camera_transition_abortions: expectedCameraAbortions,
				pmtiles_requests: pmtilesRequests.length,
				pmtiles_range_requests: pmtilesRequests.filter((request) => request.range).length,
				request_budget: 80,
				request_count: defaultHttp.length,
				rest_requests: restRequests.length,
				satellite_requests: satelliteRequests.length,
				state: defaultState,
				terrain_requests: terrainRequests.length,
				third_party_requests: defaultThirdParty,
				unexpected_failed_requests: unexpectedDefaultFailures,
			},
			fallback: {
				blocked_asset_failures: blockedFailures,
				blocked_pmtiles_failures: blockedPmtilesFailures.length,
				blocked_satellite_failures: blockedSatelliteFailures.length,
				request_budget: 45,
				request_count: fallbackHttp.length,
				state: fallbackState,
				third_party_requests: fallbackThirdParty,
				unexpected_failed_requests: unexpectedFallbackFailures,
			},
			passed: true,
			playwright_cli_package: '@playwright/cli@0.1.18',
		};
	} finally {
		if (typeof page.unrouteAll === 'function') await page.unrouteAll({ behavior: 'ignoreErrors' }).catch(() => {});
		page.off('request', onRequest);
		page.off('requestfailed', onRequestFailed);
		page.off('console', onConsole);
		page.off('pageerror', onPageError);
	}
}
