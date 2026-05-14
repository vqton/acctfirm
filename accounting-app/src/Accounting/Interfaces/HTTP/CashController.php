<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\CashService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Helpers;

class CashController
{
    private CashService $cash;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(CashService $cash, AccountRepositoryInterface $accountRepo)
    {
        $this->cash = $cash;
        $this->accountRepo = $accountRepo;
    }

    // ── Cash Receipts ──

    public function receipts(): void
    {
        Helpers::requirePermission('cash', 'view');
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by
            FROM transactions t WHERE t.description LIKE 'Cash receipt:%'
            ORDER BY t.created_at DESC LIMIT 200");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createReceipt(): void
    {
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
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createPayment(): void
    {
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
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createDeposit(): void
    {
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

    // ── Account picker ──

    public function accounts(): void
    {
        echo json_encode(array_map(fn($a) => [
            'code' => $a->getCode(), 'name' => $a->getName(),
            'type' => $a->getType(), 'balance' => $a->getBalance(),
        ], $this->accountRepo->findAll()));
    }

    private function getPdo(): \PDO
    {
        $ref = new \ReflectionClass($this->accountRepo);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        return $prop->getValue($this->accountRepo);
    }
}
