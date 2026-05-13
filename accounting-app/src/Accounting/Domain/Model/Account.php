<?php
// src/Accounting/Domain/Model/Account.php

namespace Accounting\Domain\Model;

class Account
{
    private string $id;
    private string $name;
    private string $type; // asset, liability, equity, revenue, expense
    private float $balance;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $name, string $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->balance = 0.0;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function credit(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        $this->balance += $amount;
    }

    public function debit(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        if ($this->balance < $amount) {
            throw new \InvalidArgumentException('Insufficient balance');
        }
        $this->balance -= $amount;
    }
}