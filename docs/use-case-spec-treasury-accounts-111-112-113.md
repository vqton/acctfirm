# Use Case Specification: Treasury Account Management (TK 111, 112, 113)
## Circular 99/2025/TT-BTC — Vietnamese Enterprise Accounting Regime

---

## 1. Source

- **URLs:**
  - https://ketoanthienung.net/cach-hach-toan-tai-khoan-111-tien-mat-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-hach-toan-tai-khoan-112-tien-gui-ngan-hang-khong-ky-han-theo-thong-tu-99.htm
  - https://ketoanthienung.net/hach-toan-tai-khoan-113-tien-dang-chuyen-theo-thong-tu-99.htm
- **Domain Context:** Vietnamese Enterprise Accounting — Treasury & Cash Management
- **Regulatory Context:** Circular 99/2025/TT-BTC issued by Ministry of Finance, effective 2025 (replacing portions of Circular 200/2014/TT-BTC)
- **Analysis Summary:** The three sources prescribe the accounting treatment for three interrelated cash-equivalent accounts: Cash on Hand (111), Non-Term Bank Deposits (112), and Cash in Transit (113). The content covers 40+ distinct journal entry patterns spanning receipts, disbursements, transfers, reconciliation adjustments, period-end revaluation, and suspense clearing. These form the complete treasury transaction lifecycle under the new regime.

### Assumptions

- All use cases assume double-entry bookkeeping under the Vietnamese Chart of Accounts (system TK).
- The enterprise operates under VAS (Vietnamese Accounting Standards), not IFRS.
- Monetary gold transactions follow the same pattern as FX but with domestic purchase price as reference.
- VAT input credits are tracked separately via TK 1331 when applicable.
- Bank overdrafts are treated as loans (TK 341), not negative balances on TK 112.
- All use cases assume proper source documents exist (receipt/payment vouchers for cash, bank advices for deposits).

---

## 2. Domain Breakdown

---

### Domain: Cash Receipt Management

Processing all incoming cash flows registered on Account 111 — Cash on Hand.

---

#### UC-001: Record Cash Sales Revenue

**Description**
Record revenue from cash sales of goods or services, including indirect tax handling (VAT, excise tax, export tax).

**Goal**
Accurately recognize sales revenue and tax obligations when cash is received at point of sale.

**Primary Actors**
- Accountant
- Cashier

**Supporting Actors**
- Customer

**Preconditions**
- Cash has been received and verified at the cash register/desk.
- Sales invoice or equivalent source document exists.
- Cash receipt voucher (phiếu thu) has been prepared.

**Trigger**
Customer tenders cash payment for goods or services.

**Main Flow**
1. Cashier receives cash and issues receipt voucher.
2. Accountant determines whether the transaction is subject to indirect tax (VAT, excise, export tax).
3. **If separate tax method** (tax separable at recognition):
   - Debit TK 111 — Cash (total settlement amount)
   - Credit TK 511 — Revenue (ex-tax price)
   - Credit TK 333 — Taxes payable (by each tax type)
4. **If combined method** (tax not separable at recognition):
   - Debit TK 111 — Cash (total settlement amount)
   - Credit TK 511 — Revenue (including tax)
   - Periodically, determine tax obligation and reduce revenue:
     - Debit TK 511 — Revenue
     - Credit TK 333 — Taxes payable
5. Post to cash journal and general ledger.

**Alternate Flow**

- **AF-1: Foreign currency sale**
  Cash received in foreign currency. Record at actual exchange rate on transaction date. FX difference handled at period-end (see UC-027).

**Business Rules**
- BR-001: Indirect tax types must be separated by tax code (33311 = VAT, 3332 = excise, 3333 = export tax).
- BR-002: The method of tax separation (immediate vs. periodic) must be applied consistently per accounting policy.
- BR-003: All cash receipts require a sequentially numbered receipt voucher with authorized signatures.

**Input Data**
- Receipt voucher ID, date, amount (total, ex-tax, by tax type), customer ID, currency, exchange rate, description.

**Output Data**
- Journal entry: Debit TK 111, Credit TK 511 / TK 333.
- Updated cash book balance.
- Updated accounts receivable (if on-account).
- Updated tax payable balance.

**Dependencies**
- Chart of Accounts (TK 111, 511, 333 sub-accounts).
- Tax rate configuration (for automatic tax splitting).

**Frequency**
- Daily / Event-driven.

**Priority**
- Critical.

**Compliance Impact**
- Direct impact on VAT and CIT declarations. Incorrect tax separation leads to under/overpayment of indirect taxes.

---

#### UC-002: Record Cash Financial and Other Income

**Description**
Record financial income (interest, dividends) and other income (gains from non-operating activities) received in cash.

**Goal**
Recognize non-operating income accurately, including VAT obligations on taxable items.

**Primary Actors**
- Accountant

**Preconditions**
- Cash received.
- Supporting document (contract, notice, invoice) available.

**Trigger**
Cash received for interest, dividends, rental income, asset disposal, or other non-sale income.

**Main Flow**
1. Accountant classifies the income type (financial vs. other).
2. Identifies whether the receipt is subject to VAT.
3. Records:
   - Debit TK 111 — Cash (total settlement)
   - Credit TK 515 — Financial income (ex-VAT)
   - Credit TK 711 — Other income (ex-VAT)
   - Credit TK 3331 — VAT payable (if applicable)

**Business Rules**
- BR-004: VAT applies to certain other income items per current tax law.
- BR-005: Financial income (TK 515) and other income (TK 711) must not be commingled.

**Input Data**
- Receipt amount, income category, VAT flag, supporting document reference.

**Output Data**
- Journal entry: Debit TK 111, Credit TK 515/711/3331.

**Frequency**
- Event-driven.

**Priority**
- High.

---

#### UC-003: Record Cash Receivable Recovery

**Description**
Record collection of outstanding receivables, recovery of loans, advances, deposits, and receipt of escrow/collateral in cash.

**Goal**
Reduce receivable balances and recognize cash inflow accurately.

**Primary Actors**
- Accountant

**Preconditions**
- Corresponding receivable/advance/deposit balance exists on balance sheet.
- Cash received and receipt voucher prepared.

**Trigger**
Customer pays outstanding invoice, employee returns unused advance, or third party deposits collateral in cash.

**Main Flow**
1. Accountant identifies the receivable account being recovered.
2. Records:
   - Debit TK 111 — Cash
   - Credit respective receivable account: TK 128, 131, 136, 138, 141, 244, or 344
3. Updates subsidiary ledger for the counterparty.

**Input Data**
- Counterparty ID, original transaction reference, amount, receivable type.

**Output Data**
- Journal entry. Reduced receivable balance. Updated subsidiary ledger.

**Frequency**
- Daily / Event-driven.

**Priority**
- High.

---

#### UC-004: Record Cash Capital Contribution

**Description**
Record owner capital contributions received in cash, including share premium treatment.

**Goal**
Accurately reflect equity increase from cash capital injections.

**Primary Actors**
- Accountant

**Preconditions**
- Capital contribution decision/documentation exists.
- Cash received and verified.

**Trigger**
Founder/owner contributes capital in cash.

**Main Flow**
1. Accountant determines par value vs. contributed amount.
2. If contributed amount > par value (share premium):
   - Debit TK 111 — Cash (total)
   - Debit TK 4112 — Share premium (if contributed < par)
   - Credit TK 4111 — Owner's equity (par value)
   - Credit TK 4112 — Share premium (if contributed > par)
3. Updates equity register.

**Business Rules**
- BR-006: Capital contributions must comply with Enterprise Law and company charter.
- BR-007: Share premium (TK 4112) must track the difference between contributed amount and par value distinctly.

**Input Data**
- Contributor ID, contribution amount, par value per share, number of shares (for JSC).

**Output Data**
- Journal entry. Updated equity balance.

**Frequency**
- Event-driven.

**Priority**
- High.

**Compliance Impact**
- Affects legal capital reporting to Business Registration Authority.

---

#### UC-005: Record Cash Proceeds from Investment Disposal

**Description**
Record cash received from selling short-term or long-term investments, including gain/loss recognition and provision reversal.

**Goal**
Accurately reflect investment disposal outcomes, including realized gain/loss and associated provision adjustments.

**Primary Actors**
- Accountant

**Preconditions**
- Investment asset exists on balance sheet.
- Sale agreement executed.
- Cash received.

**Trigger**
Company sells marketable securities, bonds, or equity investments for cash.

**Main Flow**
1. Accountant calculates difference between sale proceeds and cost.
2. Reverses any related provision (TK 229) if applicable.
3. Records:
   - Debit TK 111 — Cash (proceeds)
   - Debit TK 229 — Provision reversal (if previously provisioned)
   - Debit TK 635 — Financial expense (if loss)
   - Credit TK 121/221/222/228 — Cost of investment
   - Credit TK 515 — Financial income (if gain)
4. Updates investment subsidiary ledger.

**Business Rules**
- BR-008: Provision reversal may be recorded per-transaction or at period-end, but method must be consistent.
- BR-009: Gain/loss calculation uses weighted average cost or specific identification per accounting policy.

**Input Data**
- Investment ID, sale price, cost basis, provision balance, counterparty.

**Output Data**
- Journal entry. Reduced investment balance. Realized gain/loss recognized.

**Frequency**
- Event-driven.

**Priority**
- Medium.

---

#### UC-006: Record Government Subsidy Received in Cash

**Description**
Record subsidy or price support received from the State in cash.

**Goal**
Recognize government subsidy income in accordance with regulatory requirements.

**Primary Actors**
- Accountant

**Preconditions**
- Subsidy approval/documentation received from State authority.
- Cash received.

**Trigger**
State agency disburses subsidy or price support payment.

**Main Flow**
1. Accountant verifies subsidy type and supporting documentation.
2. Records:
   - Debit TK 111 — Cash
   - Credit TK 3339 — Other taxes payable (as prescribed)

**Business Rules**
- BR-010: Government subsidies are recorded via TK 3339 per Circular 99 treatment.

**Input Data**
- Subsidy type, amount, State authority reference, supporting decision number.

**Output Data**
- Journal entry. Updated subsidy tracking register.

**Frequency**
- Event-driven.

**Priority**
- Low.

---

### Domain: Bank Deposit Receipt Management

Processing all incoming fund flows to Account 112 — Non-Term Bank Deposits.

---

#### UC-007: Record Bank Deposit Sales Revenue

**Description**
Record revenue from sales where payment is received via bank transfer (non-term deposit).

**Goal**
Recognize revenue and tax obligations when funds are credited to the company's bank account.

**Primary Actors**
- Accountant

**Supporting Actors**
- Bank
- Customer

**Preconditions**
- Bank credit advice or statement received.
- Sales invoice issued.
- Matching source document available.

**Trigger**
Customer transfers payment for goods/services to company bank account.

**Main Flow**
1. Accountant receives bank credit advice.
2. Verifies against sales invoice and source documents.
3. **If separate tax method**:
   - Debit TK 112 — Bank deposit (total)
   - Credit TK 511 — Revenue (ex-tax)
   - Credit TK 333 — Taxes payable
4. **If combined method**:
   - Debit TK 112 — Bank deposit (total)
   - Credit TK 511 — Revenue (including tax)
   - Periodic adjustment: Debit TK 511, Credit TK 333

**Alternate Flow**

- **AF-1: Customer paid via 113 (cash in transit)**
  If payment was previously recorded as cash in transit (see UC-018), clear suspense:
  - Debit TK 112 — Bank deposit
  - Credit TK 113 — Cash in transit

**Input Data**
- Bank advice ID, customer reference, amount (total/ex-tax/tax), invoice reference, bank account.

**Output Data**
- Journal entry. Updated bank deposit ledger.

**Frequency**
- Daily / Event-driven.

**Priority**
- Critical.

---

#### UC-008: Record Bank Deposit Customer Payment

**Description**
Record customer payment received via bank transfer against outstanding receivables.

**Goal**
Reduce accounts receivable upon confirmed bank receipt.

**Primary Actors**
- Accountant

**Preconditions**
- Outstanding receivable balance exists.
- Bank credit advice received.

**Trigger**
Customer transfers payment for outstanding invoice(s).

**Main Flow**
1. Accountant matches bank credit advice to open receivable.
2. Records:
   - Debit TK 112 — Bank deposit
   - Credit TK 131 — Customer receivable
   - (Or Credit TK 113 if previously recorded as in-transit)

**Input Data**
- Customer ID, invoice reference, amount, bank credit advice number.

**Output Data**
- Updated accounts receivable aging. Reduced AR balance.

**Frequency**
- Daily.

**Priority**
- High.

---

#### UC-009: Record Bank Deposit Miscellaneous Receipt

**Description**
Record financial income, other income, receivable recovery, investment proceeds, capital contributions, and government subsidies received via bank transfer.

**Goal**
Process all non-sale bank deposit receipts with appropriate account classification.

**Primary Actors**
- Accountant

**Preconditions**
- Bank credit advice received.
- Supporting documentation available.

**Trigger**
Any non-sale funds credited to company bank account.

**Main Flow**
1. Accountant classifies the receipt type.
2. Records appropriate entry:

   | Receipt Type | Debit | Credit(s) |
   |---|---|---|
   | Financial/Other Income | TK 112 | TK 515/711 + TK 3331 (if VAT) |
   | Receivable Recovery | TK 112 | TK 128/131/136/141/244/344 |
   | Investment Proceeds | TK 112 + TK 635 (loss) + TK 229 | TK 121/221/222/228 + TK 515 (gain) |
   | Capital Contribution | TK 112 + TK 4112 (discount) | TK 4111 + TK 4112 (premium) |
   | Government Subsidy | TK 112 | TK 3339 |

**Business Rules**
- BR-011: All non-sale bank receipts must be supported by appropriate contracts, decisions, or agreements.
- BR-012: Investment disposal entries follow the same gain/loss/provision logic as UC-005.

**Input Data**
- Receipt type selector, counterparty, contract reference, amount, tax status.

**Output Data**
- Journal entry. Updated bank deposit and relevant asset/liability/equity accounts.

**Frequency**
- Daily / Event-driven.

**Priority**
- High.

---

### Domain: Cash Disbursement Management

Processing all outgoing cash flows from Account 111 — Cash on Hand.

---

#### UC-010: Record Cash Purchase of Inventory and Fixed Assets

**Description**
Record payment in cash for purchase of inventory, raw materials, tools, fixed assets, and construction in progress.

**Goal**
Accurately record asset acquisition and recognize VAT input tax credits when applicable.

**Primary Actors**
- Accountant

**Preconditions**
- Payment voucher (phiếu chi) prepared with authorized signatures.
- Purchase invoice and delivery receipt available.

**Trigger**
Company purchases goods or assets and pays in cash.

**Main Flow**
1. Accountant verifies purchase invoice and asset classification.
2. Checks VAT invoice validity for input credit eligibility.
3. Records:
   - Debit TK 151/152/153/156/211/213/241 — Asset/Inventory (purchase price)
   - Debit TK 1331 — VAT input (if eligible and invoice valid)
   - Credit TK 111 — Cash

**Business Rules**
- BR-013: VAT input credit (TK 1331) is only recognized if the supplier provides a valid VAT invoice.
- BR-014: Fixed asset purchases require asset master data creation before capitalization.
- BR-015: Cash payments exceeding legal thresholds must be made via bank transfer (per Vietnamese payment regulations).

**Input Data**
- Supplier ID, invoice reference, asset/inventory item details, amount, VAT amount, payment voucher.

**Output Data**
- Journal entry. Updated inventory/asset register. Reduced cash balance.

**Frequency**
- Daily.

**Priority**
- Critical.

---

#### UC-011: Record Cash Payment for Direct Production Expenses

**Description**
Record cash payment for raw materials or supplies used directly in production, business, or administrative activities without intermediate inventory storage.

**Goal**
Direct expense recognition for materials consumed immediately upon purchase.

**Primary Actors**
- Accountant

**Preconditions**
- Materials received and consumed.
- Payment voucher prepared.

**Trigger**
Company purchases raw materials paid in cash that are immediately consumed in production, distribution, or administration.

**Main Flow**
1. Accountant identifies the consuming cost center/department.
2. Records:
   - Debit TK 621/623/627/641/642 — Direct/indirect cost account
   - Debit TK 1331 — VAT input (if applicable)
   - Credit TK 111 — Cash

**Business Rules**
- BR-016: The cost account selection depends on the consuming department (production = 621, factory overhead = 627, selling = 641, admin = 642).

**Input Data**
- Department/cost center, material description, amount, VAT amount.

**Output Data**
- Journal entry. Updated cost center ledger.

**Frequency**
- Daily.

**Priority**
- High.

---

#### UC-012: Record Cash Payment to Suppliers and Liabilities

**Description**
Record cash payment for trade payables, tax obligations, salaries, borrowings, and other liabilities.

**Goal**
Settle outstanding liabilities and accurately reduce payable balances.

**Primary Actors**
- Accountant

**Preconditions**
- Outstanding payable/liability balance exists.
- Payment voucher prepared with authorized signatures.

**Trigger**
Company pays a supplier invoice, remits taxes, pays salaries, or repays borrowings in cash.

**Main Flow**
1. Accountant identifies the liability type and associated counterparty.
2. Records:
   - Debit TK 331/333/334/335/336/338/341 — Respective payable/liability
   - Credit TK 111 — Cash

**Business Rules**
- BR-017: Tax payments (TK 333) must be supported by tax payment documents.
- BR-018: Salary payments (TK 334) require payroll approval and individual pay slips.

**Input Data**
- Liability account, counterparty ID, invoice/period reference, amount.

**Output Data**
- Journal entry. Reduced liability balance.

**Frequency**
- Daily.

**Priority**
- Critical.

---

#### UC-013: Record Cash Payment for Financial and Other Expenses

**Description**
Record financial expenses (interest, bank charges) and other expenses (fines, donations) paid in cash.

**Goal**
Recognize period expenses paid in cash with proper classification.

**Primary Actors**
- Accountant

**Preconditions**
- Supporting documents for the expense available.
- Payment voucher prepared.

**Trigger**
Company incurs and pays financial or other expenses in cash.

**Main Flow**
1. Accountant classifies expense as financial (TK 635) or other (TK 811).
2. Records:
   - Debit TK 635/811 — Expense account
   - Debit TK 1331 — VAT input (if applicable)
   - Credit TK 111 — Cash

**Input Data**
- Expense type, amount, VAT flag, supporting document reference.

**Output Data**
- Journal entry. Updated expense ledger.

**Frequency**
- Event-driven.

**Priority**
- Medium.

---

#### UC-014: Record Cash Investment Payment

**Description**
Record cash payment for purchasing securities, making loans, acquiring subsidiaries, joint ventures, or associates.

**Goal**
Accurately reflect investment asset acquisition and cash outflow.

**Primary Actors**
- Accountant

**Preconditions**
- Investment decision approved by authorized body.
- Payment voucher prepared.

**Trigger**
Company buys marketable securities, makes a loan, or invests in another entity using cash.

**Main Flow**
1. Accountant identifies the investment type.
2. Records:
   - Debit TK 121/128/221/222/228 — Investment account
   - Credit TK 111 — Cash

**Input Data**
- Investment type, target entity, amount, contract/decision reference.

**Output Data**
- Journal entry. Updated investment register.

**Frequency**
- Event-driven.

**Priority**
- High.

---

### Domain: Bank Disbursement Management

Processing all outgoing fund flows from Account 112 — Non-Term Bank Deposits.

---

#### UC-015: Record Bank Transfer Purchase of Inventory and Fixed Assets

**Description**
Record payment via bank transfer for inventory, fixed assets, and construction in progress.

**Goal**
Accurately record asset/inventory acquisition via bank payment, including VAT input credit.

**Primary Actors**
- Accountant

**Preconditions**
- Bank debit advice or statement received confirming transfer.
- Purchase invoice and delivery receipt received.

**Trigger**
Company purchases goods/assets and pays via bank transfer.

**Main Flow**
1. Accountant matches bank debit advice to purchase invoice.
2. Records:
   - Debit TK 151/152/153/156/211/213/241
   - Debit TK 1331 (if VAT eligible)
   - Credit TK 112 — Bank deposit

**Input Data**
- Supplier ID, invoice reference, asset/inventory details, amount, VAT amount, bank debit advice.

**Output Data**
- Journal entry. Updated bank balance. Asset/inventory register updated.

**Frequency**
- Daily.

**Priority**
- Critical.

---

#### UC-016: Record Bank Transfer Payment to Suppliers and Liabilities

**Description**
Record payment via bank transfer for trade payables, taxes, salaries, borrowings, and other liabilities.

**Goal**
Settle liabilities via electronic funds transfer.

**Primary Actors**
- Accountant

**Preconditions**
- Bank debit advice confirming transfer executed.
- Outstanding liability balance exists.

**Trigger**
Company pays supplier, remits tax, pays salary, or repays borrowing via bank transfer.

**Main Flow**
1. Accountant identifies liability type and matches to bank debit advice.
2. Records:
   - Debit TK 331/333/334/335/336/338/341
   - Credit TK 112 — Bank deposit

**Alternate Flow**

- **AF-1: Transfer to payee not yet received**
  If payee has not yet received funds, record via TK 113 instead (see UC-021).

**Input Data**
- Liability account, counterparty, invoice/period reference, amount, bank debit advice.

**Output Data**
- Journal entry. Reduced liability. Reduced bank balance.

**Frequency**
- Daily.

**Priority**
- Critical.

---

#### UC-017: Record Bank Transfer Capital Return, Dividend, and Welfare Payment

**Description**
Record capital return to owners, dividend payments, and welfare fund disbursements via bank transfer.

**Goal**
Process equity-related disbursements accurately.

**Primary Actors**
- Accountant

**Preconditions**
- Shareholder meeting resolution or equivalent authorization.
- Bank debit advice received.

**Trigger**
Company returns capital, pays dividends, or disburses welfare funds via bank transfer.

**Main Flow**
1. Accountant identifies payment purpose.
2. Records:

   | Purpose | Debit | Credit |
   |---|---|---|
   | Capital return | TK 411 — Owner's equity | TK 112 |
   | Dividend payment | TK 332 — Dividends payable | TK 112 |
   | Welfare spending | TK 353 — Welfare fund | TK 112 |

**Input Data**
- Payment purpose, shareholder/employee details, resolution reference, amount.

**Output Data**
- Journal entry. Reduced equity/liability. Reduced bank balance.

**Frequency**
- Event-driven (quarterly/annually).

**Priority**
- High.

---

#### UC-018: Record Bank Transfer for Direct Expenses, Financial Expenses, and Investments

**Description**
Record bank transfer payments for direct production expenses, financial/other expenses, and investment acquisitions.

**Goal**
Process expense and investment payments via bank with correct classification.

**Primary Actors**
- Accountant

**Preconditions**
- Bank debit advice confirming transfer.
- Supporting documents available.

**Trigger**
Company pays for direct production materials, financial costs, other expenses, or investments via bank.

**Main Flow**
1. Accountant classifies payment purpose.
2. Records:

   | Type | Debit | Credit |
   |---|---|---|
   | Direct production materials | TK 621/623/627/641/642 + TK 1331 | TK 112 |
   | Financial/Other expenses | TK 635/811 + TK 1331 | TK 112 |
   | Investment acquisition | TK 121/128/221/222/228 | TK 112 |

**Input Data**
- Payment purpose selector, cost center, counterparty, amount, VAT flag.

**Output Data**
- Journal entry. Updated expense/investment ledger.

**Frequency**
- Daily / Event-driven.

**Priority**
- High.

---

### Domain: Fund Transfer Management

Managing movements between cash (111), bank deposits (112), and escrow/deposit accounts (244).

---

#### UC-019: Transfer Cash to Bank Account

**Description**
Deposit physical cash from the company's cash box into the company's bank account.

**Goal**
Reduce cash on hand and increase bank deposit balance.

**Primary Actors**
- Cashier
- Accountant

**Preconditions**
- Cash counted and verified.
- Bank deposit slip prepared.
- Bank has not yet confirmed (may go through 113).

**Trigger**
Company decides to deposit excess cash into bank.

**Main Flow**
1. **If bank credit advice received immediately**:
   - Debit TK 112 — Bank deposit
   - Credit TK 111 — Cash
2. **If bank not yet confirmed** (see UC-020):
   - Debit TK 113 — Cash in transit
   - Credit TK 111 — Cash
   - (Later cleared per UC-023)

**Input Data**
- Amount, bank account, deposit slip reference.

**Output Data**
- Reduced cash balance. Increased bank deposit (or cash in transit).

**Frequency**
- Daily / Weekly.

**Priority**
- High.

---

#### UC-020: Withdraw Bank Funds to Cash

**Description**
Withdraw cash from bank account to replenish petty cash or cash box.

**Goal**
Increase cash on hand from bank funds.

**Primary Actors**
- Accountant
- Cashier

**Preconditions**
- Withdrawal slip or check prepared.
- Bank confirms debit.

**Trigger**
Company needs cash for daily operations.

**Main Flow**
1. Accountant records on receipt of bank debit advice:
   - Debit TK 111 — Cash
   - Credit TK 112 — Bank deposit

**Input Data**
- Amount, bank account, withdrawal reference.

**Output Data**
- Increased cash balance. Reduced bank balance.

**Frequency**
- Weekly / As needed.

**Priority**
- High.

---

#### UC-021: Transfer Bank Funds to Escrow or Deposit

**Description**
Transfer bank funds for escrow, collateral, security deposits, or guarantee purposes.

**Goal**
Record funds placed under restriction as escrow/deposit assets.

**Primary Actors**
- Accountant

**Preconditions**
- Escrow/deposit agreement executed.
- Bank debit advice received.

**Trigger**
Company places a security deposit, guarantee, or escrow fund via bank transfer.

**Main Flow**
1. Accountant records:
   - Debit TK 244 — Escrow, deposits
   - Credit TK 112 — Bank deposit

**Input Data**
- Escrow type, counterparty, agreement reference, amount.

**Output Data**
- Journal entry. Increased escrow asset. Reduced bank balance.

**Frequency**
- Event-driven.

**Priority**
- Medium.

---

### Domain: Cash-in-Transit Management

Handling suspense/clearing operations for funds in transit recorded on Account 113.

---

#### UC-022: Record Cash-in-Transit from Direct Deposit

**Description**
Record cash or checks deposited into the bank but not yet confirmed by the bank (no credit advice received yet).

**Goal**
Recognize the temporary suspense status of funds that have left the cash box but are not yet confirmed as deposited.

**Primary Actors**
- Accountant

**Preconditions**
- Cash disbursed or check handed to bank.
- No bank credit advice received yet.

**Trigger**
Cash withdrawn from the cash box and deposited into the bank on the same day, but bank confirmation is pending.

**Main Flow**
1. Accountant records:
   - Debit TK 113 — Cash in transit
   - Credit TK 111 — Cash
2. Monitor for bank credit advice in subsequent days.

**Business Rules**
- BR-019: TK 113 is a suspense account. Balances must be cleared promptly upon receipt of bank confirmation.

**Input Data**
- Amount, deposit date, bank name, reference.

**Output Data**
- Journal entry. Balance in TK 113 suspense.

**Frequency**
- Daily.

**Priority**
- High.

---

#### UC-023: Record Cash-in-Transit from Bank Transfer to Payee

**Description**
Record funds transferred from bank account to a payee (supplier, creditor) where the payee has not yet confirmed receipt.

**Goal**
Recognize temporary suspense for outgoing payments not yet acknowledged.

**Primary Actors**
- Accountant

**Preconditions**
- Bank debit advice confirms transfer executed.
- Payee has not confirmed receipt.

**Trigger**
Company initiates bank transfer to payee, transfer is debited from bank but payee has not yet acknowledged.

**Main Flow**
1. Accountant records:
   - Debit TK 113 — Cash in transit
   - Credit TK 112 — Bank deposit
2. Monitor for payee confirmation.

**Input Data**
- Amount, payee, bank transfer reference, date.

**Output Data**
- Journal entry. Balance in TK 113.

**Frequency**
- Daily.

**Priority**
- High.

---

#### UC-024: Record Customer Check Deposit as Cash-in-Transit

**Description**
Record customer payment made by check that has been deposited but not yet cleared by the bank.

**Goal**
Recognize suspense status for uncleared checks.

**Primary Actors**
- Accountant

**Preconditions**
- Customer check received and deposited.
- No bank credit advice received yet.

**Trigger**
Customer pays by check, company deposits check but bank has not confirmed funds.

**Main Flow**
1. Accountant records:
   - Debit TK 113 — Cash in transit
   - Credit TK 131 — Customer receivable

**Input Data**
- Customer ID, check number, amount, deposit date.

**Output Data**
- Journal entry. Reduced AR, increased cash in transit.

**Frequency**
- Daily.

**Priority**
- Medium.

---

#### UC-025: Clear Cash-in-Transit to Bank Account

**Description**
Clear suspense balance when bank confirms receipt of previously deposited funds.

**Goal**
Move funds from suspense (113) to confirmed bank deposit (112).

**Primary Actors**
- Accountant

**Supporting Actors**
- Bank

**Preconditions**
- TK 113 balance exists for the specific deposit.
- Bank credit advice received.

**Trigger**
Bank issues credit advice confirming the deposited funds are now available.

**Main Flow**
1. Accountant matches credit advice to outstanding TK 113 entry.
2. Records:
   - Debit TK 112 — Bank deposit
   - Credit TK 113 — Cash in transit

**Input Data**
- TK 113 entry reference, bank credit advice reference.

**Output Data**
- Journal entry. TK 113 balance cleared. TK 112 balance increased.

**Frequency**
- Daily.

**Priority**
- High.

---

#### UC-026: Clear Cash-in-Transit to Payable

**Description**
Clear suspense balance when payee confirms receipt of transferred funds.

**Goal**
Recognize final settlement of payable.

**Primary Actors**
- Accountant

**Preconditions**
- TK 113 balance exists for the specific transfer.
- Payee confirms receipt.

**Trigger**
Payee acknowledges receipt of funds previously recorded in transit.

**Main Flow**
1. Accountant matches payee confirmation to outstanding TK 113 entry.
2. Records:
   - Debit TK 331/341 — Payable
   - Credit TK 113 — Cash in transit

**Input Data**
- TK 113 entry reference, payee confirmation document.

**Output Data**
- Journal entry. TK 113 balance cleared. Payable balance reduced.

**Frequency**
- Daily.

**Priority**
- High.

---

### Domain: Cash Verification and Reconciliation

Physical cash counts, discrepancy handling, and bank statement reconciliation.

---

#### UC-027: Conduct Physical Cash Inventory

**Description**
Periodically count physical cash in the cash box (VND, foreign currency, monetary gold) and compare to the cash book balance.

**Goal**
Verify physical cash existence and accuracy of cash records.

**Primary Actors**
- Cashier
- Accountant (or internal auditor for independent count)
- Chief Accountant (oversight)

**Preconditions**
- Cash book is up to date.
- All transactions for the period are posted.

**Trigger**
Scheduled (daily/monthly/quarterly) or surprise cash count.

**Main Flow**
1. Cashier prepares cash book balance.
2. Counting team physically counts all cash on hand (by currency and gold).
3. Compare counted amount to cash book balance.
4. Document any difference.
5. If no difference: confirm reconciliation.
6. If difference exists → proceed to UC-029 or UC-030.

**Business Rules**
- BR-020: Cash counts must be conducted in the presence of both the cashier and an independent verifier (accountant or internal auditor).
- BR-021: Count results must be documented in a signed cash count report.

**Input Data**
- Count date, expected balance by currency/gold, actual count by currency/gold.

**Output Data**
- Cash count report. Discrepancy report (if any).

**Frequency**
- At minimum monthly, recommended daily for active cash operations.

**Priority**
- Critical.

---

#### UC-028: Reconcile Cash Book with Accounting Ledger

**Description**
Compare the cashier's cash book (sổ quỹ) with the general ledger cash account to ensure consistency.

**Goal**
Ensure dual-record consistency between custodial and accounting records.

**Primary Actors**
- Accountant
- Cashier

**Preconditions**
- Both cash book and general ledger are up to date.

**Trigger**
Period-end closing or as part of internal control procedures.

**Main Flow**
1. Accountant extracts cash ledger balance (TK 111).
2. Cashier provides cash book balance.
3. Compare balances.
4. Investigate and resolve any differences.

**Business Rules**
- BR-022: Regular reconciliation (at least monthly) is mandatory per Circular 99.
- BR-023: Unresolved differences must be documented with action plan.

**Input Data**
- General ledger balance, cash book balance.

**Output Data**
- Reconciliation report. List of outstanding items.

**Frequency**
- Monthly (minimum).

**Priority**
- Critical.

---

#### UC-029: Record Cash Shortage

**Description**
Record a cash shortage discovered during physical inventory when the cause is not yet determined.

**Goal**
Temporarily record the shortage as a receivable pending investigation.

**Primary Actors**
- Accountant

**Preconditions**
- Physical count reveals cash less than book balance.
- Cause not yet identified.

**Trigger**
Cash shortage confirmed but undocumented.

**Main Flow**
1. Accountant records:
   - Debit TK 1381 — Other receivables (shortage)
   - Credit TK 111 — Cash
2. Initiate investigation per UC-031.

**Business Rules**
- BR-024: Shortages must be recorded at the time of discovery.
- BR-025: If ultimately determined as cashier liability, transfer to TK 1388 or withhold from salary.

**Input Data**
- Shortage amount, currency, count report reference.

**Output Data**
- Journal entry. Created receivable for shortage.

**Frequency**
- Event-driven (upon discovery).

**Priority**
- High.

---

#### UC-030: Record Cash Surplus

**Description**
Record a cash surplus discovered during physical inventory when the cause is not yet determined.

**Goal**
Temporarily record the surplus as a payable pending investigation.

**Primary Actors**
- Accountant

**Preconditions**
- Physical count reveals cash more than book balance.
- Cause not yet identified.

**Trigger**
Cash surplus confirmed but undocumented.

**Main Flow**
1. Accountant records:
   - Debit TK 111 — Cash
   - Credit TK 3381 — Other payables (surplus)
2. Initiate investigation per UC-031.

**Input Data**
- Surplus amount, currency, count report reference.

**Output Data**
- Journal entry. Created payable for surplus.

**Frequency**
- Event-driven.

**Priority**
- High.

---

#### UC-031: Resolve Cash Discrepancy

**Description**
Investigate and resolve identified cash shortages or surpluses, adjusting the temporary accounts upon conclusion.

**Goal**
Clear temporary discrepancy accounts with appropriate final treatment.

**Primary Actors**
- Chief Accountant
- Accountant
- Cashier

**Preconditions**
- Discrepancy recorded in TK 1381 or TK 3381.
- Investigation completed.

**Trigger**
Investigation concludes on root cause of shortage/surplus.

**Main Flow**
1. Chief Accountant reviews investigation findings.
2. **If shortage attributable to cashier**:
   - Debit TK 1388 / TK 334 — Cashier liability
   - Credit TK 1381 — Clear suspense
3. **If shortage attributable to accounting error**:
   - Adjusting entry to correct error.
4. **If surplus attributable to unidentified source**:
   - Record as other income: Debit TK 3381, Credit TK 711.
5. Document resolution in cash count report.

**Business Rules**
- BR-026: All discrepancy resolutions must be approved by Chief Accountant.
- BR-027: Write-offs require management approval per company policy.

**Input Data**
- Investigation report, discrepancy entry reference, resolution decision.

**Output Data**
- Adjusted journal entries. Cleared discrepancy suspense account.

**Frequency**
- Event-driven.

**Priority**
- High.

---

#### UC-032: Perform Bank Statement Reconciliation

**Description**
Compare company's bank deposit ledger (TK 112) with bank statement to identify timing differences and errors.

**Goal**
Ensure bank balance accuracy and identify reconciling items.

**Primary Actors**
- Accountant

**Supporting Actors**
- Bank

**Preconditions**
- Bank statement received for the period.
- Company bank ledger is complete for the period.

**Trigger**
Bank statement received (typically monthly).

**Main Flow**
1. Accountant obtains bank statement and company bank ledger.
2. Matches each bank transaction to company entry:
   - Deposits in transit (recorded in company books, not yet on bank statement)
   - Outstanding checks (recorded in company books, not yet presented to bank)
   - Bank charges/fees not yet recorded in company books
   - Direct debits/credits by bank not yet recorded
3. Prepares reconciliation statement.
4. If differences exist and are unexplained → proceed to UC-033.

**Business Rules**
- BR-028: Bank reconciliation must be performed at least monthly.
- BR-029: Outstanding items from prior periods must be followed up and resolved.

**Input Data**
- Bank statement, company bank ledger, prior period reconciliation.

**Output Data**
- Bank reconciliation statement. List of reconciling items.

**Frequency**
- Monthly.

**Priority**
- Critical.

---

#### UC-033: Record and Resolve Bank Reconciliation Difference

**Description**
Record unidentified timing differences temporarily and resolve them in subsequent periods.

**Goal**
Maintain reconciled bank balance when difference cannot be immediately resolved.

**Primary Actors**
- Accountant

**Preconditions**
- Bank reconciliation completed with unexplained difference.

**Trigger**
Difference between company and bank records cannot be immediately identified.

**Main Flow**
1. **If company book balance > bank balance** (company recorded more):
   - Record: Debit TK 138 — Other receivables, Credit TK 112 (or relevant account)
2. **If company book balance < bank balance** (bank recorded more):
   - Record: Debit TK 112 (or relevant account), Credit TK 338 — Other payables
3. Month-end: Continue reconciliation with bank in next period.
4. When cause determined → adjust journal entry accordingly.

**Business Rules**
- BR-030: Temporary entries via 138/338 must be cleared in the following month.
- BR-031: Long-outstanding reconciling items must be escalated.

**Input Data**
- Difference amount, direction, bank statement line item, company entry reference.

**Output Data**
- Temporary journal entry via 138/338. Updated reconciliation.

**Frequency**
- Monthly.

**Priority**
- High.

---

### Domain: Period-End Treasury Valuation

Foreign currency and monetary gold revaluation at period-end closing.

---

#### UC-034: Revalue Foreign Currency Cash Balance

**Description**
Revalue foreign currency cash on hand (TK 111 — foreign currency sub-account) at period-end using the prevailing exchange rate.

**Goal**
Recognize unrealized FX gains/losses on cash holdings.

**Primary Actors**
- Accountant

**Preconditions**
- All FX cash transactions recorded for the period.
- Period-end exchange rate determined per VAS 10.

**Trigger**
Period-end closing (monthly, quarterly, annually).

**Main Flow**
1. Accountant calculates FX cash balance in original currency.
2. Converts at period-end exchange rate.
3. Compares to book value in VND.
4. **If FX rate increased (gain)**:
   - Debit TK 111 — Cash (FX)
   - Credit TK 515 — Financial income
5. **If FX rate decreased (loss)**:
   - Debit TK 635 — Financial expense
   - Credit TK 111 — Cash (FX)

**Business Rules**
- BR-032: Revaluation method must be consistent with VAS 10 (Vietnamese Accounting Standard — Foreign Currency).
- BR-033: Exchange rate source must be documented (e.g., commercial bank rate, central bank rate).

**Input Data**
- Currency code, period-end balance in original currency, period-end exchange rate, book value in VND.

**Output Data**
- FX revaluation journal entry. Updated cash balance in VND.

**Frequency**
- At each period-end (monthly/quarterly/annually).

**Priority**
- High.

---

#### UC-035: Revalue Foreign Currency Bank Deposit Balance

**Description**
Revalue foreign currency bank deposit balances (TK 112 — foreign currency sub-account) at period-end.

**Goal**
Recognize unrealized FX gains/losses on bank deposits.

**Primary Actors**
- Accountant

**Preconditions**
- All FX bank transactions recorded.
- Period-end exchange rate determined.

**Trigger**
Period-end closing.

**Main Flow**
1. Same logic as UC-034 but applied to TK 112.
2. Debit TK 112 / Credit TK 515 (gain) OR Debit TK 635 / Credit TK 112 (loss).

**Input Data**
- Same as UC-034, but for bank account.

**Output Data**
- FX revaluation journal entry for bank deposit.

**Frequency**
- Each period-end.

**Priority**
- High.

---

#### UC-036: Revalue Monetary Gold Balance

**Description**
Revalue monetary gold held in the cash box (TK 111 — gold sub-account) at period-end using domestic purchase price.

**Goal**
Recognize unrealized gains/losses on gold holdings.

**Primary Actors**
- Accountant

**Preconditions**
- Gold holdings recorded.
- Period-end domestic purchase price established.

**Trigger**
Period-end closing.

**Main Flow**
1. Accountant determines gold quantity.
2. Values at period-end domestic purchase price.
3. **If gold price increased (gain)**:
   - Debit TK 111 — Cash (gold)
   - Credit TK 515 — Financial income
4. **If gold price decreased (loss)**:
   - Debit TK 635 — Financial expense
   - Credit TK 111 — Cash (gold)

**Business Rules**
- BR-034: Gold valuation uses domestic purchase price per Circular 99.
- BR-035: Gold revaluation treatment mirrors FX revaluation per VAS guidance.

**Input Data**
- Gold quantity (grams/taels), period-end unit price, book value.

**Output Data**
- Gold revaluation journal entry.

**Frequency**
- Each period-end.

**Priority**
- Medium.

---

#### UC-037: Revalue Foreign Currency Cash-in-Transit

**Description**
Revalue foreign currency cash in transit (TK 113 — foreign currency sub-account) at balance sheet date.

**Goal**
Recognize FX gains/losses on in-transit foreign currency balances.

**Primary Actors**
- Accountant

**Preconditions**
- TK 113 FX balance exists.
- Period-end exchange rate determined.

**Trigger**
Period-end closing.

**Main Flow**
1. Accountant revalues TK 113 FX balance.
2. Records gain/loss via TK 515 or TK 635 (same logic as UC-034).

**Input Data**
- Currency, period-end balance in original currency, period-end rate.

**Output Data**
- FX revaluation journal entry for cash in transit.

**Frequency**
- Each period-end.

**Priority**
- Low.

---

## 3. Cross-Use Case Analysis

### Overlapping Use Cases

| Use Cases | Overlap Description |
|---|---|
| UC-001 / UC-007 | Both record sales revenue with tax handling (cash vs. bank). Structurally identical; differ only in debit account. |
| UC-002 / UC-009 (Financial Income) | Both record financial/other income (cash vs. bank). |
| UC-005 / UC-009 (Investment Proceeds) | Both handle investment disposal gain/loss (cash vs. bank). |
| UC-004 / UC-009 (Capital Contribution) | Both record capital contributions (cash vs. bank). |
| UC-010 / UC-015 | Both record inventory/asset purchases (cash vs. bank). |
| UC-011 / UC-018 (Direct Expenses) | Both record direct production expenses (cash vs. bank). |
| UC-012 / UC-016 | Both record liability payments (cash vs. bank). |
| UC-014 / UC-018 (Investments) | Both record investment payments (cash vs. bank). |
| UC-022 / UC-023 / UC-024 | All create TK 113 suspense entries with different credit sides. |
| UC-025 / UC-026 | Both clear TK 113 suspense to different debit sides. |
| UC-034 / UC-035 / UC-037 | All perform FX revaluation on different accounts. |

### Shared Dependencies

- **Chart of Accounts**: All use cases depend on VAS-standard account numbering (TK 111, 112, 113, 131, 133, 138, 141, 211, 244, 331, 333, 334, 338, 341, 411, 511, 515, 635, 711, 811, etc.).
- **Exchange Rate Source**: UC-034, UC-035, UC-037 share dependency on a single exchange rate determination process.
- **Tax Rate Configuration**: UC-001, UC-002, UC-007, UC-009, UC-010, UC-011, UC-015, UC-018 share dependency on current tax rates for VAT/excise/export tax.
- **Authorization Matrix**: All disbursement UCs (010-023) share dependency on payment authorization workflow.
- **Source Document Management**: All UCs depend on proper source document retention (receipt/payment vouchers, bank advices, invoices).

### Workflow Gaps

- **Check lifecycle**: No explicit use case for preparing, signing, and delivering checks (implied in general disbursement).
- **Petty cash replenishment**: No separate use case for imprest/petty cash system management (the "fixed fund" method).
- **Cash flow forecasting**: No use case for treasury forecasting based on expected receipts/disbursements.
- **Multi-currency management**: No use case for maintaining separate ledgers by currency.
- **Bank fee accrual**: No explicit use case for accruing unrecorded bank service charges.

### Missing Transitions

- **UC-019 → UC-022**: Transfer cash to bank may or may not go through TK 113. The decision point (immediate confirmation vs. uncleared) is not formalized.
- **UC-032 → UC-033**: The point at which a reconciling item qualifies as "unexplained" and triggers temporary entry is not defined with a threshold.
- **UC-027 → UC-029/UC-030**: The escalation path from discrepancy discovery to temporary recording is implicit, not formalized.
- **UC-031 → UC-029/UC-030 resolution**: Resolution of TK 1381/3381 back to final treatment lacks formal workflow.

### Inconsistent Terminology

- "Non-term deposits" (tiền gửi không kỳ hạn) vs. "demand deposits" — the term "non-term" is used consistently in sources; no inconsistency within scope.
- "Cash in transit" vs. "funds in transit" vs. "clearing" — sources use "tiền đang chuyển" consistently.
- No material inconsistency found within the three sources.

### Potential System Risks

- **Stale suspense**: TK 113 balances may remain uncleared indefinitely without aging monitoring.
- **Unreconciled differences**: Temporary 138/338 entries from bank reconciliation (UC-033) may become permanent without follow-up workflow.
- **FX revaluation frequency**: If revaluation is done only annually but FX transactions occur daily, interim reporting may be materially misstated.
- **Segregation of duties**: Cash handling involves both cashier and accountant; if the same person performs both roles, fraud risk increases.
- **Duplicate payment**: No formal "check for duplicate" validation before processing disbursements.

---

## 4. Missing Functionalities

### Missing Use Cases

| # | Missing Use Case | Rationale |
|---|---|---|
| MU-001 | **Maintain Petty Cash Fund** | No use case for establishing, replenishing, or adjusting petty cash under the imprest system. |
| MU-002 | **Prepare Cash Flow Statement** | Indirect reporting requirement from all receipt/disbursement UCs; no explicit reporting use case. |
| MU-003 | **Monitor Cash-in-Transit Aging** | No use case for monitoring uncleared TK 113 balances and escalating stale items. |
| MU-004 | **Approve Payment Voucher** | No formal approval workflow use case; implied but not specified. |
| MU-005 | **Maintain Bank Account Master Data** | No use case for adding/changing/closing bank account details used in TK 112. |
| MU-006 | **Generate Treasury Dashboard** | No dashboard use case for showing real-time cash position across all accounts. |
| MU-007 | **Archive Treasury Documents** | No use case for electronic archiving of payment vouchers, bank advices, and reconciliation reports. |

### Missing Validation Rules

- VR-001: Duplicate payment detection — same invoice reference, same amount, within X days.
- VR-002: Cash payment limit enforcement — if legal threshold requires bank transfer, system must prevent cash payment.
- VR-003: Negative cash balance prevention — TK 111 should not go below zero.
- VR-004: Bank overdraft detection — TK 112 should reflect positive balance only; overdrafts redirect to borrowings.
- VR-005: Signatory authority check — payment amount exceeds authorization level of preparer.
- VR-006: Currency consistency — payment currency must match the account's designated currency.
- VR-007: VAT invoice validity — VAT input credit requires valid invoice registered with tax authority.

### Missing Approval Flows

- AF-001: **Disbursement approval matrix** — different approval levels by amount and account type.
- AF-002: **Capital expenditure approval** — separate approval for fixed asset purchases vs. inventory.
- AF-003: **FX transaction approval** — foreign currency transactions may require treasury manager approval.
- AF-004: **Write-off approval** — shortage/write-off resolution requires CFO or board approval.

### Missing Audit Trails

- AT-001: All changes to bank account master data (who, what, when).
- AT-002: All payment voucher modifications after initial creation.
- AT-003: All reconciliation adjustments (UC-033) with before/after snapshots.
- AT-004: Cash count results with digital signatures of countersigning parties.
- AT-005: FX rate source and rate used at time of revaluation.

### Missing Error Handling

- EH-001: Insufficient cash balance — system should warn/block disbursement.
- EH-002: Invalid bank account — transfer to closed/non-existent account blocked.
- EH-003: Currency mismatch — receipt currency differs from account currency.
- EH-004: Stale-dated check — payment check not presented within validity period.
- EH-005: Reconciliation out of balance — system should prevent period-end close if reconciliation unresolved.

### Missing Compliance Controls

- CC-001: Regulatory payment limit enforcement (cash payments above threshold auto-blocked).
- CC-002: Tax authority reporting integration (automated data feed for VAT/CIT declarations).
- CC-003: Anti-money laundering screening for large/structured cash transactions.
- CC-004: Retention policy enforcement for treasury documents (legal minimum retention periods).
- CC-005: Segregation of duties enforcement (cashier and accountant must be different users).

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Treasury Receipt Management** | Process all incoming funds (cash, bank, clearing) with automatic tax handling and account determination. |
| **Treasury Disbursement Management** | Process all outgoing funds with approval workflow, payment limit checks, and duplicate detection. |
| **Cash-in-Transit Clearing** | Track and auto-clear suspense items upon matching confirmation documents; aging dashboard for stale items. |
| **Bank Reconciliation** | Automate matching of bank statements to company entries; handle timing differences and temporary entries. |
| **Physical Cash Management** | Support cash count workflow, discrepancy recording, investigation tracking, and resolution approval. |
| **FX & Gold Valuation** | Period-end revaluation engine with configurable exchange rate sources and automatic journal generation. |
| **Treasury Reporting** | Cash position dashboard, cash flow statement, aging reports (suspense, reconciling items), audit trail. |
| **Document Management** | Electronic storage and retrieval of payment vouchers, bank advices, reconciliation reports, count reports. |
| **Compliance Engine** | Enforce payment limits, authorization matrix, segregation of duties, retention policies. |

---

## 6. Suggested Improvements

### Business Improvements

| # | Improvement | Rationale |
|---|---|---|
| BI-001 | Implement daily cash position reporting | Provides real-time visibility into liquidity across all treasury accounts. |
| BI-002 | Automate inter-account transfers with optimization | System recommends optimal cash transfer amounts between cash and bank to minimize idle balances. |
| BI-003 | Introduce multi-level payment approval | Reduces fraud risk; large payments require CFO/second-level approval. |
| BI-004 | Create supplier payment scheduling | Allows batch payment runs with due-date-based prioritization. |

### Process Improvements

| # | Improvement | Rationale |
|---|---|---|
| PI-001 | Formalize month-end treasury closing checklist | Ensures all reconciliations, revaluations, and clearings are completed before GL close. |
| PI-002 | Implement three-way matching before disbursement | Match purchase order, goods receipt, and invoice before payment release. |
| PI-003 | Automate bank statement import | Reduce manual data entry errors and accelerate reconciliation. |
| PI-004 | Establish cash-in-transit aging KPIs | Escalate items in suspense beyond 5 business days. |

### Technical Improvements

| # | Improvement | Rationale |
|---|---|---|
| TI-001 | Real-time bank integration (API) | Direct connectivity to banking APIs for instant balance and transaction feeds. |
| TI-002 | QR code on payment vouchers | Enable quick scanning for document tracking and audit. |
| TI-003 | Automated FX rate feed | Pull period-end rates from central bank or commercial bank API automatically. |
| TI-004 | Machine learning for reconciliation | Auto-match bank and book transactions using fuzzy matching algorithms. |

### Compliance Improvements

| # | Improvement | Rationale |
|---|---|---|
| CI-001 | Tax authority digital integration | Auto-submit VAT input/output data to tax portal from treasury transactions. |
| CI-002 | Automated regulatory reporting | Generate required treasury-related regulatory reports (large cash transaction reports). |
| CI-003 | Embedded audit trail | All treasury transactions must have immutable audit fields: user, timestamp, IP, before/after values. |
| CI-004 | Segregation-of-duties enforcement | System must prevent same user from creating and approving same payment; cashier and accountant must be distinct roles. |
| CI-005 | Cash payment limit enforcement | System blocks cash payments exceeding VND 20 million (or current regulatory threshold) and suggests bank transfer. |

---

**Document Version:** 1.0  
**Analysis Date:** 2026-05-14  
**Prepared For:** Enterprise Accounting System Design — Treasury Module  
**Applicable Regime:** Circular 99/2025/TT-BTC
