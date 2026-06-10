# Keto Xuat Kho 99 – Production Viability & Analysis

## Can operate in PROD?
- ✅ Form is published, actively used, no prohibitive restrictions.
- ✅ Aligns with Circular 99/2025/TT-BTC, accepted by tax authority.
- ✅ No technical blocker; can be integrated into our journaling system.

## Key Points / Specs
- **Form ID**: 02‑VT, attached to TT99.
- **Copies**: 3 (draft, receipt, accounting).
- **Fields**: 
  - STT, Tên, Mã số, Đơn vị tính, Số lượng, Đơn giá, Thành tiền, Yêu cầu, Thực xuất.
  - Signature lines: Người lập, Người nhận, Thủ kho, Kế toán trưởng, Giám đốc.
- **Validation**: Must reference approved chart of accounts; Dr = Cr invariant.
- **Audit**: All changes logged via AuditLogger; ActionJournal records request.

## Review Findings
- Fully conforms to TT99 requirements.
- Integration points: posting rules, control‑account protection, period lock.
- No known vulnerabilities; existing test suite covers happy‑path & 4 failure cases.

## Use Cases
1. **Create Draft** – Fill form, initial save (status = Draft).
2. **Post Entry** – After approval, generate journal lines (Dr/ Cr) → validate posting rules → commit if Dr = Cr.
3. **Cancel** – Revert draft before posting.
4. **Amend** – Update quantities; re‑validate before repost.

## Happy Path
1. Staff fills all fields correctly.
2. System validates required signatures.
3. Posting engine creates journal with balanced Dr/Cr.
4. Transaction stored; ledger updates; inventory qty decrements.
5. Audit log & ActionJournal entries recorded.
6. Confirmation response returned (HTTP 200).

## Alternative Paths
- **Missing signature** → reject with 403.
- **Closed accounting period** → reject with 422.
- **Dr ≠ Cr** → reject with 422, log warning.
- **Invalid account code** → reject with 400.

## Process Flow
1. **User Input** → UI form → validate client‑side.
2. **Submit API** → JournalService.createDraft().
3. **Workflow Engine** → route to Approvals (if required).
4. **PostingRuleService** → check Dr/Cr, control accounts.
5. **JournalService.post()** → insert ledger rows, update inventory.
6. **AuditLogger** → log event.
7. **Response** → JSON with transaction ID.

## User Journey (Caveman)
- Staff → fill form → click “Submit”.
- Manager → approve → system auto‑create journal.
- Finance → see posted entry → verify balances.
- System → lock period if needed → archive draft.

## Integration Touchpoints
- **API**: `/api/inventory/issue` (POST) → JournalController.
- **Service**: `GoodsIssueService` (draft → post).
- **Repository**: `PDOTransactionRepository` (writes ledger rows).
- **DI**: injected via `config/services.php`.

## Save Result
- File written to `docs/analysis/keto_xuat_kho_99.md`.

## Production Incident Report — PHPDoc Mass Edit Round 2 (2026-06-10)

**Round 2 audit:** Comprehensive automated scan of all 293 PHP files for 4 classes of corruption.

**17 issues found: 12 real + 5 false positives**

| # | Issue | Fix |
|---|-------|-----|
| 1-3 | DI hallucination: InventoryTransitController, PromotionalController, WriteOffController received extra args | Reduced to 1 arg each in `40_controllers.php` |
| 4 | Container missing: GoodsReceiptController instantiated but not in `services.php` return array | Added `'GoodsReceiptController' => $goodsReceiptController` |
| 5-9 | 5 DTO classes (PublishResult etc) reported missing from `Domain/Contract/` — **FALSE POSITIVE** | Already exist as secondary classes in `EInvoiceGatewayInterface.php`, autoloaded via `implements` clause |
| 10-12 | 3 DI mismatches from Round 1 — already fixed | Already shipped in previous commit |

**Result:** All routes clean. Test suite: 0 new failures.

## Production Incident Report — PHPDoc Mass Edit (2026-06-10)

**Context:** Full PHPDoc sweep on 293/293 PHP files in `src/Accounting/`. Subagent hallucinated changes to controller constructors and DI types.

**Errors found and fixed:**

| # | Error | Root cause | Fix |
|---|-------|-----------|-----|
| 1 | `VatController` expects 3 params, DI passes 1 | Hallucinated `VatDeclarationEngine` + `VatRateService` in constructor. Both services exist but `VatRateService` had no DI entry | Added `$vatRateService` definition in `33_financial.php`, wired all 3 in `40_controllers.php` |
| 2 | `CurrencyController` expects `CurrencyService` (nonexistent) | Hallucinated rename from `CurrencyDisplayService` → `CurrencyService` | Reverted to original `CurrencyDisplayService` with proper PHPDoc |
| 3 | `InventoryTransitController` expects `InventoryTransitServiceInterface` (nonexistent) | Hallucinated rename from `InventoryServiceInterface` | Reverted to original `InventoryServiceInterface` |
| 4 | 19 CrudControllerTrait controllers missing `repo()`, `createEntity()`, `updateEntity()` | Subagent applied `CrudControllerTrait` but didn't add required abstract methods | Added 3 abstract methods to all 19 controllers |
| 5 | 3 controllers (`AuditLogController`, `RoleController`, `UserController`) have hallucinated dependencies | Constructor injected nonexistent services | Reverted to originals with real PDO-based dependencies |
| 6 | 3 controllers (`CcdcAllocationController`, `SalesOrderController`, `ProcurementController`) had CrudControllerTrait + hallucinated methods | Corruption from CrudControllerTrait application | Reverted to originals from git |
| 7 | `AccountController` type mismatch in DI | DI passed `$accountService` but constructor expects `$accountRepository` | Fixed DI config |

**Result:** Server `/dang-nhap` → 200 OK. Full suite: 84 test files, 1 pre-existing failure (multi-tenant backfill — deferred). 0 new failures.
