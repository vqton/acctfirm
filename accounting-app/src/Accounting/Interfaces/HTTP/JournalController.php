<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Repository\AccountRepositoryInterface;

class JournalController
{
    private JournalService $journal;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(JournalService $journal, AccountRepositoryInterface $accountRepo)
    {
        $this->journal = $journal;
        $this->accountRepo = $accountRepo;
    }

    public function postEntry(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Requires at least 2 lines with account_code, amount, is_debit']);
            return;
        }
        try {
            $txn = $this->journal->postEntry(
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_'),
                $data['lines'],
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode([
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
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function trialBalance(): void
    {
        $accounts = $this->accountRepo->findAll();
        $result = [];
        $totalDr = 0; $totalCr = 0;

        foreach ($accounts as $a) {
            $bal = $a->getBalance();
            if (abs($bal) < 1) continue;
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

        echo json_encode([
            'accounts' => $result,
            'total_debit' => round($totalDr, 0),
            'total_credit' => round($totalCr, 0),
            'balanced' => abs($totalDr - $totalCr) < 10,
        ]);
    }
}
