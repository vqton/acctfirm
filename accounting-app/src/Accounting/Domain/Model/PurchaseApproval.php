<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Phê duyệt mua hàng — Quy trình phê duyệt cho các đơn mua hàng.
 *
 * Mỗi bước phê duyệt có một người phê duyệt, trạng thái, ghi chú.
 * Quy trình phê duyệt có thể gồm nhiều bước (step).
 *
 * NGHIỆP VỤ:
 * - $docType: loại chứng từ (VD: 'purchase_requisition', 'purchase_order')
 * - $step: bước phê duyệt hiện tại
 * - $status: 'draft', 'pending', 'approved', 'rejected'
 */
class PurchaseApproval
{
    private ?string $id;
    private ?string $docType;
    private ?string $docId;
    private int $step;
    private ?string $approverId;
    private string $status;
    private ?string $note;
    private ?string $actedAt;
    private ?string $createdAt;

    /**
     * Khởi tạo phê duyệt mua hàng.
     *
     * @param string|null $id Định danh
     * @param string|null $docType Loại chứng từ
     * @param string|null $docId ID chứng từ
     * @param int $step Bước phê duyệt
     * @param string|null $approverId ID người phê duyệt
     * @param string $status Trạng thái
     * @param string|null $note Ghi chú
     * @param string|null $actedAt Thời điểm xử lý
     * @param string|null $createdAt Thời điểm tạo
     */
    public function __construct(
        ?string $id = null,
        ?string $docType = null,
        ?string $docId = null,
        int $step = 0,
        ?string $approverId = null,
        string $status = 'draft',
        ?string $note = null,
        ?string $actedAt = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->docType = $docType;
        $this->docId = $docId;
        $this->step = $step;
        $this->approverId = $approverId;
        $this->status = $status;
        $this->note = $note;
        $this->actedAt = $actedAt;
        $this->createdAt = $createdAt;
    }

    /** @return string|null Định danh */
    public function getId(): ?string
    {
        return $this->id;
    }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /** @return string|null Loại chứng từ */
    public function getDocType(): ?string
    {
        return $this->docType;
    }

    /** @param string|null $docType Loại chứng từ mới */
    public function setDocType(?string $docType): void
    {
        $this->docType = $docType;
    }

    /** @return string|null ID chứng từ */
    public function getDocId(): ?string
    {
        return $this->docId;
    }

    /** @param string|null $docId ID chứng từ mới */
    public function setDocId(?string $docId): void
    {
        $this->docId = $docId;
    }

    /** @return int Bước phê duyệt */
    public function getStep(): int
    {
        return $this->step;
    }

    /** @param int $step Bước phê duyệt mới */
    public function setStep(int $step): void
    {
        $this->step = $step;
    }

    /** @return string|null ID người phê duyệt */
    public function getApproverId(): ?string
    {
        return $this->approverId;
    }

    /** @param string|null $approverId ID người phê duyệt mới */
    public function setApproverId(?string $approverId): void
    {
        $this->approverId = $approverId;
    }

    /** @return string Trạng thái */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param string $status Trạng thái mới */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /** @return string|null Ghi chú */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /** @param string|null $note Ghi chú mới */
    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    /** @return string|null Thời điểm xử lý */
    public function getActedAt(): ?string
    {
        return $this->actedAt;
    }

    /** @param string|null $actedAt Thời điểm xử lý mới */
    public function setActedAt(?string $actedAt): void
    {
        $this->actedAt = $actedAt;
    }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /** @param string|null $createdAt Thời điểm tạo mới */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu phê duyệt dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->docType,
            'doc_id' => $this->docId,
            'step' => $this->step,
            'approver_id' => $this->approverId,
            'status' => $this->status,
            'note' => $this->note,
            'acted_at' => $this->actedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
