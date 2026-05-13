<?php
namespace Accounting\Domain\Model;

class DepreciationPolicy
{
    private string $id;
    private string $code;
    private string $name;
    private string $method;
    private int $defaultLife;
    private float $defaultSalvageRate;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $method = 'straight_line',
        int $defaultLife = 0, float $defaultSalvageRate = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->method = $method;
        $this->defaultLife = $defaultLife;
        $this->defaultSalvageRate = $defaultSalvageRate;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getMethod(): string { return $this->method; }
    public function getDefaultLife(): int { return $this->defaultLife; }
    public function getDefaultSalvageRate(): float { return $this->defaultSalvageRate; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setMethod(string $method): void { $this->method = $method; }
    public function setDefaultLife(int $life): void { $this->defaultLife = $life; }
    public function setDefaultSalvageRate(float $rate): void { $this->defaultSalvageRate = $rate; }
    public function setStatus(bool $status): void { $this->status = $status; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'method' => $this->method, 'default_life' => $this->defaultLife,
            'default_salvage_rate' => $this->defaultSalvageRate, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
