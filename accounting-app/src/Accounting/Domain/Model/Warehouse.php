<?php
namespace Accounting\Domain\Model;

/**
 * Kho — Đơn vị quản lý không gian lưu trữ hàng hóa, vật tư.
 *
 * Mỗi kho là một địa điểm vật lý hoặc logic chứa hàng tồn kho.
 * Hàng hóa có thể luân chuyển giữa các kho qua nghiệp vụ chuyển kho.
 *
 * NGHIỆP VỤ:
 * - Mỗi kho có một mã riêng, thường theo quy tắc: 3 ký tự đầu là tỉnh/thành
 * - Một doanh nghiệp có thể có nhiều kho: kho chính, kho phụ, kho tạm
 *
 * LIÊN KẾT:
 * - Item (qua inventory_layers) → số lượng tồn theo từng kho
 * - InventoryService → chuyển kho, kiểm kê, điều chỉnh tồn
 *
 * RỦI RO:
 * - Hàng gửi đi (TK 157) đã xuất kho nhưng chưa ghi nhận doanh thu
 * - Kiểm kê cuối kỳ phát hiện chênh lệch → cần điều chỉnh và giải trình
 */
class Warehouse
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $address;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $address = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->address = $address; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getAddress(): ?string { return $this->address; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setAddress(?string $v): void { $this->address = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    // Chuyển đổi model thành mảng để response API.
    // Kho là đơn vị quản lý tồn kho vật lý. Hàng hóa luân chuyển giữa các kho qua chuyển kho.
    // RỦI RO: Hàng gửi đi (TK 157) đã xuất kho nhưng chưa ghi nhận doanh thu — cần theo dõi riêng.
    // Kiểm kê cuối kỳ phát hiện chênh lệch → điều chỉnh và giải trình.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'address' => $this->address, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}