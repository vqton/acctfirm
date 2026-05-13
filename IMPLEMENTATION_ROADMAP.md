# Inventory Accounting System — Implementation Roadmap

**Prepared by:** Lead Business Analyst
**Date:** May 2026
**Regime:** Circular 99/2025/TT-BTC
**Current State:** 16/16 master tables implemented (100% complete)

---

## 1. Current Status

| Layer | Count | Status |
|---|---|---|
| Master Data Tables | 16 | ✅ COMPLETE |
| Database Migrations | 19 | ✅ COMPLETE |
| API CRUD Endpoints | 16 | ✅ COMPLETE |
| Bootstrap 5 Views | 16 | ✅ COMPLETE |
| Domain Models | 19 | ✅ COMPLETE |
| Repositories | 19 | ✅ COMPLETE |
| **Transaction Modules** | **0** | ❌ NOT STARTED |

---

## 2. Recommended Implementation Order

### Phase 1: Foundation (Weeks 1–2)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P1** | **Seed Chart of Accounts** | Every transaction posts to COA. Must exist first. | — |
| **P2** | **Opening Balances** | Enterprise cannot start with zero balances. Must load opening inventory, AR, AP, cash. | — |

### Phase 2: Core Cash & Bank (Weeks 3–4)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P3** | **Cash Receipt (Phiếu thu)** | Most frequent daily operation | — |
| **P4** | **Cash Payment (Phiếu chi)** | Most frequent daily operation | — |
| **P5** | **Bank Receipt/Payment (Giấy báo Có/Nợ)** | Bank transaction recording | — |
| **P6** | **Bank Reconciliation** | Monthly mandatory control | — |
| **P7** | **Petty Cash / Advance (Tạm ứng)** | Employee advance management | — |

### Phase 3: Trade Modules (Weeks 5–8)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P8** | **Purchase Order (Đơn đặt hàng)** | Controls procurement commitment | — |
| **P9** | **Purchase Receipt / Goods Receipt** | **= UC-03 (Landed Cost) + UC-05** | UC-03, UC-05 |
| **P10** | **Supplier Payment (Thanh toán NCC)** | AP settlement cycle | — |
| **P11** | **Sales Order (Đơn hàng bán)** | Sales commitment tracking | — |
| **P12** | **Sales Delivery / Invoice** | Revenue + COGS recognition | UC-10 (promotional) |
| **P13** | **Customer Receipt (Thu tiền KH)** | AR collection cycle | — |
| **P14** | **Purchase Return + Sales Return** | Reverse transactions | — |

### Phase 4: Inventory Operations (Weeks 9–10)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P15** | **Inventory Receipt / Issue / Transfer** | Core warehouse operations | UC-05, UC-12 |
| **P16** | **Physical Count (Kiểm kê)** | **= UC-07 + UC-08** | UC-07, UC-08 |
| **P17** | **Inventory Cost Calculation (Tính giá xuất kho)** | **= UC-04** | UC-04 |
| **P18** | **Inventory Impairment Provision** | **= UC-09** | UC-09 |
| **P19** | **Goods in Transit (TK 151) Tracking** | Period-end cut-off | UC-01 |
| **P20** | **Consignment Goods (TK 157) Tracking** | Agent sales monitoring | UC-02 |
| **P21** | **Warehouse Transfer** | Inter-location movement | — |

### Phase 5: Production (Weeks 11–12)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P22** | **BOM (Định mức NVL)** | Material requirement planning | — |
| **P23** | **Production Order (Lệnh sản xuất)** | Manufacturing execution | — |
| **P24** | **WIP Tracking (TK 154)** | Cost accumulation | — |
| **P25** | **Finished Goods Receipt** | Output valuation | — |
| **P26** | **Cost Calculation (Tính giá thành)** | Unit cost determination | — |

### Phase 6: Fixed Assets & Payroll (Weeks 13–14)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P27** | **Asset Addition / Depreciation** | Balance sheet impact | — |
| **P28** | **Asset Disposal / Transfer / Liquidation** | Asset lifecycle end | — |
| **P29** | **Payroll Calculation** | Monthly salary processing | — |
| **P30** | **Insurance & PIT Declaration** | Statutory compliance | — |

### Phase 7: Tax & GL (Weeks 15–16)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P31** | **VAT Declaration (GTGT)** | Monthly/quarterly tax filing | — |
| **P32** | **CIT Finalization (TNDN)** | Annual tax filing | — |
| **P33** | **General Journal (Chứng từ ghi sổ)** | Manual adjustments | — |
| **P34** | **Closing Entries (Bút toán kết chuyển)** | Period-end closing | — |
| **P35** | **Financial Statements** | Legal reporting | — |

### Phase 8: Reporting & Admin (Weeks 17–18)

| Priority | Module | Business Rationale | Use Cases |
|---|---|---|---|
| **P36** | **Management Reports** | Internal decision-making | — |
| **P37** | **User Management & RBAC** | Access control | — |
| **P38** | **Audit Log** | Compliance trail | — |
| **P39** | **Backup & Restore** | Data safety | — |

---

## 3. Dependency Graph

```
Phase 1: COA Seed ──────────────────────────────────────────┐
Phase 1: Opening Balances ──────────────────────────────────┤
                                                            │
Phase 2: Cash & Bank ──────────────────────┐                │
                                          │                │
Phase 3: Purchasing ───┐                  │                │
                       ├── Phase 4: Inventory ◄────────────┤
Phase 3: Sales ────────┘                  │                │
                                          │                │
Phase 5: Production ◄─────────────────────┘                │
                                                           │
Phase 6: Fixed Assets ──────┐                              │
                            ├── Phase 7: GL & Tax ◄────────┘
Phase 6: Payroll ───────────┘                              │
                                                           │
                              Phase 8: Reporting ◄─────────┘
```

**Critical path:** COA Seed → Opening Balances → Cash → Purchasing/Sales → Inventory → GL/Tax → Reports

---

## 4. Effort Estimate

| Phase | Modules | Weeks | Cumulative |
|---|---|---|---|
| Phase 1: Foundation | 2 | 2 | 2 |
| Phase 2: Cash & Bank | 5 | 2 | 4 |
| Phase 3: Trade | 7 | 4 | 8 |
| Phase 4: Inventory | 7 | 2 | 10 |
| Phase 5: Production | 5 | 2 | 12 |
| Phase 6: FA & Payroll | 4 | 2 | 14 |
| Phase 7: Tax & GL | 5 | 2 | 16 |
| Phase 8: Reporting & Admin | 4 | 2 | 18 |
| **Total** | **39** | **18 weeks** | **~4.5 months** |

---

## 5. Key Risks

| Risk | Impact | Mitigation |
|---|---|---|
| COA needs manual configuration per enterprise | Delays Phase 2 | Provide standard COA template for Circular 99 + industry variants |
| Opening balance data quality from legacy system | Incorrect trial balance | Build validation rules and import reconciliation |
| Foreign currency complexity in Purchasing | Cost misstatement | Implement FX rate feed + prepayment tracking from Phase 1 |
| VAT deductibility rules change per item type | Tax filing errors | Build configurable VAT deductibility matrix per item category |
| Multi-warehouse transfer loop costing | Negative inventory | Implement FIFO layer tracking + circular transfer detection |

---

## 6. Immediate Next Steps

1. **Seed COA** — create `database/seeds/ChartOfAccountsSeeder.php` with the full Circular 99 chart of accounts
2. **Build Opening Balance module** — one-time load for inventory quantities, AR/AP by customer/supplier, cash balances, FA cost
3. **Build Cash Receipt transaction** — first journal-posting module (validates architecture: Controller → AccountingService → DoubleEntry → GL)
4. **Build Purchase Receipt** — first inventory-affecting transaction (tests landed cost + valuation method)
