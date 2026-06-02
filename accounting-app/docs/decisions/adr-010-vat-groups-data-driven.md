# ADR-010: VAT Groups — Data-Driven Rate Determination

## Status
Accepted

## Date
2026-06-02

## Context
VAT rate determination currently uses hardcoded logic: `VatDeclarationEngine` and `XmlInvoiceBuilder` have switch/if-else on rate values (10%, 8%, 5%, 0%). This creates problems:

1. NQ 204/2025 8% reduction eligibility varies by product type — hardcoded logic can't express "product group X is eligible, group Y is not"
2. Rate changes require code changes, not data changes
3. Product-specific exemption rules (VR07-VR08) can't be expressed
4. The 8% reduction expiry (2026-12-31) requires a code deploy, not a config change

## Decision
Create a `vat_groups` table with product→group mapping and a `VatRateService` that resolves rates at runtime from data.

Design:

```sql
vat_groups:
  - id, code, name, default_rate (DECIMAL), is_reduction_eligible (BOOL),
    reduction_rate (DECIMAL), reduction_end_date (DATE), is_exempt (BOOL),
    exempt_reason (VARCHAR), created_at

vat_group_products:
  - id, vat_group_id (FK), item_id (nullable), category_code (nullable),
    product_type (VARCHAR), condition (JSON for eligibility rules)
```

`VatRateService::determineRate(item, transaction)` implements the 5-step algorithm from the tax spec Section 6.1 — but driven by data, not code.

## Alternatives Considered

### Keep hardcoded
- Rejected: Requires code deploy for rate changes, can't express 8% eligibility rules

### Single rate column on items table
- Rejected: Doesn't support reduction eligibility, exempt flags, expiry dates

## Consequences
- Migration 090 creates `vat_groups` + `vat_group_products` + seeds default groups
- `VatRateService` replaces hardcoded rate logic
- 8% reduction expiry (2026-12-31) is a data update, not a code change
- New product groups can be added without code changes
- Minor overhead: every invoice creation now reads vat_groups table (cached via PDO query — acceptable for ERP scale)
