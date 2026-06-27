
import Alpine from 'alpinejs';
import { listImportPreview } from './list-import-preview';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';
import { serviceFieldMask } from './service-field-masks';

window.Alpine = Alpine;
window.listImportPreview = listImportPreview;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;
window.serviceFieldMask = serviceFieldMask;

Alpine.start();
