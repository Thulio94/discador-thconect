import test from 'node:test';
import assert from 'node:assert/strict';
import { createAgentManualCall } from '../../resources/js/agent-manual-call.js';

test('starts a manual call without navigating away from the WebRTC page', async () => {
    const requests = [];
    const toasts = [];
    const call = createAgentManualCall({ url: '/tenant/agent/calls', csrfToken: 'token' }, {
        fetch: async (url, options) => {
            requests.push({ url, options });

            return {
                ok: true,
                json: async () => ({ message: 'Ligação iniciada. Aguarde o atendimento.' }),
            };
        },
        toast: (toast) => toasts.push(toast),
    });
    call.phone = '(31) 99999-0001';

    await call.submit();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.headers.Accept, 'application/json');
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        phone: '(31) 99999-0001',
        channel: 'manual',
    });
    assert.equal(toasts[0].title, 'Ligação iniciada');
    assert.equal(call.phone, '');
});
