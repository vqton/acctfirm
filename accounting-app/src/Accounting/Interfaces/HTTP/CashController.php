<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\CashService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Helpers;

class CashController
{
    private CashService $cash;
    private AccountRepositoryInterface $accountRepo;
    private \PDO $pdo;

    public function __construct(CashService $cash, AccountRepositoryInterface $accountRepo, \PDO $pdo)
    {
        $this->cash = $cash;
        $this->accountRepo = $accountRepo;
        $this->pdo = $pdo;
    }

    // ── Cash Receipts ──

    public function receipts(): void
    {
        Helpers::requirePermission('cash', 'view');
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by
            FROM transactions t WHERE t.description LIKE 'Cash receipt:%'
            ORDER BY t.created_at DESC LIMIT 200");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createReceipt(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            Helpers::jsonError('amount, credit_account_code required');
            return;
        }
        try {
            $result = $this->cash->recordReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Cash receipt',
                $data['reference'] ?? Helpers::nextVoucherNo('PT'),
                $data['created_by'] ?? 'system'
            );
            Helpers::jsonOk($result, 201);
        } catch (\InvalidArgumentException $e) {
            Helpers::jsonError($e->getMessage());
        }
    }

    // ── Cash Payments ──

    public function payments(): void
    {
        Helpers::requirePermission('cash', 'view');
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by
            FROM transactions t WHERE t.description LIKE 'Cash payment:%'
            ORDER BY t.created_at DESC LIMIT 200");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createPayment(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['debit_account_code'])) {
            Helpers::jsonError('amount, debit_account_code required');
            return;
        }
        try {
            $result = $this->cash->recordPayment(
                (float)$data['amount'], $data['debit_account_code'],
                $data['description'] ?? 'Cash payment',
                $data['reference'] ?? Helpers::nextVoucherNo('PC'),
                $data['created_by'] ?? 'system'
            );
            Helpers::jsonOk($result, 201);
        } catch (\InvalidArgumentException $e) {
            Helpers::jsonError($e->getMessage());
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
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createDeposit(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount required']);
            return;
        }
        try {
            $result = $this->cash->recordBankDeposit(
                (float)$data['amount'],
                $data['description'] ?? 'Bank deposit',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createWithdrawal(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount required']);
            return;
        }
        try {
            $result = $this->cash->recordBankWithdrawal(
                (float)$data['amount'],
                $data['description'] ?? 'Bank withdrawal',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createBankReceipt(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount, credit_account_code required']);
            return;
        }
        try {
            $result = $this->cash->recordBankReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Bank receipt',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createBankPayment(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['debit_account_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount, debit_account_code required']);
            return;
        }
        try {
            $result = $this->cash->recordBankPayment(
                (float)$data['amount'], $data['debit_account_code'],
                $data['description'] ?? 'Bank payment',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createInterest(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount required']);
            return;
        }
        try {
            $result = $this->cash->recordBankInterest(
                (float)$data['amount'],
                $data['description'] ?? 'Bank interest',
                $data['reference'] ?? uniqid('bc_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createCharge(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount required']);
            return;
        }
        try {
            $result = $this->cash->recordBankCharge(
                (float)$data['amount'],
                $data['description'] ?? 'Bank charge',
                $data['reference'] ?? uniqid('bn_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ── Transit ──

    public function transitRecords(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT * FROM cash_transit ORDER BY created_at DESC LIMIT 200");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createTransit(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount required']);
            return;
        }
        try {
            $result = $this->cash->recordTransit(
                (float)$data['amount'],
                $data['description'] ?? 'Cash in transit',
                $data['reference'] ?? uniqid('ct_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function confirmTransit(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'transit_id required']);
            return;
        }
        try {
            $result = $this->cash->confirmTransit(
                $data['transit_id'],
                $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function reverseTransit(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'transit_id required']);
            return;
        }
        try {
            $result = $this->cash->reverseTransit(
                $data['transit_id'],
                $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ── Cash Book ──

    public function cashBook(): void
    {
        try {
            $from = $_GET['from'] ?? null;
            $to = $_GET['to'] ?? null;
            echo json_encode($this->cash->getCashBook($from, $to));
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ── Petty Cash ──

    public function pettyFunds(): void
    {
        echo json_encode($this->cash->getPettyCashFunds());
    }

    public function createPettyFund(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_name'], $data['imprest_amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'fund_name, imprest_amount required']);
            return;
        }
        try {
            $result = $this->cash->establishPettyCash(
                $data['fund_name'], (float)$data['imprest_amount'],
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function disbursePetty(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'fund_id, amount required']);
            return;
        }
        try {
            $result = $this->cash->disbursePettyCash(
                $data['fund_id'], (float)$data['amount'],
                $data['description'] ?? 'Petty cash disbursement',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function replenishPetty(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['expense_account'], $data['total_amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'fund_id, expense_account, total_amount required']);
            return;
        }
        try {
            $result = $this->cash->replenishPettyCash(
                $data['fund_id'], $data['expense_account'], (float)$data['total_amount'],
                $data['description'] ?? 'Petty cash replenishment',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function closePettyFund(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'fund_id required']);
            return;
        }
        try {
            $result = $this->cash->closePettyCash(
                $data['fund_id'], (float)($data['return_amount'] ?? 0),
                $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function pettyTransactions(string $fundId): void
    {
        echo json_encode($this->cash->getPettyCashTransactions($fundId));
    }

    // ── FX ──

    public function fcBalances(): void
    {
        Helpers::jsonOk($this->cash->getFCBalances());
    }

    public function fcRevalue(): void
    {
        Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['account_code'], $data['currency_code'], $data['closing_rate'])) {
            Helpers::jsonError('account_code, currency_code, closing_rate required');
            return;
        }
        try {
            $result = $this->cash->revalueFC(
                $data['account_code'], $data['currency_code'],
                (float)$data['closing_rate'],
                $data['as_of_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? 'system'
            );
            Helpers::jsonOk($result);
        } catch (\InvalidArgumentException $e) {
            Helpers::jsonError($e->getMessage());
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
        echo json_encode($templates);
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

        echo json_encode($result);
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
