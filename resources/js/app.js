
import Alpine from 'alpinejs';
import { listImportPreview } from './list-import-preview';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';
import { agentConsoleSync } from './agent-console-sync';
import { subscribeToAgentConsole, subscribeToSupervision } from './realtime';
import { serviceFieldMask } from './service-field-masks';
import { registerAgentSoftphone } from './agent-softphone';

window.Alpine = Alpine;
window.listImportPreview = listImportPreview;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;
window.agentConsoleSync = agentConsoleSync;
window.subscribeToAgentConsole = subscribeToAgentConsole;
window.subscribeToSupervision = subscribeToSupervision;
window.serviceFieldMask = serviceFieldMask;

registerAgentSoftphone(Alpine);

Alpine.start();
