export const states = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');

export function phoneDigits(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    return /^55\d{10,11}$/.test(digits) ? digits.slice(2) : digits;
}

export function validPhone(value) {
    return /^\+?[0-9\s().-]+$/.test(String(value ?? '')) && /^[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})$/.test(phoneDigits(value));
}

function grouped(digits, groups, separators) {
    let result = '', offset = 0;
    for (let i = 0; i < groups.length && offset < digits.length; i++) {
        if (i) result += separators[i - 1];
        result += digits.slice(offset, offset + groups[i]);
        offset += groups[i];
    }
    return result;
}

export function formatInput(value, kind) {
    const raw = String(value ?? '');
    const digits = raw.replace(/\D/g, '');
    if (kind === 'phone') {
        const international = /^\s*\+55/.test(raw) || /^55\d{10,11}$/.test(digits);
        const national = (international ? digits.slice(2) : digits).slice(0, 11);
        if (!national) return international ? '+55 ' : '';
        const prefix = international ? '+55 ' : '';
        if (national.length <= 2) return prefix + '(' + national;
        return prefix + '(' + national.slice(0, 2) + ') ' + grouped(national.slice(2), [national.length > 10 ? 5 : 4, 4], ['-']);
    }
    if (kind === 'cpf') return grouped(digits.slice(0, 11), [3, 3, 3, 2], ['.', '.', '-']);
    if (kind === 'cep') return grouped(digits.slice(0, 8), [5, 3], ['-']);
    if (kind === 'uf') return raw.replace(/[^a-z]/gi, '').toUpperCase().slice(0, 2);
    if (kind === 'decimal') {
        const clean = raw.replace(/[^0-9.,]/g, '');
        const parts = clean.split(/[.,]/);
        return parts[0].slice(0, 4) + (parts.length > 1 ? clean.match(/[.,]/)[0] + parts.slice(1).join('').slice(0, 2) : '');
    }
    return raw;
}

export function inputError(value, kind) {
    if (!value) return '';
    if (kind === 'phone' && !validPhone(value)) return 'Informe um telefone válido com DDD.';
    if (kind === 'cpf' && value.replace(/\D/g, '').length !== 11) return 'Informe os 11 dígitos do CPF.';
    if (kind === 'cep' && value.replace(/\D/g, '').length !== 8) return 'Informe os 8 dígitos do CEP.';
    if (kind === 'uf' && !states.includes(value)) return 'Selecione uma sigla de estado válida.';
    if (kind === 'decimal' && !/^\d+(?:[.,]\d{1,2})?$/.test(value)) return 'Informe um número com até duas casas decimais.';
    return '';
}

// Capture sanitizes the native value before Vue's v-model input handler reads it.
export const inputMask = {
    created(el, binding) {
        const kind = binding.value;
        const apply = () => {
            const raw = el.value;
            const cursor = el.selectionStart;
            const digitsBefore = cursor === null ? null : raw.slice(0, cursor).replace(/\D/g, '').length;
            el.value = formatInput(raw, kind);
            el.setCustomValidity(inputError(el.value, kind));
            if (cursor !== null && el.value !== raw) {
                let position = 0, count = 0;
                while (position < el.value.length && count < digitsBefore) {
                    if (/\d/.test(el.value[position])) count++;
                    position++;
                }
                if (kind === 'uf') position = Math.min(cursor, el.value.length);
                el.setSelectionRange(position, position);
            }
        };
        const input = event => { if (!event.isComposing) apply(); };
        const beforeInput = event => {
            if (event.inputType !== 'deleteContentBackward' || el.selectionStart !== el.selectionEnd || kind === 'uf') return;
            const cursor = el.selectionStart;
            if (!cursor || /\d/.test(el.value[cursor - 1])) return;
            let index = cursor - 1;
            while (index >= 0 && !/\d/.test(el.value[index])) index--;
            if (index < 0) return;
            event.preventDefault();
            el.value = el.value.slice(0, index) + el.value.slice(cursor);
            el.setSelectionRange(index, index);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };
        el.addEventListener('input', input, true);
        el.addEventListener('beforeinput', beforeInput);
        el._inputMask = { apply, input, beforeInput };
    },
    mounted(el, binding) {
        el.inputMode = binding.value === 'decimal' ? 'decimal' : binding.value === 'uf' ? 'text' : 'numeric';
        el.maxLength = ({ phone: 20, cpf: 14, cep: 9, uf: 2, decimal: 7 })[binding.value];
        el._inputMask.apply();
    },
    updated(el) { el._inputMask.apply(); },
    beforeUnmount(el) {
        el.removeEventListener('input', el._inputMask.input, true);
        el.removeEventListener('beforeinput', el._inputMask.beforeInput);
        delete el._inputMask;
    },
};
