import assert from 'node:assert/strict';
import test from 'node:test';

import { hashToken, isSafeSourceFile, searchIndex, tokenize } from '../../scripts/code-index.mjs';

test('expands Portuguese dialer concepts into code terminology', () => {
    const tokens = tokenize('tela de supervisao com ligacoes do agente');

    assert.ok(tokens.includes('view'));
    assert.ok(tokens.includes('supervision'));
    assert.ok(tokens.includes('calls'));
    assert.ok(tokens.includes('agent'));
});

test('ranks a matching code area ahead of an unrelated document', () => {
    const dimensions = 1536;
    const frequencies = Array(dimensions).fill(0);
    const agentDimension = hashToken('agent') % dimensions;
    const index = {
        chunkCount: 2,
        documentFrequencies: frequencies,
        documents: [
            { id: 'a', path: 'app/Services/Supervision/AgentPresence.php', startLine: 1, endLine: 10, title: 'AgentPresence', preview: 'agent status', vector: [[agentDimension, 1]] },
            { id: 'b', path: 'docs/backup.md', startLine: 1, endLine: 10, title: 'Backup', preview: 'backup', vector: [] },
        ],
    };

    const results = searchIndex(index, 'agent');

    assert.equal(results[0].id, 'a');
});

test('excludes secrets, runtime data and dependencies from the index', () => {
    assert.equal(isSafeSourceFile('.env'), false);
    assert.equal(isSafeSourceFile('storage/logs/laravel.log'), false);
    assert.equal(isSafeSourceFile('vendor/package/source.php'), false);
    assert.equal(isSafeSourceFile('recordings/call.wav'), false);
    assert.equal(isSafeSourceFile('app/Services/Dialer/AgentCapacityService.php'), true);
    assert.equal(isSafeSourceFile('resources/views/tenant/agent/index.blade.php'), true);
});
