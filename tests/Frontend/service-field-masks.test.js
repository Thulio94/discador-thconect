import test from 'node:test';
import assert from 'node:assert/strict';
import {
    formatServiceField,
    serviceFieldMask,
} from '../../resources/js/service-field-masks.js';

test('formats cpf and cnpj progressively', () => {
    assert.equal(formatServiceField('cpf', '52998224725'), '529.982.247-25');
    assert.equal(formatServiceField('cnpj', '04252011000110'), '04.252.011/0001-10');
});

test('formats Brazilian landline and mobile numbers', () => {
    assert.equal(formatServiceField('phone', '3133334444'), '(31) 3333-4444');
    assert.equal(formatServiceField('phone', '31999990000'), '(31) 99999-0000');
});

test('preserves e164 input beginning with plus', () => {
    assert.equal(formatServiceField('phone', '+1 202-555-0123'), '+1 202-555-0123');
});

test('formats Brazilian currency', () => {
    assert.equal(formatServiceField('currency', '123456'), 'R$ 1.234,56');
    assert.equal(formatServiceField('currency', '1'), 'R$ 0,01');
});

test('alpine component formats the event target', () => {
    const component = serviceFieldMask('cpf');
    const event = { target: { value: '52998224725' } };

    component.format(event);

    assert.equal(event.target.value, '529.982.247-25');
});
