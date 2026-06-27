function digits(value, limit) {
    return String(value ?? '').replace(/\D/g, '').slice(0, limit);
}

function formatCpf(value) {
    const input = digits(value, 11);

    return input
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
}

function formatCnpj(value) {
    const input = digits(value, 14);

    return input
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function formatPhone(value) {
    const original = String(value ?? '');

    if (original.trim().startsWith('+')) {
        return original;
    }

    const input = digits(original, 11);

    if (input.length <= 10) {
        return input
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return input
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

function formatCurrency(value) {
    const input = digits(value, 15);
    const cents = input.padStart(3, '0');
    const whole = cents.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `R$ ${whole},${cents.slice(-2)}`;
}

export function formatServiceField(type, value) {
    return {
        cpf: formatCpf,
        cnpj: formatCnpj,
        phone: formatPhone,
        currency: formatCurrency,
    }[type]?.(value) ?? value;
}

export function serviceFieldMask(type) {
    return {
        format(event) {
            event.target.value = formatServiceField(type, event.target.value);
        },
    };
}
