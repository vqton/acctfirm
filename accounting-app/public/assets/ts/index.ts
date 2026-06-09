// Bundle entry point — exports all components to window globals
// Backward compatible: inline PHP scripts reference VAS, FormToast, FormGrid, etc.

import { VAS } from './lib/vas-financial';
import { statusBadge } from './lib/status-badge';
import { exportCSV, printForm, printTransaction, printFromData } from './lib/print-utils';
import { importFromExcel, importValidate, importCommit, downloadTemplate } from './lib/import-utils';
import { FormToast } from './components/form-toast';
import { FormConfirm } from './components/form-confirm';
import { FormValidation } from './components/form-validation';
import { FormModal } from './components/form-modal';
import { FormGrid } from './components/form-grid';
import { AccountPicker } from './components/account-picker';
import { PartnerPicker } from './components/partner-picker';

// Export types for consumers that import from the bundle
export { VAS, statusBadge, exportCSV, printForm, printTransaction, printFromData, importFromExcel, importValidate, importCommit, downloadTemplate, FormToast, FormConfirm, FormValidation, FormModal, FormGrid, AccountPicker, PartnerPicker };
export type { VasApi } from './lib/vas-financial';

// Extend Window interface for TypeScript consumers
declare global {
  interface Window {
    VAS: typeof VAS;
    statusBadge: (status: string) => string;
    exportCSV: (tableSelector: string | HTMLTableElement, filename: string) => void;
    printForm: (title: string, bodyHtml: string) => Window | null;
    printTransaction: (title: string, apiUrl: string, fieldMap: Record<string, string>, linesField?: string, lineFields?: Record<string, string>, partnerField?: string) => void;
    printFromData: (title: string, data: Record<string, unknown>, fieldMap: Record<string, string>, linesField?: string, lineFields?: Record<string, string>) => void;
    importFromExcel: (entityType: string) => void;
    importValidate: () => void;
    importCommit: () => void;
    downloadTemplate: (entityType: string) => void;
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
window.exportCSV = exportCSV;
window.printForm = printForm;
window.printTransaction = printTransaction;
window.printFromData = printFromData;
window.importFromExcel = importFromExcel;
window.importValidate = importValidate;
window.importCommit = importCommit;
window.downloadTemplate = downloadTemplate;
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
