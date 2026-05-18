<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class JournalController
{
    private JournalService $journal;
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;

    public function __construct(JournalService $journal, AccountRepositoryInterface $accountRepo, TransactionRepositoryInterface $txnRepo)
    {
        $this->journal = $journal;
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
    }

    public function list(): void
    {
        $period = $_GET['period'] ?? date('Y-m');
        $txns = $this->txnRepo->getTransactionsByPeriod($period);
        $result = [];
        foreach ($txns as $txn) {
            $lines = [];
            foreach ($txn->getLedgerEntries() as $e) {
                $a = $this->accountRepo->findById($e->getAccountId());
                $lines[] = [
                    'account_code' => $a ? $a->getCode() : $e->getAccountId(),
                    'account_name' => $a ? $a->getName() : '',
                    'amount' => $e->getAmount(),
                    'is_debit' => $e->isDebit(),
                ];
            }
            $result[] = [
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'description' => $txn->getDescription(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
                'status' => $txn->getStatus(),
                'created_by' => $txn->getCreatedBy(),
                'lines' => $lines,
            ];
        }
        JsonResponse::ok($result);
    }

    public function get(string $id): void
    {
        $txn = $this->txnRepo->findById($id);
        if (!$txn) { JsonResponse::error('Not found', 404); return; }
        $lines = [];
        foreach ($txn->getLedgerEntries() as $e) {
            $a = $this->accountRepo->findById($e->getAccountId());
            $lines[] = [
                'account_code' => $a ? $a->getCode() : $e->getAccountId(),
                'account_name' => $a ? $a->getName() : '',
                'amount' => $e->getAmount(),
                'is_debit' => $e->isDebit(),
            ];
        }
        JsonResponse::ok([
            'id' => $txn->getId(),
            'reference' => $txn->getReference(),
            'description' => $txn->getDescription(),
            'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            'status' => $txn->getStatus(),
            'created_by' => $txn->getCreatedBy(),
            'lines' => $lines,
        ]);
    }

    public function createDraft(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) < 2) {
            JsonResponse::error('Requires at least 2 lines with account_code, amount, is_debit', 400);
            return;
        }
        try {
            $txn = $this->journal->createDraft(
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_'),
                $data['lines'],
                $_SESSION['user']['username'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function approveDraft(string $id): void
    {
        Auth::requirePermission('journal', 'approve');
        try {
            $txn = $this->journal->approveDraft($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function postEntry(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) < 2) {
            JsonResponse::error('Requires at least 2 lines with account_code, amount, is_debit', 400);
            return;
        }
        try {
            $txn = $this->journal->postEntry(
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_'),
                $data['lines'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
                'description' => $txn->getDescription(),
                'lines' => array_map(fn($e) => [
                    'account_id' => $e->getAccountId(),
                    'amount' => $e->getAmount(),
                    'is_debit' => $e->isDebit(),
                ], $txn->getLedgerEntries()),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function trialBalance(): void
    {
        $accounts = $this->accountRepo->findAll();
        $result = [];
        $totalDr = 0; $totalCr = 0;

        foreach ($accounts as $a) {
            $bal = $a->getBalance();
            if (abs($bal) < 500) continue;
            $isDr = in_array($a->getType(), ['asset', 'expense']);
            $dr = $isDr ? $bal : 0;
            $cr = $isDr ? 0 : $bal;
            $totalDr += $dr;
            $totalCr += $cr;
            $result[] = [
                'code' => $a->getCode(),
                'name' => $a->getName(),
                'type' => $a->getType(),
                'debit' => round($dr, 0),
                'credit' => round($cr, 0),
            ];
        }

        JsonResponse::ok([
            'accounts' => $result,
            'total_debit' => round($totalDr, 0),
            'total_credit' => round($totalCr, 0),
            'balanced' => abs($totalDr - $totalCr) < 10,
        ]);
    }
}
