<?php
namespace Accounting\Domain\Model;

/**
 * Phương pháp tính giá xuất kho — Xác định đơn giá hàng xuất kho.
 *
 * Phương pháp này quyết định giá vốn hàng bán (TK 632) và giá trị tồn kho
 * cuối kỳ (TK 152, 153, 155, 156). Đây là một trong những lựa chọn kế toán
 * quan trọng nhất — đã chọn thì không được thay đổi trong kỳ.
 *
 * CÁC PHƯƠNG PHÁP:
 * - 'fifo': Nhập trước xuất trước — giá vốn tính theo giá của lô nhập đầu tiên
 * - 'weighted_average': Bình quân gia quyền — giá vốn = tổng giá trị / tổng số lượng
 * - 'specific': Từng lô — theo dõi riêng từng lô hàng (cho hàng hóa đặc thù)
 *
 * LIÊN KẾT:
 * - Item → mỗi mặt hàng gắn với một phương pháp
 * - InventoryService → tính giá xuất kho theo phương pháp đã chọn
 * - inventory_layers → lưu chi tiết từng lô nhập (cho FIFO)
 *
 * RỦI RO:
 * - Thay đổi phương pháp giữa kỳ (được phép) nhưng phải thuyết minh BCTC
 * - FIFO cho lợi nhuận cao hơn trong thời kỳ lạm phát so với bình quân
 * - Phương pháp khác nhau → lợi nhuận khác nhau → thuế TNDN khác nhau
 */
class ValuationMethod
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $description;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $description = null)
    {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setDescription(?string $desc): void { $this->description = $desc; }
    public function setStatus(bool $status): void { $this->status = $status; }

    // Chuyển đổi model thành mảng để response API.
    // Phương pháp tính giá xuất kho quyết định giá vốn (TK 632) và giá trị tồn kho cuối kỳ.
    // 'code': 'fifo' (nhập trước xuất trước), 'weighted_average' (bình quân gia quyền).
    // RỦI RO: Phương pháp khác nhau → lợi nhuận khác nhau → thuế TNDN khác nhau.
    // Đã chọn không được thay đổi trong kỳ kế toán.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'description' => $this->description, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
