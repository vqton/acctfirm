# ADR-009: Tax Module — Priority-First Implementation

## Status
Accepted

## Date
2026-06-02

## Context
The tax-engine-brain-logic.md spec defines an 8-phase, 22-week implementation roadmap. However, not all phases have equal legal or operational urgency. Given a 3-sprint budget (6 weeks), we need to prioritize items that:

1. Address legal compliance risk (fines, penalties)
2. Enable operational capability (can file declarations)
3. Provide audit trail and control (pass inspection)

The original Phase 1→8 ordering (Core → Declaration → CIT → PIT → Recon → E-Invoice → Reports → Polish) mixes P0 legal-must items across multiple phases. A straight phase-by-phase build would deliver incomplete capability for too long.

## Decision
Build in **priority order** (P0 → P1 → P2) as defined by our Lead BA + Chief Accountant analysis, grouping work by legal severity rather than by tax type.

Implementation order:

| Priority | Items | Rationale |
|---|---|---|
| **P0 — Legal MUST** | QR code in XML, vat_groups, HTKK export, 4-eyes approval, re-open workflow, 03/KHBS, 03/TNDN, 05/KK-TNCN, 05/QTT-TNCN | Fines if missing |
| **P1 — Operational MUST** | Incentive mgmt, installment tracking, non-deductible UI, deduction checklist UI, e-invoice vs declaration recon, purchase auto-import, bulk publish | Can't operate without |
| **P2 — Efficiency** | Dashboard, data room, tax calendar, anomaly detection, multi-year comparison | Nice-to-have |

## Alternatives Considered

### Phase-order (original spec)
- Pros: Aligned with spec document
- Cons: Would deliver non-urgent CIT incentives before legally-required QR code. Would leave P0 items scattered across 4 phases.

### Tax-type grouping (all VAT first, then CIT, then PIT)
- Pros: Clean module boundaries
- Cons: 05/KK-TNCN submission has hard legal deadline (20th monthly) — can't defer to Phase 4.

## Consequences
- Implementation order differs from `docs/analysis/tax-engine-brain-logic.md` Phase ordering
- Each P0/P1 item is a vertical slice (migration → service → test → commit)
- ADR-009 supersedes the original Phase ordering in the tax spec for sprint planning
- P2 items deferred to future sprints
