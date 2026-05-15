# Use Case Specification

## Law on Accounting 2015 — Unique Provisions Beyond Circular 99/2025/TT-BTC

**Version:** 1.0
**Last Updated:** 2026-05-15
**Regulatory Basis:** Circular 99/2025/TT-BTC, Law on Accounting 2015

---

## 1. Source

- **URL:** https://thuvienphapluat.vn/van-ban/Ke-toan-Kiem-toan/Luat-ke-toan-2015-298369.aspx
- **Domain Context:** Vietnamese Enterprise Accounting Law (Luật Kế Toán 2015, Law No. 88/2015/QH13). Effective 1 January 2017. This is the primary legislation; Circular 99/2025/TT-BTC is the implementing regulation.
- **Scope Note:** This document captures ONLY use cases that Circular 99 does NOT address. Excluded: COA management, journal posting, posting rules, document/ledger/FS templates — these are covered by Circular 99's implementing provisions. Included: legal foundations, period management, internal control, transitional accounting, document retention, inspection, organizational requirements.
- **Regulatory Context:** The Law is organized into 6 chapters, 74 articles. Chapter II (Content of Accounting Work) overlaps most with Circular 99. Chapters III–VI contain provisions foundational to system design but not detailed by Circular 99.
- **Analysis Summary:** The Law mandates: accounting period definitions (year/quarter/month), period open/close/lock lifecycle, internal control systems, internal audit, physical inventory at period-end and at restructuring events, document retention (5/10 years/permanent), error correction methods (3 prescribed methods), fair value/FX revaluation authority, FS disclosure and audit requirements, transitional accounting workflows for 6 restructuring event types, regulatory inspection procedures, and accounting personnel standards.

---

## 2. Domain Breakdown

### Domain 1: Accounting Period Management (PRIMARY)

#### UC-001: Define Accounting Period

**Description:** Establish and configure accounting periods at the legal foundation of the system. The Law defines three period types — month, quarter, year — with specific start/end rules. Special rules apply for newly established entities and entities at dissolution.

**Goal:** Configure the system's fundamental time segmentation for accounting entries, closing, and reporting.

**Primary Actors:** System Administrator, Chief Accountant

**Preconditions:**
- Entity registered with legal establishment date
- Entity type determined

**Trigger:**
- Initial system setup
- New fiscal year
- New entity registration
- Entity restructuring event

**Main Flow:**
1. Chief Accountant selects period type structure based on entity needs:
   - **Annual period (kỳ kế toán năm):** 12 months, default 1 Jan–31 Dec (Article 12.1a)
   - **Quarterly period (kỳ kế toán quý):** 3 months (Article 12.1b)
   - **Monthly period (kỳ kế toán tháng):** 1 month (Article 12.1c)
2. If entity has operational特殊性 (special characteristics), may choose a different 12-month fiscal year starting at quarter beginning (Article 12.1a)
3. System generates period hierarchy: year → quarters → months
4. System validates: no overlapping periods, no gaps in sequence

**Alternate Flow:**
- **First fiscal period:** Start from entity establishment date (Article 12.2a):
  - Enterprise: from date of Enterprise Registration Certificate
  - Other entities: from effective date of establishment decision
  - End of first period aligned to standard year/quarter/month end
- **If first or last period < 90 days:** may be combined with next/prior period into a single fiscal year (Article 12.4). Combined period must be < 15 months.

**Postconditions:**
- Period hierarchy configured (year → quarters → months)
- First period start/end dates determined

**Business Rules:**
- BR01: Default fiscal year: 1 Jan – 31 Dec (Article 12.1a)
- BR02: Alternative 12-month period allowed for entities with specific operational characteristics, must start at quarter beginning, must notify tax authority (Article 12.1a)
- BR03: Quarter = 3 months from quarter start (Article 12.1b)
- BR04: Month = 1 calendar month (Article 12.1c)
- BR05: First period for new entity starts from establishment/certificate date (Article 12.2)
- BR06: First or last period < 90 days may be merged (Article 12.4)
- BR07: Merged first/last period must be < 15 months total (Article 12.4)
- BR08: Entity must notify financial/tax authority of alternative fiscal year (Article 12.1a)

**Input Data:**
- Entity establishment date
- Selected fiscal year start month
- Regulatory notifications

**Output Data:**
- Configured period hierarchy (years → quarters → months)
- Current period status (open)

**Dependencies:**
- Entity registration data

**Frequency:** Once at setup; annually recurring

**Priority:** Critical

---

#### UC-002: Open Accounting Period

**Description:** Open a new accounting period for transaction entry. Periods must follow sequentially — the next period opens when the prior period is closed or at the start of a new fiscal year.

**Goal:** Enable transaction recording in the correct temporal context.

**Primary Actors:** System (automatic), Chief Accountant

**Preconditions:**
- Period hierarchy defined (UC-001)
- Prior period closed (or initial setup)

**Trigger:**
- New fiscal year start
- First period for new entity
- Period hierarchy setup completion

**Main Flow:**
1. System opens the period with status "Open"
2. Opening balances carried forward from prior period closing balances (Article 5.5)
3. For initial period: opening balances are zero (or opening balance entry if conversion)
4. System records period open timestamp and actor

**Alternative Flows:**
None documented

**Postconditions:**
- Period status set to "Open"
- Opening balances carried forward

**Business Rules:**
- BR09: Periods must be sequential with no gaps (Article 5.5)
- BR10: Opening balances = prior period closing balances (Article 5.5)

**Priority:** Critical

---

#### UC-003: Close Accounting Period

**Description:** Close the accounting period: verify all entries are posted, validate trial balance, execute nominal account closure, transfer profit/loss, lock the period. Period closing occurs before FS preparation (Article 26.6). The ledger is signed and sealed after closing.

**Goal:** Finalize the period's financial records. Prevent further changes.

**Primary Actors:** Chief Accountant

**Supporting Actors:** Accountant

**Preconditions:**
- All transactions for the period posted
- Sub-ledgers reconciled to general ledger
- Physical inventory completed, discrepancies adjusted (UC-007)
- FC revaluation posted
- Trial balance: total debits = total credits

**Trigger:**
- Period-end date reached
- Pre-close checklist complete
- Regulatory deadline approaching

**Main Flow:**
1. **Pre-close verification:**
   - All documents posted to ledger
   - All sub-ledger → general ledger balances reconciled (Article 9.2b)
   - Physical inventory completed, adjustments recorded (Article 40.3)
   - Accruals and prepayments recorded
   - FC revaluation posted
   - Trial balance verified: total debits = total credits
2. **Execute closing entries:**
   - Close revenue accounts → P&L (TK 911)
   - Close expense accounts → P&L (TK 911)
   - Calculate net profit/loss
   - Close P&L → Retained Earnings (TK 421)
3. **Period lock:**
   - Set period status to "Closed"
   - Block all new postings to this period
   - Generate period-end report package
4. **Ledger finalization** (Article 26.6):
   - Final ledger page signed by preparer, chief accountant, legal representative
   - For electronic ledgers: print and bind into separate volumes per fiscal year (Article 26.7)
5. **Next period opens** with closing balances as opening balances

**Alternate Flow:**
- **Loss:** Dr Retained Earnings — Cr P&L (reverse of profit)
- **Period re-opening (auditor-requested):** Chief Accountant may re-open with audit trail, justification, and time limit
- **Quarterly close:** Nominal accounts NOT closed (only annual). Trial balance and FS generated without closing entries.

**Business Rules:**
- BR11: Ledger closing mandatory at period-end before FS preparation (Article 26.6)
- BR12: Closing also mandatory at: entity division, merger, consolidation, conversion, dissolution, bankruptcy (Article 40.2b–40.2c)
- BR13: Subsidiary & general ledger balances must reconcile (Article 9.2b)
- BR14: Revenue and expense accounts zeroed via closing entries at year-end
- BR15: Closing balances → next period opening balances (Article 5.5)
- BR16: Closed period cannot be re-opened without authorization and audit trail
- BR17: Electronic ledger must be printed and bound after annual closing (Article 26.7)
- BR18: Ledger signatures required: preparer, chief accountant, legal representative (Article 24.2)

**Input Data:**
- Trial balance
- Sub-ledger reconciliation reports
- Physical inventory results
- FC revaluation entries

**Output Data:**
- Closed ledger with signed final pages
- Closing entries (revenue/expense → P&L → retained earnings)
- Period status: Closed
- Opening balances for next period

**Dependencies:**
- UC-001 (Period defined)
- UC-007 (Physical inventory completed)
- UC-008 (FC revaluation posted)
- All transaction-posting use cases completed

**Frequency:** Monthly/Quarterly/Annually

**Priority:** Critical

**Compliance Impact:** Article 26.6 mandates closing before FS. Failure to close = cannot produce FS = regulatory violation.

---

#### UC-004: Re-Open Closed Period (Correction)

**Description:** In exceptional circumstances (auditor request, error correction after FS submission), re-open a closed period to post corrective entries. The original period status is preserved with full audit trail.

**Goal:** Correct prior-period errors while maintaining immutability of original records.

**Primary Actors:** Chief Accountant

**Supporting Actors:** External Auditor

**Preconditions:**
- Period is closed
- Valid justification exists (auditor finding, material error)
- Dual authorization obtained

**Trigger:**
- External audit adjustment
- Material error discovered after FS submission and closure
- Tax inspection re-assessment

**Main Flow:**
1. Chief Accountant initiates re-open request with justification
2. Dual authorization: Chief Accountant + Legal Representative (or Auditor for audit-required adjustments)
3. System sets period status to "Reconciling" — temporarily allows entry posting
4. Corrective entries posted with explicit "prior-period adjustment" flag
5. Period re-closed after corrections completed
6. System records: re-open user, timestamp, justification, entries posted, close user, timestamp

**Alternative Flows:**
None documented

**Postconditions:**
- Period temporarily re-opened for corrections
- Corrective entries posted with prior-period flag
- Period re-closed with audit trail

**Business Rules:**
- BR19: Re-opening requires material justification and authorization (Article 27.4)
- BR20: Errors found after annual FS submission: correct in discovery period, not in original period (Article 27.4) — note: this is the normal flow; re-open only when prior-period FS restatement is required
- BR21: All re-open events are audit-logged immutably

**Priority:** High

---

### Domain 2: Accounting Entity & Personnel (Legal Foundations)

#### UC-005: Establish Accounting Organization

**Description:** Configure the system to reflect the entity's accounting organizational structure: identify the accounting unit, its legal representative, chief accountant, and organizational hierarchy as defined by the Law.

**Goal:** Ensure the system knows who is legally responsible for accounting work.

**Primary Actors:** Legal Representative, System Administrator

**Preconditions:**
- Entity legally established
- System instance created

**Trigger:**
- Entity registration
- Change in accounting organization

**Main Flow:**
1. System records legal representative (chịu trách nhiệm tổ chức bộ máy kế toán — Article 50)
2. System records chief accountant (người đứng đầu bộ máy kế toán — Article 53)
3. System validates: chief accountant meets standards (Article 54):
   - Professional accounting qualification (intermediate college degree minimum)
   - Chief accountant training certificate
   - 2 years practical experience (university degree) or 3 years (intermediate/college)
4. System validates no prohibited relationships (Article 52.3):
   - Legal representative, director, chief accountant's parent/spouse/child/sibling cannot also serve as accountant in same entity
   - Exception: single-member LLCs, sole proprietorships
5. System validates no role conflicts (Article 52.4):
   - Accountant, cashier, warehouse keeper, asset buyer must be separate individuals
6. System records position assignments with effective dates

**Alternative Flows:**
None documented

**Postconditions:**
- Accounting organization structure configured
- Role assignments recorded with effective dates

**Business Rules:**
- BR22: Legal representative responsible for accounting organization (Article 50)
- BR23: Chief accountant required in all state agencies, state-funded entities, SOEs (>50% capital) (Article 53)
- BR24: Chief accountant minimum qualifications: intermediate degree + certificate + 2/3 years experience (Article 54)
- BR25: Prohibited relationships: family members of legal rep/director/chief accountant cannot be same-entity accountant (Article 52.3)
- BR26: Segregation of duties: accountant, cashier, warehouse keeper, asset buyer cannot be same person (Article 52.4)
- BR27: Chief accountant has professional independence, reports to legal representative (Article 55.1)

**Priority:** Critical

---

#### UC-006: Manage Accounting Personnel Changes

**Description:** Handle personnel transitions: appointment, reassignment, dismissal, handover of accounting responsibilities. When the accountant changes, a formal handover procedure is required.

**Goal:** Ensure continuity of accounting records during personnel changes.

**Primary Actors:** Chief Accountant, Legal Representative

**Main Flow:**
1. Upon personnel change: outgoing accountant prepares handover documentation
2. Incoming accountant verifies records and accepts
3. System records the personnel assignment change with date
4. Outgoing accountant's access revoked; incoming accountant's access granted
5. Prior-period entries retain the original preparer's identity (immutable)

**Alternative Flows:**
None documented

**Postconditions:**
- Personnel change recorded with handover documentation
- Access rights updated

**Business Rules:**
- BR28: Each accountant is responsible for their period of work (Article 51.3)
- BR29: Handover documentation required when accountant changes (Article 51.3)

**Priority:** High

---

### Domain 3: Internal Control & Audit

#### UC-007: Establish Internal Control System

**Description:** Design and implement internal control mechanisms: policies, procedures, segregation of duties, approval workflows to prevent, detect, and handle risks.

**Goal:** Ensure asset safety, proper authorization, and complete recording.

**Primary Actors:** Legal Representative, Chief Accountant

**Preconditions:**
- Accounting system operational
- Organizational structure defined (UC-005)

**Trigger:**
- Entity establishment
- Regulatory requirement

**Main Flow:**
1. System enforces segregation of duties (Article 39.2):
   - Cash disbursement requires dual signatures (approver + chief accountant) — Article 19.2
   - Recording user ≠ approving user
   - Custody (cashier, warehouse) ≠ accounting personnel
2. System enforces approval workflows per delegation matrix
3. System blocks prohibited role combinations (Article 52)

**Alternative Flows:**
None documented

**Postconditions:**
- Internal controls enforced
- Segregation of duties maintained

**Business Rules:**
- BR30: Internal control system mandatory (Article 39.1)
- BR31: Controls must ensure assets are safeguarded from misuse (Article 39.2a)
- BR32: Controls must ensure transactions are authorized and completely recorded (Article 39.2b–39.2c)
- BR33: Prohibited acts include: falsifying documents, colluding to falsify, erasing documents (Article 13.1)

**Priority:** Critical

---

#### UC-008: Conduct Internal Audit

**Description:** Perform independent examination and evaluation of internal control effectiveness, FS information reliability, legal compliance, and fraud detection.

**Goal:** Provide assurance on control quality and identify improvement opportunities.

**Primary Actors:** Internal Auditor

**Main Flow:**
1. Internal auditor evaluates:
   - Internal control adequacy and effectiveness (Article 39.3a)
   - FS information quality and reliability (Article 39.3b)
   - Legal compliance (Article 39.3c)
   - Control gaps, fraud risks, improvement recommendations (Article 39.3d)
2. Findings recorded in system
3. Recommendations tracked to closure

**Alternative Flows:**
None documented

**Postconditions:**
- Audit findings documented
- Recommendations tracked for closure

**Priority:** High

---

### Domain 4: Physical Inventory

#### UC-009: Perform Physical Inventory

**Description:** Systematically verify existence, quantity, quality, and value of all assets and compare to book records. Mandatory at year-end and at restructuring events.

**Goal:** Ensure book records match physical reality.

**Primary Actors:** Accountant, Inventory Controller

**Supporting Actors:** Count Team, Internal Auditor

**Preconditions:**
- Count scheduled
- Count teams assigned

**Trigger:**
- Period-end closing (annual minimum — Article 40.2a)
- Entity restructuring: division, merger, consolidation, conversion, dissolution, bankruptcy (Article 40.2b–40.2c)
- Disaster: fire, flood (Article 40.2d)
- Regulatory revaluation order (Article 40.2đ)

**Main Flow:**
1. System generates count sheets per location/category (Article 40.1)
2. Count team conducts physical count: weight, measure, count
3. System loads results, compares to book records
4. For each discrepancy: calculate variance
5. Accountant investigates variance, determines cause
6. System records adjustment: Dr/Cr inventory — Cr/Dr corresponding account
7. Adjusted balances used for FS (Article 40.3)
8. Comprehensive inventory report generated

**Alternative Flows:**
None documented

**Postconditions:**
- Physical count completed
- Discrepancies investigated and adjusted
- Book records updated to match physical

**Business Rules:**
- BR34: Physical inventory minimum: annually at year-end (Article 40.2a)
- BR35: Physical inventory required for all 6 restructuring event types (Article 40.2b–40.2c)
- BR36: Physical inventory required after disaster (Article 40.2d)
- BR37: Discrepancies must be investigated, documented, adjusted before FS (Article 40.3)
- BR38: Period close blocked if inventory not completed

**Priority:** Critical

---

### Domain 5: Document Retention & Archiving

#### UC-010: Archive Accounting Documents

**Description:** Transfer completed-period documents to secure storage within 12 months. Retain for legally prescribed periods. Three retention tiers: 5 years, 10 years, permanent.

**Goal:** Ensure legally compliant document preservation for audit and inspection.

**Primary Actors:** Accountant

**Supporting Actors:** Archivist

**Preconditions:**
- Period closed
- FS prepared and submitted

**Trigger:**
- 12-month post-period deadline approaching (Article 41.1)
- Periodic archiving cycle

**Main Flow:**
1. Accountant identifies documents ready for archiving
2. System classifies by retention tier (Article 41.2):
   - **Tier 1 — 5 years:** administrative/management documents, non-ledger-relevant documents
   - **Tier 2 — 10 years:** directly ledger-relevant documents, ledgers, annual FS
   - **Tier 3 — Permanent:** historically significant, national security/economic importance
3. Documents packaged, sealed, transferred to archive
4. Archive index created with retrieval metadata
5. System tracks retention expiry; auto-alerts before deletion is permitted

**Alternative Flows:**
None documented

**Postconditions:**
- Documents archived with retention tier
- Archive index created

**Business Rules:**
- BR39: Documents must be archived within 12 months of period-end (Article 41.1)
- BR40: Tier 1: minimum 5 years (Article 41.2a)
- BR41: Tier 2: minimum 10 years — ledgers, FS, primary accounting evidence (Article 41.2b)
- BR42: Tier 3: permanent — historically significant documents (Article 41.2c)
- BR43: Electronic documents stored on secure electronic media, retrievable (Article 18.5)
- BR44: System must NOT auto-delete before legal retention expiry

**Priority:** High

---

#### UC-011: Recover Lost or Destroyed Documents

**Description:** When documents are lost or destroyed: investigate, attempt recovery from counterparties/banks/backups, and if impossible, conduct physical inventory to reconstruct balances.

**Goal:** Restore accounting information after loss events.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. Entity investigates loss extent
2. Recovery attempts:
   - Re-request invoices/statements from counterparties
   - Re-request bank statements
   - Restore from electronic backups
3. If recovery impossible → conduct physical inventory (Article 42)
4. Reconstructed values recorded with supporting protocol
5. Loss reported to authorities if material

**Alternative Flows:**
None documented

**Postconditions:**
- Lost documents reconstructed or recovered
- Physical inventory conducted (if recovery fails)

**Business Rules:**
- BR45: Recovery must be attempted before reconstruction (Article 42)
- BR46: Physical inventory is fallback when recovery fails (Article 42)

**Priority:** High

---

### Domain 6: Transitional Accounting Events (6 types)

#### UC-012: Handle Entity Division (Chia đơn vị)

**Description:** One entity divides into multiple. Each resulting entity receives a portion of assets, liabilities, and records.

**Main Flow:**
1. Close ledger at division date (Article 43)
2. Physical inventory (Article 40.2b)
3. Determine debts, prepare FS
4. Distribute assets, debts per division plan
5. Transfer protocols signed
6. New entities open ledgers with received balances

**Priority:** High

---

#### UC-013: Handle Entity Demerger (Tách đơn vị)

**Description:** A portion of an entity separates to form a new entity. Only the separated portion is inventoried.

**Main Flow:**
1. Physical inventory of separated portion only (Article 44)
2. Determine debts, document in transfer protocol
3. New entity opens ledger with separated balances

**Priority:** High

---

#### UC-014: Handle Entity Merger (Hợp nhất)

**Description:** Multiple entities merge into one. Each constituent closes books, inventories, prepares FS, transfers everything to the survivor.

**Main Flow:**
1. Each constituent entity: close ledger, inventory, determine debts, prepare FS (Article 45)
2. Transfer all assets, documents to surviving entity
3. Survivor assumes document retention responsibility
4. Post-merger entity opens consolidated ledger

**Priority:** High

---

#### UC-015: Handle Entity Consolidation (Sáp nhập)

**Description:** One entity is absorbed by another. The absorbed entity closes and transfers. The absorbing entity records the transfer.

**Main Flow:**
1. Absorbed entity: close ledger, inventory, determine debts, prepare FS (Article 46)
2. Transfer all assets, documents to absorbing entity
3. Absorbing entity records per transfer protocol

**Priority:** High

---

#### UC-016: Handle Entity Type Conversion (Chuyển đổi)

**Description:** Entity changes legal type or ownership. Books close at conversion date. Post-conversion entity opens new ledger.

**Main Flow:**
1. Close ledger at conversion date (Article 47)
2. Physical inventory (Article 40.2c)
3. Prepare FS
4. Post-conversion entity opens ledger with converted balances

**Priority:** High

---

#### UC-017: Handle Entity Dissolution or Bankruptcy

**Description:** Entity dissolves or enters bankruptcy. Close books, inventory, prepare final FS. Track post-dissolution transactions separately. Archive documents after final settlement.

**Main Flow:**
1. Close ledger at dissolution/bankruptcy date (Article 48)
2. Physical inventory (Article 40.2b)
3. Determine debts, process claims, prepare FS
4. Open separate ledger for post-dissolution transactions (Article 48.1)
5. After final settlement: archive documents per retention policy (Article 48.2)

**Priority:** High

---

### Domain 7: Regulatory Inspection & Compliance

#### UC-018: Support On-Site Accounting Inspection

**Description:** Regulatory authority examines entity's accounting records and practices on-site. Maximum 10 working days (extendable +5 for complex cases). Entity must provide documents and comply.

**Goal:** Support regulatory inspection while protecting entity's rights.

**Primary Actors:** Chief Accountant

**Supporting Actors:** Legal Representative, Regulatory Inspector

**Preconditions:**
- Inspection decision issued by competent authority (Article 34)
- Notification received

**Trigger:**
- Scheduled inspection
- Compliance concern

**Main Flow:**
1. Inspector presents credentials and decision (Article 37.1)
2. Entity validates: competent authority, correct scope (Article 38.2a)
3. Entity provides requested documents and explanations (Article 38.1)
4. Inspector examines: accounting content, organization, service compliance (Article 35)
5. Inspector documents findings
6. Inspection report prepared; entity receives copy (Article 37.2)
7. Entity complies with conclusions or appeals (Article 38.2b)

**Business Rules:**
- BR47: Inspection requires formal decision from competent authority (Article 34.1)
- BR48: Inspection limited to 10 working days; +5 for complex (Article 36)
- BR49: Entity may refuse if authority or scope invalid (Article 38.2a)
- BR50: Entity may appeal conclusions (Article 38.2b)

**Priority:** Medium

---

### Domain 8: FS Disclosure & External Audit

#### UC-019: Disclose Financial Statements

**Description:** Make FS publicly available. Disclosure rules vary by entity type. Audited FS must include audit report.

**Primary Actors:** Chief Accountant, Legal Representative

**Preconditions:**
- FS prepared and audited (if applicable — Article 33)
- Period closed

**Trigger:**
- Statutory deadline

**Main Flow:**
1. Determine applicable disclosure requirements per entity type (Article 31):
   - Business: assets, liabilities, equity, results, fund allocations, employee income
   - State-budget entities: budget revenue/expenditure
2. Publish via regulated methods: printed, online, posting, notice (Article 32.1)
3. Audited FS must include audit report (Article 31.4, 33.2)

**Business Rules:**
- BR51: Business entities: disclose within 120 days of year-end (Article 32.2)
- BR52: Non-budget entities: disclose within 30 days of FS submission (Article 32.2)
- BR53: FS must be audited before submission to authorities and before publication (Article 33)

**Priority:** High

---

## 3. Cross-Use Case Analysis

### Period-Centric Dependency Graph

```
UC-001: Define Period ──────────────────────────────────┐
                                                        │
UC-002: Open Period ────────────────────────────────────┤
                                                        │
[All transaction-posting UCs — covered by Circular 99]  │
                                                        │
UC-009: Physical Inventory ─────────────────────────────┤
                                                        │
[FC Revaluation — covered by Circular 99]              │
                                                        │
UC-003: Close Period (includes pre-close checklist) ────┤
    ├── Verifies inventory done (UC-009)                │
    ├── Posts closing entries                           │
    └── Locks period                                    │
                                                        ▼
UC-019: Disclose FS ───────── after UC-003 completes
```

### Shared Dependencies
- **Period status** (open/closed): consumed by ALL transaction-posting UCs — determines whether entry is allowed
- **Personnel assignments** (UC-005): consumed by UC-003 (closing requires chief accountant)
- **Retention rules** (UC-010): consumed by UC-017 (post-dissolution archiving)

### Workflow Gaps in Circular 99
- Circular 99 does not define period lifecycle (open/close/lock). The Law provides this foundation.
- Circular 99 does not cover transitional accounting events (6 types). The Law mandates workflows for each.
- Circular 99 does not address document retention periods or archiving procedures.
- Circular 99 does not define internal control or internal audit requirements.
- Circular 99 does not specify inspection procedures or timelines.

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Error Correction Workflow (Article 27) | Support 3 correction methods: strikethrough, negative entry, adjusting entry with system enforcement | High |
| Fair Value Measurement (Article 28) | Period-end fair value for financial instruments and volatile assets | Medium |

### Missing Validation Rules (Law-specific)
- **Period enforcement:** Transaction date must fall in an open period. Closed period → reject.
- **Concurrent period check:** Only one period may be open at a time.
- **Ledger singleton:** Only one active ledger per period (Article 25.1). System must enforce.
- **First period < 90 days check:** System should alert if first period < 90 days and suggest merging with next period (Article 12.4).

### Missing Approval Flows
- **Period re-open:** Requires Chief Accountant + Legal Representative (or Auditor) dual authorization.
- **Cash disbursement:** Requires approver pre-approval + Chief Accountant pre-signature (Article 19.2).
- **Personnel override:** Exception to prohibited relationship rules requires legal counsel review.

### Missing Audit Trails (Law-specific)
- Period status changes: timestamp, actor, old status, new status, justification
- Personnel assignments: appointment date, role, removal date
- Physical inventory results: count date, counters, discrepancies, adjustments, approval
- Document retention lifecycle: archive date, retention tier, destruction authorization

---

## 5. Recommended System Modules (Law-Specific Additions)

| Module | Responsibility |
|---|---|
| **Period Engine** | Define, open, close, lock periods. Enforce sequential integrity, pre-close checklist, closing entries. |
| **Personnel & Authorization** | Manage accountants, chief accountant, legal representative. Enforce segregation of duties and prohibited relationship rules. |
| **Internal Control Framework** | Approval workflows, authorization matrix, segregation enforcement, control testing. |
| **Document Retention Manager** | Classify, archive, track retention expiry. Tier-based lifecycle (5/10 years/permanent). |
| **Transitional Accounting** | 6 restructuring workflows: division, demerger, merger, consolidation, conversion, dissolution. Each with inventory → close → transfer → open sequence. |
| **Inspection Support** | Document provision packager, inspection timeline tracker, finding response management. |
| **Physical Inventory** | Count sheet generation, discrepancy analysis, adjustment posting. Enforce minimum annual count (Article 40.2). |
| **Correction Engine** | 3 correction methods per Article 27: strikethrough, negative entry, adjusting entry. |

---

## 6. Suggested Improvements (Beyond Circular 99 Scope)

### Business Improvements
1. **Period dashboard:** Real-time view of period status, pre-close checklist completion, days until close deadline.
2. **Retention alerting:** Auto-notify when documents approach retention expiry for review/destruction authorization.

### Process Improvements
1. **Pre-close automation:** System checks all pre-conditions and blocks close if any is incomplete (inventory not done, FC not revalued, sub-ledgers not reconciled).
2. **Annual close scheduling:** System generates close timeline with task assignments and deadlines.

### Technical Improvements
1. **Period-level access control:** Read-only access to closed periods, full access only to open period.
2. **Immutable closed period enforcement:** Database-level: no INSERT/UPDATE/DELETE in closed periods. Not just application-level — use DB triggers or schema.
3. **Correction method enforcement:** System should only allow Article 27 correction methods (not deletion). For electronic ledgers: adjusting entry method only (Article 27.5).

### Compliance Improvements
1. **Dual-accounting-system detection:** System alert if any data operation creates or implies a second set of books (prohibited per Article 13.13 — criminal offense).
2. **Family relationship validation:** Input validation when assigning accounting personnel — check for prohibited family relationships per Article 52.3.
3. **Role conflict prevention:** System must prevent assigning same person as accountant and cashier (Article 52.4).

---

*Document generated via BA/SA analysis of Law on Accounting 2015 (88/2015/QH13). Scope limited to provisions NOT covered by Circular 99/2025/TT-BTC. Period management treated as primary domain per requirements. All inferred assumptions explicitly marked.*
