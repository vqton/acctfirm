# Spec: Procurement Engine (Mua hàng) — Full Lifecycle

## Version & Sources

| Source | Active | Key Finding |
|---|---|---|
| ketoanthienung.net | ✅ Updated 2026 | 6-step purchase decision process, inventory docs 01-VT→07-VT |
| ketoanleanh.edu.vn | ✅ Updated 2026 | "kế toán mua hàng" training, TT99 compliance, 26-buổi course covers PR→PO→payment |
| webketoan.com | ✅ 652K members | PR→PO→GR→Invoice 3-way match, supplier management, budget control threads |
| EY Vietnam | ✅ active | Source-to-Pay cycle, direct procurement digital transformation, tax compliance |
| PWC Vietnam | ✅ active | Internal controls advisory, procurement process audit, SoD (Segregation of Duties) |
| Deloitte Vietnam | ✅ active | PO management, supplier communication, contract & pricing compliance, tail-spend |
| KPMG Vietnam | ✅ active | PR→PO streamlining, supplier/contract management, procurement KPIs |
| Circular 99/2025/TT-BTC | ✅ Effective 01-Jan-2026 | **Mandatory**: documented approval matrix, 3-step procurement workflow (Request→Review→Authorize), internal controls |

## Objective

Build procurement engine matching MISA/FAST/Bravo standards — full purchase lifecycle from requisition to payment reconciliation. Current system has AP invoices + inventory receipt but no purchase ordering, no approval workflow, no budget control.

Scope: Trade (mua hàng bán lại), Manufacturing (mua NVL), Service (mua dịch vụ), Asset (mua TSCĐ).

## Boundary

- **Always:** PR→PO→GR→Invoice 3-way match, budget check, approval routing, audit trail
- **Ask first:** e-Procurement integration (e-Invoice auto-fetch), supplier portal, multi-company
- **Never:** Delete purchase doc after approval, bypass approval for > threshold, allow PO edit after GR

---

## 1. Use Cases

### UC-01: Create Purchase Requisition (PR)

| Field | Value |
|---|---|
| Actor | Dept staff (requester) |
| Trigger | Need for goods/services identified |
| Precondition | User has `purchase.create` permission |
| Postcondition | PR created in status `draft` |

**Happy path:**
1. User opens PR form → system loads items from inventory or free-text
2. User enters: items (code/qty/uom/price_est), delivery date, department, project (optional), note
3. System validates: qty > 0, delivery date >= today+lead_time, estimated total <= dept budget_remaining
4. System saves PR with status `pending` → triggers approval workflow (UC-04)
5. System logs audit: `purchase.pr.create`, PR id, items, total_est

**Alternative A1 — Budget exceeded:**
1. System shows warning "Vượt ngân sách phòng ban: XXX VND"
2. User can override with CFO reason — PR goes to CFO-level approval

**Alternative A2 — Item not in catalog:**
1. User enters free-text item name + estimated price
2. System flags as `non-catalog` — requires manager approval

### UC-02: Create Purchase Order (PO)

| Field | Value |
|---|---|
| Actor | Purchase staff (buyer) |
| Trigger | Approved PR ready for sourcing |
| Precondition | PR status = `approved`, supplier selected |
| Postcondition | PO created, status `sent` → supplier notified |

**Happy path:**
1. Buyer selects approved PR → system pre-fills items from PR
2. Buyer selects supplier from approved supplier list or creates one-time supplier
3. Buyer enters: negotiated price, payment terms, delivery terms, contract ref (optional)
4. System auto-generates PO number: `PO{YYYY}-{000000}` (sequence per year)
5. System validates: PO total <= PR estimated total (or flag override)
6. System saves PO status `pending_approval` → triggers PO approval (if PO total > buyer authority)
7. If PO total within buyer authority → auto-approve → status `sent`
8. System logs audit: `purchase.po.create`, PO id, supplier, total

**Alternative A3 — Partial fulfillment from PR:**
1. Buyer selects only part of PR items → system marks PR as `partially_fulfilled`
2. Remaining items can be ordered later via another PO

**Alternative A4 — Multiple PRs consolidated into one PO:**
1. Buyer selects 2+ approved PRs → system merges items, preserves PR references
2. Each PR status updated to `fulfilled` after PO sent

**Alternative A5 — Urgent PO without PR:**
1. User with `purchase.urgent` permission creates PO directly
2. System requires override reason + CFO approval

### UC-03: Goods Receipt (GR) / Service Entry

| Field | Value |
|---|---|
| Actor | Warehouse staff (goods) / Dept staff (services) |
| Trigger | Goods arrive / service completed |
| Precondition | PO status = `sent` or `partially_received` |
| Postcondition | Inventory updated (goods) or Service entry recorded |

**Happy path (goods):**
1. User selects PO → system shows ordered items, qty, expected delivery date
2. User enters: received qty, batch/lot (if tracked), expiry date (if tracked), warehouse location
3. System creates GR with status `completed` → generates PNK number: `PNK{YYYY}-{000000}`
4. System updates PO: received_qty += qty → if total_received >= ordered_qty → PO status `completed`
5. System posts inventory journal: Dr 152/153/155/156 / Cr 331 (via JournalService)
6. If VAT invoice available → Dr 1331 / Cr 331 (separate line)
7. System logs audit: `purchase.gr.create`, GR id, PO ref, qty

**Alternative A6 — Partial receipt:**
1. User receives less than ordered → PO status stays `partially_received`
2. Remaining qty can be received later until PO close date

**Alternative A7 — Over-delivery:**
1. Received qty > ordered qty → system requires manager approval
2. If approved → PO ordered_qty updated to match receipt

**Alternative A8 — Quality reject:**
1. User enters received_qty = 0, reject_qty = full → creates return to supplier workflow
2. System notifies buyer for replacement order

**Alternative A9 — Service entry (no inventory):**
1. No inventory impact — system records service acceptance certificate
2. System creates journal: Dr 641/642/627/241 / Cr 331

### UC-04: Approval Workflow (PR/PO)

| Field | Value |
|---|---|
| Actor | Approver (manager/CFO/director) |
| Trigger | PR or PO submitted for approval |
| Precondition | Document status = `pending` |
| Postcondition | Document approved/rejected |

**Happy path:**
1. Approver sees pending docs on approval dashboard
2. System shows: doc type (PR/PO), total amount, department, urgency
3. Approver reviews → clicks Approve (with optional note)
4. System updates status to `approved` → notifies requester/buyer
5. System logs audit: `purchase.approve`, doc type, doc id, approver

**Approval routing rules:**
| Threshold | Required approvers |
|---|---|
| < 5,000,000 | Dept manager |
| 5,000,000 – 50,000,000 | Dept manager + CFO |
| 50,000,000 – 500,000,000 | Dept manager + CFO + Director |
| > 500,000,000 | Dept manager + CFO + Director + BOD |

**Alternative A10 — Rejection:**
1. Approver clicks Reject + required reason
2. System returns doc to `draft` → requester revises
3. System notifies requester with rejection reason

**Alternative A11 — Escalation:**
1. If approver does not act within 48h → system escalates to next level
2. Escalation chain defined in dept config

### UC-05: Supplier Invoice Matching (3-Way Match)

| Field | Value |
|---|---|
| Actor | AP accountant |
| Trigger | Supplier invoice received (e-Invoice or manual) |
| Precondition | PO exists, GR exists |
| Postcondition | Invoice verified → ready for payment |

**Happy path:**
1. AP enters invoice: PO ref, invoice number, invoice date, total amount, VAT amount
2. System auto-loads GR lines from PO
3. System performs 3-way match:

| Match criteria | Expected | Actual | Tolerance |
|---|---|---|---|
| Qty invoiced vs Qty received | = | = | ±5% (configurable) |
| Unit price invoiced vs PO price | = | = | ±2% |
| VAT rate | = | = | 0% |

4. System displays match result: `matched` / `warning` / `mismatch`
5. If all matched → system records invoice as `verified` → AP can schedule payment
6. System creates AP invoice record → updates supplier balance
7. System logs audit: `purchase.invoice.match`, invoice ref, match result

**Alternative A12 — Price variance within tolerance:**
1. System shows warning but allows posting with override note
2. Variance posted to price variance account (TK 152/156 price diff sub-account)

**Alternative A13 — Price variance exceeds tolerance:**
1. System blocks posting → requires buyer negotiation with supplier
2. Supplier must issue credit note or PO amendment

**Alternative A14 — Quantity variance (under-delivery invoiced at full qty):**
1. System flags mismatch → AP holds invoice pending GR
2. If supplier insists on invoicing full qty → create "bill-only" line with GR pending

**Alternative A15 — Invoice without PO (non-PO invoice):**
1. AP creates direct invoice → requires manager approval
2. System flags as `non-PO` — higher audit risk

### UC-06: Supplier Management

| Feature | Detail |
|---|---|
| Onboarding | Supplier registration form: tax code, bank account, payment terms, category |
| Approval | New supplier requires manager approval + KYC check |
| Blacklist | Supplier can be blacklisted with reason — blocks PO creation |
| Performance | Rating (1-5), on-time %, quality reject %, price competitiveness |
| Contracts | Link contracts to supplier, auto-calculate contract spend vs budget |
| Integration | E-Invoice auto-download for registered suppliers via TCT API |

### UC-07: Budget Control

| Feature | Detail |
|---|---|
| Budget setup | Annual budget per dept/project, broken down by month/quarter |
| Commitment | PR approval = commitment booking (encumbrance accounting) |
| Actual | PO → GR → Invoice → Payment reduce remaining budget |
| Warning | 80% consumed → yellow alert, 95% → red alert |
| Override | Budget override requires CFO approval + budget reallocation doc |

---

## 2. Processes

### Core Process: Purchase-to-Pay (P2P)

```
[Dept]           [Purchase]        [Warehouse]      [AP/Finance]     [Supplier]
  │                  │                  │                │               │
  ├─ PR ───────────► │                  │                │               │
  │  (req)           ├─ PO ────────────────────────────────────────────► │
  │                  │  (order)         │                │               │
  │                  │                  │◄────────────── GR ─────────────┤
  │                  │                  │  (goods +      │  (goods      │
  │                  │                  │   invoice)     │   delivery)  │
  │                  │                  │                │               │
  │                  │                  │  GR ──────────►│               │
  │                  │                  │                │               │
  │                  │                  │                ├─ 3-way match │
  │                  │                  │                │               │
  │                  │                  │                ├─ Payment ────►│
  │                  │                  │                │               │
```

### Sub-process: Procurement Approval

```
PR Draft ──→ Pending ──→ Approved ──→ PO
  ▲               │           │
  │               ├─ Rejected │
  │               │    │      │
  └───────────────┘    │      └──→ Budget check ──→ OK
                       │               │
                       │               └─ Exceeded ──→ CFO override
                       │                              │
                       └──────────────────────────────┘
```

---

## 3. Business Rules

| ID | Rule | Severity |
|---|---|---|
| PUR-R01 | PR total > 0 | ERROR |
| PUR-R02 | PO total = sum(line qty × unit price) | ERROR |
| PUR-R03 | Line qty > 0 | ERROR |
| PUR-R04 | Unit price > 0 (except FOC items = 0) | ERROR |
| PUR-R05 | Supplier must be active (not blacklisted) | ERROR |
| PUR-R06 | PR delivery date >= PR date + dept min lead time | WARN |
| PUR-R07 | PO unit price <= PR estimated price × 110% | WARN |
| PUR-R08 | GR received_qty <= PO ordered_qty (over-delivery requires approval) | ERROR |
| PUR-R09 | 3-way match qty tolerance = ±5% | WARN |
| PUR-R10 | 3-way match price tolerance = ±2% | WARN |
| PUR-R11 | Invoice without PO = must be manually approved | ERROR |
| PUR-R12 | Budget consumed > 80% → warning to dept manager | WARN |
| PUR-R13 | Budget consumed > 95% → blocks PR creation (CFO override) | ERROR |
| PUR-R14 | PR/PO cannot be deleted after approval — only cancel with reason | ERROR |
| PUR-R15 | Every P2P document must have unique number per year | ERROR |
| PUR-R16 | Approval chain must follow SoD: Requester ≠ Approver ≠ Buyer ≠ AP | ERROR |

---

## 4. Data Model

```sql
-- Purchase Requisition (Đề nghị mua hàng)
CREATE TABLE purchase_requisitions (
    id VARCHAR(36) PRIMARY KEY,         -- PR{YYYY}-{000000}
    pr_number VARCHAR(20) UNIQUE,
    status ENUM('draft','pending','approved','rejected','fulfilled','cancelled'),
    requester_id VARCHAR(36),
    department_id VARCHAR(36),
    project_id VARCHAR(36) NULL,
    total_estimated DECIMAL(15,2),
    delivery_date DATE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
    -- FK: created_by -> users, dept -> departments
);

CREATE TABLE purchase_requisition_lines (
    id VARCHAR(36) PRIMARY KEY,
    pr_id VARCHAR(36) NOT NULL,
    item_id VARCHAR(36) NULL,           -- NULL if non-catalog
    free_text_name VARCHAR(255) NULL,    -- for non-catalog items
    qty DECIMAL(15,2) NOT NULL,
    uom_id VARCHAR(36),
    price_estimate DECIMAL(15,2),
    total_estimate DECIMAL(15,2) GENERATED ALWAYS AS (qty * price_estimate),
    is_catalog BOOLEAN DEFAULT TRUE
);

-- Purchase Order (Đơn đặt hàng)
CREATE TABLE purchase_orders (
    id VARCHAR(36) PRIMARY KEY,         -- PO{YYYY}-{000000}
    po_number VARCHAR(20) UNIQUE,
    status ENUM('draft','pending_approval','sent','partially_received','completed','cancelled'),
    supplier_id VARCHAR(36) NOT NULL,
    contract_id VARCHAR(36) NULL,
    buyer_id VARCHAR(36),
    payment_terms VARCHAR(100),
    delivery_terms VARCHAR(100),
    total_amount DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    grand_total DECIMAL(15,2) GENERATED ALWAYS AS (total_amount + tax_amount),
    currency_code VARCHAR(3) DEFAULT 'VND',
    exchange_rate DECIMAL(15,4) DEFAULT 1,
    expected_delivery DATE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE purchase_order_lines (
    id VARCHAR(36) PRIMARY KEY,
    po_id VARCHAR(36) NOT NULL,
    pr_line_id VARCHAR(36) NULL,        -- reference to PR line
    item_id VARCHAR(36) NULL,
    free_text_name VARCHAR(255) NULL,
    qty_ordered DECIMAL(15,2) NOT NULL,
    qty_received DECIMAL(15,2) DEFAULT 0,
    qty_invoiced DECIMAL(15,2) DEFAULT 0,
    uom_id VARCHAR(36),
    unit_price DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) GENERATED ALWAYS AS (qty_ordered * unit_price)
);

-- Goods Receipt (Phiếu nhập kho)
CREATE TABLE goods_receipts (
    id VARCHAR(36) PRIMARY KEY,         -- PNK{YYYY}-{000000}
    gr_number VARCHAR(20) UNIQUE,
    po_id VARCHAR(36) NOT NULL,
    status ENUM('draft','completed','cancelled'),
    warehouse_id VARCHAR(36),
    received_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE goods_receipt_lines (
    id VARCHAR(36) PRIMARY KEY,
    gr_id VARCHAR(36) NOT NULL,
    po_line_id VARCHAR(36) NOT NULL,
    item_id VARCHAR(36) NOT NULL,
    qty_received DECIMAL(15,2) NOT NULL,
    qty_rejected DECIMAL(15,2) DEFAULT 0,
    batch_no VARCHAR(50) NULL,
    expiry_date DATE NULL,
    unit_price DECIMAL(15,2),           -- from PO
    total DECIMAL(15,2) GENERATED ALWAYS AS (qty_received * unit_price)
);

-- Invoice Matching (Đối chiếu hóa đơn)
CREATE TABLE purchase_invoice_matches (
    id VARCHAR(36) PRIMARY KEY,
    po_id VARCHAR(36) NOT NULL,
    gr_id VARCHAR(36) NULL,
    supplier_invoice_no VARCHAR(100),
    invoice_date DATE,
    invoice_amount DECIMAL(15,2),
    vat_amount DECIMAL(15,2),
    match_status ENUM('pending','matched','warning','mismatch','cancelled'),
    matched_by VARCHAR(36),
    matched_at TIMESTAMP NULL,
    note TEXT
);

CREATE TABLE purchase_invoice_match_lines (
    id VARCHAR(36) PRIMARY KEY,
    match_id VARCHAR(36) NOT NULL,
    po_line_id VARCHAR(36) NOT NULL,
    gr_line_id VARCHAR(36) NULL,
    qty_invoiced DECIMAL(15,2),
    qty_received DECIMAL(15,2),
    unit_price_invoiced DECIMAL(15,2),
    unit_price_po DECIMAL(15,2),
    qty_tolerance_pass BOOLEAN,
    price_tolerance_pass BOOLEAN
);

-- Approval Workflow (Phê duyệt)
CREATE TABLE purchase_approvals (
    id VARCHAR(36) PRIMARY KEY,
    doc_type ENUM('pr','po','non_po_invoice'),
    doc_id VARCHAR(36) NOT NULL,
    step INT DEFAULT 1,
    approver_id VARCHAR(36) NOT NULL,
    status ENUM('pending','approved','rejected','escalated'),
    note TEXT,
    acted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_step (doc_type, doc_id, step)
);

-- Supplier Management (Quản lý nhà cung cấp)
ALTER TABLE suppliers ADD COLUMN (
    supplier_category VARCHAR(50),       -- raw_material/ services/ assets/ trading
    payment_terms VARCHAR(100),
    bank_account VARCHAR(50),
    bank_name VARCHAR(255),
    credit_limit DECIMAL(15,2) DEFAULT 0,
    rating INT DEFAULT 0,
    is_blacklisted BOOLEAN DEFAULT FALSE,
    blacklist_reason TEXT NULL,
    tax_authority_code VARCHAR(50)       -- for e-Invoice auto-download
);

CREATE TABLE supplier_performance (
    id VARCHAR(36) PRIMARY KEY,
    supplier_id VARCHAR(36) NOT NULL,
    period VARCHAR(7) NOT NULL,          -- YYYY-MM
    on_time_rate DECIMAL(5,2),           -- %
    quality_reject_rate DECIMAL(5,2),    -- %
    price_competitiveness INT,           -- 1-5
    overall_rating DECIMAL(3,2)
);

-- Budget Control (Kiểm soát ngân sách)
CREATE TABLE purchase_budgets (
    id VARCHAR(36) PRIMARY KEY,
    department_id VARCHAR(36) NOT NULL,
    period VARCHAR(7) NOT NULL,          -- YYYY-MM
    budget_amount DECIMAL(15,2) NOT NULL,
    committed_amount DECIMAL(15,2) DEFAULT 0,  -- from approved PR
    actual_amount DECIMAL(15,2) DEFAULT 0,      -- from GR/Invoice
    remaining DECIMAL(15,2) GENERATED ALWAYS AS (budget_amount - committed_amount - actual_amount)
);
```

---

## 5. Data Flow

### State Machine — Purchase Order

```
                  ┌──────────────┐
                  │    Draft     │
                  └──────┬───────┘
                         │ submit
                  ┌──────▼───────┐
                  │Pending       │
                  │Approval      │
                  └──────┬───────┘
                    ┌────┴────┐
             ┌──────▼──┐ ┌───▼──────┐
             │Approved │ │ Rejected │
             └────┬────┘ └────┬─────┘
                  │            │ re-submit
                  │ send       └──→ Draft
             ┌────▼────┐
             │  Sent   │
             └────┬────┘
                  │ partial GR
             ┌────▼──────────┐
             │Partially      │
             │Received       │
             └────┬──────────┘
                  │ full GR
             ┌────▼──────┐
             │Completed  │
             └────┬──────┘
                  │ cancel
             ┌────▼──────┐
             │ Cancelled │
             └───────────┘
```

### Journal Entry Mapping

| Event | Dr | Cr | Module |
|---|---|---|---|
| GR (goods) | 152/153/155/156 @ unit_price × qty | 331 @ unit_price × qty | Inventory → AP |
| GR (VAT) | 1331 @ vat_amount | 331 @ vat_amount (same invoice) | Tax → AP |
| GR (service) | 641/642/627/241 | 331 | Cost → AP |
| GR (FOC) | 152/153/155/156 @ 0 | 711 @ estimated_value | Inventory → Income |
| Invoice match (price diff) | 152/156 (price_var) @ diff | 331 @ diff (adjust) | AP adjust |
| Payment | 331 @ paid_amount | 1111/1121 @ paid_amount | Cash → AP |
| Prepayment | 331 (prepay) | 1111/1121 | Cash → AP |

---

## 6. Workflow Detail

### Source-to-Pay (S2P) Swimlanes

```
Step │ Requester   │ Dept Mgr   │ Buyer      │ Warehouse │ AP/Finance  │ Supplier
─────┼─────────────┼────────────┼────────────┼───────────┼─────────────┼──────────
  1  │ Need ID     │            │            │           │             │
  2  │ Create PR   │            │            │           │             │
  3  │             │ Approve PR │            │           │             │
  4  │             │            │ Source     │           │             │ RFQ
  5  │             │            │ Create PO  │           │             │ PO sent
  6  │             │            │            │           │             │ Deliver
  7  │             │            │            │ Receive   │             │
  8  │             │            │            │ GR        │             │ Invoice
  9  │             │            │            │           │ 3-way match │
 10  │             │            │            │           │ Schedule pmt│
 11  │             │            │            │           │ Payment     │ ← Money
```

---

## 7. User Journeys

### Journey A — Kế toán mua hàng (daily)

1. Login → dashboard shows: PRs pending approval (5), POs to send (3), GRs to match (12)
2. Open pending PRs → check budget → approve 3 items vs dept budget
3. Open approved PRs → select supplier → create PO → send
4. Receive GR notification → verify quantities → post to inventory
5. Receive supplier invoice (e-Invoice auto-fetched) → 3-way match → verify
6. End of month → run Purchase Report by supplier, by item, by dept

### Journey B — Chief Accountant (monthly)

1. Review procurement budget vs actual by dept
2. Check SOX controls: no PO split to avoid approval (check POs just below threshold)
3. Verify unmatched GRs older than 30 days → resolve
4. Review supplier performance ratings → flag underperformers
5. Approve supplier blacklist actions

### Journey C — Director (quarterly)

1. Dashboard: Total procurement spend YTD, top 10 suppliers, budget variance
2. Drill down by dept → see dept manager procurement KPI
3. Approve large purchase contracts (> 500M)

---

## 8. Integration Contract with Existing System

| Integration Point | Existing Module | Interface |
|---|---|---|
| Journal posting | `JournalService::postEntry()` | PO → GR → JournalService (inventory + AP posting) |
| AP invoice | `ApService::create()` | Invoice match → AP invoice creation |
| Cash payment | `CashService` | AP payment schedule → Cash disbursement |
| Inventory cost | `InventoryService::receive()` | GR → Inventory cost layer update (FIFO/avg) |
| Tax | `VatService` | Invoice VAT → VAT declaration preparation |
| Period close | `PeriodService` | Block PO/GR in closed period |
| Audit log | `AuditLogger::log()` | Every P2P event logged |
| Notification | `NotificationService` (future) | Approval requests, GR arrivals, payment due |

---

## 9. Posting Rules (GL Engine)

75 existing posting rules + new rules for procurement:

| Rule | Dr | Cr | Action |
|---|---|---|---|
| PUR-001 | 152/153/155/156 | 331 | Goods purchase (no VAT) | pass |
| PUR-002 | 152/153/155/156 + 1331 | 331 | Goods purchase (with VAT) | pass |
| PUR-003 | 641/642/627 | 331 | Service purchase | pass |
| PUR-004 | 211 | 331 | Asset purchase | pass |
| PUR-005 | 1331 | 331 | VAT input only (separate line) | pass |
| PUR-006 | 152 | 1111/1121 | Direct cash purchase (small value) | block (must go through PO) |
| PUR-007 | 331 | 1111/1121 | Supplier payment | pass |
| PUR-008 | 331 | 331 | Prepayment clearing | pass |

---

## 10. Reports

| Report | Purpose | Source |
|---|---|---|
| Purchase Order Register | List all POs by period/supplier/dept | purchase_orders |
| Goods Receipt Aging | GRs not yet invoiced > X days | goods_receipts |
| Invoice Matching Report | Matched/warning/mismatch summary | purchase_invoice_matches |
| Supplier Performance | On-time %, reject %, rating | supplier_performance |
| Budget vs Actual | Dept budget consumption by month | purchase_budgets + actuals |
| Procurement Spend by Category | Spend by item category/supplier | purchase_order_lines |
| Top 10 Suppliers | By total spend this year | purchase_orders aggregate |
| Non-PO Invoice Report | High-risk non-PO invoices | ap_invoices WHERE no PO ref |
| Approval Cycle Time | Avg hours from PR→PO→approve | purchase_approvals |

---

## 11. Validation & Internal Control (Circular 99 compliance)

| Control | Required by | Implementation |
|---|---|---|
| SoD: Requester ≠ Approver | Circ 99 Art 3 + PWC IA | `purchase_approvals` check user chain |
| SoD: Buyer ≠ AP | Circ 99 Art 3 | PO creator ≠ Invoice matcher |
| 3-step approval chain | Circ 99 Art 3 + KPMG | PR→Review→Authorize workflow |
| Budget control | EY S2P best practice | `purchase_budgets` commitment tracking |
| No delete after approval | Circ 99 record retention | `status` = cancelled only, data preserved |
| Audit trail | Circ 99 Art 13 | `AuditLogger::log()` every event |
| Document numbering | Circ 99 sequential req | `PO{YYYY}-{000000}` auto-sequence |
| 3-way match | Deloitte procurement ops | `purchase_invoice_matcher` engine |
| Supplier blacklist | KPMG supplier mgmt | `suppliers.is_blacklisted` flag |
| e-Invoice integration | ND 70/2025 + EY tax | Future: TCT API auto-download |

---

## 12. Success Criteria

- [ ] PR→PO→GR→Invoice 3-way match end-to-end works
- [ ] Budget control: blocks PR when 95% consumed (CFO override allowed)
- [ ] Approval routing: routes to correct approver per amount threshold
- [ ] SoD enforced: requester ≠ approver ≠ buyer ≠ AP
- [ ] All 8 journal entry types post correctly via JournalService
- [ ] Audit trail recorded for every P2P event
- [ ] Full test suite: 20+ tests (happy path + failure cases)
- [ ] All existing 49 test files still pass

## 13. Open Questions

1. e-Invoice auto-download from TCT API — P2 or P3?
2. Multi-currency PO support — needed now or later?
3. Supplier portal (self-service) — P3?
4. Mobile approval for PR/PO — needed?
5. Contract management depth — simple contract ref or full CLM?

---

## 14. Effort Estimate

| Phase | Component | Effort |
|---|---|---|
| P1 | DB tables (10 tables) + migrations | 2 days |
| P1 | Models + Repository interfaces + PDO repos | 3 days |
| P1 | Services: PR, PO, GR, Approval, Budget, Supplier | 8 days |
| P1 | Controllers + Routes + JSON API | 3 days |
| P1 | Views (Bootstrap 5 + jQuery) | 5 days |
| P1 | 3-way match engine | 3 days |
| P1 | Tests | 3 days |
| P2 | Budget control UI + reports | 3 days |
| P2 | Supplier performance + blacklist | 2 days |
| P2 | Non-PO invoice workflow | 2 days |
| P3 | e-Invoice integration | 5 days |
| P3 | Supplier portal | 5 days |
| | **Total** | **~44 days** |
