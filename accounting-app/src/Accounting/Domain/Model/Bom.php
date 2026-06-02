<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

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

    public function __construct(string $id, string $productId, int $version, string $effectiveDate, ?string $notes = null, ?string $createdBy = null)
    {
        $this->id = $id; $this->productId = $productId; $this->version = $version;
        $this->status = 'draft'; $this->effectiveDate = $effectiveDate;
        $this->notes = $notes; $this->createdBy = $createdBy; $this->lines = [];
    }

    public function getId(): string { return $this->id; }
    public function getProductId(): string { return $this->productId; }
    public function getVersion(): int { return $this->version; }
    public function getStatus(): string { return $this->status; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getLines(): array { return $this->lines; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function setLines(array $v): void { $this->lines = $v; }

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
