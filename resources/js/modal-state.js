function synchronizeBodyScroll() {
    const hasOpenModal = document.querySelector('[data-ui-modal][data-modal-open="true"]');

    document.body.classList.toggle('overflow-hidden', Boolean(hasOpenModal));
}

export function modalState(name, initiallyOpen = false) {
    return {
        name,
        show: initiallyOpen,

        init() {
            this.$watch('show', () => this.$nextTick(() => synchronizeBodyScroll()));
            synchronizeBodyScroll();
        },

        openModal(requestedName) {
            if (requestedName === this.name) {
                this.show = true;
                return;
            }

            this.show = false;
        },

        closeModal(requestedName) {
            if (requestedName === this.name) {
                this.show = false;
            }
        },

        close() {
            this.show = false;
        },
    };
}
