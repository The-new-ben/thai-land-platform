'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '..', 'prototype', 'app.js'), 'utf8');
const startMarker = '  /* Keep the existing Tawk chat compact while preserving its current callback. */';
const endMarker = '  /* Back to top */';
const start = source.indexOf(startMarker);
const end = source.indexOf(endMarker, start);

assert.notEqual(start, -1, 'Tawk state-machine start marker is missing.');
assert.notEqual(end, -1, 'Tawk state-machine end marker is missing.');

const tawkBlock = source.slice(start, end);

const createHarness = (api) => {
  let nextTimerId = 1;
  const timers = new Map();
  const listeners = new Map();
  const window = {
    Tawk_API: api,
    setTimeout(callback, delay) {
      const id = nextTimerId;
      nextTimerId += 1;
      timers.set(id, { callback, delay });
      return id;
    },
    clearTimeout(id) {
      timers.delete(id);
    },
    addEventListener(name, callback) {
      listeners.set(name, callback);
    }
  };

  new Function('window', tawkBlock)(window);

  return {
    window,
    pendingTimers: () => timers.size,
    fire(name, event = {}) {
      assert.equal(typeof listeners.get(name), 'function', `Missing ${name} listener.`);
      listeners.get(name)(event);
    },
    runNextTimer() {
      const first = [...timers.entries()].sort(([left], [right]) => left - right)[0];
      assert.ok(first, 'Expected a pending timer.');
      timers.delete(first[0]);
      first[1].callback();
      return first[1].delay;
    }
  };
};

const createReadyApi = () => {
  const state = {
    engaged: false,
    ongoing: false,
    minimized: false,
    minimizeCalls: 0
  };
  const api = {
    isVisitorEngaged: () => state.engaged,
    isChatOngoing: () => state.ongoing,
    minimize() {
      state.minimizeCalls += 1;
      state.minimized = true;
    },
    isChatMinimized: () => state.minimized
  };
  return { api, state };
};

{
  const { api, state } = createReadyApi();
  const harness = createHarness(api);
  assert.equal(state.minimizeCalls, 1, 'Initial ready chat was not minimized exactly once.');
  assert.equal(harness.pendingTimers(), 0, 'Settled chat left a retry timer behind.');
}

{
  const { api, state } = createReadyApi();
  state.engaged = true;
  const harness = createHarness(api);
  assert.equal(state.minimizeCalls, 0, 'Engaged visitor chat was minimized.');
  assert.equal(harness.pendingTimers(), 0, 'Engaged visitor chat left a retry timer behind.');
}

{
  const { api, state } = createReadyApi();
  state.ongoing = true;
  createHarness(api);
  assert.equal(state.minimizeCalls, 0, 'Ongoing visitor chat was minimized.');
}

{
  let engaged = false;
  let minimizeCalls = 0;
  const api = {
    isVisitorEngaged: () => engaged,
    isChatOngoing: () => false,
    minimize: () => { minimizeCalls += 1; },
    isChatMinimized: () => false
  };
  const harness = createHarness(api);
  assert.equal(minimizeCalls, 1, 'Retry scenario did not make its initial minimize attempt.');
  assert.equal(harness.pendingTimers(), 1, 'Retry scenario did not schedule a retry.');
  engaged = true;
  harness.runNextTimer();
  assert.equal(minimizeCalls, 1, 'Retry minimized chat after the visitor became engaged.');
  assert.equal(harness.pendingTimers(), 0, 'Preserved visitor chat kept retrying.');
}

{
  const harness = createHarness(undefined);
  assert.equal(harness.pendingTimers(), 1, 'Late Tawk readiness did not schedule a retry.');
  let minimizeCalls = 0;
  let minimized = false;
  Object.assign(harness.window.Tawk_API, {
    isVisitorEngaged: () => false,
    isChatOngoing: () => false,
    minimize() {
      minimizeCalls += 1;
      minimized = true;
    },
    isChatMinimized: () => minimized
  });
  harness.runNextTimer();
  assert.equal(minimizeCalls, 1, 'Late-ready Tawk API was not minimized.');
  assert.equal(harness.pendingTimers(), 0, 'Late-ready Tawk API did not settle.');
}

{
  const events = [];
  const { api, state } = createReadyApi();
  const callbackContext = { source: 'existing-callback' };
  api.onChatMessageAgent = function (message) {
    assert.equal(this, callbackContext, 'Existing agent callback context changed.');
    assert.equal(message, 'hello', 'Existing agent callback arguments changed.');
    events.push('previous');
  };
  api.minimize = () => {
    state.minimizeCalls += 1;
    state.minimized = true;
    events.push('minimize');
  };
  const harness = createHarness(api);
  events.length = 0;
  state.minimizeCalls = 0;
  state.minimized = false;
  api.onChatMessageAgent.call(callbackContext, 'hello');
  assert.deepEqual(events, ['previous'], 'Agent callback did not run before deferred settling.');
  harness.runNextTimer();
  assert.deepEqual(events, ['previous', 'minimize'], 'Greeting settle callback order changed.');
}

{
  const { api, state } = createReadyApi();
  const originalMaximized = () => {};
  let visitorCallbackCalls = 0;
  api.onChatMaximized = originalMaximized;
  api.onChatMessageVisitor = () => { visitorCallbackCalls += 1; };
  const harness = createHarness(api);
  state.minimizeCalls = 0;
  state.minimized = false;
  api.onChatMessageVisitor('visitor message');
  api.onChatMessageAgent('agent reply');
  harness.runNextTimer();
  assert.equal(visitorCallbackCalls, 1, 'Existing visitor callback was not preserved.');
  assert.equal(state.minimizeCalls, 0, 'Visitor-initiated conversation was minimized.');
  assert.equal(api.onChatMaximized, originalMaximized, 'Visitor maximization callback was intercepted.');
}

{
  const { api, state } = createReadyApi();
  const harness = createHarness(api);
  state.minimizeCalls = 0;
  state.minimized = false;
  api.onChatMessageAgent('first');
  api.onChatMessageSystem('second');
  api.onChatMessageAgent('third');
  assert.equal(harness.pendingTimers(), 1, 'Greeting debounce created more than one timer.');
  harness.runNextTimer();
  assert.equal(state.minimizeCalls, 1, 'Debounced greetings did not settle exactly once.');
}

{
  const { api, state } = createReadyApi();
  const harness = createHarness(api);
  const initialCalls = state.minimizeCalls;
  harness.fire('pagehide');
  harness.fire('pageshow', { persisted: true });
  assert.equal(state.minimizeCalls, initialCalls, 'Idle BFCache restoration minimized chat again.');
}

{
  let engaged = false;
  let minimizeCalls = 0;
  const api = {
    isVisitorEngaged: () => engaged,
    isChatOngoing: () => false,
    minimize: () => { minimizeCalls += 1; },
    isChatMinimized: () => false
  };
  const harness = createHarness(api);
  assert.equal(harness.pendingTimers(), 1, 'Pending BFCache scenario did not schedule a retry.');
  harness.fire('pagehide');
  assert.equal(harness.pendingTimers(), 0, 'Page exit did not clear the retry timer.');
  engaged = true;
  harness.fire('pageshow', { persisted: true });
  assert.equal(minimizeCalls, 1, 'BFCache resume minimized a newly engaged visitor.');
  assert.equal(harness.pendingTimers(), 0, 'BFCache resume retried an engaged visitor.');
}

{
  let minimizeCalls = 0;
  const api = {
    isVisitorEngaged() {
      throw new Error('API unavailable');
    },
    isChatOngoing: () => false,
    minimize: () => { minimizeCalls += 1; },
    isChatMinimized: () => false
  };
  const harness = createHarness(api);
  assert.equal(minimizeCalls, 0, 'Tawk API errors caused an unsafe minimize call.');
  assert.equal(harness.pendingTimers(), 0, 'Tawk API errors caused an unsafe retry loop.');
}

process.stdout.write('PASS: Tawk chat behavior\n');
