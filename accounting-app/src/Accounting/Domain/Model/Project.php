<?php
namespace Accounting\Domain\Model;

/**
 * Dự án — Đối tượng tập hợp chi phí và doanh thu.
 *
 * Dự án cho phép theo dõi chi phí (TK 154 — CPSXKD dở dang) và doanh thu
 * theo từng công trình, đơn hàng, hoặc hoạt động cụ thể. Sử dụng phổ biến
 * trong doanh nghiệp xây lắp, sản xuất theo đơn hàng, hoặc dịch vụ.
 *
 * NGHIỆP VỤ:
 * - $customerId: khách hàng sở hữu dự án
 * - $startDate/$endDate: thời gian thực hiện dự án
 * - $budget: dự toán — dùng để so sánh chi phí thực tế với dự toán
 *
 * LIÊN KẾT:
 * - Contract → dự án thuộc hợp đồng nào
 * - LedgerEntry → các bút toán chi phí được gán vào dự án (cost object)
 * - Kết chuyển cuối kỳ: chi phí dự án → giá vốn (TK 632) khi bàn giao
 *
 * RỦI RO:
 * - Chi phí vượt dự toán cần được phê duyệt bổ sung
 * - Dự án dở dang cuối kỳ (TK 154) phải được xác định giá trị chính xác
 * - Bàn giao dự án không đúng tiến độ ảnh hưởng doanh thu ghi nhận
 */
class Project
{
    private string $id;
    private string $code;
    private string $name;
    private string $customerId;
    private string $startDate;
    private ?string $endDate;
    private float $budget;
    private bool $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $customerId, string $startDate,
        ?string $endDate = null, float $budget = 0, ?string $notes = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->customerId = $customerId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->budget = $budget;
        $this->status = true;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getStartDate(): string { return $this->startDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function getBudget(): float { return $this->budget; }
    public function isStatus(): bool { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setCustomerId(string $id): void { $this->customerId = $id; }
    public function setStartDate(string $date): void { $this->startDate = $date; }
    public function setEndDate(?string $date): void { $this->endDate = $date; }
    public function setBudget(float $budget): void { $this->budget = $budget; }
    public function setStatus(bool $status): void { $this->status = $status; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    // Chuyển đổi model thành mảng để response API.
    // Dự án là đối tượng tập hợp chi phí (cost object) — mọi chi phí được gán vào dự án qua TK 154.
    // 'budget': dự toán — so sánh với chi phí thực tế để kiểm soát chi phí.
    // RỦI RO: Dự án dở dang cuối kỳ (TK 154) phải được xác định giá trị chính xác.
    // Bàn giao kết chuyển: Nợ 632 / Có 154 khi dự án hoàn thành.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'customer_id' => $this->customerId, 'start_date' => $this->startDate,
            'end_date' => $this->endDate, 'budget' => $this->budget,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
