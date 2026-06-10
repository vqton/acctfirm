<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Yêu cầu tạm ứng — Đề nghị tạm ứng tiền cho nhân viên.
 *
 * NGHIỆP VỤ:
 * - Nhân viên đề nghị tạm ứng tiền để thực hiện công việc (đi công tác, mua vật tư...)
 * - $status: 'draft' (nháp), 'approved' (duyệt), 'disbursed' (đã chi), 'settled' (đã thanh toán)
 * - $amount: số tiền đề nghị tạm ứng
 * - $repaymentTerm: thời hạn hoàn ứng
 */
class AdvancePaymentRequest
{
    private ?string $id;
    private ?string $requestNumber;
    private ?string $requestDate;
    private ?string $requesterName;
    private ?string $requesterDepartment;
    private float $amount;
    private ?string $amountInWords;
    private ?string $reason;
    private ?string $repaymentTerm;
    private string $status;
    private ?string $notes;
    private int $entityId;
    private string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;

    /**
     * Khởi tạo yêu cầu tạm ứng.
     *
     * @param string|null $id Định danh
     * @param string|null $requestNumber Số phiếu đề nghị
     * @param string|null $requestDate Ngày đề nghị
     * @param string|null $requesterName Người đề nghị
     * @param string|null $requesterDepartment Phòng ban
     * @param float $amount Số tiền
     * @param string|null $amountInWords Số tiền bằng chữ
     * @param string|null $reason Lý do tạm ứng
     * @param string|null $repaymentTerm Thời hạn hoàn ứng
     * @param string $status Trạng thái: 'draft', 'approved', 'disbursed', 'settled'
     * @param string|null $notes Ghi chú
     * @param int $entityId ID đơn vị (multi-tenant)
     * @param string $createdBy Người tạo
     * @param string|null $createdAt Thời điểm tạo
     * @param string|null $updatedAt Thời điểm cập nhật
     */
    public function __construct(
        ?string $id = null,
        ?string $requestNumber = null,
        ?string $requestDate = null,
        ?string $requesterName = null,
        ?string $requesterDepartment = null,
        float $amount = 0.0,
        ?string $amountInWords = null,
        ?string $reason = null,
        ?string $repaymentTerm = null,
        string $status = 'draft',
        ?string $notes = null,
        int $entityId = 1,
        string $createdBy = 'system',
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->requestNumber = $requestNumber;
        $this->requestDate = $requestDate;
        $this->requesterName = $requesterName;
        $this->requesterDepartment = $requesterDepartment;
        $this->amount = $amount;
        $this->amountInWords = $amountInWords;
        $this->reason = $reason;
        $this->repaymentTerm = $repaymentTerm;
        $this->status = $status;
        $this->notes = $notes;
        $this->entityId = $entityId;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /** @return string|null Định danh yêu cầu tạm ứng */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $v Định danh mới */
    public function setId(?string $v): void { $this->id = $v; }

    /** @return string|null Số phiếu đề nghị tạm ứng */
    public function getRequestNumber(): ?string { return $this->requestNumber; }

    /** @param string|null $v Số phiếu mới */
    public function setRequestNumber(?string $v): void { $this->requestNumber = $v; }

    /** @return string|null Ngày đề nghị */
    public function getRequestDate(): ?string { return $this->requestDate; }

    /** @param string|null $v Ngày đề nghị mới */
    public function setRequestDate(?string $v): void { $this->requestDate = $v; }

    /** @return string|null Người đề nghị */
    public function getRequesterName(): ?string { return $this->requesterName; }

    /** @param string|null $v Người đề nghị mới */
    public function setRequesterName(?string $v): void { $this->requesterName = $v; }

    /** @return string|null Phòng ban người đề nghị */
    public function getRequesterDepartment(): ?string { return $this->requesterDepartment; }

    /** @param string|null $v Phòng ban mới */
    public function setRequesterDepartment(?string $v): void { $this->requesterDepartment = $v; }

    /** @return float Số tiền tạm ứng */
    public function getAmount(): float { return $this->amount; }

    /** @param float $v Số tiền mới */
    public function setAmount(float $v): void { $this->amount = $v; }

    /** @return string|null Số tiền bằng chữ */
    public function getAmountInWords(): ?string { return $this->amountInWords; }

    /** @param string|null $v Số tiền bằng chữ mới */
    public function setAmountInWords(?string $v): void { $this->amountInWords = $v; }

    /** @return string|null Lý do tạm ứng */
    public function getReason(): ?string { return $this->reason; }

    /** @param string|null $v Lý do mới */
    public function setReason(?string $v): void { $this->reason = $v; }

    /** @return string|null Thời hạn hoàn ứng */
    public function getRepaymentTerm(): ?string { return $this->repaymentTerm; }

    /** @param string|null $v Thời hạn hoàn ứng mới */
    public function setRepaymentTerm(?string $v): void { $this->repaymentTerm = $v; }

    /** @return string Trạng thái: 'draft', 'approved', 'disbursed', 'settled' */
    public function getStatus(): string { return $this->status; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /** @return int ID đơn vị (multi-tenant) */
    public function getEntityId(): int { return $this->entityId; }

    /** @param int $v ID đơn vị mới */
    public function setEntityId(int $v): void { $this->entityId = $v; }

    /** @return string Người tạo */
    public function getCreatedBy(): string { return $this->createdBy; }

    /** @param string $v Người tạo mới */
    public function setCreatedBy(string $v): void { $this->createdBy = $v; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param string|null $v Thời điểm tạo mới */
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

    /** @return string|null Thời điểm cập nhật */
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /** @param string|null $v Thời điểm cập nhật mới */
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu yêu cầu tạm ứng dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->requestNumber,
            'request_date' => $this->requestDate,
            'requester_name' => $this->requesterName,
            'requester_department' => $this->requesterDepartment,
            'amount' => $this->amount,
            'amount_in_words' => $this->amountInWords,
            'reason' => $this->reason,
            'repayment_term' => $this->repaymentTerm,
            'status' => $this->status,
            'notes' => $this->notes,
            'entity_id' => $this->entityId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
