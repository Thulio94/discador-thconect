import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildColumnConfig,
    hasPhoneColumn,
    listImportPreview,
    parseDelimitedText,
} from '../../resources/js/list-import-preview.js';

test('parses semicolon csv with quoted separators', () => {
    const parsed = parseDelimitedText('Nome;Telefone;Obs\n"Maria; Silva";31999990001;VIP', ';', '"');

    assert.deepEqual(parsed.headers, ['Nome', 'Telefone', 'Obs']);
    assert.deepEqual(parsed.previewRows[0], ['Maria; Silva', '31999990001', 'VIP']);
});

test('returns only the first two preview rows', () => {
    const parsed = parseDelimitedText('Nome;Telefone\nMaria;31999990001\nJoao;31999990002\nAna;31999990003', ';', '"');

    assert.deepEqual(parsed.previewRows, [
        ['Maria', '31999990001'],
        ['Joao', '31999990002'],
    ]);
});

test('drops columns without headers from config', () => {
    const columns = buildColumnConfig(['Nome', '', 'Telefone']);

    assert.deepEqual(columns.map((column) => column.index), [0, 2]);
    assert.equal(columns[0].enabled, true);
    assert.equal(columns[0].type, 'extra');
});

test('requires at least one active phone column', () => {
    assert.equal(hasPhoneColumn([{ enabled: true, type: 'extra' }]), false);
    assert.equal(hasPhoneColumn([{ enabled: false, type: 'phone' }]), false);
    assert.equal(hasPhoneColumn([{ enabled: true, type: 'phone' }]), true);
});

test('rejects files that are not csv or txt', async () => {
    const component = listImportPreview();
    const file = {
        name: 'leads.xlsx',
        text: async () => 'Nome;Telefone\nMaria;31999990001',
    };

    await component.loadFile(file);

    assert.equal(component.error, 'Envie um arquivo CSV ou TXT.');
    assert.equal(component.fileName, 'leads.xlsx');
    assert.deepEqual(component.columns, []);
});
