<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Contract\CashServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\Helpers;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Tiền mặt & Tiền gửi Ngân hàng (TK 111, 112)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận phiếu thu (PT) — tiền mặt vào quỹ
 *   - Ghi nhận phiếu chi (PC) — tiền mặt ra khỏi quỹ
 *   - Chuyển tiền giữa các tài khoản (111 ⇄ 112, nội bộ)
 *   - Tất cả giao dịch đều qua CashService → JournalService (đảm bảo Dr = Cr)
 *
 * API endpoints:
 *   GET    /api/cash/receipts        — Danh sách phiếu thu
 *   POST   /api/cash/receipts        — Tạo phiếu thu mới
 *   GET    /api/cash/payments        — Danh sách phiếu chi
 *   POST   /api/cash/payments        — Tạo phiếu chi mới
 *   POST   /api/cash/transfers       — Chuyển tiền giữa các tài khoản
 *   GET    /api/cash/accounts        — Danh sách tài khoản tiền
 *   POST   /api/cash/{id}/reverse    — Hoàn nhập giao dịch
 *
 * Rủi ro:
 *   - R002: Dr ≠ Cr do lỗi nhập liệu — đã kiểm tra qua JournalService
 *   - R005: Sai account code (111 thay vì 1111) — control account check
 *   - R001: Post vào kỳ đã đóng — PeriodService kiểm tra trước
 *   - R006: Trùng số chứng từ — VoucherService dùng SELECT FOR UPDATE
 *
 * Tích hợp:
 *   - CashService → JournalService (bắt buộc)
 *   - PettyCashController dùng chung CashService cho tạm ứng
 *   - BankReconciliationController đối chiếu số dư cuối kỳ
 */
class CashController
{
    private CashServiceInterface $cash;

    public function __construct(CashServiceInterface $cash, AccountRepositoryInterface $accountRepo, \PDO $pdo)
    {
        $this->cash = $cash;
        $this->accountRepo = $accountRepo;
        $this->pdo = $pdo;
    }

    // ── Cash Receipts ──

    public function receipts(): void
    {
        Auth::requirePermission('cash', 'view');
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status,
            t.created_at, t.created_by,
            t.transaction_date, t.payer_name, t.payer_type, t.payer_id,
            t.payer_address, t.book_number, t.currency, t.exchange_rate,
            (SELECT SUM(le.amount) FROM ledger_entries le WHERE le.transaction_id = t.id AND le.is_debit = 1) as amount,
            (SELECT a.code FROM ledger_entries le JOIN accounts a ON a.id = le.account_id WHERE le.transaction_id = t.id AND le.is_debit = 0 LIMIT 1) as credit_account
            FROM transactions t WHERE t.description LIKE 'Cash receipt:%'
            ORDER BY t.created_at DESC LIMIT 200");
        header('Content-Type: application/json; charset=utf-8');
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận phiếu thu tiền mặt (PT) — Nợ 111 / Có (đối ứng)
    // Input: { amount, credit_account_code, description?, reference?, created_by?, payer_name?, payer_type?, payer_id?, transaction_date? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordReceipt() → CashService → JournalService.postEntry
    // Transaction: CashService tự wrap beginTransaction/commit/rollback
    // Permission: CSRF check + Auth::requirePermission('cash', 'create') (implicit qua service)
    // Rủi ro: R002 — Dr=Cr kiểm tra trong JournalService. R001 — Period open check
    // R006: Số CT tự động sinh qua Helpers::nextVoucherNo('PT') với SELECT FOR UPDATE
    // Tích hợp: Sau ghi nhận, cập nhật payer info vào transactions table
    public function createReceipt(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            JsonResponse::error('Vui lòng nhập số tiền và tài khoản đối ứng');
            return;
        }
        try {
            // THUẾ GTGT: Nếu vat_amount > 0 → hạch toán tách thuế:
            //   Nợ 111 (tổng) / Có credit_account (chưa thuế) + Có 33311 (VAT)
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Cash receipt',
                $data['reference'] ?? Helpers::nextVoucherNo('PT'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            // Save payer info and transaction date
            $txnId = $result['transaction_id'] ?? null;
            if ($txnId && ($data['payer_name'] ?? null)) {
                $pdo = $this->getPdo();
                $pdo->prepare('UPDATE transactions SET
                    transaction_date = COALESCE(?, transaction_date),
                    payer_name = ?, payer_type = ?, payer_id = ?
                    WHERE id = ?')
                    ->execute([
                        $data['transaction_date'] ?? null,
                        $data['payer_name'] ?? null,
                        $data['payer_type'] ?? null,
                        $data['payer_id'] ?? null,
                        $txnId
                    ]);
            }
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // ── Cash Payments ──

    public function payments(): void
    {
        Auth::requirePermission('cash', 'view');
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status,
            t.created_at, t.created_by,
            t.transaction_date, t.payer_name, t.payer_type, t.payer_id,
            t.payer_address, t.book_number, t.currency, t.exchange_rate,
            (SELECT SUM(le.amount) FROM ledger_entries le WHERE le.transaction_id = t.id AND le.is_debit = 0) as amount,
            (SELECT a.code FROM ledger_entries le JOIN accounts a ON a.id = le.account_id WHERE le.transaction_id = t.id AND le.is_debit = 1 LIMIT 1) as debit_account
            FROM transactions t WHERE t.description LIKE 'Cash payment:%'
            ORDER BY t.created_at DESC LIMIT 200");
        header('Content-Type: application/json; charset=utf-8');
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận phiếu chi tiền mặt (PC) — Nợ (đối ứng) / Có 111
    // Input: { amount, debit_account_code, description?, reference?, created_by?, ... }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordPayment() → JournalService.postEntry
    // Permission: CSRF check
    // Rủi ro: R005 — debit_account_code phải là TK con (không phải control account 111)
    // Số CT tự động: Helpers::nextVoucherNo('PC') với SELECT FOR UPDATE
    // NGHIỆP VỤ: Chi tiết phiếu chi — dùng cho in Mẫu 02-TT
    // Trả về thông tin giao dịch + các bút toán chi tiết
    public function getPayment(string $id): void
    {
        Auth::requirePermission('cash', 'view');
        $stmt = $this->pdo->prepare(
            "SELECT t.*,
                (SELECT SUM(le.amount) FROM ledger_entries le WHERE le.transaction_id = t.id AND le.is_debit = 0) as amount,
                (SELECT a.code FROM ledger_entries le JOIN accounts a ON a.id = le.account_id WHERE le.transaction_id = t.id AND le.is_debit = 1 LIMIT 1) as debit_account
             FROM transactions t WHERE t.id = ? AND t.description LIKE 'Cash payment:%'"
        );
        $stmt->execute([$id]);
        $txn = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$txn) {
            JsonResponse::error('Không tìm thấy phiếu chi', 404);
            return;
        }
        // Lấy các bút toán chi tiết (hiển thị Có 1111)
        $stmt = $this->pdo->prepare(
            "SELECT le.*, a.code AS account_code, a.name AS account_name
             FROM ledger_entries le JOIN accounts a ON a.id = le.account_id
             WHERE le.transaction_id = ? ORDER BY le.line_order"
        );
        $stmt->execute([$id]);
        $txn['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        JsonResponse::ok($txn);
    }

    public function createPayment(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['debit_account_code'])) {
            JsonResponse::error('Vui lòng nhập số tiền và tài khoản đối ứng');
            return;
        }
        try {
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordPayment(
                (float)$data['amount'], $data['debit_account_code'],
                $data['description'] ?? 'Cash payment',
                $data['reference'] ?? Helpers::nextVoucherNo('PC'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            $txnId = $result['transaction_id'] ?? null;
            if ($txnId) {
                $pdo = $this->getPdo();
                $fields = [];
                $params = [];
                foreach (['transaction_date', 'payer_name', 'payer_type', 'payer_id', 'payer_address', 'book_number', 'currency', 'exchange_rate'] as $f) {
                    if (isset($data[$f]) && $data[$f] !== '') {
                        $fields[] = "$f = ?";
                        $params[] = $data[$f];
                    }
                }
                if ($fields) {
                    $params[] = $txnId;
                    $pdo->prepare('UPDATE transactions SET ' . implode(', ', $fields) . ' WHERE id = ?')
                        ->execute($params);
                }

                // T11: Auto-link AP supplier payment — nếu TK Nợ là 331 và payer là supplier
                // Ghi nhận payment allocation để tracking công nợ NCC
                $debitCode = $data['debit_account_code'] ?? '';
                $payerType = $data['payer_type'] ?? '';
                $payerId = $data['payer_id'] ?? '';
                $amount = (float)$data['amount'];
                if (str_starts_with($debitCode, '331') && $payerType === 'supplier' && $payerId && $amount > 0) {
                    try {
                        $pdo->prepare("INSERT INTO payment_allocations (payment_type, transaction_id, invoice_id, amount, entity_id) VALUES ('ap', ?, 0, ?, 1)")
                            ->execute([$txnId, $amount]);
                    } catch (\PDOException $e) {}
                }
            }
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // ── Bank Transactions ──

    public function bankTransactions(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by,
            (SELECT SUM(IF(le.is_debit=1,le.amount,0)) FROM ledger_entries le WHERE le.transaction_id=t.id) as debit_total,
            (SELECT SUM(IF(le.is_debit=0,le.amount,0)) FROM ledger_entries le WHERE le.transaction_id=t.id) as credit_total
            FROM transactions t
            WHERE t.description LIKE 'Bank deposit:%'
               OR t.description LIKE 'Bank withdrawal:%'
               OR t.description LIKE 'Bank receipt:%'
               OR t.description LIKE 'Bank payment:%'
               OR t.description LIKE 'Bank interest:%'
               OR t.description LIKE 'Bank charge:%'
            ORDER BY t.created_at DESC LIMIT 200");
        header('Content-Type: application/json; charset=utf-8');
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận nộp tiền vào ngân hàng — Nợ 112 / Có 111 (chuyển tiền mặt ra NH)
    // Input: { amount, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankDeposit() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Dr 112 (tiền gửi) / Cr 111 (tiền mặt) — cùng một doanh nghiệp
    // Rủi ro: Phải đảm bảo tiền mặt đủ để nộp. Kiểm tra period open.
    public function createDeposit(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $result = $this->cash->recordBankDeposit(
                (float)$data['amount'],
                $data['description'] ?? 'Bank deposit',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi nhận rút tiền ngân hàng về quỹ — Nợ 111 / Có 112
    // Input: { amount, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankWithdrawal() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Dr 111 (tiền mặt) / Cr 112 (tiền gửi)
    // Rủi ro: Số dư TK 112 phải đủ để rút. Cần kiểm tra số dư trước khi ghi nhận
    public function createWithdrawal(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $result = $this->cash->recordBankWithdrawal(
                (float)$data['amount'],
                $data['description'] ?? 'Bank withdrawal',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi nhận thu tiền qua ngân hàng (KH chuyển khoản) — Nợ 112 / Có (đối ứng)
    // Input: { amount, credit_account_code, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankReceipt() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Dr 112 / Cr 511 (doanh thu), Cr 131 (thu hồi công nợ), Cr 515 (lãi), ...
    // Rủi ro: R005 — credit_account_code không được là control account
    public function createBankReceipt(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            JsonResponse::error('Vui lòng nhập số tiền và tài khoản đối ứng', 400);
            return;
        }
        try {
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordBankReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Bank receipt',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi nhận chi tiền qua ngân hàng — Nợ (đối ứng) / Có 112
    // Input: { amount, debit_account_code, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankPayment() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Dr 331 (thanh toán NCC), Dr 152 (mua hàng), Dr 642 (chi phí), ... / Cr 112
    // Rủi ro: R005 — debit_account_code không control account. Cần kiểm tra số dư 112
    public function createBankPayment(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['debit_account_code'])) {
            JsonResponse::error('Vui lòng nhập số tiền và tài khoản đối ứng', 400);
            return;
        }
        try {
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordBankPayment(
                (float)$data['amount'], $data['debit_account_code'],
                $data['description'] ?? 'Bank payment',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi nhận lãi tiền gửi ngân hàng — Nợ 112 / Có 515
    // Input: { amount, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankInterest() → JournalService.postEntry
    // Hạch toán: Dr 112 (tiền gửi tăng) / Cr 515 (doanh thu hoạt động tài chính)
    // Rủi ro: Lãi NH thường được NH báo cuối tháng, cần đối chiếu với sao kê
    // Ảnh hưởng BC02: Tăng chỉ tiêu doanh thu HĐTC (515)
    public function createInterest(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $result = $this->cash->recordBankInterest(
                (float)$data['amount'],
                $data['description'] ?? 'Bank interest',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi nhận phí ngân hàng — Nợ 642 (chi phí QLDN) / Có 112
    // Input: { amount, description?, reference?, created_by? }
    // Output: { transaction_id, reference, status } — 201 Created
    // Service: CashService.recordBankCharge() → JournalService.postEntry
    // Hạch toán: Dr 6425 (phí ngân hàng) / Cr 112 (tiền gửi giảm)
    // Rủi ro: Phí NH cần đối chiếu với sao kê. Sai TK đối ứng → sai BC02 (642 vs 635)
    public function createCharge(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordBankCharge(
                (float)$data['amount'],
                $data['description'] ?? 'Bank charge',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // ── Transit ──

    public function transitRecords(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT * FROM cash_transit ORDER BY created_at DESC LIMIT 200");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận tiền đang chuyển — giữa 2 tài khoản chưa xác định
    // Input: { amount, description?, reference?, created_by? }
    // Output: { transit_id, status } — 201 Created
    // Service: CashService.recordTransit() — ghi vào cash_transit table (tạm thời)
    // Permission: CSRF check
    // Rủi ro: Tiền đang chuyển chưa ảnh hưởng số dư TK 111/112. Cần confirmTransit để ghi nhận
    // Đây là bước trung gian, sau khi xác nhận sẽ ghi bút toán chính thức
    public function createTransit(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $result = $this->cash->recordTransit(
                (float)$data['amount'],
                $data['description'] ?? 'Cash in transit',
                $data['reference'] ?? uniqid('ct_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Xác nhận tiền đã đến tài khoản đích — ghi bút toán chính thức
    // Input: { transit_id, created_by? }
    // Output: { transaction_id, status } — 200 OK
    // Service: CashService.confirmTransit() → JournalService.postEntry
    // Hạch toán: Dr 1112/112 (tài khoản đích) / Cr 1111 (tài khoản nguồn)
    // Rủi ro: R007 — Nếu confirm thất bại, transit record vẫn tồn tại để xử lý thủ công
    public function confirmTransit(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'])) {
            JsonResponse::error('Vui lòng nhập mã giao dịch tạm giữ', 400);
            return;
        }
        try {
            $result = $this->cash->confirmTransit(
                $data['transit_id'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Hủy giao dịch tiền đang chuyển — xóa transit record, không ghi bút toán
    // Input: { transit_id, created_by? }
    // Output: { success: true } — 200 OK
    // Service: CashService.reverseTransit() — xóa transit (chỉ khi chưa confirm)
    // Rủi ro: Chỉ reverse được transit chưa confirm. Nếu đã confirm → phải tạo reversing journal
    public function reverseTransit(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'])) {
            JsonResponse::error('Vui lòng nhập mã giao dịch tạm giữ', 400);
            return;
        }
        try {
            $result = $this->cash->reverseTransit(
                $data['transit_id'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // ── Cash Book ──

    // NGHIỆP VỤ: Sổ quỹ tiền mặt — tổng hợp thu/chi theo ngày, tồn quỹ cuối kỳ
    // Input: GET ?from=2025-01-01&to=2025-01-31
    // Output: { opening_balance, receipts: [...], payments: [...], closing_balance }
    // Service: CashService.getCashBook() — đọc từ TransactionRepository
    // Mục đích: Đối chiếu sổ quỹ thực tế với số dư kế toán (111)
    // Rủi ro: Chỉ tính giao dịch status=posted, không tính draft
    public function cashBook(): void
    {
        try {
            $from = $_GET['from'] ?? null;
            $to = $_GET['to'] ?? null;
            JsonResponse::ok($this->cash->getCashBook($from, $to));
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    // ── FX ──

    public function fcBalances(): void
    {
        JsonResponse::ok($this->cash->getFCBalances());
    }

    // NGHIỆP VỤ: Đánh giá lại ngoại tệ cho một tài khoản cụ thể (theo VAS 10)
    // Input: { account_code, currency_code, closing_rate, as_of_date?, created_by? }
    // Output: { difference, gain_loss_account, journal_entry }
    // Service: CashService.revalueFC() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Dr 1112/1122 / Cr 515 (lãi TG) hoặc Dr 635 / Cr 1112/1122 (lỗ TG)
    // Rủi ro: R001 — Không đánh giá lại nếu kỳ đã đóng. closing_rate phải là tỷ giá cuối kỳ
    // Ràng buộc: Chỉ áp dụng cho TK 1112, 1122 (tiền mặt/gửi NH ngoại tệ)
    public function fcRevalue(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['account_code'], $data['currency_code'], $data['closing_rate'])) {
            JsonResponse::error('Vui lòng nhập mã tài khoản, mã ngoại tệ và tỷ giá cuối kỳ');
            return;
        }
        try {
            $result = $this->cash->revalueFC(
                $data['account_code'], $data['currency_code'],
                (float)$data['closing_rate'],
                $data['as_of_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // ── Transaction Templates ──

    public function transactionTemplates(): void
    {
        $type = $_GET['type'] ?? 'receipt';

        $receiptTemplates = [
            ['id' => 'sales', 'name' => 'Thu tiền bán hàng', 'default_account' => '511', 'has_vat' => true, 'vat_rate' => 10],
            ['id' => 'ar_recovery', 'name' => 'Thu hồi công nợ', 'default_account' => '131', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'finance_income', 'name' => 'Thu nhập tài chính', 'default_account' => '515', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'other_income', 'name' => 'Thu nhập khác', 'default_account' => '711', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'bank_withdrawal', 'name' => 'Rút tiền NH về quỹ', 'default_account' => '112', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'capital_contribution', 'name' => 'Nhận vốn góp', 'default_account' => '4111', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'deposit_return', 'name' => 'Thu ký quỹ, ký cược', 'default_account' => '344', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'advance_recovery', 'name' => 'Thu hồi tạm ứng', 'default_account' => '141', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'subsidy', 'name' => 'Nhận trợ cấp Nhà nước', 'default_account' => '3339', 'has_vat' => false, 'vat_rate' => 0],
        ];

        $paymentTemplates = [
            ['id' => 'inventory', 'name' => 'Mua hàng tồn kho', 'default_account' => '152', 'has_vat' => true, 'vat_rate' => 10],
            ['id' => 'fixed_asset', 'name' => 'Mua TSCĐ', 'default_account' => '211', 'has_vat' => true, 'vat_rate' => 10],
            ['id' => 'expense', 'name' => 'Chi phí SXKD', 'default_account' => '642', 'has_vat' => true, 'vat_rate' => 10],
            ['id' => 'supplier_payment', 'name' => 'Thanh toán nhà cung cấp', 'default_account' => '331', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'tax_payment', 'name' => 'Nộp thuế', 'default_account' => '333', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'salary', 'name' => 'Trả lương', 'default_account' => '334', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'loan_repayment', 'name' => 'Trả vay', 'default_account' => '341', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'investment', 'name' => 'Mua đầu tư tài chính', 'default_account' => '121', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'finance_cost', 'name' => 'Chi phí tài chính', 'default_account' => '635', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'bank_deposit', 'name' => 'Gửi tiền vào NH', 'default_account' => '112', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'escrow', 'name' => 'Ký quỹ, ký cược', 'default_account' => '244', 'has_vat' => false, 'vat_rate' => 0],
            ['id' => 'advance', 'name' => 'Tạm ứng', 'default_account' => '141', 'has_vat' => false, 'vat_rate' => 0],
        ];

        $templates = ($type === 'receipt') ? $receiptTemplates : $paymentTemplates;
        JsonResponse::ok($templates);
    }

    // ── Account picker ──

    public function accounts(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        $for = $_GET['for'] ?? 'all';

        // Define allowed account types per transaction nature
        // Receipt (Dr 111 -> Cr X): credit-normal accounts
        $receiptTypes = ['liability', 'equity', 'revenue'];
        // Payment (Dr X -> Cr 111): debit-normal accounts
        $paymentTypes = ['asset', 'expense'];

        $all = $this->accountRepo->findAll();
        $result = [];

        foreach ($all as $a) {
            $code = $a->getCode();
            // Always exclude cash/bank accounts (111, 112, 113)
            if (in_array($code, ['111', '112', '113'])) continue;
            // Exclude control accounts (parent accounts with sub-accounts)
            if ($a->isControl()) continue;
            // Exclude result determination account
            if ($code === '911') continue;

            // Filter by transaction nature
            $type = $a->getType();
            if ($for === 'receipt') {
                // Credit accounts for receipt: revenue/liability/equity + AR (131)
                $allowed = in_array($type, $receiptTypes) || $code === '131';
                if (!$allowed) continue;
            }
            if ($for === 'payment') {
                // Debit accounts for payment: asset/expense + AP (331)
                $allowed = in_array($type, $paymentTypes) || $code === '331';
                if (!$allowed) continue;
            }

            $result[] = [
                'code' => $code,
                'name' => $a->getName(),
                'type' => $type,
                'balance' => $a->getBalance(),
            ];
        }

        JsonResponse::ok($result);
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
