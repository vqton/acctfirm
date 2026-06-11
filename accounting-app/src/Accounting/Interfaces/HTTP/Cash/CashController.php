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
 *   - Ghi nhận phiếu thu (PT)
 *   - Ghi nhận phiếu chi (PC)
 *   - Chuyển tiền giữa các tài khoản
 *   - Mọi giao dịch qua CashService → JournalService (Dr = Cr)
 *
 * API endpoints:
 *   GET  /api/cash/receipts — Danh sách phiếu thu
 *   POST /api/cash/receipts — Tạo phiếu thu
 *   GET  /api/cash/payments — Danh sách phiếu chi
 *   POST /api/cash/payments — Tạo phiếu chi
 *   GET  /api/cash/payments/{id} — Chi tiết phiếu chi
 *   POST /api/cash/transfers — Chuyển tiền
 *   GET  /api/cash/accounts — Danh sách TK tiền
 *   POST /api/cash/reverse — Hoàn nhập
 *
 * Rủi ro:
 *   - R002: Dr ≠ Cr
 *   - R005: Sai account code (control account)
 *   - R001: Post vào kỳ đã đóng
 *   - R006: Trùng số chứng từ
 *
 * Tích hợp:
 *   - CashService → JournalService
 *   - PettyCashController
 *   - BankReconciliationController
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

    /**
     * Danh sách phiếu thu
     *
     * @return void
     */
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

    /**
     * Chi tiết phiếu thu — dùng cho in Mẫu 01-TT
     *
     * @param string $id ID phiếu thu
     * @return void
     */
    public function getReceipt(string $id): void
    {
        Auth::requirePermission('cash', 'view');
        $stmt = $this->pdo->prepare(
            "SELECT t.*,
                (SELECT SUM(le.amount) FROM ledger_entries le WHERE le.transaction_id = t.id AND le.is_debit = 1) as amount,
                (SELECT a.code FROM ledger_entries le JOIN accounts a ON a.id = le.account_id WHERE le.transaction_id = t.id AND le.is_debit = 0 LIMIT 1) as credit_account
             FROM transactions t WHERE t.id = ? AND t.description LIKE 'Cash receipt:%'"
        );
        $stmt->execute([$id]);
        $txn = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$txn) {
            JsonResponse::error('Không tìm thấy phiếu thu', 404);
            return;
        }
        $stmt = $this->pdo->prepare(
            "SELECT le.*, a.code AS account_code, a.name AS account_name
             FROM ledger_entries le JOIN accounts a ON a.id = le.account_id
             WHERE le.transaction_id = ? ORDER BY le.line_order"
        );
        $stmt->execute([$id]);
        $txn['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        JsonResponse::ok($txn);
    }

    /**
     * Ghi nhận phiếu thu tiền mặt (PT) — Nợ 111 / Có (đối ứng)
     *
     * @return void
     */
    public function createReceipt(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            JsonResponse::error('Vui lòng nhập số tiền và tài khoản đối ứng');
            return;
        }
        try {
            $vatAmount = (float)($data['vat_amount'] ?? 0);
            $vatRate = (float)($data['vat_rate'] ?? 0);
            $result = $this->cash->recordReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Cash receipt',
                $data['reference'] ?? Helpers::nextVoucherNo('PT'),
                $data['created_by'] ?? 'system',
                $vatAmount, $vatRate
            );
            $txnId = $result['transaction_id'] ?? null;
            if ($txnId) {
                $pdo = $this->getPdo();
                $fields = [];
                $params = [];
                foreach (['transaction_date', 'payer_name', 'payer_type', 'payer_id', 'payer_address', 'document_count'] as $f) {
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
            }
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    /**
     * Danh sách phiếu chi
     *
     * @return void
     */
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

    /**
     * Chi tiết phiếu chi — dùng cho in Mẫu 02-TT
     *
     * @param string $id ID phiếu chi
     * @return void
     */
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
        $stmt = $this->pdo->prepare(
            "SELECT le.*, a.code AS account_code, a.name AS account_name
             FROM ledger_entries le JOIN accounts a ON a.id = le.account_id
             WHERE le.transaction_id = ? ORDER BY le.line_order"
        );
        $stmt->execute([$id]);
        $txn['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        JsonResponse::ok($txn);
    }

    /**
     * Ghi nhận phiếu chi tiền mặt (PC) — Nợ (đối ứng) / Có 111
     *
     * @return void
     */
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
                foreach (['transaction_date', 'payer_name', 'payer_type', 'payer_id', 'payer_address', 'book_number', 'currency', 'exchange_rate', 'document_count'] as $f) {
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

    /**
     * Danh sách giao dịch ngân hàng
     *
     * @return void
     */
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

    /**
     * Nộp tiền vào ngân hàng — Nợ 112 / Có 111
     *
     * @return void
     */
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

    /**
     * Rút tiền ngân hàng về quỹ — Nợ 111 / Có 112
     *
     * @return void
     */
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

    /**
     * Thu tiền qua ngân hàng — Nợ 112 / Có (đối ứng)
     *
     * @return void
     */
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

    /**
     * Chi tiền qua ngân hàng — Nợ (đối ứng) / Có 112
     *
     * @return void
     */
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

    /**
     * Lãi tiền gửi ngân hàng — Nợ 112 / Có 515
     *
     * @return void
     */
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

    /**
     * Phí ngân hàng — Nợ 642 (chi phí QLDN) / Có 112
     *
     * @return void
     */
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

    /**
     * Danh sách tiền đang chuyển
     *
     * @return void
     */
    public function transitRecords(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT * FROM cash_transit ORDER BY created_at DESC LIMIT 200");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Ghi nhận tiền đang chuyển — ghi vào cash_transit table (tạm thời)
     *
     * @return void
     */
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

    /**
     * Xác nhận tiền đến tài khoản đích — ghi bút toán chính thức
     *
     * @return void
     */
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

    /**
     * Hủy giao dịch tiền đang chuyển — xóa transit record
     *
     * @return void
     */
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

    /**
     * Sổ quỹ tiền mặt — tổng hợp thu/chi theo ngày
     *
     * @return void
     */
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

    /**
     * Số dư ngoại tệ
     *
     * @return void
     */
    public function fcBalances(): void
    {
        JsonResponse::ok($this->cash->getFCBalances());
    }

    /**
     * Đánh giá lại ngoại tệ cho một tài khoản (VAS 10)
     *
     * @return void
     */
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

    /**
     * Transaction templates cho phiếu thu/chi
     *
     * @return void
     */
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

    /**
     * Danh sách tài khoản đối ứng cho phiếu thu/chi
     *
     * @return void
     */
    public function accounts(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        $for = $_GET['for'] ?? 'all';
        $receiptTypes = ['liability', 'equity', 'revenue'];
        $paymentTypes = ['asset', 'expense'];
        $all = $this->accountRepo->findAll();
        $result = [];
        foreach ($all as $a) {
            $code = $a->getCode();
            if (in_array($code, ['111', '112', '113'])) continue;
            if ($a->isControl()) continue;
            if ($code === '911') continue;
            $type = $a->getType();
            if ($for === 'receipt') {
                $allowed = in_array($type, $receiptTypes) || $code === '131';
                if (!$allowed) continue;
            }
            if ($for === 'payment') {
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

    /**
     * @return \PDO
     */
    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
