<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

class CorrectionController
{
    private JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    public function supplementary(): void
    {
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $originalId = $data['original_transaction_id'] ?? '';
        $reason = $data['reason'] ?? '';
        $lines = $data['lines'] ?? [];

        if (!$originalId || !$reason || count($lines) < 2) {
            JsonResponse::error('Vui lòng nhập đầy đủ: bút toán gốc, lý do điều chỉnh và các dòng bổ sung', 400);
            return;
        }

        try {
            $txn = $this->journalService->createSupplementaryEntry(
                $originalId, $lines, $reason, $_SESSION['user_id'] ?? 'system', !empty($data['allow_control'])
            );
            JsonResponse::ok(['transaction_id' => $txn->getId(), 'reference' => $txn->getReference()], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function negative(): void
    {
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $originalId = $data['original_transaction_id'] ?? '';
        $reason = $data['reason'] ?? '';

        if (!$originalId || !$reason) {
            JsonResponse::error('Vui lòng nhập: bút toán gốc và lý do điều chỉnh', 400);
            return;
        }

        try {
            $txn = $this->journalService->createNegativeEntry(
                $originalId, $reason, $_SESSION['user_id'] ?? 'system', !empty($data['allow_control'])
            );
            JsonResponse::ok(['transaction_id' => $txn->getId(), 'reference' => $txn->getReference()], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function adjusting(): void
    {
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $originalId = $data['original_transaction_id'] ?? '';
        $reason = $data['reason'] ?? '';
        $lines = $data['lines'] ?? [];

        if (!$originalId || !$reason || count($lines) < 2) {
            JsonResponse::error('Vui lòng nhập đầy đủ: bút toán gốc, lý do và các dòng điều chỉnh', 400);
            return;
        }

        try {
            $txn = $this->journalService->createAdjustingEntry(
                $originalId, $lines, $reason, $_SESSION['user_id'] ?? 'system', !empty($data['allow_control'])
            );
            JsonResponse::ok(['transaction_id' => $txn->getId(), 'reference' => $txn->getReference()], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function history(string $transactionId): void
    {
        Auth::requirePermission('journal', 'read');
        try {
            $history = $this->journalService->getCorrectionHistory($transactionId);
            JsonResponse::ok($history);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/corrections.php';
    }
}
