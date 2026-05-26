<?php
namespace Accounting\Domain\Model;

/**
 * Đơn vị tính (UoM) — Danh mục đơn vị đo lường cho vật tư, hàng hóa.
 *
 * Mỗi Item gắn với một đơn vị tính (cái, kg, mét, thùng, bao...).
 * Đơn vị tính ảnh hưởng đến giá vốn, số lượng tồn kho, và báo cáo.
 *
 * NGHIỆP VỤ:
 * - Một mặt hàng có thể có nhiều đơn vị tính (VD: thùng = 24 chai)
 * - $code: mã viết tắt (VD: 'cai', 'kg', 'm', 'thung')
 * - Chuyển đổi đơn vị tính cần tỷ lệ quy đổi chính xác
 *
 * RỦI RO:
 * - Nhầm đơn vị tính khi nhập/xuất → sai số lượng tồn kho
 * - Cần kiểm soát chặt chẽ khi cho phép quy đổi giữa các đơn vị
 */
class Uom
{
    private string $id;
    private string $code;
    private string $name;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name)
    {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setStatus(bool $status): void { $this->status = $status; }

    // Chuyển đổi model thành mảng để response API.
    // Đơn vị tính gắn với Item — ảnh hưởng số lượng tồn kho và giá vốn.
    // RỦI RO: Nhầm đơn vị tính khi nhập/xuất → sai số lượng tồn kho.
    // Một mặt hàng có thể có nhiều đơn vị tính với tỷ lệ quy đổi.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'status' => $this->status, 'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
