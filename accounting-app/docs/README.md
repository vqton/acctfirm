# Documentation Index

> Tài liệu nghiệp vụ kế toán hệ thống. Gồm phân tích nghiệp vụ (analysis), quyết định kiến trúc (decisions), và nghiên cứu đối thủ/compliance (research).

## `analysis/` — Business Analysis & Brain Logic

Phân tích nghiệp vụ chuyên sâu từ góc nhìn BA Lead + Chief Accountant. Mỗi tài liệu phân tích module kế toán theo các section: tổng quan, life cycle, integration, internal control, reporting, roadmap.

| File | Module | Lines | Nội dung chính |
|---|---|---|---|
| [`accounting-engine-brain.md`](analysis/accounting-engine-brain.md) | Core Engine | 2,808 | Foundation: monthly close, BC09, trial balance, period engine, correction methods |
| [`ap-ar-engine-brain-logic.md`](analysis/ap-ar-engine-brain-logic.md) | AP/AR | 1,236 | Creditor/debtor lifecycle, 3-way matching, aging, bad debt provision |
| [`cash-flow-engine-brain-logic.md`](analysis/cash-flow-engine-brain-logic.md) | Cash Flow | 233 | BC03 direct method, CSV export, prior-period comparative |
| [`consolidated-business-spec.md`](analysis/consolidated-business-spec.md) | Cross-module | 275 | De-duplicated consolidation of all specs: architecture, glossary, rules |
| [`debt-collection-engine-brain-logic.md`](analysis/debt-collection-engine-brain-logic.md) | Debt Collection | 1,254 | Automated dunning, payment promises, collection activities |
| [`fa-ccdc-chief-accountant-analysis.md`](analysis/fa-ccdc-chief-accountant-analysis.md) | FA/CCDC | 1,957 | Fixed assets + CCDC lifecycle, tax compliance, 6 integration contracts |
| [`gap-analysis-matrix.md`](analysis/gap-analysis-matrix.md) | Cross-module | 71 | 27-item gap matrix: structural/performance/documentation gaps |
| [`gl-posting-engine-roadmap.md`](analysis/gl-posting-engine-roadmap.md) | GL | 374 | Posting controls, approval workflow, sub-ledger reconciliation, multi-currency |
| [`inventory-engine-deep-analysis.md`](analysis/inventory-engine-deep-analysis.md) | Inventory | 1,548 | 4 stock movements, FIFO costing, period validation chain |
| [`inventory-engine-roadmap.md`](analysis/inventory-engine-roadmap.md) | Inventory | 569 | Task-level implementation plan with acceptance criteria |
| [`payroll-engine-brain-logic.md`](analysis/payroll-engine-brain-logic.md) | Payroll | 1,928 | Payroll lifecycle, Gross-to-Net, 93 rules, statutory reports |
| [`payroll-implementation-roadmap.md`](analysis/payroll-implementation-roadmap.md) | Payroll | 882 | 14-phase roadmap, current-state assessment, dependency graph |
| [`period-management-engine-brain-logic.md`](analysis/period-management-engine-brain-logic.md) | Period | 1,042 | Period lifecycle, pre-close checklist, closing entries, tax adjustments |
| [`tax-engine-brain-logic.md`](analysis/tax-engine-brain-logic.md) | Tax | 1,517 | VAT/CIT/PIT/FCT, e-invoice, 93 functional rules |

## `decisions/` — Architecture Decision Records

| File | Decision |
|---|---|
| [`adr-006-vietnamese-message-audit.md`](decisions/adr-006-vietnamese-message-audit.md) | Standardize user-facing messages to Vietnamese |
| [`adr-007-compositedb-declined.md`](decisions/adr-007-compositedb-declined.md) | Decline CompositeDB library (conflicts with No-Composer) |
| [`adr-008-auth-refactor-plan.md`](decisions/adr-008-auth-refactor-plan.md) | Auth + page-loading flow refactor, 7 anti-patterns found |

## `research/` — Research & Competitive Analysis

| File | Nội dung |
|---|---|
| [`circular99-compliance-check.md`](research/circular99-compliance-check.md) | Compliance check vs Circular 99 Mẫu 01-TT / 02-TT |
| [`payment-receipt-form-comparison.md`](research/payment-receipt-form-comparison.md) | Form comparison: MISA, Fast Accounting, BRAVO vs current app |
