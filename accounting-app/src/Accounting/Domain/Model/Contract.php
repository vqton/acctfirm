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

    /**
     * Khởi tạo hợp đồng.
     *
     * @param string $id Định danh hợp đồng
     * @param string $code Mã hợp đồng
     * @param string $name Tên hợp đồng
     * @param string $contractType Loại hợp đồng: 'sale', 'purchase', 'service', 'construction', 'lease'
     * @param string $partyId ID đối tác
     * @param string $partyName Tên đối tác
     * @param string $contractDate Ngày ký hợp đồng
     * @param float $totalAmount Tổng giá trị hợp đồng
     * @param string $currency Loại tiền tệ
     * @param string|null $notes Ghi chú
     */
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

    /** @return string Định danh hợp đồng */
    public function getId(): string { return $this->id; }

    /** @return string Mã hợp đồng */
    public function getCode(): string { return $this->code; }

    /** @return string Tên hợp đồng */
    public function getName(): string { return $this->name; }

    /** @return string Loại hợp đồng */
    public function getContractType(): string { return $this->contractType; }

    /** @return string ID đối tác */
    public function getPartyId(): string { return $this->partyId; }

    /** @return string Tên đối tác */
    public function getPartyName(): string { return $this->partyName; }

    /** @return string Ngày ký hợp đồng */
    public function getContractDate(): string { return $this->contractDate; }

    /** @return float Tổng giá trị hợp đồng */
    public function getTotalAmount(): float { return $this->totalAmount; }

    /** @return string Loại tiền tệ */
    public function getCurrency(): string { return $this->currency; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã hợp đồng mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $name Tên hợp đồng mới */
    public function setName(string $name): void { $this->name = $name; }

    /** @param string $type Loại hợp đồng mới */
    public function setContractType(string $type): void { $this->contractType = $type; }

    /** @param string $id ID đối tác mới */
    public function setPartyId(string $id): void { $this->partyId = $id; }

    /** @param string $name Tên đối tác mới */
    public function setPartyName(string $name): void { $this->partyName = $name; }

    /** @param string $date Ngày ký mới */
    public function setContractDate(string $date): void { $this->contractDate = $date; }

    /** @param float $amount Tổng giá trị mới */
    public function setTotalAmount(float $amount): void { $this->totalAmount = $amount; }

    /** @param string $currency Loại tiền tệ mới */
    public function setCurrency(string $currency): void { $this->currency = $currency; }

    /** @param bool $status Trạng thái mới */
    public function setStatus(bool $status): void { $this->status = $status; }

    /** @param string|null $notes Ghi chú mới */
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu hợp đồng dạng mảng
     */
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
