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
