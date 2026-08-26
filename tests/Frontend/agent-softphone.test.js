import test from 'node:test';
import assert from 'node:assert/strict';
import { createAgentSoftphone } from '../../resources/js/agent-softphone.js';

function harness({ acceptIncoming = true } = {}) {
    const handlers = {};
    const sessionHandlers = {};
    let answered = false;
    let terminated = false;
    const ua = {
        on: (event, callback) => { handlers[event] = callback; },
        start: () => handlers.registered?.(),
        stop: () => {},
    };
    class WebSocketInterface {}
    class UA { constructor() { return ua; } }
    const dependencies = {
        fetch: async () => ({ ok: true, json: async () => ({ websocket_url: 'wss://example.test/sip-ws', uri: 'sip:t1-e1301@example.test', endpoint: 't1-e1301', password: 'A'.repeat(48) }) }),
        getUserMedia: async () => ({ getTracks: () => [{ stop: () => {} }] }),
        loadJsSIP: async () => ({ WebSocketInterface, UA }),
        attachRemoteStream: async () => {},
        toast: () => {},
        setTimeout: () => 1,
        clearTimeout: () => {},
        setInterval: () => 1,
        clearInterval: () => {},
        markOffline: async () => {},
        reload: () => {},
    };
    const softphone = createAgentSoftphone({ sessionUrl: '/session', csrfToken: 'token', acceptIncoming }, dependencies);
    const session = {
        on: (event, callback) => { sessionHandlers[event] = callback; },
        answer: () => { answered = true; },
        terminate: () => { terminated = true; },
    };
    return { softphone, handlers, session, answered: () => answered, terminated: () => terminated };
}

test('registers the agent endpoint only after microphone permission', async () => {
    const context = harness();
    globalThis.RTCPeerConnection = class {};
    globalThis.WebSocket = class {};
    await context.softphone.start();
    assert.equal(context.softphone.registered, true);
    assert.equal(context.softphone.state, 'registered');
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
