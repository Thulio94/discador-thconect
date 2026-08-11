import test from 'node:test';
import assert from 'node:assert/strict';
import { createPollingRegion } from '../../resources/js/polling.js';

function successfulResponse() {
    return {
        ok: true,
        status: 200,
        headers: { get: () => '10' },
        text: async () => '<div id="region">novo</div>',
    };
}

function harness(overrides = {}) {
    const scheduled = [];
    const cleared = [];
    const intervals = [];
    const clearedIntervals = [];
    let visibilityState = 'visible';
    let currentTime = 1_000_000;

    return {
        scheduled,
        cleared,
        intervals,
        clearedIntervals,
        setVisibility(value) {
            visibilityState = value;
        },
        setTime(value) {
            currentTime = value;
        },
        dependencies: {
            fetch: overrides.fetch ?? (async () => successfulResponse()),
            parse: overrides.parse ?? (() => new Map([
                ['#region', '<span>novo</span>'],
            ])),
            replace: overrides.replace ?? (() => {}),
            setTimeout: (callback, delay) => {
                scheduled.push({ callback, delay });

                return scheduled.length;
            },
            clearTimeout: (id) => cleared.push(id),
            setInterval: (callback, delay) => {
                intervals.push({ callback, delay });

                return intervals.length;
            },
            clearInterval: (id) => clearedIntervals.push(id),
            now: () => currentTime,
            visibility: () => visibilityState,
            warn: () => {},
            AbortController: overrides.AbortController ?? AbortController,
        },
    };
}

test('starts with one delayed poll and never uses overlapping requests', async () => {
    let fetchCalls = 0;
    let resolveFetch;
    const pendingFetch = new Promise((resolve) => {
        resolveFetch = resolve;
    });
    const context = harness({
        fetch: () => {
            fetchCalls += 1;

            return pendingFetch;
        },
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    region.start();

    assert.equal(context.scheduled.length, 1);
    assert.equal(context.scheduled[0].delay, 10000);

    const firstPoll = context.scheduled[0].callback();
    await region.poll();
    assert.equal(fetchCalls, 1);

    resolveFetch(successfulResponse());
    await firstPoll;
    assert.equal(context.scheduled.at(-1).delay, 10000);
});

test('pauses while hidden and polls immediately when visible again', async () => {
    let fetchCalls = 0;
    const context = harness({
        fetch: async () => {
            fetchCalls += 1;

            return successfulResponse();
        },
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    region.start();
    context.setVisibility('hidden');
    region.handleVisibilityChange();
    await region.poll();

    assert.equal(fetchCalls, 0);
    assert.deepEqual(context.cleared, [1]);

    context.setVisibility('visible');
    region.handleVisibilityChange();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(fetchCalls, 1);
});

test('allows a realtime event to refresh a hidden region', async () => {
    let fetchCalls = 0;
    const context = harness({
        fetch: async () => {
            fetchCalls += 1;

            return successfulResponse();
        },
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    context.setVisibility('hidden');
    await region.poll(true);

    assert.equal(fetchCalls, 1);
});

test('runs one supervision clock and formats elapsed time as hh:mm:ss', () => {
    const context = harness();
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
        clock: true,
    }, context.dependencies);

    region.start();
    assert.equal(context.intervals.length, 1);
    assert.equal(context.intervals[0].delay, 1000);
    assert.equal(region.formatElapsed(900), '00:01:40');

    context.setTime(3_701_000);
    context.intervals[0].callback();
    assert.equal(region.formatElapsed(0), '01:01:41');
    assert.equal(region.formatElapsed(0, 61), '00:01:01');

    region.destroy();
    assert.deepEqual(context.clearedIntervals, [1]);
});

test('keeps supervision polling alive with a guarded heartbeat', async () => {
    let fetchCalls = 0;
    const context = harness({
        fetch: async () => {
            fetchCalls += 1;

            return successfulResponse();
        },
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
        heartbeat: true,
    }, context.dependencies);

    region.start();
    assert.equal(context.scheduled.length, 0);
    assert.equal(context.intervals.length, 1);
    assert.equal(context.intervals[0].delay, 10000);

    context.setTime(1_010_000);
    await context.intervals[0].callback();
    await Promise.resolve();
    assert.equal(fetchCalls, 1);

    context.setTime(1_020_000);
    await context.intervals[0].callback();
    await Promise.resolve();
    assert.equal(fetchCalls, 2);

    region.destroy();
    assert.deepEqual(context.clearedIntervals, [1]);
});

test('queues a realtime refresh that arrives during an active request', async () => {
    let fetchCalls = 0;
    let resolveFirstFetch;
    const firstFetch = new Promise((resolve) => {
        resolveFirstFetch = resolve;
    });
    const context = harness({
        fetch: () => {
            fetchCalls += 1;

            return fetchCalls === 1 ? firstFetch : Promise.resolve(successfulResponse());
        },
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    const initialPoll = region.poll();
    await region.poll(true);
    assert.equal(region.refreshPending, true);

    resolveFirstFetch(successfulResponse());
    await initialPoll;
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(fetchCalls, 2);
    assert.equal(region.refreshPending, false);
});

test('backs off after temporary failure and resets after success', async () => {
    const responses = [
        { ok: false, status: 500, headers: { get: () => null } },
        { ok: false, status: 503, headers: { get: () => null } },
        { ok: false, status: 500, headers: { get: () => null } },
        successfulResponse(),
    ];
    const context = harness({
        fetch: async () => responses.shift(),
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    await region.poll();
    assert.equal(context.scheduled.at(-1).delay, 20000);
    await region.poll();
    assert.equal(context.scheduled.at(-1).delay, 40000);
    await region.poll();
    assert.equal(context.scheduled.at(-1).delay, 60000);
    await region.poll();
    assert.equal(context.scheduled.at(-1).delay, 10000);
});

test('stops permanently on authentication or authorization errors', async () => {
    for (const status of [401, 403, 404, 419]) {
        const context = harness({
            fetch: async () => ({
                ok: false,
                status,
                headers: { get: () => null },
            }),
        });
        const region = createPollingRegion({
            url: '/poll',
            selectors: ['#region'],
        }, context.dependencies);

        await region.poll();

        assert.equal(region.stopped, true);
        assert.equal(context.scheduled.length, 0);
    }
});

test('aborts active request when destroyed', async () => {
    let abortCalls = 0;
    class ControlledAbortController {
        constructor() {
            this.signal = {};
        }

        abort() {
            abortCalls += 1;
        }
    }

    const context = harness({
        AbortController: ControlledAbortController,
        fetch: () => new Promise(() => {}),
    });
    const region = createPollingRegion({
        url: '/poll',
        selectors: ['#region'],
    }, context.dependencies);

    region.start();
    void context.scheduled[0].callback();
    region.destroy();

    assert.equal(abortCalls, 1);
    assert.equal(region.stopped, true);
    assert.deepEqual(context.cleared, [1]);
});
