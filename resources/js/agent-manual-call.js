function defaultDependencies() {
    return {
        fetch: (...args) => window.fetch(...args),
        toast: (detail) => window.dispatchEvent(new CustomEvent('toast', { detail })),
    };
}

export function createAgentManualCall(config, dependencies = defaultDependencies()) {
    return {
        phone: '',
        submitting: false,

        formatPhone(value) {
            const digits = value.replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '').slice(0, 11);
            if (digits.length <= 2) return digits;
            if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

            return digits.length <= 10
                ? `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
                : `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
        },

        async submit() {
            if (this.submitting) return;

            this.submitting = true;
            try {
                const response = await dependencies.fetch(config.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ phone: this.phone, channel: 'manual' }),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'A ligação não foi iniciada.');
                }

                this.phone = '';
                dependencies.toast({
                    title: 'Ligação iniciada',
                    message: payload.message,
                    severity: 'info',
                });
            } catch (error) {
                dependencies.toast({
                    title: 'Ligação não iniciada',
                    message: error.message || 'Tente novamente ou avise o administrador.',
                    severity: 'error',
                });
            } finally {
                this.submitting = false;
            }
        },
    };
}

export function agentManualCall(config) {
    return createAgentManualCall(config);
}
