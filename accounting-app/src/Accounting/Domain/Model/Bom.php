<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Định mức nguyên vật liệu (BOM) — Danh sách nguyên vật liệu cấu thành sản phẩm.
 *
 * Mỗi BOM gồm một danh sách các nguyên vật liệu (lines) cần thiết để sản xuất
 * một đơn vị thành phẩm. BOM được sử dụng trong ManufacturingService để
 * tính toán nhu cầu nguyên vật liệu và giá thành sản xuất.
 *
 * NGHIỆP VỤ:
 * - Một sản phẩm có thể có nhiều phiên bản BOM (version)
 * - $status: 'draft', 'active', 'archived'
 * - $lines: mảng BomLine chứa itemId, qty, unitPrice
 */
class Bom
{
    private string $id;
    private string $productId;
    private int $version;
    private string $status;
    private string $effectiveDate;
    private ?string $notes;
    private ?string $createdBy;
    private array $lines;

    /**
     * Khởi tạo BOM.
     *
     * @param string $id Định danh BOM
     * @param string $productId ID thành phẩm
     * @param int $version Phiên bản BOM
     * @param string $effectiveDate Ngày hiệu lực
     * @param string|null $notes Ghi chú
     * @param string|null $createdBy Người tạo
     */
    public function __construct(string $id, string $productId, int $version, string $effectiveDate, ?string $notes = null, ?string $createdBy = null)
    {
        $this->id = $id; $this->productId = $productId; $this->version = $version;
        $this->status = 'draft'; $this->effectiveDate = $effectiveDate;
        $this->notes = $notes; $this->createdBy = $createdBy; $this->lines = [];
    }

    /** @return string Định danh BOM */
    public function getId(): string { return $this->id; }

    /** @return string ID thành phẩm */
    public function getProductId(): string { return $this->productId; }

    /** @return int Phiên bản BOM */
    public function getVersion(): int { return $this->version; }

    /** @return string Trạng thái: 'draft', 'active', 'archived' */
    public function getStatus(): string { return $this->status; }

    /** @return string Ngày hiệu lực */
    public function getEffectiveDate(): string { return $this->effectiveDate; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return string|null Người tạo */
    public function getCreatedBy(): ?string { return $this->createdBy; }

    /** @return array Danh sách dòng BOM */
    public function getLines(): array { return $this->lines; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param array $v Danh sách dòng BOM mới */
    public function setLines(array $v): void { $this->lines = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu BOM dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'product_id' => $this->productId, 'version' => $this->version,
            'status' => $this->status, 'effective_date' => $this->effectiveDate,
            'notes' => $this->notes, 'created_by' => $this->createdBy,
            'lines' => $this->lines,
        ];
    }
}
