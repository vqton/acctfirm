# Use Case Specification

## Chart of Accounts (COA) System — Circular 99/2025/TT-BTC Appendix II

---

## 1. Source

- **URLs:**
  - https://expertis.vn/van-ban/phu-luc-ii-thong-tu-99-2025-tt-btc/ (main)
  - https://expertis.vn/wp-content/uploads/2026/01/Loai-tai-khoan-chi-phi-san-xuat-kinh-doanh-TK-621-%E2%9F%B6-642.pdf
  - https://expertis.vn/wp-content/uploads/2026/01/Loai-tai-khoan-thu-nhap-khac-TK-711.pdf
  - https://expertis.vn/wp-content/uploads/2026/01/Loai-tai-khoan-chi-phi-khac-TK-811-%E2%9F%B6-821.pdf
  - https://expertis.vn/wp-content/uploads/2026/01/Loai-tai-khoan-xac-dinh-ket-qua-kinh-doanh-TK-911.pdf

- **Domain Context:** Chart of Accounts (Hệ thống tài khoản kế toán) for Vietnamese enterprises under Circular 99/2025/TT-BTC, effective 01 January 2026, replacing Circular 200/2014/TT-BTC.

- **Regulatory Context:** VAS 01 (Framework), VAS 21 (Financial Statement Presentation), VAS 10 (Foreign Exchange); Ministry of Finance mandate that all enterprises must use the prescribed uniform chart of accounts with optional expansion at sub-account level.

- **Analysis Summary:** The source defines the complete uniform chart of accounts comprising 8 account classes (Loại TK). Each contains embedded accounting principles specifying: normal balance side (debit/credit), posting rules, sub-account structure, foreign currency handling. This is the system of record for all financial transactions.

---

## 2. Domain Breakdown

### Domain 1: COA Master Data

#### UC-01: Configure Uniform Chart of Accounts

**Description:** Load and maintain the standard COA: 8 account classes, Level 1 (3-digit) and Level 2 (4-digit) accounts, each classified by economic nature with prescribed normal balance side.

**Goal:** Establish the regulation-compliant account structure as foundation for all transaction processing.

**Primary Actors:** System Administrator, Chief Accountant

**Preconditions:** Circular 99 accounting regime is configured. System is in initial setup.

**Trigger:** Enterprise registration; regulatory update.

**Main Flow:**
1. System loads standard COA seed data comprising 8 account classes:
   - Class 1–2: Asset accounts (TK 111–258)
   - Class 3: Liability accounts (TK 311–358)
   - Class 4: Equity accounts (TK 411–421)
   - Class 5: Revenue accounts (TK 511–521)
   - Class 6: Production & business expense accounts (TK 621–642)
   - Class 7: Other income accounts (TK 711–721)
   - Class 8: Other expense accounts (TK 811–821)
   - Class 9: Determination of business results (TK 911)
2. For each account, records: account number, name, class, normal balance side (Dr/Cr), parent-child hierarchy, status.
3. Validates complete structure: total debit capacity = total credit capacity.
4. Sets all accounts to Active status. Logs COA version with effective date.

**Alternate Flow:** Transition from Circular 200: system loads legacy COA, applies account mapping rules, transfers opening balances.

**Business Rules:**
- BR01: 8 account classes only. No class can be added or removed.
- BR02: Level 1 = 3 digits. Level 2 = 4 digits (parent + 1 digit).
- BR03: Level 1 = control accounts. Level 2+ = detail accounts. Posting only to detail accounts.
- BR04: Normal balance: Asset/Expense = Debit; Liability/Equity/Revenue = Credit.
- BR05: Sub-accounts inherit parent accounting nature.
- BR06: Consistent application across periods.
- BR07: COA version must be disclosed in financial statement notes.

**Priority:** Critical

---

#### UC-02: Manage Account Lifecycle

**Description:** Activate, deactivate, add sub-accounts, merge accounts while preserving transactional integrity.

**Goal:** Maintain COA as a living structure that prevents posting to obsolete accounts.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. User requests action: add sub-account, deactivate account, reactivate, merge.
2. System validates: deactivation requires zero balance; merge transfers balance then deactivates source.
3. System executes and logs.

**Business Rules:**
- BR08: Direct posting to Level 1 control accounts is prohibited.
- BR09: Account with non-zero balance cannot be deactivated.
- BR10: Deactivation is reversible.
- BR11: All changes audit-logged immutably.

**Priority:** High

---

### Domain 2: Posting Validation

#### UC-03: Validate Posting Rules

**Description:** Enforce correct posting logic: detail-only posting, normal balance enforcement, active account check.

**Goal:** Prevent erroneous journal entries.

**Primary Actors:** System (automatic), Accountant

**Main Flow:**
1. For each journal line, system validates:
   a. Target account is Level 2+ (detail account)
   b. Normal balance side: Dr increases asset/expense; Cr increases liability/equity/revenue
   c. Account is Active
2. Pass → accept. Fail → reject with specific error.

**Business Rules:**
- BR12: Asset accounts normally Dr; Liability/Equity accounts normally Cr.
- BR13: Revenue accounts normally Cr; Expense accounts normally Dr.
- BR14: Dr to asset = increase; Cr to asset = decrease. Reverse for liability/equity.
- BR15: Level 1 posting blocked unless Chief Accountant override.
- BR16: Inactive accounts block all postings.

**Priority:** Critical

---

### Domain 3: Foreign Currency Accounting

#### UC-04: Manage Foreign Currency Accounts and Revaluation

**Description:** Track monetary accounts (cash, bank, AR, AP) by original currency. Apply spot-rate, prepayment-rate, and period-end revaluation per VAS 10 and Circular 99 TK 413.

**Goal:** Ensure foreign currency transactions and balances are accurately recorded and periodically revalued.

**Primary Actors:** Accountant, Chief Accountant

**Main Flow:**
1. At transaction time:
   - Cash/bank debit side → spot rate. Credit side → spot rate or book rate.
   - Payable credit side → spot rate. Debit side → spot rate or book rate.
   - Prepayment locks rate at prepayment date for both prepaid portion and corresponding payable.
2. At period-end: system revalues all monetary items at closing rate.
3. Exchange difference → TK 413 (not P&L immediately per VAS 10).
4. Exchange rate policy must be consistently applied and disclosed.

**Business Rules:**
- BR17: Cash/bank Dr → spot rate; Cr → spot rate or book rate.
- BR18: Payable Cr → spot rate; Dr → spot rate or book rate.
- BR19: Prepayment locks rate at prepayment date.
- BR20: Period-end rate = average buying/selling transfer rate of commercial bank.
- BR21: Exchange differences → TK 413.
- BR22: Rate policy must be consistent and disclosed.

**Priority:** High

---

### Domain 4: Financial Statement Mapping

#### UC-05: Map Accounts to Financial Statement Lines

**Description:** Define mapping from COA accounts to Balance Sheet (BC 01), Income Statement (BC 02), Cash Flow (BC 03), and Notes (BC 09) line items.

**Goal:** Enable automatic financial statement generation from GL balance.

**Primary Actors:** Chief Accountant, System Administrator

**Main Flow:**
1. Each FS line item maps to one or more accounts or account ranges.
2. System stores mapping. At period-end, aggregates balances per mapping.
3. Every active account must map to exactly one FS line.
4. Mapping is versioned; changes are audit-logged.

**Priority:** Critical

---

### Domain 5: COA Versioning & Migration

#### UC-06: Migrate COA Between Accounting Regimes

**Description:** Transition from Circular 200/133 to Circular 99, including account mapping, balance transfer, and comparative adjustment.

**Goal:** Seamless regulatory transition with auditable mapping.

**Primary Actors:** Chief Accountant, System Administrator

**Main Flow:**
1. Load mapping table: old account → new account.
2. Copy opening balances per mapping.
3. Verify: total assets = total liabilities + equity in both old and new COA.
4. Deactivate old accounts. Activate new accounts.
5. Generate migration report.

**Business Rules:**
- BR23: Every old account maps to a new account.
- BR24: Balance transfer preserves accounting equation.
- BR25: Prior-period comparatives must be restated per new COA.

**Priority:** Critical

---

### Domain 6: COA Audit

#### UC-07: Audit COA Changes

**Description:** Maintain immutable audit trail of all COA changes: additions, status changes, mapping modifications, migrations.

**Goal:** Full traceability for statutory auditors and tax authorities.

**Primary Actors:** System (automatic), Auditor

**Main Flow:**
1. System logs each change: timestamp, user, action, before/after values, justification.
2. Prior versions preserved for comparative reporting.
3. Deletion physically prohibited; accounts are deactivated.

**Priority:** High

---

### Class-by-Class Accounting Principles

#### UC-08: Manage Asset Accounts (Class 1–2)

**Scope:** TK 111 (Cash), 112 (Bank), 113 (Cash in transit), 121 (Trading securities), 128 (HTM investments), 131 (AR), 133 (Input VAT), 136 (Interco AR), 138 (Other AR), 141 (Advances), 151–158 (Inventory), 211–217 (Fixed assets), 221–228 (Investments), 241 (CIP), 242 (Prepaid expenses), 243 (Deferred tax assets), 244 (Deposits), 258 (Other assets).

**Rules:** Normally debit balance. Dr increases, Cr decreases. Foreign currency: Dr at spot rate, Cr at spot rate or book rate. Sub-accounts inherit asset nature.

#### UC-09: Manage Liability Accounts (Class 3)

**Scope:** TK 311 (Payables), 331 (AP), 333 (Taxes payable), 334 (Wages), 335 (Accruals), 336 (Interco AP), 337 (Bond payables), 338 (Other payables), 341 (Borrowings), 343 (Bonds issued), 344 (Conversion debt), 347 (Lease liabilities), 352 (Provisions), 356 (Other liabilities), 358 (Deferred revenue).

**Rules:** Normally credit balance. Cr increases, Dr decreases. Foreign currency: Cr at spot rate, Dr at spot rate or book rate. Prepayment: rate locked at prepayment date.

#### UC-10: Manage Equity Accounts (Class 4)

**Scope:** TK 411 (Capital), 418 (Share premium), 419 (Treasury shares), 421 (Retained earnings).

**Rules:** Normally credit balance. Cr increases, Dr decreases. Key accounts for financial position.

#### UC-11: Manage Revenue Accounts (Class 5)

**Scope:** TK 511 (Sales revenue), 515 (Finance income), 521 (Revenue deductions).

**Rules:** Normally credit balance. Cr records revenue, Dr records deductions/closing transfers. Zero balance at period-end after closing.

#### UC-12: Manage Production & Business Expense Accounts (Class 6)

**Scope:** TK 621 (Raw materials), 622 (Labor), 623 (Machine costs), 627 (Overhead), 631 (Trading COGS), 632 (Production COGS), 635 (Finance costs), 641 (Selling expenses), 642 (Admin expenses).

**Rules:** Normally debit balance. Dr records expenses, Cr records closing transfers. Zero balance at period-end. Abnormal costs charged to TK 632 (not inventoried).

#### UC-13: Manage Other Income Accounts (Class 7)

**Scope:** TK 711 (Other income), 721 (Other contribution income).

**Rules:** Normally credit balance. Cr records income, Dr records closing transfers. Zero balance at period-end.

#### UC-14: Manage Other Expense Accounts (Class 8)

**Scope:** TK 811 (Other expenses), 821 (CIT expense).

**Rules:** Normally debit balance. Dr records expenses, Cr records closing transfers. Zero balance at period-end.

#### UC-15: Manage Result Determination Account (Class 9)

**Scope:** TK 911 (Profit & loss determination).

**Rules:** Collects all revenue and expense balances at period-end. Dr records expense transfers, Cr records revenue transfers. Balance represents net profit/loss. Zero balance after profit distribution closing.

---

## 3. Cross-Use Case Analysis

### Overlapping Use Cases
- UC-03 (Posting Rules) consumed by UC-08 through UC-15 (all account classes)
- UC-04 (FX Revaluation) applies to UC-08 (cash/bank/AR) and UC-09 (AP/payables)
- UC-05 (FS Mapping) depends on UC-08–15 for correct account-to-FS-line mapping

### Shared Dependencies
- **Account master data:** all use cases depend on COA
- **Account hierarchy (Level 1/2):** shared by UC-03 (posting restriction) and UC-05 (FS aggregation)
- **Account status:** consumed by UC-03 (blocks posting) and UC-02 (lifecycle)

### Workflow Gaps
- No explicit use case for bulk account import from CSV/Excel
- No explicit use case for account reconciliation (GL vs sub-ledger)
- No explicit use case for opening balance entry per account at setup

### Potential System Risks
- Posting to Level 1 control accounts: inconsistent GL, no sub-ledger detail
- FX revaluation mismatch: different bank rates for different accounts → reconciliation issues
- Migration data loss: unmapped accounts orphan balances
- FS mapping incompleteness: unmapped accounts cause FS totals to be understated

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Bulk Account Import | Load full COA seed from CSV/Excel | High |
| Opening Balance Entry | Enter opening balances at enterprise setup | Critical |
| Account Reconciliation Report | Validate every used account exists in COA | Medium |
| Sub-Account Auto-Numbering | Generate next available code within parent | Low |

### Missing Validation Rules
- Account code uniqueness (no duplicate 3-digit or 4-digit codes)
- Account code format: Level 1 = 3 digits only; Level 2 = parent + 1 digit
- Prevents creating Level 1 account conflicting with prescribed Circular 99 account
- Balance check before deactivation: all sub-accounts must have zero balance

### Missing Approval Flows
- Account deactivation requires Chief Accountant approval
- FS mapping changes require auditor review
- Migration execution requires dual authorization (Chief Accountant + System Admin)

### Missing Compliance Controls
- COA version frozen at period-end: no changes after closing
- FS mapping version must match COA version for that period

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **COA Master Data** | Account management, hierarchy, status |
| **COA Seed/Import** | Bulk load from Circular 99 standard, CSV import |
| **Posting Validation Engine** | Enforce Level 1/2 posting, normal balance, active status |
| **Foreign Currency Engine** | FX rate application, prepayment tracking, period-end revaluation |
| **FS Mapping** | Account-to-FS-line mapping, versioning, validation |
| **COA Migration** | Circular 200/133 to Circular 99 transition toolkit |
| **COA Audit** | Immutable change log, version snapshots, compliance reports |
| **Opening Balance** | One-time balance entry per account at setup |

---

## 6. Suggested Improvements

### Business Improvements
1. **Industry-specific COA templates:** Pre-configure sub-accounts for manufacturing, construction, trading, services on top of standard COA
2. **COA comparison tool:** Compare enterprise COA against Circular 99 standard

### Process Improvements
1. **Closed-period COA locking:** Automatically freeze COA for closed periods
2. **Automated FS mapping validation:** Run completeness check at period-end

### Technical Improvements
1. **COA API:** Expose account lookup, validation, balance inquiry for integration
2. **Real-time FS preview:** Show journal entry impact on FS lines at posting time

### Compliance Improvements
1. **Regulatory update notification:** Alert when new circular amends COA
2. **Dual COA mode:** Support parallel old/new COA during migration transition
