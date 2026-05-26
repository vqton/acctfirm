<?php
namespace Accounting\Domain\Model;

/**
 * Hợp đồng — Thỏa thuận pháp lý với khách hàng hoặc nhà cung cấp.
 *
 * Hợp đồng làm cơ sở phát sinh các giao dịch mua/bán, tạm ứng, thanh lý.
 * Trong kế toán, hợp đồng quyết định thời điểm ghi nhận doanh thu,
 * thời hạn thanh toán, và các điều khoản tài chính.
 *
 * NGHIỆP VỤ:
 * - $contractType: 'sale' (bán hàng), 'purchase' (mua hàng), 'service' (dịch vụ),
 *   'construction' (xây dựng), 'lease' (thuê)
 * - $partyId/$partyName: đối tác — có thể là Customer hoặc Supplier
 * - $totalAmount: tổng giá trị hợp đồng (chưa bao gồm thuế)
 * - $currency: loại tiền của hợp đồng
 *
 * LIÊN KẾT:
 * - Project → một hợp đồng có thể có nhiều dự án
 * - Payment terms ảnh hưởng đến lịch thanh toán và công nợ
 *
 * RỦI RO:
 * - Doanh thu ghi nhận theo % hoàn thành (construction) cần ước lượng đáng tin cậy
 * - Hợp đồng ngoại tệ phải theo dõi chênh lệch tỷ giá
 * - Thanh lý hợp đồng có thể phát sinh phạt vi phạm (thu nhập khác - TK 711)
 */
class Contract
{
    private string $id;
    private string $code;
    private string $name;
    private string $contractType;
    private string $partyId;
    private string $partyName;
    private string $contractDate;
    private float $totalAmount;
    private string $currency;
    private bool $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $contractType, string $partyId,
        string $partyName, string $contractDate, float $totalAmount = 0, string $currency = 'VND',
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->contractType = $contractType;
        $this->partyId = $partyId;
        $this->partyName = $partyName;
        $this->contractDate = $contractDate;
        $this->totalAmount = $totalAmount;
        $this->currency = $currency;
        $this->status = true;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getContractType(): string { return $this->contractType; }
    public function getPartyId(): string { return $this->partyId; }
    public function getPartyName(): string { return $this->partyName; }
    public function getContractDate(): string { return $this->contractDate; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getCurrency(): string { return $this->currency; }
    public function isStatus(): bool { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setContractType(string $type): void { $this->contractType = $type; }
    public function setPartyId(string $id): void { $this->partyId = $id; }
    public function setPartyName(string $name): void { $this->partyName = $name; }
    public function setContractDate(string $date): void { $this->contractDate = $date; }
    public function setTotalAmount(float $amount): void { $this->totalAmount = $amount; }
    public function setCurrency(string $currency): void { $this->currency = $currency; }
    public function setStatus(bool $status): void { $this->status = $status; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    // Chuyển đổi model thành mảng để response API.
    // 'contract_type': 'sale' (doanh thu), 'purchase' (chi phí), 'construction' (dở dang).
    // 'total_amount': tổng giá trị — ảnh hưởng doanh thu/chi phí theo tiến độ thực hiện.
    // RỦI RO: Hợp đồng ngoại tệ phải theo dõi chênh lệch tỷ giá cuối kỳ.
    // Thanh lý hợp đồng có thể phát sinh phạt vi phạm (thu nhập khác TK 711).
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'contract_type' => $this->contractType, 'party_id' => $this->partyId,
            'party_name' => $this->partyName, 'contract_date' => $this->contractDate,
            'total_amount' => $this->totalAmount, 'currency' => $this->currency,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
