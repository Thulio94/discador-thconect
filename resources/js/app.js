
import Alpine from 'alpinejs';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';

window.Alpine = Alpine;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;

Alpine.start();
