# Use Case Specification

## Cash and Bank Accounting (Kế toán vốn bằng tiền) — Circular 99/2025/TT-BTC

---

## 1. Source

- **URL:** https://ketoanleanh.edu.vn/kinh-nghiem-ke-toan/ke-toan-von-bang-tien-la-gi.html
- **Domain Context:** Cash and bank accounting (Vốn bằng tiền) for Vietnamese enterprises under Circular 99/2025/TT-BTC, covering TK 111 (Cash on hand), TK 112 (Bank deposits), and TK 113 (Cash in transit).
- **Regulatory Context:** VAS 01 (Framework — monetary asset measurement), VAS 10 (Foreign Exchange), Circular 99/2025/TT-BTC (Accounts 111–113), Vietnamese Enterprise Accounting Law §29–35 (documentary evidence requirements).
- **Analysis Summary:** The source defines the full lifecycle of cash and bank accounting: receipt/payment processing, documentary evidence management, cash book maintenance, bank deposit tracking, cash in transit accounting, and period-end reconciliation. The system must enforce: complete and accurate recording, proper cost allocation, correct account classification, and full supporting documentation for every transaction.

---

## 2. Domain Breakdown

### Domain 1: Cash Receipt Management

#### UC-01: Process Cash Receipt

**Description:** Record incoming cash from customers, debtors, or other sources. Generate cash receipt voucher (Phiếu thu) as the primary documentary evidence for every cash inflow transaction.

**Goal:** Ensure every cash inflow is recorded with complete, accurate, and timely documentation.

**Primary Actors:** Accountant, Cashier (Thủ quỹ)

**Supporting Actors:** Customer, Accounts Receivable Clerk

**Preconditions:**
- Cash account (TK 111) is active in COA
- Counterparty account (AR, revenue, or other) is active
- Cashier has physical custody of cash

**Trigger:**
- Customer pays outstanding invoice
- Cash sale occurs
- Debtor settles obligation
- Advance recovery from employee
- Miscellaneous cash receipt

**Main Flow:**
1. Cashier receives cash from payer
2. Cashier verifies amount matches supporting document (invoice, receipt advice)
3. Cashier prepares cash receipt voucher (Phiếu thu) with:
   - Receipt date
   - Payer name and address
   - Amount in words and figures
   - Description/purpose
   - Supporting document reference
4. Accountant reviews voucher for:
   a. Correct debit account (TK 111 — Cash on hand)
   b. Correct credit account (TK 131 — AR, TK 511 — Revenue, etc.)
   c. Amount accuracy
   d. Completeness of supporting documents
5. Accountant approves and records in accounting system:
   - Dr TK 111 — Cr corresponding account
6. Cashier updates cash book (Sổ quỹ tiền mặt)
7. System updates account balances in real-time

**Alternate Flow:**
- **Partial receipt:** Customer pays less than full invoice amount. System records partial receipt against invoice, maintains outstanding balance.
- **Prepayment receipt:** Customer pays in advance. Dr TK 111 — Cr TK 131 (or customer prepayment account).
- **Foreign currency cash receipt:** Record at spot exchange rate. Track both original currency and VND equivalent.

**Exception Flow:**
- If cash count differs from documented amount, transaction is blocked until discrepancy is resolved.
- If payer identity cannot be verified, transaction is held for manager approval.
- If supporting documents are incomplete, transaction is rejected.

**Business Rules:**
- BR01: Every cash receipt must have a corresponding cash receipt voucher (Phiếu thu).
- BR02: Cash receipt voucher must be sequentially numbered.
- BR03: Amount must be recorded in both figures and words.
- BR04: Dr TK 111 always; Cr account depends on transaction nature.
- BR05: Foreign currency receipts recorded at spot exchange rate on transaction date.
- BR06: Receipt cannot be posted without matching supporting document.
- BR07: Once posted, cash receipt cannot be deleted — only reversible via adjusting entry (UC-06 in Journal Posting spec).

**Input Data:**
- Receipt date, voucher number, payer, amount (VND/FC), exchange rate, description
- Debit: TK 111 (auto-assigned)
- Credit: counterparty account code
- Supporting document reference(s)

**Output Data:**
- Cash receipt voucher (printed/electronic)
- Account balance update: TK 111 increased, counterparty decreased/increased
- Cash book entry
- Audit log entry

**Dependencies:**
- COA configured with TK 111 active
- Invoice/AR record exists (for customer payments)
- Cash book initialized

**Frequency:** Daily (high volume)

**Priority:** Critical

**Compliance Impact:** Statutory requirement per Circular 99 — cash receipt voucher is mandatory for tax inspection.

---

### Domain 2: Cash Payment Management

#### UC-02: Process Cash Payment

**Description:** Record outgoing cash for expenses, supplier payments, advances, and other disbursements. Generate cash payment voucher (Phiếu chi) as the primary documentary evidence.

**Goal:** Ensure every cash outflow is properly authorized, documented, and recorded.

**Primary Actors:** Accountant, Cashier

**Supporting Actors:** Payee, Department Manager, Accounts Payable Clerk

**Preconditions:**
- Cash account (TK 111) has sufficient balance
- Counterparty account (expense, AP, or other) is active
- Payment has been authorized per delegation of authority

**Trigger:**
- Supplier invoice due for payment
- Operating expense incurred
- Employee advance requested
- Petty cash reimbursement
- Salary/wage payment
- Tax payment

**Main Flow:**
1. Requisitioner submits payment request with supporting documents (invoice, PO, contract)
2. Approver authorizes payment per authority limit
3. Cashier verifies:
   a. Authorization signature exists
   b. Supporting documents are valid
   c. Cash balance is sufficient
4. Cashier disburses cash to payee and obtains signature/acknowledgment
5. Cashier prepares cash payment voucher (Phiếu chi) with:
   - Payment date
   - Payee name and address
   - Amount in words and figures
   - Description/purpose
   - Supporting document reference
6. Accountant records in accounting system:
   - Dr corresponding expense/liability account
   - Cr TK 111 (Cash on hand)
7. Cashier updates cash book
8. System updates account balances

**Alternate Flow:**
- **Partial payment:** Supplier invoice partially paid. System records partial payment, maintains outstanding AP balance.
- **Prepayment:** Advance payment to supplier. Dr prepayment/advance account — Cr TK 111.
- **Petty cash reimbursement:** Dr relevant expense accounts — Cr TK 111 (replenishment flow in UC-04).
- **Foreign currency payment:** Record at spot or book exchange rate per entity policy.

**Exception Flow:**
- If cash balance is insufficient, transaction is blocked.
- If authorization is missing or exceeds delegated limit, transaction is rejected.
- If supporting documents are invalid (missing VAT invoice, incorrect supplier), transaction is held.

**Business Rules:**
- BR08: Every cash payment must have a corresponding cash payment voucher (Phiếu chi).
- BR09: Cash payment voucher must be sequentially numbered (separate sequence from receipts).
- BR10: Payment requires authorized approval before execution.
- BR11: Dr account must reflect the economic nature of the expenditure.
- BR12: Cr TK 111 always.
- BR13: VAT-deductible expenses require valid VAT invoice; input VAT (TK 133) must be separated.
- BR14: Once posted, cash payment cannot be deleted — only reversible.

**Input Data:**
- Payment date, voucher number, payee, amount, description
- Debit: expense/asset/liability account code
- Credit: TK 111 (auto-assigned)
- Authorization reference, supporting document reference(s)

**Output Data:**
- Cash payment voucher (printed/electronic)
- Account balance update: TK 111 decreased, counterparty increased/decreased
- Cash book entry
- Audit log entry

**Dependencies:**
- COA configured with TK 111 active
- Sufficient cash balance
- Authorization matrix configured

**Frequency:** Daily (high volume)

**Priority:** Critical

**Compliance Impact:** Statutory requirement per Circular 99 — cash payment voucher and authorization are mandatory for tax inspection.

---

### Domain 3: Bank Deposit Management

#### UC-03: Process Bank Deposit and Withdrawal

**Description:** Record cash deposits to bank accounts and withdrawals from bank accounts. Track movement between cash on hand (TK 111) and bank deposits (TK 112), as well as direct bank transactions.

**Goal:** Ensure all bank account movements are accurately recorded and reconciled with bank statements.

**Primary Actors:** Accountant, Cashier

**Supporting Actors:** Bank Teller, ERP System (bank feed)

**Preconditions:**
- Bank account (TK 112) is configured in COA with correct bank account details
- Corresponding cash or counterparty account is active

**Trigger:**
- Excess cash deposited to bank
- Cash withdrawn from bank for operations
- Customer pays directly to bank account
- Supplier paid via bank transfer
- Bank charges, interest income posted by bank

**Main Flow (Deposit):**
1. Cashier prepares cash for deposit
2. Cashier completes bank deposit slip at bank
3. Bank confirms deposit with stamped slip
4. Accountant records:
   - Dr TK 112 (Bank deposit) — Cr TK 111 (Cash on hand)
5. Cashier reduces cash book balance
6. System updates both account balances

**Main Flow (Withdrawal):**
1. Requisitioner submits payment request for bank transfer
2. Approver authorizes
3. Accountant initiates bank transfer or prepares withdrawal slip
4. Bank executes transfer/disburses cash
5. Accountant records:
   - Dr TK 111 (Cash on hand) — Cr TK 112 (Bank deposit) for cash withdrawal
   - OR Dr expense/liability account — Cr TK 112 for direct payment

**Alternate Flow:**
- **Direct customer payment to bank:** Customer pays invoice directly to company bank account. Skip cash step: Dr TK 112 — Cr TK 131/511.
- **Direct supplier payment from bank:** Company pays supplier invoice via bank transfer. Dr TK 331 — Cr TK 112.
- **Bank interest:** Period-end bank interest credited. Dr TK 112 — Cr TK 515 (Finance income).
- **Bank charges:** Service fees debited by bank. Dr TK 642 — Cr TK 112.

**Exception Flow:**
- If deposit slip is lost, transaction is held until bank confirmation obtained.
- If bank transfer fails (insufficient funds, incorrect account), transaction is reversed and flagged.

**Business Rules:**
- BR15: Bank deposit requires stamped bank deposit slip as supporting document.
- BR16: Bank withdrawal requires authorized payment order or withdrawal slip.
- BR17: Direct bank transactions (interest, fees) recorded on bank statement date.
- BR18: Dr TK 112 for deposits; Cr TK 112 for withdrawals.
- BR19: Foreign currency bank accounts track original currency, exchange rate, and VND equivalent separately.

**Input Data:**
- Transaction date, bank account code, amount, currency
- Debit/Credit account code per transaction nature
- Bank reference number, deposit/withdrawal slip number
- Exchange rate (for foreign currency accounts)

**Output Data:**
- Bank transaction record
- Account balance update: TK 112 increased/decreased
- Bank reconciliation matching data point

**Dependencies:**
- Bank account master data (bank name, account number, currency)
- Cash account (TK 111) for cash-to-bank movements

**Frequency:** Daily (high volume)

**Priority:** Critical

**Compliance Impact:** Bank transactions are primary audit evidence — every transaction must be traceable to a bank statement or deposit/withdrawal slip.

---

### Domain 4: Cash in Transit Accounting

#### UC-04: Track Cash in Transit

**Description:** Record cash and cheques that have left the entity but not yet been received by the bank (or vice versa). Account TK 113 (Cash in transit) serves as a clearing account for timing differences.

**Goal:** Ensure accurate balance sheet presentation by capturing the time lag between cash movement and bank crediting.

**Primary Actors:** Accountant

**Supporting Actors:** Cashier, Bank

**Preconditions:**
- TK 113 (Cash in transit) is active in COA
- Source and destination accounts are active

**Trigger:**
- Cash deposited at bank but not yet credited (end-of-day deposit)
- Cheque received from customer but not yet cleared
- Cash transferred between bank accounts on different dates
- Cash in transit at period-end cut-off

**Main Flow:**
1. Entity deposits cash or sends cheque to bank
2. At transaction date, accountant records:
   - Dr TK 113 (Cash in transit) — Cr TK 111 (Cash on hand) for cash deposit
   - OR Dr TK 113 — Cr TK 131 (AR) for customer cheque received
3. When bank confirms crediting (next day or later), accountant records:
   - Dr TK 112 (Bank deposit) — Cr TK 113 (Cash in transit)
4. TK 113 balance returns to zero

**Alternate Flow:**
- **Period-end cut-off:** Cash in transit at month-end/quarter-end remains on balance sheet under TK 113. Reversed automatically when bank confirms receipt in the next period.
- **Cheque dishonour:** Customer cheque bounces. Reverse original entry: Dr TK 131 — Cr TK 113. Notify AR clerk for collection action.

**Exception Flow:**
- If cash in transit exceeds reasonable period (e.g., > 5 business days), system flags for investigation.
- If TK 113 balance is non-zero at period-end without valid explanation, system flags for audit review.

**Business Rules:**
- BR20: TK 113 is a transit/clearing account — balance must be zero after bank confirmation.
- BR21: Cash in transit recorded at the amount deposited/transferred (not estimated).
- BR22: Period-end cash in transit must be disclosed in financial statement notes.
- BR23: Cheque dishonour reverses the original receivable and re-instates the customer balance.

**Input Data:**
- Transit date, amount, source account, destination account
- Bank confirmation date (when available)
- Cheque details (number, issuing bank)

**Output Data:**
- Transit journal entries (Dr TK 113 → Cr source; Dr destination → Cr TK 113)
- Period-end transit balance report

**Dependencies:**
- TK 113 configured in COA
- Bank account master data

**Frequency:** Daily (for high-volume cash businesses); periodic at period-end

**Priority:** High

**Compliance Impact:** TK 113 balance must be accurately stated at period-end for true and fair view of cash and cash equivalents per VAS 21.

---

### Domain 5: Cash Book Management

#### UC-05: Maintain Cash Book

**Description:** Maintain the official cash book (Sổ quỹ tiền mặt) as the chronological record of all cash receipts, payments, and daily balance. The cash book is legally mandated and serves as the primary audit trail for cash transactions.

**Goal:** Provide a complete, chronological, auditable record of all cash movements with running balance after each transaction.

**Primary Actors:** Cashier, Accountant

**Supporting Actors:** Auditor

**Preconditions:**
- Cash book is initialized with opening balance
- Cash receipts and payments are recorded (UC-01, UC-02)

**Trigger:**
- Every cash receipt or payment transaction
- End-of-day cash count reconciliation
- Period-end closing
- Audit request

**Main Flow:**
1. Cashier records each cash transaction in cash book immediately after execution:
   - Transaction date
   - Voucher number (Phiếu thu/Phiếu chi)
   - Description
   - Receipt amount (column)
   - Payment amount (column)
   - Running balance
2. End of each day, cashier:
   a. Calculates closing balance = opening balance + total receipts − total payments
   b. Physically counts cash in drawer
   c. Compares physical count to book balance
   d. Records any discrepancy for investigation
3. Accountant reviews cash book periodically for:
   a. Sequential voucher numbering
   b. Unexplained gaps in numbering
   c. Unusual transactions
   d. Large or frequent discrepancies

**Alternate Flow:**
- **Cash over/short:** If physical count differs from book balance, record the difference in a separate account (TK 138 — Other receivables / TK 338 — Other payables) pending investigation. Dr/Cr TK 111 — Cr/Dr TK 138/338.
- **Multi-currency cash book:** Separate cash book maintained per currency. Each tracks both original currency and VND equivalent.

**Exception Flow:**
- If daily cash count is not performed, system flags compliance alert.
- If discrepancy exceeds configurable threshold, system escalates to Chief Accountant.

**Business Rules:**
- BR24: Cash book must be updated in real-time — no batching of entries.
- BR25: Running balance must be calculated after each transaction.
- BR26: Daily physical count is mandatory per internal control standards.
- BR27: Cash over/short must be recorded separately — never netted against receipts/payments.
- BR28: Cash book is a legal book — cannot be deleted or retrospectively modified.
- BR29: Corrections must be made via adjusting entries with clear audit trail (BR07, BR14).
- BR30: Cash book must be printed, signed, and bound at period-end per Circular 99.

**Input Data:**
- All cash receipt and payment transactions
- Daily physical count results

**Output Data:**
- Cash book (chronological register with running balance)
- Daily cash position report
- Cash discrepancy report

**Dependencies:**
- Cash receipt processing (UC-01)
- Cash payment processing (UC-02)

**Frequency:** Continuous (daily)

**Priority:** Critical

**Compliance Impact:** Cash book is a legally required accounting book per Circular 99. Failure to maintain constitutes a compliance violation.

---

### Domain 6: Bank Reconciliation

#### UC-06: Perform Bank Reconciliation

**Description:** Periodically compare the entity's bank account ledger (TK 112) against the bank statement to identify and resolve timing differences, errors, and unauthorized transactions.

**Goal:** Ensure the cash balance per the accounting records matches the bank statement after adjusting for reconciling items.

**Primary Actors:** Accountant (not the cashier — segregation of duties)

**Supporting Actors:** Bank (provides statement), Auditor

**Preconditions:**
- Bank statement is received (electronic or paper)
- All bank transactions up to statement date are recorded in the system
- Previous period reconciliation is balanced

**Trigger:**
- Monthly bank statement received
- Period-end closing
- Discrepancy identified between book and bank balance
- Audit requirement

**Main Flow:**
1. Accountant imports or manually enters bank statement transactions
2. System automatically matches bank transactions to book entries by:
   a. Amount
   b. Transaction date (within tolerance)
   c. Reference number
3. Accountant reviews unmatched items:
   a. **Deposits in transit:** Recorded in books but not yet on bank statement → reconciling item
   b. **Outstanding cheques/payments:** Recorded in books but not yet presented to bank → reconciling item
   c. **Bank charges/interest:** On bank statement but not yet in books → record adjusting entry
   d. **Errors:** Bank error or book error → investigate and correct
4. Accountant records adjusting entries for bank charges, interest, and errors
5. System produces reconciliation report showing:
   - Book balance
   - Plus: deposits in transit
   - Minus: outstanding cheques
   - Plus/minus: adjustments
   - = Adjusted book balance
   - Bank statement balance
   - Plus/minus: bank errors
   - = Adjusted bank balance
   - Confirmation: Adjusted book balance = Adjusted bank balance
6. Accountant approves reconciliation
7. System locks reconciliation for the period

**Alternate Flow:**
- **Automatic bank feed:** Bank provides electronic statement (MT940, CSV, or API). System auto-matches with configurable matching rules. Unmatched items flagged for manual review.
- **Multi-currency reconciliation:** Separate reconciliation per currency. FX rate differences calculated and posted to TK 413 (Exchange rate differences).
- **Prior-period reconciliation:** If prior period was not reconciled, start with opening balance verification before current period reconciliation.

**Exception Flow:**
- If reconciliation does not balance (adjusted book ≠ adjusted bank), accountant must identify the root cause before period-end closing.
- If unreconciled items exceed materiality threshold, system requires Chief Accountant override.
- If reconciliation is not completed before period-end close, system blocks period closing.

**Business Rules:**
- BR31: Bank reconciliation must be performed at least monthly (statutory requirement).
- BR32: Person performing reconciliation must be different from cashier (segregation of duties).
- BR33: All reconciling items must be:
   a. Identified and documented
   b. Aged (older items flagged first)
   c. Resolved within the next reconciliation period
- BR34: Bank charges and interest must be recorded in the period they appear on the bank statement.
- BR35: Reconciliation report must be approved by Chief Accountant.
- BR36: Unreconciled items older than 90 days must be escalated to management.

**Input Data:**
- Bank statement (electronic or scanned)
- Book transactions for TK 112 up to reconciliation date
- Previous reconciliation report (opening position)

**Output Data:**
- Bank reconciliation report (adjusted book balance = adjusted bank balance)
- Adjusting journal entries (bank charges, interest, errors)
- Aged unreconciled items report
- Approval record

**Dependencies:**
- Bank transaction recording (UC-03)
- Cash in transit tracking (UC-04)
- Prior reconciliation completed

**Frequency:** Monthly (minimum); may be performed more frequently for high-volume accounts

**Priority:** Critical

**Compliance Impact:** Mandatory per Vietnamese accounting standards and internal control requirements. Unreconciled bank accounts are a primary audit risk flagged by external auditors and tax authorities.

---

### Domain 7: Petty Cash Management

#### UC-07: Manage Petty Cash Fund

**Description:** Establish, operate, reimburse, and close petty cash funds for small-value operational expenses that are impractical to process through the formal payment cycle.

**Goal:** Provide a controlled, auditable mechanism for small cash disbursements while maintaining proper documentation and approval.

**Primary Actors:** Petty Cash Custodian, Accountant

**Supporting Actors:** Department Staff, Approver

**Preconditions:**
- Petty cash fund is established (imprest system)
- Fund amount is approved by management
- TK 111 sub-account (or separate petty cash account) is configured

**Trigger:**
- Employee needs small cash for office supplies, transport, etc.
- Petty cash fund runs low and requires replenishment
- Period-end petty cash count
- Fund closure or amount change

**Main Flow (Disbursement):**
1. Employee submits petty cash request with supporting documents (receipt, invoice)
2. Approver authorizes disbursement
3. Petty cash custodian disburses cash and obtains employee signature
4. Custodian records disbursement on petty cash voucher
5. Accountant reviews and records (at replenishment time)

**Main Flow (Replenishment):**
1. Custodian totals disbursements and requests reimbursement to restore fund to imprest amount
2. Accountant verifies all disbursements have approved supporting documents
3. Accountant prepares cash payment voucher:
   - Dr relevant expense accounts (per nature of each disbursement)
   - Cr TK 111 (Cash on hand)
4. Cashier disburses replenishment amount to custodian
5. Petty cash fund restored to imprest level

**Alternate Flow:**
- **Fund increase/decrease:** Management approves fund size change. Dr/Cr TK 111 to adjust fund balance.
- **Fund closure:** Return remaining cash to main cashier. Dr TK 111 — Cr petty cash sub-account.

**Exception Flow:**
- If custodian cannot account for all disbursements at replenishment, shortage is deducted from custodian's responsibility or investigated.
- If supporting documents are missing, replenishment is withheld until documents are provided.

**Business Rules:**
- BR37: Petty cash operates on imprest system — fund is restored to fixed amount at each replenishment.
- BR38: Maximum disbursement amount per transaction is defined by company policy.
- BR39: Petty cash cannot be used for capital expenditures, salary advances, or supplier payments.
- BR40: Petty cash custodian is personally responsible for the fund.
- BR41: Surprise petty cash counts must be performed periodically by internal audit.
- BR42: At replenishment, each disbursement is posted to its appropriate expense account — never netted.

**Input Data:**
- Petty cash voucher with amount, purpose, requester, date
- Supporting receipts/invoices
- Approval signature

**Output Data:**
- Disbursement record
- Replenishment journal entry
- Petty cash balance report
- Expense allocation summary

**Dependencies:**
- Cash payment processing (UC-02)
- Expense account configuration in COA

**Frequency:** Daily (disbursements); weekly/bi-weekly (replenishment)

**Priority:** High

**Compliance Impact:** Petty cash is a high-risk area for misappropriation. Proper documentation and surprise counts are standard internal control requirements.

---

### Domain 8: Foreign Currency Cash Management

#### UC-08: Account for Foreign Currency Cash and Bank Transactions

**Description:** Record cash and bank transactions denominated in foreign currencies. Maintain dual-currency tracking (original currency + VND equivalent) and apply period-end revaluation per VAS 10.

**Goal:** Ensure foreign currency cash and bank balances are accurately recorded and reported.

**Primary Actors:** Accountant

**Supporting Actors:** Bank (provides exchange rate), Chief Accountant

**Preconditions:**
- Foreign currency accounts configured (e.g., TK 1122 — USD bank account)
- Exchange rate source is configured (central bank rate, commercial bank rate)

**Trigger:**
- Receipt or payment in foreign currency
- Period-end revaluation
- Exchange rate fluctuation exceeding threshold

**Main Flow:**
1. Accountant records foreign currency transaction:
   - Original currency amount
   - Exchange rate at transaction date
   - VND equivalent = original amount × exchange rate
   - Dr/Cr per transaction nature
2. System maintains dual-currency sub-ledger for each FC account
3. At period-end, system revalues FC balances:
   a. Identify all FC monetary accounts with open balances
   b. Apply period-end closing exchange rate
   c. Calculate revaluation difference: (closing rate − book rate) × FC balance
   d. Record unrealized gain/loss:
      - Dr/Cr TK 111/112 — Cr/Dr TK 413 (Exchange rate differences)
4. Realized FX gain/loss recorded at transaction time for actual conversions

**Alternate Flow:**
- **Advance payment in FC:** Rate locked at prepayment date per VAS 10.
- **Multiple bank rates:** System uses configured rate policy (buying rate, selling rate, transfer rate) consistently.

**Exception Flow:**
- If exchange rate source is unavailable, system uses last available rate and flags for review.
- If revaluation difference exceeds materiality threshold, system requires Chief Accountant approval.

**Business Rules:**
- BR43: Every FC transaction must record original currency amount, exchange rate, and VND equivalent.
- BR44: Exchange rate policy (which rate to use) must be consistently applied and disclosed.
- BR45: Dr TK 111/112 at spot rate; Cr at spot or book rate per entity policy (BR17/BR18 in COA spec).
- BR46: Period-end revaluation applies to all FC monetary accounts (cash, bank, AR, AP).
- BR47: Unrealized FX differences go to TK 413 (not P&L) as per VAS 10.
- BR48: Realized FX differences go to TK 515 (gain) or TK 635 (loss).

**Input Data:**
- FC transaction: currency code, amount, exchange rate, VND equivalent
- Period-end closing exchange rate
- FC account balance sub-ledger

**Output Data:**
- Dual-currency transaction record
- FC account balance report (original currency + VND)
- Period-end revaluation entry
- FX gain/loss report (realized vs. unrealized)

**Dependencies:**
- Exchange rate master data (UC-04 in COA spec)
- FC account configuration in COA
- Cash receipt/payment processing (UC-01, UC-02, UC-03)

**Frequency:** Daily (transactions); monthly/quarterly (revaluation)

**Priority:** High

**Compliance Impact:** VAS 10 mandates period-end FC revaluation. Vietnamese tax authorities require FC transaction details for CIT and VAT purposes.

---

### Domain 9: Cash and Bank Reporting

#### UC-09: Generate Cash and Bank Reports

**Description:** Produce standard and ad-hoc reports on cash and bank positions, movements, and aging to support management decision-making and statutory reporting.

**Goal:** Provide timely, accurate information on the entity's cash position and movement for operational and compliance purposes.

**Primary Actors:** Accountant, Financial Manager, Chief Accountant

**Supporting Actors:** Auditor, Tax Authority

**Preconditions:**
- All cash and bank transactions are recorded
- Cash book is up to date
- Bank reconciliations are current

**Trigger:**
- Daily cash position request
- Period-end (monthly, quarterly, yearly)
- Management request
- Audit request
- Tax inspection

**Main Flow:**
1. User selects report type and date range
2. System queries cash/bank transaction data:
   - Cash book (Sổ quỹ tiền mặt)
   - Bank ledger (Sổ tiền gửi ngân hàng)
   - Cash in transit register
   - FC cash tracking report
3. System generates report with:
   a. Opening balance
   b. Total receipts (itemized by category)
   c. Total payments (itemized by category)
   d. Closing balance
   e. Daily/weekly/monthly trends
4. For period-end statutory reports:
   a. Cash and cash equivalents disclosure (BC 01 — Balance Sheet, line 110)
   b. Cash flow statement (BC 03 — Báo cáo lưu chuyển tiền tệ)
   c. Notes to financial statements
5. Report is exported to PDF, Excel, or printed

**Alternate Flow:**
- **Cash flow forecast:** Project future cash position based on scheduled receipts (AR aging) and payments (AP aging).
- **Multi-bank consolidation:** Aggregate balances across all bank accounts in a single report.
- **FX report:** Show FC cash balances in original currency and VND equivalent.

**Business Rules:**
- BR49: Cash and cash equivalents = TK 111 + TK 112 + TK 113 (short-term, highly liquid).
- BR50: Cash flow statement (BC 03) must reconcile to opening/closing cash balance per BR49.
- BR51: Restricted cash (pledged as collateral) must be disclosed separately.
- BR52: Reports must be available on-demand with real-time data.

**Input Data:**
- Report type, date range, currency, bank account filter
- Transaction data from UC-01 through UC-08

**Output Data:**
- Cash position report
- Cash book (Sổ quỹ tiền mặt)
- Bank ledger (Sổ tiền gửi ngân hàng)
- Cash flow statement inputs
- FC cash position report

**Dependencies:**
- All cash/bank transaction processing (UC-01–08)
- Bank reconciliation completed for period-end reports

**Frequency:** Daily (cash position); monthly/quarterly/yearly (statutory)

**Priority:** High

**Compliance Impact:** Cash flow statement (BC 03) is a mandated financial statement per Circular 99. Cash disclosures audited annually.

---

## 3. Cross-Use Case Analysis

### Use Case Dependency Graph

```
UC-01: Cash Receipt ──────────────────────────────────────────────────────┐
UC-02: Cash Payment ──────────────────────────────────────────────────────┤
UC-03: Bank Deposit/Withdrawal ───────────────────────────────────────────┤
UC-04: Cash in Transit ───────────────────────────────────────────────────┤
                                                                          │
UC-05: Cash Book ─────────────────────────────────────────────────────────┤
                                                                          │
UC-06: Bank Reconciliation ◄──────────────────────────────────────────────┤
                                                                          │
UC-07: Petty Cash ────────┤                                              │
                          ├── UC-05 (Cash Book) feeds into               │
UC-08: FC Cash ───────────┤              │                               │
                          │              ▼                               ▼
                          └──── UC-09: Cash & Bank Reports ◄──── All UCs
```

### Overlapping Use Cases
- UC-01 (Receipt) and UC-05 (Cash Book): Every receipt immediately updates the cash book.
- UC-02 (Payment) and UC-05 (Cash Book): Every payment immediately updates the cash book.
- UC-03 (Bank) and UC-04 (Transit): Deposits in transit use UC-04 until confirmed, then become UC-03.
- UC-06 (Reconciliation) consumes data from UC-03, UC-04 (transit items), UC-08 (FX).
- UC-09 (Reporting) aggregates data from all other use cases.

### Shared Dependencies
- **TK 111/112/113:** All use cases depend on COA configuration of these three accounts.
- **Documentary evidence:** Every transaction requires supporting documentation (vouchers, receipts, bank slips).
- **Accountant segregation:** UC-02 (payment), UC-06 (reconciliation), UC-07 (petty cash) require different individuals for execution vs. recording vs. reconciliation.
- **Real-time balance:** UC-05 (cash book running balance) is consumed by UC-02 (sufficient balance check).

### Workflow Gaps
- No explicit use case for **electronic bank statement import** (auto-matching from MT940/CSV).
- No explicit use case for **cash flow forecasting** from historical data.
- No explicit use case for **bank account master data management** (add/update/close bank accounts).
- No explicit use case for **cash transfer between bank accounts** of the same entity.
- No explicit use case for **L/C (Letter of Credit) tracking** for import payments.

### Inconsistent Terminology
- Source material uses "Thu tiền" / "Chi tiền" (receive/pay money) interchangeably with "Phiếu thu" / "Phiếu chi" (receipt/payment voucher). Normalized to "Process Cash Receipt" and "Process Cash Payment" as the business capability, with vouchers as the documentary evidence.

### Potential System Risks
- **Real-time vs. batch conflict:** Cash book (UC-05) requires real-time updating, but bank reconciliation (UC-06) is periodic. If transactions are posted late, reconciliation fails.
- **Segregation of duties violation:** Same person creating and approving receipts/payments. System must enforce role-based access.
- **Orphaned transit items:** TK 113 entries never cleared. System must age and escalate uncleared transit items.
- **FX rate inconsistency:** Different rates used for different transactions in the same currency. System must enforce consistent rate policy.
- **Back-dated entries:** Post-dated or back-dated cash transactions could distort period-end balances. System should restrict entry dates to the current open period.

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Bank Account Master Data Management | Create, update, close bank accounts with account number, bank name, currency, branch | High |
| Intra-Entity Bank Transfer | Transfer cash between own bank accounts (Dr TK 112A — Cr TK 112B) | High |
| Electronic Bank Statement Import | Import MT940/CSV bank statements with auto-matching | High |
| Cash Flow Forecasting | Project future cash position from AR/AP aging | Medium |
| L/C and Bank Guarantee Tracking | Track letters of credit, guarantees issued/received | Medium |
| Cash Threshold Alert | Warn when cash balance exceeds or falls below configured thresholds | Low |

### Missing Validation Rules
- Voucher number sequence check: alert on gaps or duplicates
- Daily cash count mandatory before end-of-day system closure
- Maximum cheque value for petty cash
- Bank account closure: zero balance required before deactivation
- Cheque date: post-dated cheques cannot be recorded as cash

### Missing Approval Flows
- Payment above configurable threshold requires dual authorization (two signatures)
- Bank account closure requires Chief Accountant + Financial Manager approval
- Write-off of cash shortage requires management committee approval
- New bank account setup requires board resolution

### Missing Audit Trails
- Cashier shift handover log (opening/closing balance per cashier per shift)
- Cheque book receipt and issuance log
- Bank statement upload timestamp and user
- Cash count certification with witness signature

### Missing Error Handling
- Duplicate bank statement line detection (same reference, amount, date)
- Cash shortage/surplus workflow: record → investigate → resolve → adjust
- Bank reconciliation out-of-balance: block period-end closing

### Missing Compliance Controls
- Cash book must be printed and bound at period-end (Circular 99 requirement)
- Maximum cash payment limit per transaction (tax regulation)
- Monthly cash count certification by Chief Accountant
- Quarterly bank confirmation letter from all banks

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Cash Receipt Engine** | Process, validate, post cash receipts (UC-01) |
| **Cash Payment Engine** | Process, authorize, post cash payments (UC-02) |
| **Bank Transaction Engine** | Record deposits, withdrawals, direct bank debits/credits (UC-03) |
| **Cash in Transit Tracker** | Transit clearing management, aging, auto-reversal (UC-04) |
| **Cash Book** | Real-time cash register, running balance, daily count verification (UC-05) |
| **Bank Reconciliation** | Statement import, auto-matching, adjustment posting, aging (UC-06) |
| **Petty Cash Manager** | Imprest fund tracking, disbursement logging, replenishment (UC-07) |
| **FX Cash Engine** | Dual-currency tracking, revaluation, FX gain/loss calculation (UC-08) |
| **Cash Reporting** | Cash position, cash book, bank ledger, cash flow inputs (UC-09) |
| **Bank Master Data** | Bank account configuration, closure, authorized signatories |
| **Cash Control Dashboard** | Real-time cash position, alerts, pending reconciliation items |

---

## 6. Suggested Improvements

### Business Improvements
1. **Automated bank feed integration:** Connect directly to Vietnamese banks (Vietcombank, Techcombank, BIDV, VietinBank) via API or supported file format for automatic transaction import and reconciliation.
2. **Cash flow forecasting module:** Combine scheduled AR receipts, AP payments, and payroll to project 30/60/90-day cash position.
3. **Multi-branch cash consolidation:** Aggregate cash positions across branches with automatic inter-branch settlement.

### Process Improvements
1. **Approval matrix engine:** Configurable approval thresholds by role, department, and transaction type. Route high-value payments through sequential approvals.
2. **Automated daily cash count:** Integrate with cash registers or POS for automatic end-of-day cash reconciliation.
3. **Bank confirmation automation:** Generate and track bank confirmation letters for period-end audit.

### Technical Improvements
1. **Real-time bank balance integration:** Display actual bank balance alongside book balance from UC-06 reconciliation status.
2. **Cheque management module:** Track cheque books, issued cheques, presented cheques, cancelled cheques, and stop-payment requests.
3. **Batch payment processing:** Upload batch payment file for multiple suppliers with single approval (integrate with bank corporate portal).

### Compliance Improvements
1. **Cash transaction limit enforcement:** Per regulations limiting cash payments for transactions above VND 20 million (or current threshold), block over-limit cash payments and force bank transfer.
2. **Tax authority-ready cash book export:** Generate cash book in the format required by tax inspection (Circular 99 template).
3. **Period-end cash certification workflow:** Chief Accountant must certify cash balance before period-end closing — enforced by system lock.
