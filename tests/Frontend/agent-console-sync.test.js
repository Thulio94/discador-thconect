import test from 'node:test';
import assert from 'node:assert/strict';
import { createAgentConsoleSync } from '../../resources/js/agent-console-sync.js';

function harness(signature, dirty = false) {
    const intervals = [];
    let reloads = 0;

    return {
        intervals,
        reloads: () => reloads,
        dependencies: {
            fetch: async () => ({
                ok: true,
                status: 200,
                json: async () => ({ signature }),
            }),
            setInterval: (callback, delay) => {
                intervals.push({ callback, delay });

                return intervals.length;
            },
            clearInterval: () => {},
            visibility: () => 'visible',
            hasDirtyForm: () => dirty,
            reload: () => { reloads += 1; },
        },
    };
}

test('reloads only when the server signature changes', async () => {
    const context = harness('new-signature');
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'old-signature',
    }, context.dependencies);

    sync.start();
    await sync.check();

    assert.equal(context.intervals[0].delay, 5000);
    assert.equal(context.reloads(), 1);
});

test('preserves a dirty disposition form until the agent confirms refresh', async () => {
    const context = harness('new-signature', true);
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'old-signature',
    }, context.dependencies);

    await sync.check();

    assert.equal(sync.pending, true);
    assert.equal(context.reloads(), 0);
    sync.applyPending();
    assert.equal(context.reloads(), 1);
});

test('does nothing while the state signature is unchanged', async () => {
    const context = harness('same-signature');
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'same-signature',
    }, context.dependencies);

    await sync.check();

    assert.equal(context.reloads(), 0);
    assert.equal(sync.pending, false);
});

test('defers refreshing the page while a server call still needs the WebRTC ramal', async () => {
    let state = { signature: 'call-started', call_active: true };
    let reloads = 0;
    const sync = createAgentConsoleSync({
        url: '/tenant/agent/state',
        signature: 'before-call',
        canReload: () => true,
    }, {
        fetch: async () => ({ ok: true, status: 200, json: async () => state }),
        setInterval: () => 1,
        clearInterval: () => {},
        visibility: () => 'visible',
        hasDirtyForm: () => false,
        reload: () => { reloads += 1; },
    });

    await sync.check();

    assert.equal(reloads, 0);
    assert.equal(sync.pending, true);

    state = { signature: 'call-ended', call_active: false };
    await sync.check();

    assert.equal(reloads, 1);
});
