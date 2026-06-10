<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Điều chỉnh & Hoàn nhập Bút toán (Correction & Reversal)
 *
 * Mục đích nghiệp vụ:
 *   - Hoàn nhập (reverse) bút toán đã post — tạo bút toán đảo ngược
 *   - Điều chỉnh chênh lệch sai sót (negative entry)
 *   - Ghi nhận bút toán điều chỉnh hồi tố (prior period adjustment)
 *
 * API endpoints:
 *   POST /api/corrections/negative — Tạo bút toán điều chỉnh giảm (negative entry)
 *   POST /api/corrections/reverse  — Hoàn nhập bút toán
 *
 * Rủi ro:
 *   - R002: Reverse sai -> mất cân đối Dr = Cr
 *   - Hoàn nhập không đúng bút toán gốc -> audit trail rối
 *   - R001: Không reverse được nếu kỳ đã đóng
 *
 * Tích hợp:
 *   - JournalService.createNegativeEntry xử lý điều chỉnh
 *   - AuditLogger ghi lại mọi correction
 */
class CorrectionController
{
    private JournalServiceInterface $journal;

    public function __construct(JournalServiceInterface $journal) { $this->journal = $journal; }

    /**
     * Tạo bút toán điều chỉnh (reversal) — đảo Dr/Cr bút toán gốc
     *
     * @return void
     */
    public function revert(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transaction_id'])) {
            JsonResponse::error('Vui lòng nhập mã giao dịch cần reverse', 400);
            return;
        }
        try {
            $txn = $this->journal->createNegativeEntry(
                $data['transaction_id'],
                $data['description'] ?? 'Điều chỉnh bút toán',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'original_transaction_id' => $data['transaction_id'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Hoàn nhập bút toán gốc — tạo bút toán mới với Dr/Cr đảo ngược
     *
     * @return void
     */
    public function reverse(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transaction_id'])) {
            JsonResponse::error('Vui lòng nhập mã giao dịch cần hoàn nhập', 400);
            return;
        }
        try {
            $txn = $this->journal->reverseEntry(
                $data['transaction_id'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'original_transaction_id' => $data['transaction_id'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Bút toán điều chỉnh hồi tố — chỉ áp dụng cho prior period adjustments
     *
     * @return void
     */
    public function priorPeriodAdjustment(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transaction_id'], $data['adjustment_lines'])) {
            JsonResponse::error('Vui lòng nhập mã giao dịch và danh sách điều chỉnh', 400);
            return;
        }
        try {
            $txn = $this->journal->priorPeriodAdjustment(
                $data['transaction_id'],
                $data['adjustment_lines'],
                $data['description'] ?? 'Điều chỉnh hồi tố',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }
}
