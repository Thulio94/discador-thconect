
import Alpine from 'alpinejs';
import { listImportPreview } from './list-import-preview';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';

window.Alpine = Alpine;
window.listImportPreview = listImportPreview;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;

Alpine.start();
