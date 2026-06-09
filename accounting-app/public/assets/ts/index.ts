// Bundle entry point — exports all components to window globals
// Backward compatible: inline PHP scripts reference VAS, FormToast, FormGrid, etc.

import { VAS } from './lib/vas-financial';
import { statusBadge } from './lib/status-badge';
import { FormToast } from './components/form-toast';
import { FormConfirm } from './components/form-confirm';
import { FormValidation } from './components/form-validation';
import { FormModal } from './components/form-modal';
import { FormGrid } from './components/form-grid';
import { AccountPicker } from './components/account-picker';
import { PartnerPicker } from './components/partner-picker';

// Export types for consumers that import from the bundle
export { VAS, statusBadge, FormToast, FormConfirm, FormValidation, FormModal, FormGrid, AccountPicker, PartnerPicker };
export type { VasApi } from './lib/vas-financial';

// Extend Window interface for TypeScript consumers
declare global {
  interface Window {
    VAS: typeof VAS;
    statusBadge: (status: string) => string;
    FormToast: typeof FormToast;
    FormConfirm: typeof FormConfirm;
    FormValidation: typeof FormValidation;
    FormModal: typeof FormModal;
    FormGrid: typeof FormGrid;
    AccountPicker: typeof AccountPicker;
    PartnerPicker: typeof PartnerPicker;
    showToast: (msg: string, type?: 'success' | 'error' | 'warning' | 'info', duration?: number, callback?: () => void) => JQuery;
    csrf?: string;
  }
}

// Assign to window for backward compat with inline PHP scripts
window.VAS = VAS;
window.statusBadge = statusBadge;
window.FormToast = FormToast;
window.FormConfirm = FormConfirm;
window.FormValidation = FormValidation;
window.FormModal = FormModal;
window.FormGrid = FormGrid;
window.AccountPicker = AccountPicker;
window.PartnerPicker = PartnerPicker;

// Backward-compat shim: showToast('msg','success')
window.showToast = (msg: string, type?: 'success' | 'error' | 'warning' | 'info', duration?: number, callback?: () => void) => {
  return FormToast.show(msg, type, duration, callback);
};
