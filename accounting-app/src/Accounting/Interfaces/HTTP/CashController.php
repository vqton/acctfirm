<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\CashService;
use Accounting\Domain\Repository\AccountRepositoryInterface;

class CashController
{
    private CashService $cash;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(CashService $cash, AccountRepositoryInterface $accountRepo)
    {
        $this->cash = $cash;
        $this->accountRepo = $accountRepo;
    }

    public function receipts(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by
            FROM transactions t WHERE t.description LIKE 'Cash receipt:%' OR t.description LIKE 'Receipt:%'
            ORDER BY t.created_at DESC LIMIT 200");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function payments(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at, t.created_by
            FROM transactions t WHERE t.description LIKE 'Cash payment:%' OR t.description LIKE 'Payment:%'
            ORDER BY t.created_at DESC LIMIT 200");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createReceipt(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['credit_account_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount, credit_account_code required']);
            return;
        }
        try {
            $result = $this->cash->recordReceipt(
                (float)$data['amount'], $data['credit_account_code'],
                $data['description'] ?? 'Cash receipt',
                $data['reference'] ?? uniqid('pt_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createPayment(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['debit_account_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount, debit_account_code required']);
            return;
        }
        try {
            $result = $this->cash->recordPayment(
                (float)$data['amount'], $data['debit_account_code'],
                $data['description'] ?? 'Cash payment',
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
