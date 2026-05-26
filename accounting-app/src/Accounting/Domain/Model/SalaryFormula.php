<?php
namespace Accounting\Domain\Model;

/**
 * Cong thuc tinh luong — dinh nghia cach tinh luong net, BH, thue TNCN.
 *
 * NGHIEP VU:
 * - type: gross_to_net (tinh luong thuc lanh), insurance (tinh BH),
 *         tax (tinh thue TNCN), overtime (tinh tang ca)
 * - formula_expression: bieu thuc tinh toan (VD: gross - insurance_ee - tax)
 *   Co the la ten ham PHP hoac bieu thuc don gian
 *
 * LUU Y: Giai doan 1 chi ho tro tinh co ban (gross - BH - thue).
 * Cong thuc phuc tap se phat trien sau.
 */
class SalaryFormula
{
    private string $id;
    private string $code;
    private string $name;
    private string $type;
    private ?string $description;
    private string $formulaExpression;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $name,
        string $type = 'gross_to_net',
        ?string $description = null,
        string $formulaExpression = '',
        bool $status = true
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->formulaExpression = $formulaExpression;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getDescription(): ?string { return $this->description; }
    public function getFormulaExpression(): string { return $this->formulaExpression; }
    public function isStatus(): bool { return $this->status; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setType(string $v): void { $this->type = $v; }
    public function setDescription(?string $v): void { $this->description = $v; }
    public function setFormulaExpression(string $v): void { $this->formulaExpression = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'formula_expression' => $this->formulaExpression,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
