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

    /**
     * Khởi tạo phòng ban.
     *
     * @param string $id Định danh phòng ban
     * @param string $code Mã phòng ban
     * @param string $name Tên phòng ban
     * @param string|null $parentId ID phòng ban cha (cấu trúc cây)
     */
    public function __construct(string $id, string $code, string $name, ?string $parentId = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->parentId = $parentId; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh phòng ban */
    public function getId(): string { return $this->id; }

    /** @return string Mã phòng ban */
    public function getCode(): string { return $this->code; }

    /** @return string Tên phòng ban */
    public function getName(): string { return $this->name; }

    /** @return string|null ID phòng ban cha */
    public function getParentId(): ?string { return $this->parentId; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Mã phòng ban mới */
    public function setCode(string $v): void { $this->code = $v; }

    /** @param string $v Tên phòng ban mới */
    public function setName(string $v): void { $this->name = $v; }

    /** @param string|null $v ID phòng ban cha mới */
    public function setParentId(?string $v): void { $this->parentId = $v; }

    /** @param bool $v Trạng thái mới */
    public function setStatus(bool $v): void { $this->status = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu phòng ban dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'parent_id' => $this->parentId, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
