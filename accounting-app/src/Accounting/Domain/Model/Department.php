<?php
namespace Accounting\Domain\Model;

/**
 * Phòng ban — Đơn vị tổ chức trong doanh nghiệp.
 *
 * Phòng ban là đối tượng tập hợp chi phí (cost center) và là cơ sở để
 * phân bổ chi phí khấu hao, lương, và các chi phí chung khác.
 *
 * NGHIỆP VỤ:
 * - Cấu trúc cây: $parentId cho phép phân cấp phòng ban
 *   (VD: Khối Văn phòng → Phòng Kế toán → Tổ thanh toán)
 * - Chi phí phát sinh ở phòng ban nào ghi nhận chi phí ở phòng ban đó
 * - Dùng trong báo cáo chi phí theo bộ phận (segment reporting)
 *
 * LIÊN KẾT:
 * - Employee → nhân viên thuộc phòng ban
 * - FixedAsset → TSCĐ do phòng ban quản lý
 * - Các chi phí (lương, khấu hao, CCDC) được phân bổ vào TK 627/641/642
 *   theo phòng ban
 */
class Department
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $parentId;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $parentId = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->parentId = $parentId; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getParentId(): ?string { return $this->parentId; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setParentId(?string $v): void { $this->parentId = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    // Chuyển đổi model thành mảng để response API.
    // Phòng ban là cost center — chi phí lương (TK 334), khấu hao (TK 214), CCDC (TK 153)
    // được phân bổ vào TK 627/641/642 theo phòng ban sử dụng.
    // 'parent_id': cấu trúc cây cho phép báo cáo chi phí theo bộ phận (segment reporting).
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'parent_id' => $this->parentId, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}