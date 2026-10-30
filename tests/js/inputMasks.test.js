import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatInput, inputError, inputMask, phoneDigits, validPhone } from '../../resources/js/forms/inputMasks.js';

test('phone mask supports mobile, landline, country code and mixed pasted input', () => {
    assert.equal(formatInput('71996651964', 'phone'), '(71) 99665-1964');
    assert.equal(formatInput('7133334444', 'phone'), '(71) 3333-4444');
    assert.equal(formatInput('+55 (71) 99665-1964', 'phone'), '+55 (71) 99665-1964');
    assert.equal(formatInput('5571996651964', 'phone'), '+55 (71) 99665-1964');
    assert.equal(formatInput('abc71x99665?1964', 'phone'), '(71) 99665-1964');
    assert.equal(validPhone('(71) 99665-1964'), true);
    assert.equal(validPhone('(00) 99665-1964'), false);
    assert.equal(validPhone('(71) 1234-5678'), false);
    assert.equal(validPhone('71999'), false);
    assert.equal(formatInput('', 'phone'), '');
});

test('CPF, CEP, UF and decimal masks constrain format and length', () => {
    assert.equal(formatInput('12345678901234', 'cpf'), '123.456.789-01');
    assert.equal(formatInput('40100120999', 'cep'), '40100-120');
    assert.equal(formatInput('b1ahia', 'uf'), 'BA');
    assert.equal(formatInput('78,255kg', 'decimal'), '78,25');
    assert.equal(inputError('1', 'cpf').length > 0, true);
    assert.equal(inputError('12345', 'cep').length > 0, true);
    assert.equal(inputError('ZZ', 'uf').length > 0, true);
    assert.equal(inputError('12,', 'decimal').length > 0, true);
    for (const kind of ['cpf', 'cep', 'uf', 'decimal']) assert.equal(inputError('', kind), '');
});

class Input extends EventTarget {
    constructor(value) { super(); this.value = value; this.selectionStart = this.selectionEnd = value.length; }
    setCustomValidity(value) { this.error = value; }
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; }
}

test('mask runs before model listener and updates custom validity and cursor', () => {
    const input = new Input('abc71996651964');
    inputMask.created(input, { value: 'phone' });
    let model;
    input.addEventListener('input', () => { model = input.value; });
    input.dispatchEvent(new Event('input'));
    assert.equal(model, '(71) 99665-1964');
    assert.equal(input.error, '');
    assert.equal(input.selectionStart, input.value.length);
    input.value = '719'; input.setSelectionRange(3, 3);
    input.dispatchEvent(new Event('input'));
    assert.notEqual(input.error, '');
    inputMask.beforeUnmount(input);
});

test('backspace over a separator deletes a digit and permits clearing', () => {
    const input = new Input('(71) 99665-1964');
    inputMask.created(input, { value: 'phone' });
    input.setSelectionRange(11, 11);
    const event = new Event('beforeinput', { cancelable: true });
    Object.defineProperty(event, 'inputType', { value: 'deleteContentBackward' });
    input.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(phoneDigits(input.value), '7199661964');
    input.value = ''; input.setSelectionRange(0, 0);
    input.dispatchEvent(new Event('input'));
    assert.equal(input.value, '');
    assert.equal(input.error, '');
    inputMask.beforeUnmount(input);
});
