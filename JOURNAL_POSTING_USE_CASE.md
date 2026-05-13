# Use Case Specification

## Double-Entry Journal Posting — Circular 99/2025/TT-BTC

---

## 1. Source

- **URL:** https://thuvienphapluat.vn/van-ban/Doanh-nghiep/Thong-tu-99-2025-TT-BTC-huong-dan-Che-do-ke-toan-doanh-nghiep-565484.aspx
- **Domain Context:** General ledger journal posting engine for Vietnamese enterprise accounting under Circular 99/2025/TT-BTC.
- **Regulatory Context:** VAS 01 (Framework — double-entry principle), VAS 21 (Financial Statement Presentation), Vietnamese Enterprise Accounting Law §29–35.
- **Analysis Summary:** The double-entry journal posting engine is the core of the accounting system. Every financial transaction flows through: (1) journal entry creation, (2) posting to general ledger, (3) trial balance extraction, (4) period-end closing, (5) financial statement generation. The engine must enforce: debits = credits, posting to detail accounts only, normal balance side, period integrity, and immutability after posting.

---

## 2. Domain Breakdown

### Domain 1: Journal Entry Management

#### UC-01: Create Journal Entry

**Description:** Create a journal entry (chứng từ ghi sổ) with one or more debit/credit lines referencing COA accounts. Each entry represents a single financial transaction.

**Goal:** Record an economic event in the accounting system with balanced debit and credit amounts.

**Primary Actors:** Accountant

**Supporting Actors:** ERP System (automatic posting from sub-ledgers)

**Preconditions:**
- COA is configured with detail accounts (Level 2+)
- Period is open (not closed)
- User has posting permission

**Trigger:**
- Business transaction occurs (purchase, sale, payment, receipt)
- Manual adjustment required
- Period-end closing entries

**Main Flow:**
1. Accountant (or sub-ledger system) specifies:
   - Entry date (within current open period)
   - Description/reference
   - One or more lines: (account code, debit amount, credit amount)
2. System validates each line:
   a. Account code exists and is a **detail account** (Level 2+). If Level 1 → reject.
   b. Account is **Active** (not deactivated). If inactive → reject.
   c. Amount is positive and non-zero.
   d. At least one line has debit > 0 and one has credit > 0.
3. System validates the entry as a whole:
   a. **Total debits = Total credits**. If unequal → reject with difference.
   b. All lines share the same period.
4. System assigns a unique journal entry number (configurable format: e.g., `CT-2026-00001`).
5. System records the entry with status "Pending" (unposted).
6. System logs: user, timestamp, IP address.

**Alternate Flow:**
- **Auto-generated from sub-ledger:** Sales invoice posts automatically: Dr AR — Cr Revenue. System uses pre-defined accounting templates (UC-04).
- **Reversing entry:** Created at period open to reverse prior-period accruals. Automatically reverses on a specified date.

**Exception Flow:**
- If any referenced account does not exist, system rejects the entire entry (atomic).
- If period is closed, system rejects with "Period locked" message.
- If entry causes negative cash balance (configurable), system warns but allows.

**Business Rules:**
- BR01: Total debits MUST equal total credits for every journal entry.
- BR02: Each line references exactly one detail account (Level 2+). Level 1 accounts are control accounts — direct posting prohibited.
- BR03: Each line must have EITHER debit > 0 OR credit > 0, never both, never zero.
- BR04: Debit to asset/expense account = increase; debit to liability/equity/revenue = decrease.
- BR05: Credit to asset/expense account = decrease; credit to liability/equity/revenue = increase.
- BR06: Entry date must fall within an open accounting period.
- BR07: Journal entry number is auto-generated, configurable per document type.
- BR08: Once saved, journal entries cannot be edited or deleted — only reversed (UC-06).
- BR09: Foreign currency entries require original currency amount, exchange rate, and VND equivalent.

**Input Data:**
- Date, description, reference
- Lines: [{account_code, debit_amount, credit_amount, memo}]
- Currency (VND or foreign)
- User ID

**Output Data:**
- Journal entry record with unique number
- Audit log entry
- Pending (unposted) status

**Dependencies:**
- COA must be loaded (accounts exist)
- Accounting period must be configured

**Frequency:** Daily (high volume)

**Priority:** Critical

---

#### UC-02: Post Journal Entry to General Ledger

**Description:** Post a pending journal entry to the general ledger, updating account balances. Once posted, the entry becomes immutable and affects financial reports.

**Goal:** Migrate the entry from draft state to the ledger, making it part of the financial record.

**Primary Actors:** Accountant, Chief Accountant

**Preconditions:**
- Journal entry exists with status "Pending" (UC-01)
- Entry is balanced (debits = credits)

**Trigger:**
- Manual posting action
- Batch posting at period-end
- Automatic posting from sub-ledger

**Main Flow:**
1. User selects pending entry and requests "Post".
2. System re-validates:
   a. Entry is still balanced.
   b. All accounts are still active.
   c. Period is still open.
3. System updates each account's running balance:
   - Asset/Expense accounts: debit → balance increases; credit → balance decreases.
   - Liability/Equity/Revenue accounts: credit → balance increases; debit → balance decreases.
4. System sets entry status to "Posted".
5. System records posting timestamp and user.
6. Entry becomes immutable — no edits, no deletion.

**Alternate Flow:**
- **Batch posting:** Multiple entries posted in a single transaction. If any fails, all roll back.
- **Automatic posting:** Sub-ledger (AP, AR, inventory, payroll) posts entries automatically via API.

**Exception Flow:**
- If any account was deactivated between entry creation and posting, system blocks and notifies.
- If period was closed since entry creation, system blocks.

**Business Rules:**
- BR10: Posted entries are IMMUTABLE — cannot be edited, deleted, or modified.
- BR11: Posting updates account running balances in real-time.
- BR12: Posting timestamp is the ledger timestamp, NOT the entry date.
- BR13: Sub-ledger auto-posting uses pre-defined accounting templates (UC-04).
- BR14: Period-end batch posting locks the period after completion.

**Input Data:** Journal entry ID

**Output Data:**
- Updated account balances
- Posted entry with timestamp
- GL impact (running balance before/after per account)

**Priority:** Critical

---

#### UC-03: Validate Account Posting Rules

**Description:** Enforce COA-specific posting logic: detail-only posting, normal balance side, active status check, and foreign currency treatment.

**Goal:** Prevent erroneous postings that violate accounting principles.

**Primary Actors:** System (automatic)

**Main Flow:**
1. For each journal line, validate:
   a. Account is Level 2+ (detail account).
   b. Account status is Active.
   c. Normal balance:
      - Dr entry to asset/expense = within normal side → allowed.
      - Cr entry to asset/expense = contra entry → allowed (but warned if excessive).
      - Cr entry to liability/equity/revenue = within normal side → allowed.
      - Dr entry to liability/equity/revenue = contra entry → allowed (but warned).
2. For foreign currency lines:
   a. Monetary accounts (cash, AR, AP) track original currency + VND.
   b. Exchange rate at transaction date is recorded.
   c. Period-end revaluation follows VAS 10 via TK 413.

**Business Rules:**
- BR15: Posting to Level 1 accounts is strictly prohibited (exception: Chief Accountant override with audit log).
- BR16: Account deactivation blocks all postings regardless of user role.
- BR17: Normal balance: Asset/Expense = Debit; Liability/Equity/Revenue = Credit.
- BR18: Contra entries (opposite to normal side) are allowed but flagged for review.

**Priority:** Critical

---

#### UC-04: Manage Accounting Templates

**Description:** Define pre-configured journal entry templates for recurring transactions (e.g., purchase receipt, sales invoice, salary payment, depreciation).

**Goal:** Automate routine journal entries from sub-ledger events with consistent account mapping.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. Chief Accountant defines template:
   - Trigger event (e.g., "Purchase Receipt")
   - Entry lines with account codes and Dr/Cr rules
   - Variables: amount, tax, date, reference
2. System stores template.
3. When triggered, system generates entry with actual values.
4. System posts entry automatically (or queues for approval).

**Business Rules:**
- BR19: Templates must reference detail accounts only.
- BR20: Template-generated entries follow same validation as manual entries.
- BR21: Template changes require Chief Accountant approval.

**Priority:** High

---

### Domain 2: Period-End Closing

#### UC-05: Perform Period-End Closing

**Description:** Close the accounting period: verify all entries are posted, run trial balance, execute closing entries, lock the period.

**Goal:** Finalize the period's financial records. Prevent further changes.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. **Pre-close checklist:**
   a. All sub-ledgers are posted to GL (AP, AR, inventory, payroll, FA depreciation).
   b. All bank reconciliations are complete.
   c. All intercompany transactions are reconciled.
   d. No unposted entries remain.
2. **Run trial balance** (UC-07).
3. **Closing entries:**
   a. Close revenue accounts (Class 5, 7): Dr Revenue — Cr P&L (TK 911).
   b. Close expense accounts (Class 6, 8): Dr P&L — Cr Expense.
   c. Calculate net profit/loss on TK 911.
   d. Close P&L to Retained Earnings (TK 421).
4. **Period lock:**
   a. Set period status to "Closed".
   b. No new entries can be posted to this period.
   c. Generate period-end reports.
5. **Open next period.**

**Alternate Flow:**
- **Loss:** Dr Retained Earnings — Cr P&L (reverse of profit entry).
- **Re-opening (with auditor approval):** Chief Accountant can re-open a closed period for corrections, subject to audit notification.

**Business Rules:**
- BR22: All revenue and expense accounts must be zeroed at period-end via closing entries.
- BR23: Profit distribution entries (dividends, bonuses) are posted AFTER closing to retained earnings.
- BR24: Closed period cannot be re-opened without dual authorization (Chief Accountant + Auditor).
- BR25: Inventory count adjustments must be posted before closing.

**Priority:** Critical

---

#### UC-06: Reverse or Adjust Posted Entry

**Description:** Correct an error in a posted journal entry. Direct editing is prohibited — corrections use reversing entries or adjusting entries.

**Goal:** Maintain audit trail while correcting errors.

**Primary Actors:** Accountant, Chief Accountant

**Main Flow:**
1. **Reversal (full correction):**
   a. Create new entry with opposite Dr/Cr of the original.
   b. Reference the original entry number.
   c. Post the reversal.
   d. The original and reversal net to zero.
2. **Adjustment (partial correction):**
   a. Create new entry with only the incorrect amount difference.
   b. Post the adjustment.
   c. Original and adjustment together show correct amounts.

**Business Rules:**
- BR26: Posted entries are NEVER edited or deleted.
- BR27: Reversing entries reference the original entry for audit trail.
- BR28: Adjusting entries must include a narrative explaining the correction.
- BR29: Prior-period corrections require Chief Accountant approval.

**Priority:** High

---

### Domain 3: Financial Reporting

#### UC-07: Generate Trial Balance

**Description:** List all COA accounts with their debit/credit balances at a point in time. Total debits must equal total credits.

**Goal:** Verify the accounting equation: Assets = Liabilities + Equity.

**Primary Actors:** Accountant

**Main Flow:**
1. User selects period-end date.
2. System queries: for each active COA account, SUM(debits) — SUM(credits) = balance.
3. System presents:
   - Account code, name
   - Debit balance (if Dr > Cr)
   - Credit balance (if Cr > Dr)
4. System computes totals: total debits, total credits.
5. System validates: total debits = total credits.
6. If unequal, system flags accounts with errors.

**Business Rules:**
- BR30: Trial balance must balance: total debits = total credits.
- BR31: Asset/Expense accounts normally have debit balance.
- BR32: Liability/Equity/Revenue accounts normally have credit balance.
- BR33: Trial balance is the source for financial statements.

**Priority:** Critical

---

#### UC-08: Generate Financial Statements

**Description:** Produce Balance Sheet (BC 01), Income Statement (BC 02), Cash Flow Statement (BC 03), and Notes (BC 09) from the trial balance.

**Goal:** Meet statutory reporting requirements.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. System loads trial balance (UC-07).
2. System maps each account balance to FS line item per FS mapping (UC-05 in COA spec).
3. **Balance Sheet:**
   - Assets (Class 1–2) → current/long-term classification.
   - Liabilities (Class 3) → current/long-term.
   - Equity (Class 4).
4. **Income Statement:**
   - Revenue (Class 5) — Cost of goods sold (TK 632) — Expenses (Class 6, 8) = Net profit/loss.
5. **Cash Flow:**
   - Direct or indirect method.
6. **Notes:** Accounting policies, contingent liabilities, related party transactions.

**Business Rules:**
- BR34: FS must match trial balance totals.
- BR35: Prior-year comparatives shown alongside current period.
- BR36: FS signed by Chief Accountant and Legal Representative.
- BR37: Submitted to tax authority within 90 days of year-end (or 180 days for large enterprises).

**Priority:** Critical

---

### Domain 4: Foreign Currency & Revaluation

#### UC-09: Revalue Foreign Currency Balances

**Description:** At period-end, revalue all monetary items (cash, AR, AP) at the closing exchange rate. Record unrealized gain/loss through TK 413.

**Goal:** Ensure foreign currency balances reflect current exchange rates per VAS 10.

**Primary Actors:** Accountant

**Main Flow:**
1. Identify all monetary accounts with foreign currency balances.
2. Apply closing rate (average buying/selling transfer rate of commercial bank).
3. Calculate revaluation difference: (closing rate − book rate) × balance.
4. Record entry: Dr/Cr TK 413 — Cr/Dr corresponding monetary account.
5. Disclose revaluation policy in FS notes.

**Business Rules:**
- BR38: All monetary items revalued at period-end.
- BR39: Exchange differences → TK 413 (not P&L until realized).
- BR40: Revaluation rate must be consistently applied and disclosed.

**Priority:** High

---

### Domain 5: Audit & Compliance

#### UC-10: Maintain Journal Audit Trail

**Description:** Record every journal entry event (create, post, reverse, adjust) with user, timestamp, before/after values. Immutable log.

**Goal:** Full auditability for statutory auditors and tax authorities.

**Primary Actors:** System (automatic), Auditor

**Main Flow:**
1. Every journal entry event logs:
   - Timestamp, user, IP
   - Action (create, post, reverse, adjust)
   - Entry ID, account code, amount, Dr/Cr
   - Before/after balance for affected accounts
2. Log is append-only. No deletion.
3. Auditor can query by: date range, user, account, entry number.

**Business Rules:**
- BR41: All journal entry actions are logged immutably.
- BR42: Deletion of journal entries is physically impossible.
- BR43: Audit log retained for minimum 10 years (per Vietnamese accounting law).

**Priority:** High

---

#### UC-11: Lock/Unlock Accounting Period

**Description:** Control period status: Open, Closing, Closed. Prevent or allow posting based on status.

**Goal:** Maintain period integrity.

**Primary Actors:** Chief Accountant, System Administrator

**Main Flow:**
1. New period is created with status "Open".
2. During closing process (UC-05), status changes to "Closing" (prevents new entries from sub-ledgers).
3. After closing entries are posted, status changes to "Closed".
4. Re-opening requires dual authorization.

**Business Rules:**
- BR44: Only one period can be "Open" at a time.
- BR45: "Closing" status prevents new journal entries.
- BR46: "Closed" status blocks ALL postings.

**Priority:** Critical

---

## 3. Cross-Use Case Analysis

### End-to-End Journal Entry Flow

```
Business Event (purchase, sale, etc.)
    │
    ▼
UC-01: Create Journal Entry (Dr/Cr lines, account validation)
    │
    ▼
UC-03: Validate Posting Rules (detail account, normal balance, active status)
    │
    ▼
UC-02: Post to General Ledger (update account balances, immutable record)
    │
    ▼
[Period-end]
    │
    ├── UC-09: Revalue FX balances
    ├── UC-07: Generate Trial Balance
    ├── UC-05: Closing entries (zero revenue/expense, transfer to retained earnings)
    ├── UC-08: Generate Financial Statements (BC01–BC09)
    └── UC-11: Lock Period
```

### Overlapping Use Cases
- UC-03 (Posting Validation) consumed by UC-01 (Create Entry) and UC-02 (Post Entry)
- UC-07 (Trial Balance) consumed by UC-05 (Closing) and UC-08 (FS Generation)
- UC-06 (Reverse Entry) depends on UC-02 (Posted entries exist)

### Workflow Gaps
- No explicit use case for **budget checking** (warn when posting would exceed budget)
- No explicit use case for **approval workflow** (manager approval required for entries above threshold)
- No explicit use case for **intercompany reconciliation** (matching entries between related entities)

### Potential System Risks
- **Orphan sub-ledger entries:** If sub-ledger posts to GL but sub-ledger record fails, entries are orphaned
- **Double-posting:** If sub-ledger sends duplicate, GL receives duplicate entries
- **Reversal without reference:** If reversal doesn't reference original, audit trail is broken
- **Period-end race condition:** If two users close the period simultaneously, entries could be lost

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Approval Workflow | Route high-value entries for manager approval before posting | High |
| Budget Checking | Compare entry amount against period budget; warn/block if exceeded | Medium |
| Intercompany Matching | Auto-reconcile intercompany AR/AP between entities | Medium |
| Batch Import | Import journal entries from CSV/Excel | Medium |

### Missing Validation Rules
- Entry date cannot be before the account was activated
- Entry date cannot be after the account was deactivated
- Foreign currency entry requires both original currency amount and exchange rate
- Negative amounts are prohibited (use Dr/Cr direction instead)

### Missing Approval Flows
- Entries above configurable threshold require Chief Accountant approval before posting
- Prior-period corrections require dual authorization
- Period re-opening requires auditor notification

### Missing Audit Trails
- Exchange rate used per foreign currency line (rate lookup point-in-time)
- GL snapshot at period-end (freeze balances for audit)

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Journal Entry Engine** | Create, validate, post journal entries |
| **Accounting Template Engine** | Define and execute auto-posting rules |
| **GL Posting Engine** | Update account balances, maintain immutable ledger |
| **Trial Balance** | Generate period-end trial balance |
| **Period Management** | Open/close periods, lock/unlock |
| **FX Revaluation** | Period-end FX revaluation via TK 413 |
| **Closing Engine** | Execute closing entries (revenue/expense → P&L → retained earnings) |
| **Audit Log** | Immutable journal entry audit trail |

---

## 6. Suggested Improvements

### Business Improvements
1. **Real-time trial balance:** Always-available trial balance without waiting for period-end
2. **Automated closing checklist:** System tracks pre-close tasks (all sub-ledgers posted, reconciliations done) and prevents closing until complete

### Process Improvements
1. **Three-way matching:** Purchase Order → Goods Receipt → Supplier Invoice must match before AP entry is created
2. **Segregation of duties:** Entry creator cannot be the entry approver

### Technical Improvements
1. **Database transaction:** Journal entry creation + posting wrapped in DB transaction for atomicity
2. **Idempotency key:** Prevent duplicate sub-ledger posting via idempotency key (unique reference per source event)
3. **Drill-down:** From FS line → trial balance → journal entry → source document
