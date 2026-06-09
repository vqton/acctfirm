<?php
declare(strict_types=1);
// Dịch vụ đề nghị tạm ứng — Mẫu số 03-TT theo Thông tư 99/2025/TT-BTC
//
// Nghiệp vụ:
//   - Giấy đề nghị tạm ứng là căn cứ để xét duyệt tạm ứng, làm thủ tục lập phiếu chi
//     và xuất quỹ cho tạm ứng (theo hướng dẫn TT 99)
//   - Nhân viên viết giấy đề nghị → chuyển KTT xem xét → Giám đốc duyệt chi
//     → Kế toán lập phiếu chi → Thủ quỹ xuất quỹ
//
// Quy trình Lifecycle:
//   draft → submitted → approved → paid → cancelled
//   (draft: người dùng tự sửa; submitted: chờ duyệt; approved: đã duyệt; paid: đã chi)
//
// Hạch toán (khi lập phiếu chi từ đề nghị):
//   Nợ 141 (Tạm ứng) / Có 1111 (Tiền mặt) — theo TT 99
//
// Audit trail: Ghi log cho toàn bộ thay đổi trạng thái
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Model\AdvancePaymentRequest;
use Accounting\Domain\ValueObject\VnWords;

class AdvancePaymentRequestService
{
    private \PDO $pdo;
    private VoucherService $voucherService;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        \PDO $pdo,
        VoucherService $voucherService,
        AuditLoggerInterface $auditLogger
    ) {
        $this->pdo = $pdo;
        $this->voucherService = $voucherService;
        $this->auditLogger = $auditLogger;
    }

    // TẠO MỚI: Giấy đề nghị tạm ứng (status = draft)
    // Input: { request_date, requester_name, requester_department, amount,
    //          amount_in_words (optional — tự động sinh nếu để trống),
    //          reason, repayment_term, notes, entity_id (default 1) }
    public function createDraft(array $data): array
    {
        $id = uniqid('apr_');
        $requestNumber = $this->voucherService->nextNumber('TA');
        $requestDate = $data['request_date'] ?? date('Y-m-d');
        $requesterName = $data['requester_name'] ?? '';
        if (trim($requesterName) === '') {
            throw new \InvalidArgumentException('Vui lòng nhập họ tên người đề nghị tạm ứng');
        }
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền tạm ứng phải lớn hơn 0');
        }
        $amountInWords = $data['amount_in_words'] ?? null;
        if ($amountInWords === null || trim($amountInWords) === '') {
            try {
                $amountInWords = VnWords::toWords($amount);
            } catch (\Exception $e) {
                $amountInWords = '';
            }
        }
        $reason = $data['reason'] ?? '';
        $repaymentTerm = $data['repayment_term'] ?? '';
        $notes = $data['notes'] ?? '';
        $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : 1;
        $createdBy = $data['created_by'] ?? 'system';

        $stmt = $this->pdo->prepare(
            "INSERT INTO advance_payment_requests
                (id, request_number, request_date, requester_name, requester_department,
                 amount, amount_in_words, reason, repayment_term, status, notes,
                 entity_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $id, $requestNumber, $requestDate, $requesterName,
            $data['requester_department'] ?? null, $amount, $amountInWords, $reason,
            $repaymentTerm, $notes, $entityId, $createdBy
        ]);

        $this->auditLogger->log(
            'advance_payment.create_draft', 'advance_payment_requests', $id,
            null,
            ['request_number' => $requestNumber, 'amount' => $amount, 'requester' => $requesterName],
            $createdBy
        );

        return $this->getRequest($id);
    }

    // GỬI DUYỆT: Chuyển trạng thái từ draft → submitted
    public function submitRequest(string $id, string $submittedBy): array
    {
        $request = $this->getRequest($id);
        if ($request['status'] !== 'draft') {
            throw new \InvalidArgumentException(
                "Chỉ có thể gửi duyệt đề nghị tạm ứng ở trạng thái nháp. Trạng thái hiện tại: {$request['status']}"
            );
        }

        $stmt = $this->pdo->prepare(
            "UPDATE advance_payment_requests SET status = 'submitted', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);

        $this->auditLogger->log(
            'advance_payment.submit', 'advance_payment_requests', $id,
            ['status' => 'draft'], ['status' => 'submitted'], $submittedBy
        );

        return $this->getRequest($id);
    }

    // DUYỆT: Chuyển trạng thái từ submitted → approved
    public function approveRequest(string $id, string $approvedBy): array
    {
        $request = $this->getRequest($id);
        if ($request['status'] !== 'submitted') {
            throw new \InvalidArgumentException(
                "Chỉ có thể duyệt đề nghị tạm ứng ở trạng thái đã gửi duyệt. Trạng thái hiện tại: {$request['status']}"
            );
        }

        $stmt = $this->pdo->prepare(
            "UPDATE advance_payment_requests SET status = 'approved', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);

        $this->auditLogger->log(
            'advance_payment.approve', 'advance_payment_requests', $id,
            ['status' => 'submitted'], ['status' => 'approved'], $approvedBy
        );

        return $this->getRequest($id);
    }

    // TỪ CHỐI: Chuyển trạng thái từ submitted → cancelled
    public function rejectRequest(string $id, string $rejectedBy, ?string $reason = null): array
    {
        $request = $this->getRequest($id);
        if ($request['status'] !== 'submitted') {
            throw new \InvalidArgumentException(
                "Chỉ có thể từ chối đề nghị tạm ứng ở trạng thái đã gửi duyệt. Trạng thái hiện tại: {$request['status']}"
            );
        }

        $stmt = $this->pdo->prepare(
            "UPDATE advance_payment_requests SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ?), updated_at = NOW() WHERE id = ?"
        );
        $rejectNote = $reason ? "\n[TỪ CHỐI] {$reason}" : "\n[TỪ CHỐI]";
        $stmt->execute([$rejectNote, $id]);

        $this->auditLogger->log(
            'advance_payment.reject', 'advance_payment_requests', $id,
            ['status' => 'submitted'], ['status' => 'cancelled', 'reason' => $reason], $rejectedBy
        );

        return $this->getRequest($id);
    }

    // HỦY: Chỉ hủy được khi ở draft (người tạo tự hủy)
    public function cancelDraft(string $id, string $cancelledBy): array
    {
        $request = $this->getRequest($id);
        if ($request['status'] !== 'draft') {
            throw new \InvalidArgumentException(
                "Chỉ có thể hủy đề nghị tạm ứng ở trạng thái nháp. Trạng thái hiện tại: {$request['status']}"
            );
        }

        $stmt = $this->pdo->prepare(
            "UPDATE advance_payment_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);

        $this->auditLogger->log(
            'advance_payment.cancel', 'advance_payment_requests', $id,
            ['status' => 'draft'], ['status' => 'cancelled'], $cancelledBy
        );

        return $this->getRequest($id);
    }

    // ĐÁNH DẤU ĐÃ CHI: Chuyển từ approved → paid
    // Gọi từ CashController khi lập phiếu chi từ đề nghị này
    public function markAsPaid(string $id, string $paidBy): array
    {
        $request = $this->getRequest($id);
        if ($request['status'] !== 'approved') {
            throw new \InvalidArgumentException(
                "Chỉ có thể đánh dấu đã chi cho đề nghị tạm ứng đã duyệt. Trạng thái hiện tại: {$request['status']}"
            );
        }

        $stmt = $this->pdo->prepare(
            "UPDATE advance_payment_requests SET status = 'paid', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);

        $this->auditLogger->log(
            'advance_payment.mark_paid', 'advance_payment_requests', $id,
            ['status' => 'approved'], ['status' => 'paid'], $paidBy
        );

        return $this->getRequest($id);
    }

    // LẤY CHI TIẾT
    public function getRequest(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM advance_payment_requests WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException("Không tìm thấy đề nghị tạm ứng: {$id}");
        }

        $request = new AdvancePaymentRequest(
            $row['id'], $row['request_number'], $row['request_date'],
            $row['requester_name'], $row['requester_department'],
            (float)$row['amount'], $row['amount_in_words'],
            $row['reason'], $row['repayment_term'],
            $row['status'], $row['notes'],
            (int)$row['entity_id'], $row['created_by'],
            $row['created_at'], $row['updated_at']
        );

        return $request->toArray();
    }

    // DANH SÁCH
    public function listRequests(?string $status = null, int $limit = 50): array
    {
        $sql = "SELECT id, request_number, request_date, requester_name,
                       requester_department, amount, amount_in_words, reason,
                       repayment_term, status, notes, created_by, created_at, updated_at
                FROM advance_payment_requests";
        $params = [];
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => (new AdvancePaymentRequest(
            $row['id'], $row['request_number'], $row['request_date'],
            $row['requester_name'], $row['requester_department'],
            (float)$row['amount'], $row['amount_in_words'],
            $row['reason'], $row['repayment_term'],
            $row['status'], $row['notes'],
            1, $row['created_by'], $row['created_at'], $row['updated_at']
        ))->toArray(), $rows);
    }
}
