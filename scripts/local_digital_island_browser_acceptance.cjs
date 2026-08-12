#!/usr/bin/env node

'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');

const ROOT = path.resolve(__dirname, '..');
const WORK_ROOT = path.dirname(ROOT);
const SERVER_PATH = path.join(ROOT, 'tests', 'fixtures', 'digital-islands-browser-server.cjs');
const PROBE_PATH = path.join(ROOT, 'tests', 'fixtures', 'digital-islands-browser-probe.js');
const DEFAULT_OUTPUT_PARENT = path.join(WORK_ROOT, 'output');
const PLAYWRIGHT_CLI_PACKAGE = '@playwright/cli@0.1.18';
const PLAYWRIGHT_COMMAND_TIMEOUT_MS = 120000;
const SCENARIOS = [
	{ id: 'desktop-3d', hash: '', mobile: false },
	{ id: 'desktop-2d', hash: '#view=2d', mobile: false },
	{ id: 'mobile-2d', hash: '#view=2d', mobile: true },
	{ id: 'reduced-motion', hash: '', mobile: false },
	{ id: 'data-saver', hash: '', mobile: false },
	{ id: 'no-webgl', hash: '', mobile: false },
	{ id: 'asset-failure', hash: '', mobile: false },
];

function invariant(condition, message) {
	if (!condition) throw new Error(message);
}

function parseArguments(argv) {
	const options = { headed: false, output: '' };
	for (let index = 0; index < argv.length; index += 1) {
		const argument = argv[index];
		if (argument === '--headed') options.headed = true;
		else if (argument === '--output') options.output = path.resolve(argv[++index]);
		else throw new Error(`Unknown argument: ${argument}`);
	}
	return options;
}

function timestamp() {
	return new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z').replace('T', '-');
}

function sha256(filename) {
	return crypto.createHash('sha256').update(fs.readFileSync(filename)).digest('hex');
}

function fileReceipt(filename) {
	const stats = fs.statSync(filename);
	return {
		bytes: stats.size,
		path: filename.replace(/\\/g, '/'),
		sha256: sha256(filename),
	};
}

function npxInvocation() {
	if (process.platform !== 'win32') return { command: 'npx', prefix: [] };
	const cli = path.join(path.dirname(process.execPath), 'node_modules', 'npm', 'bin', 'npx-cli.js');
	invariant(fs.existsSync(cli), `Bundled npx CLI was not found beside Node.js: ${cli}`);
	return { command: process.execPath, prefix: [cli] };
}

function run(command, args, options = {}) {
	return new Promise((resolve, reject) => {
		const child = spawn(command, args, {
			cwd: options.cwd || ROOT,
			env: { ...process.env, ...(options.env || {}) },
			stdio: ['ignore', 'pipe', 'pipe'],
			windowsHide: true,
		});
		let stdout = '';
		let stderr = '';
		let settled = false;
		let timedOut = false;
		child.stdout.setEncoding('utf8');
		child.stderr.setEncoding('utf8');
		child.stdout.on('data', (chunk) => { stdout += chunk; });
		child.stderr.on('data', (chunk) => { stderr += chunk; });
		const timeoutMs = options.timeoutMs || PLAYWRIGHT_COMMAND_TIMEOUT_MS;
		const timeoutId = setTimeout(() => {
			if (settled) return;
			timedOut = true;
			child.kill();
		}, timeoutMs);
		child.on('error', (error) => {
			if (settled) return;
			settled = true;
			clearTimeout(timeoutId);
			reject(error);
		});
		child.on('close', (code) => {
			if (settled) return;
			settled = true;
			clearTimeout(timeoutId);
			if (timedOut) reject(new Error(`Command timed out after ${timeoutMs}ms: ${command} ${args.join(' ')}`));
			else if (code === 0 || options.allowFailure) resolve({ code, stderr, stdout });
			else reject(new Error(`Command failed (${code}): ${command} ${args.join(' ')}\n${stderr || stdout}`));
		});
	});
}

function startFixtureServer(logDirectory) {
	return new Promise((resolve, reject) => {
		const child = spawn(process.execPath, [SERVER_PATH, '--port', '0'], {
			cwd: ROOT,
			stdio: ['ignore', 'pipe', 'pipe'],
			windowsHide: true,
		});
		let stdout = '';
		let stderr = '';
		let settled = false;
		const timeoutId = setTimeout(() => {
			if (settled) return;
			settled = true;
			child.kill();
			reject(new Error(`Fixture server did not become ready\n${stderr || stdout}`));
		}, 10000);
		child.stdout.setEncoding('utf8');
		child.stderr.setEncoding('utf8');
		child.stdout.on('data', (chunk) => {
			stdout += chunk;
			const match = /THP_DI_FIXTURE_READY (\{[^\r\n]+\})/.exec(stdout);
			if (!match || settled) return;
			settled = true;
			clearTimeout(timeoutId);
			const address = JSON.parse(match[1]);
			resolve({
				baseUrl: `http://${address.host}:${address.port}`,
				child,
				flushLogs() {
					fs.writeFileSync(path.join(logDirectory, 'fixture-server.stdout.log'), stdout, 'utf8');
					fs.writeFileSync(path.join(logDirectory, 'fixture-server.stderr.log'), stderr, 'utf8');
				},
			});
		});
		child.stderr.on('data', (chunk) => { stderr += chunk; });
		child.on('error', (error) => {
			if (settled) return;
			settled = true;
			clearTimeout(timeoutId);
			reject(error);
		});
		child.on('exit', (code) => {
			if (settled) return;
			settled = true;
			clearTimeout(timeoutId);
			reject(new Error(`Fixture server exited before readiness (${code})\n${stderr || stdout}`));
		});
	});
}

async function main() {
	const options = parseArguments(process.argv.slice(2));
	const runDirectory = options.output || path.join(DEFAULT_OUTPUT_PARENT, `koh-phangan-maplibre-0.5.2-${timestamp()}`);
	fs.mkdirSync(runDirectory, { recursive: true });
	invariant(fs.existsSync(SERVER_PATH), 'Browser fixture server is missing');
	invariant(fs.existsSync(PROBE_PATH), 'Playwright CLI probe is missing');
	const npx = npxInvocation();
	const cliPrefix = ['--yes', '--package', PLAYWRIGHT_CLI_PACKAGE, 'playwright-cli'];
	// @playwright/cli 0.1.18 prints its version and then exits non-zero under Node 25
	// on Windows because of an upstream libuv shutdown assertion. The operational
	// browser commands do not share that failure, so retain the printed receipt.
	const versionResult = await run(npx.command, [...npx.prefix, ...cliPrefix, '--version'], { cwd: runDirectory, allowFailure: true });
	const cliVersion = versionResult.stdout.trim() || 'npx:@playwright/cli';
	invariant(cliVersion === '0.1.18', `Expected Playwright CLI 0.1.18, received ${cliVersion}`);
	const server = await startFixtureServer(runDirectory);
	const sessions = new Set();
	const results = [];
	const startedAt = new Date().toISOString();

	const cli = async (session, args, extra = {}) => run(
		npx.command,
		[...npx.prefix, ...cliPrefix, '--session', session, ...args],
		{ cwd: runDirectory, allowFailure: extra.allowFailure === true, timeoutMs: PLAYWRIGHT_COMMAND_TIMEOUT_MS },
	);

	try {
		const health = await fetch(`${server.baseUrl}/__health`);
		invariant(health.ok, `Fixture health endpoint returned ${health.status}`);
		const healthContract = await health.json();
		invariant(healthContract.status === 'ready', 'Fixture health contract is not ready');
		invariant(healthContract.playwright_cli_package === PLAYWRIGHT_CLI_PACKAGE, 'Fixture Playwright package pin changed');

		for (const definition of SCENARIOS) {
			const session = `thp-di-051-${process.pid}-${definition.id}`;
			sessions.add(session);
			const url = `${server.baseUrl}/fixture?scenario=${encodeURIComponent(definition.id)}${definition.hash}`;
			const openArgs = ['open', url];
			if (definition.mobile) openArgs.push('--mobile');
			if (options.headed) openArgs.push('--headed');
			await cli(session, openArgs);
			await cli(session, ['resize', ...(definition.mobile ? ['412', '915'] : ['1440', '1000'])]);
			await cli(session, ['snapshot']);
			const probe = await cli(session, ['--raw', 'run-code', '--filename', PROBE_PATH]);
			let result;
			try {
				result = JSON.parse(probe.stdout.trim());
			} catch (error) {
				throw new Error(`Playwright CLI probe returned invalid JSON for ${definition.id}: ${probe.stdout}\n${probe.stderr}`);
			}
			invariant(result && result.passed === true && result.scenario === definition.id, `${definition.id} did not return a passing receipt`);
			await cli(session, ['snapshot']);
			const screenshotPath = path.join(runDirectory, `${definition.id}.png`);
			await cli(session, ['screenshot', '[data-acceptance-capture]', '--filename', screenshotPath]);
			const consoleResult = await cli(session, ['console', 'warning'], { allowFailure: true });
			const consolePath = path.join(runDirectory, `${definition.id}.console.log`);
			fs.writeFileSync(consolePath, `${consoleResult.stdout}${consoleResult.stderr}`, 'utf8');
			result.artifacts = {
				console: fileReceipt(consolePath),
				screenshot: fileReceipt(screenshotPath),
			};
			results.push(result);
			await cli(session, ['close'], { allowFailure: true });
			sessions.delete(session);
			process.stdout.write(`PASS ${definition.id}: ${result.evidence.active_renderer}\n`);
		}

		const report = {
			assertions: {
				all_scenarios_passed: results.length === SCENARIOS.length && results.every((result) => result.passed === true),
				asset_failure_fail_closed: results.find((result) => result.scenario === 'asset-failure').assertions.asset_failure_fail_closed,
				data_saver_list_only: results.find((result) => result.scenario === 'data-saver').assertions.data_saver_list_only,
				no_third_party_requests: results.every((result) => result.assertions.no_third_party_requests),
				real_maplibre_execution: results.filter((result) => ['desktop-3d', 'desktop-2d', 'mobile-2d', 'reduced-motion'].includes(result.scenario)).every((result) => (
					result.assertions.real_maplibre_execution
					&& result.network.pmtiles_requests === result.network.pmtiles_range_requests
					&& result.network.request_count <= result.network.request_budget
				)),
			},
			contract_id: 'thp-digital-islands-maplibre-browser-v1',
			finished_at: new Date().toISOString(),
			fixture: healthContract,
			playwright_cli: {
				package: PLAYWRIGHT_CLI_PACKAGE,
				version: cliVersion,
			},
			reviewed_assets: {
				client: fileReceipt(path.join(ROOT, 'assets', 'digital-islands', 'digital-islands.js')),
				maplibre: fileReceipt(path.join(ROOT, 'assets', 'digital-islands', 'vendor', 'maplibre-gl', '5.18.0', 'maplibre-gl.js')),
				pmtiles: fileReceipt(path.join(ROOT, 'assets', 'digital-islands', 'vendor', 'pmtiles', '4.5.0', 'pmtiles.js')),
				satellite: fileReceipt(path.join(ROOT, 'assets', 'digital-islands', 'imagery', 'koh-phangan-sentinel2-20260326.webp')),
				vector: fileReceipt(path.join(ROOT, 'assets', 'digital-islands', 'data', 'koh-phangan-basemap-20260811.pmtiles')),
			},
			release: '0.5.2',
			results,
			started_at: startedAt,
		};
		invariant(Object.values(report.assertions).every(Boolean), 'Aggregate browser acceptance assertion failed');
		const reportPath = path.join(runDirectory, 'acceptance-report.json');
		fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
		process.stdout.write(`PASS: Digital Islands real-browser acceptance (${results.length} scenarios).\n`);
		process.stdout.write(`Report: ${reportPath}\n`);
	} finally {
		for (const session of sessions) {
			await cli(session, ['close'], { allowFailure: true }).catch(() => {});
		}
		server.flushLogs();
		server.child.kill();
	}
}

main().catch((error) => {
	process.stderr.write(`FAIL: ${error.stack || error.message}\n`);
	process.exitCode = 1;
});
