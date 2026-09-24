import test from 'node:test';
import assert from 'node:assert/strict';
import { createAgentConsoleSync } from '../../resources/js/agent-console-sync.js';

function harness(state) {
    const intervals = [];
    let reloads = 0;

    return {
        intervals,
        reloads: () => reloads,
        dependencies: {
            fetch: async () => ({
                ok: true,
                status: 200,
                json: async () => state,
            }),
            setInterval: (callback, delay) => {
                intervals.push({ callback, delay });

                return intervals.length;
            },
            clearInterval: () => {},
            visibility: () => 'visible',
            reload: () => { reloads += 1; },
        },
    };
}

test('applies a changed agent state without reloading the registered softphone page', async () => {
    const context = harness({
        signature: 'new-signature',
        agent_status: 'in_call',
        status_elapsed_seconds: 6,
        call_active: true,
        call_status: 'in_progress',
        call: {
            id: 42,
            phone: '31999990001',
            status: 'in_progress',
            campaign_name: 'Cobranca',
        },
    });
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'old-signature',
    }, context.dependencies);

    sync.start();
    await sync.check();

    assert.equal(context.intervals[0].delay, 5000);
    assert.equal(context.reloads(), 0);
    assert.equal(sync.signature, 'new-signature');
    assert.equal(sync.agentStatus, 'in_call');
    assert.equal(sync.statusElapsedSeconds, 6);
    assert.equal(sync.call.phone, '31999990001');
});

test('updates live state even while the disposition form is dirty', async () => {
    const context = harness({
        signature: 'new-signature',
        agent_status: 'wrap_up',
        status_elapsed_seconds: 3,
        call_active: false,
        call_status: 'wrap_up',
        call: { id: 42, status: 'wrap_up' },
    });
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'old-signature',
    }, context.dependencies);

    await sync.check();

    assert.equal(sync.agentStatus, 'wrap_up');
    assert.equal(sync.call.status, 'wrap_up');
    assert.equal(context.reloads(), 0);
});

test('does nothing while the state signature is unchanged', async () => {
    const context = harness({
        signature: 'same-signature',
        agent_status: 'available',
        call_active: false,
        call_status: null,
        call: null,
    });
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'same-signature',
    }, context.dependencies);

    await sync.check();

    assert.equal(context.reloads(), 0);
    assert.equal(sync.agentStatus, 'available');
    assert.equal(sync.call, null);
});

test('uses the realtime event to fetch and apply state without a navigation', async () => {
    let listener;
    const context = harness({
        signature: 'call-started',
        agent_status: 'dialing',
        status_elapsed_seconds: 1,
        call_active: true,
        call_status: 'dialing',
        call: { id: 9, phone: '31999990002', status: 'dialing' },
    });
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'before-call',
        subscribe: (callback) => {
            listener = callback;
            return () => { listener = null; };
        },
    }, context.dependencies);

    sync.start();
    listener();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(sync.agentStatus, 'dialing');
    assert.equal(sync.call.id, 9);
    assert.equal(context.reloads(), 0);

    sync.destroy();
    assert.equal(listener, null);
});

test('changes agent availability through JSON without navigating away from the softphone', async () => {
    let request;
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        statusUrl: '/tenant/agent/status',
        csrfToken: 'csrf-token',
        signature: 'before-status-change',
    }, {
        fetch: async (url, options) => {
            request = { url, options };
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    signature: 'available-signature',
                    agent_status: 'available',
                    status_elapsed_seconds: 0,
                    call_active: false,
                    call_status: null,
                    call: null,
                }),
            };
        },
        setInterval: () => 1,
        clearInterval: () => {},
        visibility: () => 'visible',
    });

    await sync.setAgentStatus('available');

    assert.equal(request.url, '/tenant/agent/status');
    assert.equal(request.options.method, 'PATCH');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(sync.agentStatus, 'available');
});

test('marks the current availability action as unavailable while keeping pause changes explicit', () => {
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'paused-signature',
        initialState: {
            agent_status: 'paused',
            status_elapsed_seconds: 4,
            call_active: false,
            call_status: null,
            call: null,
        },
    }, {
        fetch: async () => { throw new Error('not needed'); },
        setInterval: () => 1,
        clearInterval: () => {},
        visibility: () => 'visible',
        toast: () => {},
    });

    assert.equal(sync.isCurrentStatus('paused'), true);
    assert.equal(sync.isCurrentStatus('available'), false);
    assert.equal(sync.pauseActionLabel(), 'Alterar pausa');
});

test('offline elapsed time stays at zero across polling and clock ticks', async () => {
    const context = harness({
        agent_status: 'offline',
        status_elapsed_seconds: 92000,
        call_active: false,
        call_status: null,
        call: null,
    });
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        initialState: {
            agent_status: 'offline',
            status_elapsed_seconds: 86000,
        },
    }, context.dependencies);

    sync.start();
    assert.equal(sync.statusElapsedSeconds, 0);
    context.intervals.find(({ delay }) => delay === 1000).callback();
    assert.equal(sync.statusElapsedSeconds, 0);
    await sync.check();
    assert.equal(sync.statusElapsedSeconds, 0);
});
