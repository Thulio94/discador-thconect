import test from 'node:test';
import assert from 'node:assert/strict';
import { createAgentSoftphone } from '../../resources/js/agent-softphone.js';

function harness({ acceptIncoming = true, acceptIncomingNow = null, getUserMedia, setTimeout, clearTimeout, raw = (value) => value } = {}) {
    const handlers = {};
    const sessionHandlers = {};
    let answered = false;
    let terminated = false;
    let failureCallback;
    const timerDelays = [];
    let offlineUpdates = 0;
    let reconciliations = 0;
    let uaStopReceiver = null;
    const reportedPresence = [];
    const ua = {
        on: (event, callback) => { handlers[event] = callback; },
        start: () => handlers.registered?.(),
        stop: function () { uaStopReceiver = this; },
    };
    class WebSocketInterface {}
    class UA { constructor() { return ua; } }
    const dependencies = {
        fetch: async () => ({ ok: true, json: async () => ({ websocket_url: 'wss://example.test/sip-ws', uri: 'sip:t1-e1301@example.test', endpoint: 't1-e1301', password: 'A'.repeat(48) }) }),
        getUserMedia: getUserMedia ?? (async () => ({ getTracks: () => [{ stop: () => {} }] })),
        loadJsSIP: async () => ({ WebSocketInterface, UA }),
        attachRemoteStream: async () => {},
        toast: () => {},
        setTimeout: setTimeout ?? ((callback, delay) => {
            timerDelays.push(delay);
            failureCallback = callback;
            return 1;
        }),
        clearTimeout: clearTimeout ?? (() => {}),
        setInterval: () => 1,
        clearInterval: () => {},
        raw,
        markOffline: async () => { offlineUpdates += 1; },
        reconcile: () => { reconciliations += 1; },
        reportPresence: async (_url, _csrf, _connectionId, registered) => { reportedPresence.push(registered); },
    };
    const softphone = createAgentSoftphone({
        sessionUrl: '/session',
        presenceUrl: '/presence',
        csrfToken: 'token',
        currentStatus: 'available',
        acceptIncoming,
        acceptIncomingNow,
    }, dependencies);
    const session = {
        on: (event, callback) => { sessionHandlers[event] = callback; },
        answer: () => { answered = true; },
        terminate: () => { terminated = true; },
    };
    return {
        softphone,
        handlers,
        session,
        answered: () => answered,
        terminated: () => terminated,
        triggerFailure: () => failureCallback?.(),
        timerDelays,
        offlineUpdates: () => offlineUpdates,
        reconciliations: () => reconciliations,
        ua,
        uaStopReceiver: () => uaStopReceiver,
        reportedPresence: () => [...reportedPresence],
    };
}

test('registers the agent endpoint only after microphone permission', async () => {
    const context = harness();
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    await context.softphone.start();
    assert.equal(context.softphone.registered, true);
    assert.equal(context.softphone.state, 'registered');
    assert.deepEqual(context.reportedPresence(), [true]);
});

test('removes live SIP presence immediately when the WebSocket disconnects', async () => {
    const context = harness();
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    await context.softphone.start();

    context.handlers.disconnected();
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(context.reportedPresence(), [true, false]);
});

test('stops reporting a stale live SIP registration when the agent leaves the page', async () => {
    const context = harness();
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    await context.softphone.start();
    context.softphone.stop();
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(context.reportedPresence(), [true, false]);
});

test('answers remote sessions while the operational status accepts calls', async () => {
    const context = harness();
    context.softphone.handleSession({ originator: 'remote', session: context.session });
    assert.equal(context.answered(), true);
    assert.equal(context.terminated(), false);
});

test('rejects an incoming session while the agent is offline', () => {
    const context = harness({ acceptIncoming: false });
    context.softphone.handleSession({ originator: 'remote', session: context.session });
    assert.equal(context.answered(), false);
    assert.equal(context.terminated(), true);
});

test('uses the live agent status when deciding whether to accept a delivered call', () => {
    const context = harness({ acceptIncoming: false, acceptIncomingNow: () => true });
    context.softphone.handleSession({ originator: 'remote', session: context.session });

    assert.equal(context.answered(), true);
    assert.equal(context.terminated(), false);
});

test('does not reload the page when a disconnected ramal is marked offline', async () => {
    const context = harness();
    context.softphone.scheduleFailure();
    context.triggerFailure();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(context.softphone.state, 'failed');
    assert.equal(context.offlineUpdates(), 1);
    assert.equal(context.reconciliations(), 1);
});

test('allows JsSIP transport recovery before declaring the ramal disconnected', async () => {
    const context = harness();
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    await context.softphone.start();

    context.handlers.disconnected();

    assert.equal(context.softphone.state, 'reconnecting');
    assert.ok(context.timerDelays.at(-1) >= 40_000);
});

test('allows retry after microphone permission stalls without registering a stale attempt', async () => {
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    const timers = [];
    const pendingPermissions = [];
    const clearedTimers = [];
    let stoppedTracks = 0;
    const context = harness({
        getUserMedia: () => new Promise((resolve) => pendingPermissions.push(resolve)),
        setTimeout: (callback) => {
            timers.push(callback);
            return timers.length;
        },
        clearTimeout: (timer) => clearedTimers.push(timer),
    });

    const firstAttempt = context.softphone.start();
    timers[0]();
    assert.equal(context.softphone.state, 'microphone-pending');

    const retry = context.softphone.retry();
    pendingPermissions[0]({ getTracks: () => [{ stop: () => { stoppedTracks += 1; } }] });
    await firstAttempt;
    assert.equal(context.softphone.registered, false);
    assert.equal(stoppedTracks, 1);
    assert.equal(clearedTimers.includes(2), false);

    pendingPermissions[1]({ getTracks: () => [{ stop: () => { stoppedTracks += 1; } }] });
    await retry;
    assert.equal(context.softphone.state, 'registered');
    assert.equal(stoppedTracks, 2);
    assert.equal(clearedTimers.includes(2), true);
});

test('does not register when the panel closes during microphone permission', async () => {
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    let grantPermission;
    const context = harness({
        getUserMedia: () => new Promise((resolve) => { grantPermission = resolve; }),
    });

    const start = context.softphone.start();
    context.softphone.stop();
    grantPermission({ getTracks: () => [{ stop: () => {} }] });
    await start;

    assert.equal(context.softphone.registered, false);
    assert.equal(context.softphone.state, 'offline');
});

test('keeps the JsSIP user agent outside Alpine reactivity before stopping it', async () => {
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    const rawValues = [];
    const context = harness({ raw: (value) => { rawValues.push(value); return value; } });

    await context.softphone.start();
    context.softphone.stop();

    assert.deepEqual(rawValues, [context.ua]);
    assert.equal(context.uaStopReceiver(), context.ua);
});
