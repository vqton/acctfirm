<?php

use Accounting\Infrastructure\JsonResponse;

// === JOURNAL / TRANSACTIONS / TRIAL BALANCE ===
$router->post('/api/journal', function() use ($c) { $c['JournalController']->postEntry(); });
$router->post('/api/journal/draft', function() use ($c) { $c['JournalController']->createDraft(); });
$router->post('/api/journal/approve/:id', function($id) use ($c) { $c['JournalController']->approveDraft($id); });
$router->post('/api/journal/duplicate/:id', function($id) use ($c) { $c['JournalController']->duplicate($id); });
$router->post('/api/journal/:id/delete', function($id) use ($c) { $c['JournalController']->softDelete($id); });
$router->post('/api/journal/:id/restore', function($id) use ($c) { $c['JournalController']->restore($id); });
$router->post('/api/journal/bulk-post', function() use ($c) { $c['JournalController']->bulkPost(); });
$router->get('/api/transactions', function() use ($c) { $c['JournalController']->list(); });
$router->get('/api/transactions/:id', function($id) use ($c) { $c['JournalController']->get($id); });
$router->get('/api/trial-balance', function() use ($c) { $c['JournalController']->trialBalance(); });

// === APPROVALS ===
$router->get('/api/approvals/pending', function() use ($c) { $c['ApprovalController']->getPending(); });
$router->post('/api/approvals/:id/approve', function($id) use ($c) { $c['ApprovalController']->approve($id); });
$router->post('/api/approvals/:id/reject', function($id) use ($c) { $c['ApprovalController']->reject($id); });
$router->get('/api/approvals/history/:id', function($id) use ($c) { $c['ApprovalController']->history($id); });
$router->get('/api/approvals/routing', function() use ($c) { $c['ApprovalController']->routing(); });

// === AP (TK 331) ===
$router->get('/api/ap/invoices', function() use ($c) { $c['ApController']->invoices(); });
$router->post('/api/ap/invoices', function() use ($c) { $c['ApController']->create(); });
$router->get('/api/ap/invoices/:id', function($id) use ($c) { $c['ApController']->get($id); });
$router->get('/api/ap/invoices/:id/payments', function($id) use ($c) { $c['ApController']->payments($id); });
$router->post('/api/ap/invoices/:id/pay', function($id) use ($c) { $c['ApController']->pay($id); });
$router->post('/api/ap/invoices/:id/return', function($id) use ($c) { $c['ApController']->returnGoods($id); });
$router->post('/api/ap/invoices/:id/discount', function($id) use ($c) { $c['ApController']->discount($id); });
$router->post('/api/ap/invoices/:id/write-off', function($id) use ($c) { $c['ApController']->writeOff($id); });
$router->get('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });
$router->post('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });
$router->get('/api/ap/aging', function() use ($c) { $c['ApController']->aging(); });
$router->get('/api/ap/suppliers', function() use ($c) { $c['ApController']->suppliers(); });
$router->get('/api/ap/suppliers/:id/statement', function($id) use ($c) { $c['ApController']->statement($id); });

// === AR (TK 131) ===
$router->get('/api/ar/invoices', function() use ($c) { $c['ArController']->invoices(); });
$router->post('/api/ar/invoices', function() use ($c) { $c['ArController']->create(); });
$router->get('/api/ar/invoices/:id', function($id) use ($c) { $c['ArController']->get($id); });
$router->post('/api/ar/invoices/:id/pay', function($id) use ($c) { $c['ArController']->pay($id); });
$router->post('/api/ar/invoices/:id/return', function($id) use ($c) { $c['ArController']->returnGoods($id); });
$router->post('/api/ar/invoices/:id/discount', function($id) use ($c) { $c['ArController']->discount($id); });
$router->post('/api/ar/invoices/:id/write-off', function($id) use ($c) { $c['ArController']->writeOff($id); });
$router->post('/api/ar/prepay', function() use ($c) { $c['ArController']->prepay(); });
$router->get('/api/ar/aging', function() use ($c) { $c['ArController']->aging(); });
$router->get('/api/ar/customers', function() use ($c) { $c['ArController']->customers(); });
$router->get('/api/ar/customers/:id/statement', function($id) use ($c) { $c['ArController']->statement($id); });

// === FS (Báo cáo tài chính) ===
$router->get('/api/fs/bc01', function() use ($c) { $c['FsController']->bc01(); });
$router->get('/api/fs/bc02', function() use ($c) { $c['FsController']->bc02(); });
$router->get('/api/fs/tt99', function() use ($c) { $c['FsController']->tt99(); });
$router->get('/api/fs/bc03', function() use ($c) { $c['FsController']->bc03(); });
$router->get('/api/fs/bc03-direct', function() use ($c) { $c['FsController']->bc03Direct(); });

// === XBRL EXPORT (GDT submission format) ===
$router->get('/api/fs/xbrl/bc01', function() use ($c) { $c['FsController']->exportXbrlBC01(); });
$router->get('/api/fs/xbrl/bc02', function() use ($c) { $c['FsController']->exportXbrlBC02(); });
$router->get('/api/fs/xbrl/bc03', function() use ($c) { $c['FsController']->exportXbrlBC03(); });

// === PERIODS ===
$router->get('/api/periods', function() use ($c) { $c['PeriodController']->list(); });
$router->get('/api/periods/compare', function() use ($c) { $c['PeriodController']->compare(); });
$router->get('/api/periods/:id', function($id) use ($c) { $c['PeriodController']->get($id); });
$router->post('/api/periods', function() use ($c) { $c['PeriodController']->create(); });
$router->post('/api/periods/generate', function() use ($c) { $c['PeriodController']->generate(); });
$router->get('/api/periods/config', function() use ($c) { $c['PeriodController']->listConfigs(); });
$router->post('/api/periods/config', function() use ($c) { $c['PeriodController']->setConfig(); });
$router->post('/api/periods/:id/close', function($id) use ($c) { $c['PeriodController']->close($id); });
$router->post('/api/periods/:id/reopen', function($id) use ($c) { $c['PeriodController']->reOpen($id); });
$router->post('/api/periods/:id/close-with-checklist', function($id) use ($c) { $c['PeriodController']->closeWithChecklist($id); });
$router->post('/api/periods/:id/deadline', function($id) use ($c) { $c['PeriodController']->setDeadline($id); });
$router->post('/api/periods/:id/deadline/override', function($id) use ($c) { $c['PeriodController']->overrideDeadline($id); });
$router->get('/api/periods/:id/can-close', function($id) use ($c) { $c['PeriodController']->canClose($id); });
$router->get('/api/periods/:id/checklist', function($id) use ($c) { $c['PeriodController']->checklist($id); });
$router->post('/api/periods/:id/execute-closing', function($id) use ($c) { $c['PeriodController']->executeClosing($id); });
$router->post('/api/periods/:id/archive', function($id) use ($c) { $c['PeriodController']->archive($id); });

// === GL (Sổ cái) ===
$router->get('/api/gl/ledger', function() use ($c) { $c['GlController']->ledger(); });
$router->get('/api/gl/subsidiary', function() use ($c) { $c['GlController']->subsidiaryLedger(); });
$router->get('/api/gl/accounts', function() use ($c) { $c['GlController']->accounts(); });

// === EXPORT (CSV/HTML) ===
$router->get('/api/export/csv/ledger', function() use ($c) { $c['ReportExportController']->exportCsvLedger(); });
$router->get('/api/export/html/ledger', function() use ($c) { $c['ReportExportController']->exportHtmlLedger(); });
$router->get('/api/export/csv/trial-balance', function() use ($c) { $c['ReportExportController']->exportCsvTrialBalance(); });
$router->get('/api/export/csv/bc03', function() use ($c) { $c['ReportExportController']->exportCsvBC03(); });

// === RECONCILIATION ===
$router->get('/api/reconciliation/run', function() use ($c) { $c['ReconciliationController']->run(); });

// === FX REVALUATION ===
$router->post('/api/fx/revaluate/:periodId', function($periodId) use ($c) { $c['FxController']->revaluate($periodId); });
$router->get('/api/fx/report/:periodId', function($periodId) use ($c) { $c['FxController']->report($periodId); });

// === GENERAL JOURNAL ===
$router->get('/api/general-journal', function() use ($c) { $c['JournalBookController']->journal(); });

// === AUDIT LOG ===
$router->get('/api/audit-log', function() use ($c) { $c['AuditLogController']->list(); });
$router->get('/api/audit-log/:id', function($id) use ($c) { $c['AuditLogController']->get($id); });

// === DASHBOARD ===
$router->get('/api/dashboard', function() use ($c) { $c['CashReportController']->kpis(); });

// === CORRECTIONS ===
$router->post('/api/corrections/supplementary', function() use ($c) { $c['CorrectionController']->supplementary(); });
$router->post('/api/corrections/negative', function() use ($c) { $c['CorrectionController']->negative(); });
$router->post('/api/corrections/adjusting', function() use ($c) { $c['CorrectionController']->adjusting(); });
$router->get('/api/corrections/history/:transactionId', function($transactionId) use ($c) { $c['CorrectionController']->history($transactionId); });

// === BC09 - Notes to Financial Statements ===
$router->get('/api/fs/bc09/:periodId', function($periodId) use ($c) { $c['FsNotesController']->getReport($periodId); });
$router->post('/api/fs/bc09/:periodId/generate', function($periodId) use ($c) { $c['FsNotesController']->generate($periodId); });
$router->put('/api/fs/bc09/:periodId/indicator/:code', function($periodId, $code) use ($c) { $c['FsNotesController']->updateIndicator($periodId, $code); });
$router->post('/api/fs/bc09/:periodId/validate', function($periodId) use ($c) { $c['FsNotesController']->validate($periodId); });
$router->get('/api/fs/bc09/policies', function() use ($c) { $c['FsNotesController']->getPolicies(); });
$router->get('/bao-cao-tai-chinh/thuyet-minh-bc09', function() use ($c) { $c['FsNotesController']->viewIndex(); });

// === IMPORT (R-4 Safety Framework + R-5 Items + R-6 Opening Balance) ===
$router->get('/api/import/supported-types', function() use ($c) { $c['ImportController']->supportedTypes(); });
$router->get('/api/import/template/:type', function($type) use ($c) { $c['ImportController']->template($type); });
$router->post('/api/import/dry-run/:type', function($type) use ($c) { $c['ImportController']->dryRun($type); });
$router->post('/api/import/commit/:type', function($type) use ($c) { $c['ImportController']->commit($type); });
$router->post('/api/import/rollback/:batchId', function($batchId) use ($c) { $c['ImportController']->rollback($batchId); });
$router->get('/api/import/batches', function() use ($c) { $c['ImportController']->listBatches(); });
$router->get('/api/import/batches/:id', function($id) use ($c) { $c['ImportController']->getBatch($id); });

// === CURRENCY (R-11 Multi-Currency Display) ===
$router->get('/api/currencies', function() use ($c) { $c['CurrencyController']->listCurrencies(); });
$router->get('/api/currencies/rate/:code', function($code) use ($c) { $c['CurrencyController']->getRate($code); });
$router->post('/api/currencies/convert', function() use ($c) { $c['CurrencyController']->convert(); });
$router->get('/api/currencies/preference', function() use ($c) { $c['CurrencyController']->getPreference(); });
$router->post('/api/currencies/preference', function() use ($c) { $c['CurrencyController']->setPreference(); });

// === NOTIFICATIONS (R-12 In-App Notifications) ===
$router->get('/api/notifications', function() use ($c) { $c['NotificationController']->list(); });
$router->get('/api/notifications/unread', function() use ($c) { $c['NotificationController']->unreadCount(); });
$router->post('/api/notifications/:id/read', function($id) use ($c) { $c['NotificationController']->markRead($id); });
$router->post('/api/notifications/read-all', function() use ($c) { $c['NotificationController']->markAllRead(); });

// === PRINT TEMPLATES (R-10 Print Designer v1) ===
$router->get('/api/print/templates', function() use ($c) { $c['PrintTemplateController']->list(); });
$router->post('/api/print/templates', function() use ($c) { $c['PrintTemplateController']->save(); });
$router->get('/api/print/templates/:id', function($id) use ($c) { $c['PrintTemplateController']->get($id); });
$router->post('/api/print/templates/:id/preview', function($id) use ($c) { $c['PrintTemplateController']->preview($id); });
$router->post('/api/print/templates/:id/render', function($id) use ($c) { $c['PrintTemplateController']->render($id); });
$router->delete('/api/print/templates/:id', function($id) use ($c) { $c['PrintTemplateController']->deactivate($id); });
