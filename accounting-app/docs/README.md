# Documentation Index

> Tài liệu nghiệp vụ kế toán hệ thống. Gồm phân tích nghiệp vụ (analysis), quyết định kiến trúc (decisions), và nghiên cứu đối thủ/compliance (research).

## `analysis/` — Business Analysis & Brain Logic

Phân tích nghiệp vụ chuyên sâu từ góc nhìn BA Lead + Chief Accountant. Mỗi tài liệu phân tích module kế toán theo các section: tổng quan, life cycle, integration, internal control, reporting, roadmap.

| File | Module | Lines | Nội dung chính |
|---|---|---|---|
| [`accounting-engine-brain.md`](analysis/accounting-engine-brain.md) | Core Engine | 2,808 | Foundation: monthly close, BC09, trial balance, period engine, correction methods |
| [`ap-ar-engine-brain-logic.md`](analysis/ap-ar-engine-brain-logic.md) | AP/AR | 1,236 | Creditor/debtor lifecycle, 3-way matching, aging, bad debt provision |
| [`cash-flow-engine-brain-logic.md`](analysis/cash-flow-engine-brain-logic.md) | Cash Flow | 233 | BC03 direct method, CSV export, prior-period comparative |
| [`cit-engine-spec.md`](analysis/cit-engine-spec.md) | CIT | — | 03/TNDN 25-indicator engine, non-deductible adjustments, loss carryforward |
| [`config-service-design.md`](analysis/config-service-design.md) | Config Service | — | ConfigService + business_config data-driven pattern |
| [`consolidated-business-spec.md`](analysis/consolidated-business-spec.md) | Cross-module | ~300 | De-duplicated consolidation of all specs: architecture, glossary, rules |
| [`debt-collection-engine-brain-logic.md`](analysis/debt-collection-engine-brain-logic.md) | Debt Collection | 1,254 | Automated dunning, payment promises, collection activities |
| [`e-invoice-implementation.md`](analysis/e-invoice-implementation.md) | E-Invoice | — | TT32 v2.0.0 XML, PKCS#7 signature, VNPT gateway, full lifecycle |
| [`fa-ccdc-chief-accountant-analysis.md`](analysis/fa-ccdc-chief-accountant-analysis.md) | FA/CCDC | 1,957 | Fixed assets + CCDC lifecycle, tax compliance, 6 integration contracts |
| [`gap-analysis-matrix.md`](analysis/gap-analysis-matrix.md) | Cross-module | ~60 | 27-item gap matrix: structural/performance/documentation gaps |
| [`gl-posting-engine-roadmap.md`](analysis/gl-posting-engine-roadmap.md) | GL | 374 | Posting controls, approval workflow, sub-ledger reconciliation, multi-currency |
| [`inventory-engine-deep-analysis.md`](analysis/inventory-engine-deep-analysis.md) | Inventory | 1,548 | 4 stock movements, FIFO costing, period validation chain |
| [`inventory-engine-roadmap.md`](analysis/inventory-engine-roadmap.md) | Inventory | 569 | Task-level implementation plan with acceptance criteria |
| [`payroll-engine-brain-logic.md`](analysis/payroll-engine-brain-logic.md) | Payroll | 1,928 | Payroll lifecycle, Gross-to-Net, 93 rules, statutory reports |
| [`payroll-implementation-roadmap.md`](analysis/payroll-implementation-roadmap.md) | Payroll | 882 | 14-phase roadmap, current-state assessment, dependency graph |
| [`period-management-engine-brain-logic.md`](analysis/period-management-engine-brain-logic.md) | Period | 1,042 | Period lifecycle, pre-close checklist, closing entries, tax adjustments |
| [`pit-engine-spec.md`](analysis/pit-engine-spec.md) | PIT | — | 05/KK-TNCN monthly + 05/QTT-TNCN annual, progressive brackets, deductions |
| [`procurement-engine-spec.md`](analysis/procurement-engine-spec.md) | Procurement | — | Purchase requisition → PO → goods receipt → 3-way match |
| [`tax-engine-brain-logic.md`](analysis/tax-engine-brain-logic.md) | Tax | ~1,600 | VAT/CIT/PIT/FCT, e-invoice, 93 functional rules, 8-phase roadmap (6/8 ✅) |

## `decisions/` — Architecture Decision Records

| File | Decision |
|---|---|
| [`adr-006-vietnamese-message-audit.md`](decisions/adr-006-vietnamese-message-audit.md) | Standardize user-facing messages to Vietnamese |
| [`adr-007-compositedb-declined.md`](decisions/adr-007-compositedb-declined.md) | Decline CompositeDB library (conflicts with No-Composer) |
| [`adr-008-auth-refactor-plan.md`](decisions/adr-008-auth-refactor-plan.md) | Auth + page-loading flow refactor, 7 anti-patterns found |
| [`adr-009-tax-priority-first-implementation.md`](decisions/adr-009-tax-priority-first-implementation.md) | Priority-first: P0 legal MUST before P2 nice-to-have |
| [`adr-010-vat-groups-data-driven.md`](decisions/adr-010-vat-groups-data-driven.md) | vat_groups data-driven design (no hardcoded rates) |
| [`adr-011-business-config-data-driven.md`](decisions/adr-011-business-config-data-driven.md) | Data-driven business rules via business_config table |

## `research/` — Research & Competitive Analysis

| File | Nội dung |
|---|---|
| [`circular99-compliance-check.md`](research/circular99-compliance-check.md) | Compliance check vs Circular 99 Mẫu 01-TT / 02-TT |
| [`payment-receipt-form-comparison.md`](research/payment-receipt-form-comparison.md) | Form comparison: MISA, Fast Accounting, BRAVO vs current app |
