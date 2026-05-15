# Cash & Bank Module — Implementation Roadmap

**Version:** 1.0
**Last Updated:** 2026-05-15
**Base Spec:** `docs/specs/CASH_BANK_USE_CASE_SPECIFICATION.md`

---

## 1. Current State

| Layer | Status | Detail |
|---|---|---|
| COA (111, 112, 113) | ✅ Seeded | TK 111 (Cash), TK 112 (Bank), TK 113 (Cash in transit) active |
| Journal Engine | ✅ Verified | `JournalService::postEntry()` — Dr/Cr validation, 121 tests passing |
| Inventory Module | ✅ Complete | 10 phases: receipt, issue, transfer, transit, consignment, count, impairment, promotional, periodic |
| **Cash Receipt (UC-01)** | ❌ Not started | No `CashReceiptController`, no `CashService` |
| **Cash Payment (UC-02)** | ❌ Not started | No `CashPaymentController` |
| **Bank Transaction (UC-03)** | ❌ Not started | No bank deposit/withdrawal recording |
| **Cash in Transit (UC-04)** | ❌ Not started | TK 113 automation |
| **Cash Book (UC-05)** | ❌ Not started | No cash book register |
| **Bank Reconciliation (UC-06)** | ❌ Not started | No reconciliation engine |
| **Petty Cash (UC-07)** | ❌ Not started | No imprest fund management |
| **FX Cash (UC-08)** | ❌ Not started | No dual-currency tracking |
| **Cash Reporting (UC-09)** | ❌ Not started | No cash position reports |
| **Sidebar links** | ❌ Placeholder | 8 menu items under "Vốn bằng tiền" all `href="#"` |

---

## 2. Implementation Strategy

### 2.1 Architectural Pattern (Reuse from Inventory)

Every cash/bank transaction follows the same proven pattern:

```
View (Bootstrap modal) → AJAX POST → Controller → CashService → JournalService::postEntry() → Account balance update
```

The `CashService` will mirror `InventoryService` structure:
- Single service class with one method per transaction type
- Each method validates input, builds journal lines, posts via `JournalService`, updates relevant tracking data
- Controller receives JSON, delegates to service, returns JSON response
- Tests follow the same pattern as `tests/InventoryReceiptTest.php`

### 2.2 Key Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Single service vs. split | Single `CashService` | Mirrors `InventoryService`. Keeps cohesion. All cash/bank logic in one place. |
| Cash book: DB table vs. computed | Computed from transactions | Cash book is a filtered view of TK 111 journal entries + opening balance. No separate table needed. |
| Bank reconciliation: auto vs. manual | Hybrid: auto-match + manual review | Full auto-match is risky. System matches by amount/date/ref, accountant confirms. |
| Petty cash: separate account vs. sub-account | Sub-account under TK 111 | Simplifies reporting (TK 111 total = main cash + petty cash). |
| Voucher numbering: auto vs. manual | Auto with configurable prefix | Pattern: `PT-{YYYY}-{NNNNNN}` for receipts, `PC-{YYYY}-{NNNNNN}` for payments. |

### 2.3 Transaction Journal Line Templates

Each cash/bank transaction maps to a standard journal template:

| Transaction | Debit | Credit | Source Document |
|---|---|---|---|
| Customer payment (cash) | TK 111 | TK 131 | Phiếu thu |
| Cash sale | TK 111 | TK 511 | Phiếu thu + Hóa đơn |
| Supplier payment (cash) | TK 331 | TK 111 | Phiếu chi |
| Operating expense (cash) | TK 642/641 | TK 111 | Phiếu chi |
| Bank deposit (cash→bank) | TK 112 | TK 111 | Giấy nộp tiền |
| Bank withdrawal (bank→cash) | TK 111 | TK 112 | Séc/Giấy rút tiền |
| Customer pays to bank | TK 112 | TK 131 | Giấy báo Có |
| Supplier paid from bank | TK 331 | TK 112 | Giấy báo Nợ |
| Bank interest | TK 112 | TK 515 | Giấy báo Có |
| Bank charges | TK 642 | TK 112 | Giấy báo Nợ |
| Petty cash replenishment | TK various | TK 111 | Phiếu chi |

---

## 3. Phased Implementation Plan

### Phase 1: Core Cash Transactions (Weeks 1–2)

**Goal:** Build the two most frequent daily transactions — cash receipt and cash payment. Validate the architecture end-to-end.

---

#### P1.1: Cash Receipt (UC-01) — 5 days

**Business value:** #1 daily operation. Every business needs to record money coming in.

**Files to create/modify:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashService.php` | CREATE | `recordReceipt()` — Dr 111, Cr counterparty |
| `tests/CashReceiptTest.php` | CREATE | 8–10 tests (see §5) |
| `src/.../HTTP/CashReceiptController.php` | CREATE | POST /api/cash-receipts, GET list |
| `config/routes.php` | MODIFY | Add cash receipt routes |
| `config/services.php` | MODIFY | Add CashService to container |
| `public/views/cash_receipts.php` | CREATE | Bootstrap modal form |
| `public/views/layout.php` | MODIFY | Wire sidebar "Phiếu thu" link |

**Key test scenarios:**
1. Basic cash receipt from customer (Dr 111 — Cr 131)
2. Cash sale (Dr 111 — Cr 511)
3. Prepayment from customer (Dr 111 — Cr 131)
4. Foreign currency cash receipt (Dr 111 — Cr 131, dual-currency)
5. Invalid account rejection
6. Receipt reversal
7. Sequential voucher numbering
8. Zero-amount rejection

**Acceptance criteria:**
- [ ] Cash receipt posted: TK 111 balance increases, counterparty balance adjusts
- [ ] Receipt voucher generated with sequential number
- [ ] Audit log records user, timestamp, before/after balances
- [ ] Foreign currency receipt tracks original currency + rate + VND

---

#### P1.2: Cash Payment (UC-02) — 5 days

**Business value:** Mirror of receipt. Every business pays suppliers, staff, and expenses.

**Files to create/modify:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashService.php` | MODIFY | Add `recordPayment()` — Dr counterparty, Cr 111 |
| `tests/CashPaymentTest.php` | CREATE | 8–10 tests |
| `src/.../HTTP/CashPaymentController.php` | CREATE | POST /api/cash-payments |
| `config/routes.php` | MODIFY | Add payment routes |
| `public/views/cash_payments.php` | CREATE | Bootstrap modal form |
| `public/views/layout.php` | MODIFY | Wire "Phiếu chi" sidebar link |

**Key test scenarios:**
1. Supplier payment (Dr 331 — Cr 111)
2. Operating expense payment (Dr 642 — Cr 111)
3. Employee advance (Dr 141 — Cr 111)
4. Insufficient cash balance rejection
5. Payment exceeding approval threshold (warn)
6. VAT invoice validation (Dr 642 + Dr 133 — Cr 111)
7. Foreign currency payment
8. Payment reversal

**Acceptance criteria:**
- [ ] Cash payment posted: TK 111 decreases, expense/liability adjusts
- [ ] Payment voucher generated with sequential number (separate from receipt sequence)
- [ ] Insufficient balance blocked with clear error message
- [ ] VAT-deductible expenses correctly split (expense + input VAT)

---

#### P1.3: Integration Test — 2 days

**Business value:** Verify receipt and payment work together in real workflows.

**Test scenarios:**
1. Full cycle: Customer pays invoice → receipt recorded → cash used to pay supplier → both posted
2. Trial balance verification after mixed receipt/payment cycle
3. Concurrent transactions (multi-user)

---

### Phase 2: Bank Transactions (Week 3)

**Goal:** Extend cash service to handle bank deposits, withdrawals, and direct bank transactions.

---

#### P2.1: Bank Deposit and Withdrawal (UC-03) — 3 days

**Business value:** Most businesses use bank transfers as primary payment method. Bank accounts need separate tracking.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashService.php` | MODIFY | Add `recordBankDeposit()`, `recordBankWithdrawal()` |
| `tests/CashBankTest.php` | CREATE | 6–8 tests |
| `src/.../HTTP/BankTransactionController.php` | CREATE | POST /api/bank-transactions |
| `config/routes.php` | MODIFY | Add bank transaction routes |
| `public/views/bank_transactions.php` | CREATE | Giấy báo Có/Nợ forms |
| `public/views/layout.php` | MODIFY | Wire "Giấy báo Có/Nợ" sidebar links |

**Key test scenarios:**
1. Cash deposited to bank (Dr 112 — Cr 111)
2. Cash withdrawn from bank (Dr 111 — Cr 112)
3. Customer pays directly to bank (Dr 112 — Cr 131)
4. Supplier paid via bank transfer (Dr 331 — Cr 112)
5. Bank interest credited (Dr 112 — Cr 515)
6. Bank charges debited (Dr 642 — Cr 112)

**Acceptance criteria:**
- [ ] Bank deposit/withdrawal correctly moves between TK 111 and TK 112
- [ ] Direct bank transactions (customer→bank, company→supplier via bank) skip TK 111
- [ ] Bank charges and interest recorded on bank statement date
- [ ] No double-posting when same transaction flows through transit

---

#### P2.2: Cash in Transit Tracking (UC-04) — 2 days

**Business value:** End-of-day deposits and period-end cut-off require transit tracking to avoid balance sheet misstatement.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `database/migrations/030_create_cash_transit_table.php` | CREATE | `cash_transit` tracking table |
| `src/.../Service/CashService.php` | MODIFY | Add `recordTransit()`, `confirmTransit()` |
| `tests/CashTransitTest.php` | CREATE | 4–5 tests |
| `config/routes.php` | MODIFY | Add transit routes |

**Key test scenarios:**
1. End-of-day deposit recorded as transit (Dr 113 — Cr 111)
2. Next-day bank confirmation clears transit (Dr 112 — Cr 113)
3. Cheque dishonour reverses transit entry
4. Period-end non-zero transit flagged for disclosure

---

### Phase 3: Cash Book & Petty Cash (Week 4)

---

#### P3.1: Cash Book (UC-05) — 2 days

**Business value:** The cash book is a legally mandated accounting book. It must be available for audit and tax inspection.

**Implementation note:** Cash book is a **computed view** — no separate table needed. Query all TK 111 transactions ordered by date with running balance.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashService.php` | MODIFY | Add `getCashBook()` query method |
| `tests/CashBookTest.php` | CREATE | 4–5 tests |
| `src/.../HTTP/CashReportController.php` | MODIFY | GET /api/cash-book |
| `public/views/cash_book.php` | CREATE | Cash book register view |

**Key test scenarios:**
1. Running balance calculation after each transaction
2. Opening balance + receipts − payments = closing balance
3. Cash book filtered by date range
4. Cash book sorted by voucher number (audit requirement)

**Acceptance criteria:**
- [ ] Cash book shows: date, voucher#, description, receipt amt, payment amt, running balance
- [ ] Running balance = previous balance + receipt − payment
- [ ] Print-ready format for legal binding at period-end

---

#### P3.2: Petty Cash Management (UC-07) — 3 days

**Business value:** Petty cash is the highest-risk area for cash misappropriation. Needs tight controls.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `database/migrations/031_create_petty_cash_table.php` | CREATE | `petty_cash_funds` table |
| `src/.../Service/CashService.php` | MODIFY | Add `establishPettyCash()`, `disbursePettyCash()`, `replenishPettyCash()` |
| `tests/PettyCashTest.php` | CREATE | 6–8 tests |
| `src/.../HTTP/PettyCashController.php` | CREATE | CRUD for petty cash |
| `public/views/petty_cash.php` | CREATE | Petty cash management view |

**Key test scenarios:**
1. Establish imprest fund (Dr 111-sub — Cr 111-main)
2. Disburse from petty cash (record on voucher)
3. Replenish fund (Dr expenses — Cr 111-main)
4. Disbursement exceeds single-transaction limit → rejected
5. Surprise count: book vs. physical comparison
6. Fund closure: return remaining to main cash

---

### Phase 4: Bank Reconciliation & FX (Week 5)

---

#### P4.1: Bank Reconciliation (UC-06) — 3 days

**Business value:** Monthly bank reconciliation is mandatory. It's the most time-consuming manual task for accountants.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `database/migrations/032_create_bank_reconciliation_table.php` | CREATE | `bank_reconciliation_sessions`, `bank_reconciliation_items` |
| `src/.../Service/BankReconciliationService.php` | CREATE | Reconciliation engine (separate service — complex) |
| `tests/BankReconciliationTest.php` | CREATE | 8–10 tests |
| `src/.../HTTP/BankReconciliationController.php` | CREATE | POST /api/bank-reconciliation/start, /match, /complete |
| `public/views/bank_reconciliation.php` | CREATE | Reconciliation workspace view |

**Key test scenarios:**
1. Start reconciliation: book balance vs. bank statement balance
2. Auto-match: same amount + same date → matched
3. Auto-match: same amount + same reference → matched
4. Manual match: accountant pairs unmatched items
5. Deposits in transit identified and listed
6. Outstanding cheques identified and listed
7. Bank charges recorded as adjusting entry
8. Reconciliation report: adjusted book = adjusted bank
9. Out-of-balance blocks period-end closing
10. Prior-period reconciliation carries forward opening

**Acceptance criteria:**
- [ ] Auto-match by amount + date (configurable tolerance)
- [ ] Auto-match by reference number
- [ ] Manual match with accountant confirmation
- [ ] Adjusting entries auto-generated for bank charges/interest
- [ ] Reconciliation report printed and signed
- [ ] Out-of-balance blocks period closing

---

#### P4.2: Foreign Currency Cash (UC-08) — 2 days

**Business value:** Vietnamese enterprises with import/export operations hold FC cash and bank balances. VAS 10 requires period-end revaluation.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashService.php` | MODIFY | FC variant of receipt/payment (dual-currency) |
| `tests/CashFXTest.php` | CREATE | 4–5 tests |
| `public/views/fx_revaluation.php` | CREATE | Period-end revaluation trigger |

**Key test scenarios:**
1. FC receipt recorded with original currency + rate + VND equivalent
2. FC payment recorded at spot or book rate
3. Period-end revaluation: calculate unrealized gain/loss
4. Reversal of FC transaction

---

### Phase 5: Cash Reporting (Week 6)

---

#### P5.1: Cash Reports (UC-09) — 3 days

**Business value:** Management needs real-time cash position. Auditors need cash book and bank ledger.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `src/.../Service/CashReportService.php` | CREATE | Aggregation queries |
| `tests/CashReportTest.php` | CREATE | 4 tests |
| `src/.../HTTP/CashReportController.php` | MODIFY | GET /api/cash-reports/* |
| `public/views/cash_reports.php` | CREATE | Report viewer |
| `public/views/layout.php` | MODIFY | Add report links to sidebar |

**Reports to implement:**
1. Cash position (TK 111 + TK 112 + TK 113 balance)
2. Cash book (Sổ quỹ tiền mặt) — print-ready
3. Bank ledger (Sổ tiền gửi ngân hàng) — per bank account
4. Daily cash flow summary
5. Cash concentration (multi-bank consolidation)
6. FC cash position (original currency + VND)

---

#### P5.2: Dashboard Integration — 2 days

**Business value:** The dashboard (landing page) should show key cash metrics at a glance.

**Files:**

| File | Action | Purpose |
|---|---|---|
| `public/views/dashboard.php` | MODIFY | Add cash widgets |
| `src/.../HTTP/DashboardController.php` | MODIFY | Add cash KPI endpoints |

**Dashboard widgets:**
1. Cash balance (TK 111) — current
2. Bank balance (TK 112) — per account + total
3. Today's receipts total
4. Today's payments total
5. Pending bank reconciliation items count
6. Cash flow trend (7-day / 30-day)

---

## 4. Effort Estimate

| Phase | Modules | Man-days | Weeks | Cumulative |
|---|---|---|---|---|
| **Phase 1:** Core Cash | Cash Receipt + Cash Payment + Integration | 12 | 2 | 2 |
| **Phase 2:** Bank | Bank Transactions + Cash in Transit | 5 | 1 | 3 |
| **Phase 3:** Cash Book & Petty Cash | Cash Book + Petty Cash | 5 | 1 | 4 |
| **Phase 4:** Reconciliation & FX | Bank Reconciliation + FC Cash | 5 | 1 | 5 |
| **Phase 5:** Reporting | Reports + Dashboard | 5 | 1 | 6 |
| **Total** | **11 modules** | **32 man-days** | **6 weeks** | **6 weeks** |

### Detailed Breakdown

| Module | Service | Controller | Views | Tests | Migration | Total |
|---|---|---|---|---|---|---|
| Cash Receipt | 1d | 0.5d | 1d | 1.5d | — | 4d |
| Cash Payment | 0.5d | 0.5d | 1d | 1.5d | — | 3.5d |
| Integration | — | — | — | 2d | — | 2d |
| Bank Transactions | 1d | 0.5d | 1d | 1d | — | 3.5d |
| Cash in Transit | 0.5d | 0.5d | — | 1d | 0.5d | 2.5d |
| Cash Book | 0.5d | 0.5d | 1d | 1d | — | 3d |
| Petty Cash | 1d | 0.5d | 1d | 1.5d | 0.5d | 4.5d |
| Bank Reconciliation | 2d | 1d | 1.5d | 2d | 0.5d | 7d |
| FC Cash | 1d | 0.5d | 0.5d | 1d | — | 3d |
| Reports | 1d | 0.5d | 1d | 1d | — | 3.5d |
| Dashboard | 0.5d | 0.5d | 1d | — | — | 2d |
| **Total** | **9.5d** | **5.5d** | **9d** | **13.5d** | **1.5d** | **~39d** |

---

## 5. Dependency Graph

```
Week 1-2: Core Cash
├── P1.1 Cash Receipt (UC-01) ────────────────────────┐
├── P1.2 Cash Payment (UC-02) ────────────────────────┤
│                                                      │
Week 3: Bank                                           │
├── P2.1 Bank Transactions (UC-03) ◄─────── P1.1 ─────┤
├── P2.2 Cash in Transit (UC-04) ◄── P2.1 ────────────┤
│                                                      │
Week 4: Register + Petty                               │
├── P3.1 Cash Book (UC-05) ◄── P1.1 + P1.2 ──────────┤
├── P3.2 Petty Cash (UC-07) ◄── P1.2 ────────────────┤
│                                                      │
Week 5: Reconciliation                                 │
├── P4.1 Bank Reconciliation (UC-06) ◄── P2.1 + P2.2 ─┤
├── P4.2 FC Cash (UC-08) ◄── P1.1 + P2.1 ────────────┤
│                                                      │
Week 6: Reports                                        │
├── P5.1 Cash Reports (UC-09) ◄── ALL ────────────────┤
└── P5.2 Dashboard ◄── P5.1 ──────────────────────────┘

Critical path: Receipt → Bank → Reconciliation → Reports
```

---

## 6. Testing Strategy

### 6.1 Test Count Estimate

| Test file | Tests | Phase |
|---|---|---|
| `tests/CashReceiptTest.php` | 10 | P1.1 |
| `tests/CashPaymentTest.php` | 10 | P1.2 |
| `tests/CashIntegrationTest.php` | 5 | P1.3 |
| `tests/CashBankTest.php` | 8 | P2.1 |
| `tests/CashTransitTest.php` | 5 | P2.2 |
| `tests/CashBookTest.php` | 5 | P3.1 |
| `tests/PettyCashTest.php` | 8 | P3.2 |
| `tests/BankReconciliationTest.php` | 10 | P4.1 |
| `tests/CashFXTest.php` | 5 | P4.2 |
| `tests/CashReportTest.php` | 4 | P5.1 |
| **Total new tests** | **~70** | |
| Existing tests | 121 | |
| **Grand total (after completion)** | **~191** | |

### 6.2 Test Patterns (Reuse from Inventory)

Each test file follows the exact same pattern as `tests/InventoryReceiptTest.php`:

```php
// 1. Bootstrap autoloader
// 2. Create PDO connection + repos
// 3. Create service
// 4. Define assertEq/assertTrue helpers
// 5. Reset DB state (UPDATE accounts SET balance = 0)
// 6. Test each scenario independently
// 7. Assert: account balances, transaction status, audit fields
```

### 6.3 Key Test Assertions per Transaction

Every cash transaction test must verify:
1. **Dr account balance** increased/decreased by correct amount
2. **Cr account balance** increased/decreased by correct amount
3. **Transaction status** = "posted"
4. **Voucher number** is sequential
5. **Audit fields** (created_by, created_at) populated
6. **Cash book running balance** correct (UC-05 integration)

---

## 7. Key Risks and Mitigations

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| **Insufficient cash balance not checked** | Medium | High — allows overdraft | `CashService::recordPayment()` queries TK 111 balance before posting. Same pattern as `InventoryService::issueGoods()` stock check. |
| **Duplicate voucher numbers** | Low | High — audit failure | Auto-number with DB sequence or UUID + prefix. Unique constraint on voucher_number. |
| **Bank reconciliation out of balance** | High | High — blocks period close | Reconciliation report shows exact difference. System suggests adjusting entry to force-balance (with flag). |
| **FX rate inconsistency** | Medium | Medium — misstated FC balance | Enforce single rate source per entity. Log rate used per transaction. Alert on rate policy change. |
| **Back-dated cash entries** | Medium | High — period integrity breach | Restrict entry date to current open period. Require manager override for prior-period entries. |
| **Petty cash misappropriation** | Medium | High — fraud | Surprise count workflow. Per-disbursement max limit. Replenishment requires full documentation. |

---

## 8. Integration Points with Existing Modules

| Existing Module | Integration with Cash & Bank |
|---|---|
| **InventoryService** (receipt) | Purchase of inventory → Dr 152 — Cr 331 (AP). Later, AP payment → Dr 331 — Cr 111/112 (cash payment module). |
| **InventoryService** (issue/sale) | Sale of goods → Dr 632 — Cr 152. Customer payment → Dr 111/112 — Cr 131 (cash receipt module). |
| **JournalService** | All cash transactions post through `JournalService::postEntry()`. Existing Dr/Cr validation applies. |
| **COA (111, 112, 113)** | Accounts already seeded. `is_control` flag default false for 111/112/113. |
| **Trial Balance** | Cash/bank transactions automatically reflected in trial balance (no change needed). |
| **Exchange Rates** | FC cash transactions read from existing exchange rate master data. |

---

## 9. Recommended Implementation Order

The following is the **build order** for a single developer following TDD:

```
Day  1-2:  CashService + recordReceipt() + CashReceiptController + view
Day  3-4:  CashPaymentTest + recordPayment() + CashPaymentController + view
Day  5:    Integration test: receipt → payment → TB
Day  6-7:  BankTransactionController + bank deposit/withdrawal + view
Day  8:    CashTransitTest + transit tracking
Day  9-10: CashBookTest + cash book query + view
Day 11-13: PettyCashTest + petty cash CRUD + view
Day 14-16: BankReconciliationTest + reconciliation engine + view  ← hardest
Day 17:    CashFXTest + FC cash
Day 18-19: CashReportTest + reports + dashboard widgets
Day 20:    Buffer / bug fixes
```

**Total: ~20 working days (4 weeks) for a single senior developer.**

---

## 10. Immediate Next Steps

1. **Accept this roadmap** — review phase priorities, adjust if needed
2. **Begin P1.1: Cash Receipt** — write `tests/CashReceiptTest.php` first (TDD red), then implement `CashService::recordReceipt()`, then controller + view
3. **Run existing 121 tests** — confirm zero regressions after each phase
4. **Review after P1.2** — validate the pattern before proceeding to Phase 2
