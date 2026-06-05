export function toastStack() {
    return {
        toasts: [],
        push(payload) {
            const id = Date.now() + Math.random();

            this.toasts.push({
                id,
                title: payload.title || 'Notificacao',
                message: payload.message || '',
                severity: payload.severity || 'info',
            });

            window.setTimeout(() => this.remove(id), payload.timeout || 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    };
}
