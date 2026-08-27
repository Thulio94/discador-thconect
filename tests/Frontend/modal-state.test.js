import assert from 'node:assert/strict';
import test from 'node:test';
import { modalState } from '../../resources/js/modal-state.js';

test('opens only its matching modal and releases body scrolling when closed', () => {
    const originalDocument = global.document;
    const classes = new Set();
    let anyModalOpen = false;
    let watcher;

    global.document = {
        body: {
            classList: {
                toggle(name, enabled) {
                    if (enabled) classes.add(name);
                    else classes.delete(name);
                },
            },
        },
        querySelector() {
            return anyModalOpen ? {} : null;
        },
    };

    try {
        const modal = modalState('company-create');
        modal.$watch = (property, callback) => {
            assert.equal(property, 'show');
            watcher = callback;
        };
        modal.$nextTick = (callback) => callback();
        modal.init();

        modal.openModal('another-modal');
        assert.equal(modal.show, false);

        modal.openModal('company-create');
        anyModalOpen = true;
        watcher();
        assert.equal(modal.show, true);
        assert.equal(classes.has('overflow-hidden'), true);

        modal.closeModal('another-modal');
        assert.equal(modal.show, true);

        modal.close();
        anyModalOpen = false;
        watcher();
        assert.equal(modal.show, false);
        assert.equal(classes.has('overflow-hidden'), false);
    } finally {
        global.document = originalDocument;
    }
});
