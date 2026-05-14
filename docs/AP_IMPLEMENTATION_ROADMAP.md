# Implementation Roadmap — Accounts Payable Module (TK 331)

**Base Spec:** `docs/AP_USE_CASE_SPECIFICATION.md`
**Regulatory Basis:** Circular 99/2025/TT-BTC — TK 331
**Current State:** Supplier master data exists. TK 331 used via JournalService for basic AP posting. No invoice-level tracking, no aging, no payment matching.

---

## Current State Assessment

| Capability | Status | Detail |
|---|---|---|
| Supplier master data | ✅ | `SupplierController` CRUD |
| JournalService posts to TK 331 | ✅ | Inventory receipt Dr 152 — Cr 331 |
| Cash payment posts to TK 331 | ✅ | `CashService::recordPayment()` Dr 331 — Cr 111 |
| **Invoice-level AP tracking** | ❌ | No `ap_invoices` table — can't track per-invoice payable |
| **Payment matching to invoices** | ❌ | Payments reduce total supplier balance but don't match specific invoices |
| **AP aging** | ❌ | No aging buckets (current/30/60/90/120+) |
| **Purchase returns** | ❌ | No structured return-to-supplier flow |
| **Settlement discounts** | ❌ | No automated discount capture |
| **FC AP tracking** | ❌ | No FC payable tracking per supplier |
| **AP write-off** | ❌ | No unidentifiable creditor process |
| **Supplier statement** | ❌ | No per-supplier transaction history view |

---

## Implementation Plan

### Phase 1: AP Sub-Ledger Foundation (2 days)

**Goal:** Build the `ap_invoices` table as the core sub-ledger. All AP transactions reference an invoice. This enables aging, payment matching, and per-supplier tracking.

| Task | UC | Files | Effort |
|---|---|---|---|
| **Migration 039** — `ap_invoices` table | UC-001–010 | `database/migrations/039_create_ap_invoices_table.php` | 0.5d |
| **Service** — `ApService` | UC-001–010 | `src/.../Service/ApService.php` | 1d |
| **Tests** — `ApTest` | UC-001–010 | `tests/ApTest.php` | 0.5d |

#### Schema — `ap_invoices`

```sql
CREATE TABLE ap_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(50) NOT NULL,
    invoice_number VARCHAR(100) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency_code VARCHAR(3) DEFAULT 'VND',
    exchange_rate DECIMAL(15,4) DEFAULT 1,
    gross_amount DECIMAL(15,2) NOT NULL,        -- Total including VAT
    net_amount DECIMAL(15,2) NOT NULL,          -- Before VAT
    vat_amount DECIMAL(15,2) DEFAULT 0,
    vat_rate DECIMAL(5,2) DEFAULT 0,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance DECIMAL(15,2) NOT NULL,             -- Outstanding
    status VARCHAR(20) DEFAULT 'unpaid',        -- unpaid, partial, paid, overdue
    description TEXT,
    created_by VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status)
);
```

#### Migration: also add `ap_invoice_id` column to ledger_entries

```sql
ALTER TABLE ledger_entries ADD COLUMN ap_invoice_id INT DEFAULT NULL AFTER note;
```

Or simpler: create an `ap_payments` table to track payment-invoice matching:

```sql
CREATE TABLE ap_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ap_invoice_id INT NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_type VARCHAR(20) DEFAULT 'payment',  -- payment, prepayment, discount, return
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);
```

---

### Phase 2: AP Transactions (3 days)

**Goal:** Build CRUD for AP transactions: record invoice, record payment, record return, record discount.

| Task | UC | Files | Effort |
|---|---|---|---|
| **Record invoice** — `ApService::recordInvoice()` | UC-001 | `ApService.php` | 0.5d |
| **Record payment** — `ApService::recordPayment()` | UC-003 | `ApService.php` | 0.5d |
| **Record prepayment** — `ApService::recordPrepayment()` | UC-004 | `ApService.php` | 0.5d |
| **Record return** — `ApService::recordReturn()` | UC-005 | `ApService.php` | 0.5d |
| **Record discount** — `ApService::recordDiscount()` | UC-006 | `ApService.php` | 0.5d |
| **FC AP revaluation** — `ApService::revalueFC()` | UC-007 | `ApService.php` | 0.5d |
| **AP write-off** — `ApService::writeOff()` | UC-008 | `ApService.php` | 0.5d |
| **Controller** — `ApController` | All | `ApController.php` | 0.5d |
| **Tests** | All | `ApTest.php` | 1d |

#### Key design: each method posts via JournalService + updates ap_invoices + tracks in ap_payments

```
ApService::recordInvoice():
    1. JournalService::postEntry(Dr 152/156 — Cr 331)
    2. INSERT INTO ap_invoices
    3. Update supplier balance

ApService::recordPayment():
    1. JournalService::postEntry(Dr 331 — Cr 111/112)
    2. INSERT INTO ap_payments (match to invoice)
    3. UPDATE ap_invoices SET paid_amount, balance
    
ApService::recordReturn():
    1. JournalService::postEntry(Dr 331 — Cr 152/156 + Cr 133)
    2. INSERT INTO ap_payments (return type)
    3. UPDATE ap_invoices SET balance
```

---

### Phase 3: AP Views (1.5 days)

**Goal:** Build supplier-facing AP views: aging report, supplier statement, transaction list.

| Task | UC | Files | Effort |
|---|---|---|---|
| **AP aging view** — filterable by supplier, aging buckets | UC-009 | `public/views/ap_aging.php` | 0.5d |
| **Supplier statement** — per-supplier transaction history | UC-010 | `public/views/ap_statement.php` | 0.5d |
| **Invoice list** — all invoices filterable by status/supplier | UC-001 | `public/views/ap_invoices.php` | 0.5d |

#### Aging query logic

```sql
SELECT 
    s.name as supplier_name,
    i.invoice_number, i.invoice_date, i.due_date, i.gross_amount,
    i.balance,
    CASE 
        WHEN i.balance <= 0 THEN 'paid'
        WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 'current'
        WHEN DATEDIFF(CURDATE(), i.due_date) <= 30 THEN '1-30 days'
        WHEN DATEDIFF(CURDATE(), i.due_date) <= 60 THEN '31-60 days'
        WHEN DATEDIFF(CURDATE(), i.due_date) <= 90 THEN '61-90 days'
        ELSE '90+ days'
    END as aging_bucket
FROM ap_invoices i
JOIN suppliers s ON s.id = i.supplier_id
WHERE i.balance > 0
ORDER BY i.due_date ASC;
```

---

### Phase 4: Sidebar + Routes (0.5 day)

| Task | Files | Effort |
|---|---|---|
| Add AP routes (10+ endpoints) | `routes.php` | 0.25d |
| Add sidebar links under "Mua hàng" | `layout.php` | 0.25d |

---

## Effort Summary

| Phase | Module | Days | Cumulative |
|---|---|---|---|
| P1 | AP Sub-ledger foundation | 2 | 2 |
| P2 | AP transactions (invoice/payment/return/discount) | 3 | 5 |
| P3 | AP views (aging, statement, invoices) | 1.5 | 6.5 |
| P4 | Routes + sidebar | 0.5 | 7 |
| **Total** | | **7 days** | |

---

## Dependency Graph

```
Phase 1: Migration (ap_invoices + ap_payments)
    │
    └── Phase 2: ApService methods (invoice → payment → return → discount → FC → write-off)
            │
            ├── Phase 3: AP aging view (reads ap_invoices)
            ├── Phase 3: Supplier statement (reads ap_invoices + ap_payments)
            └── Phase 3: Invoice list (reads ap_invoices)
    
Phase 4: Routes + sidebar (depends on Phase 2 controller)
```

---

## Go-Live Minimum

**Phase 1 + Phase 2 (5 days)** is the minimum viable AP module. With invoice recording + payment matching:
- Track what you owe per invoice
- Match payments to specific invoices
- Compute accurate aging

Phase 3 (views) adds visibility. Without it, AP data exists but can't be seen. Phase 1+2+3 is 6.5 days to a complete, visible AP module.

**Recommendation:** Build P1 + P2 + P3 in sequence — 6.5 days total. The views are essential because AP data without aging and statement views is invisible to the user.
