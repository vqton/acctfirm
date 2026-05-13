<?php
// src/Accounting/Domain/Model/LedgerEntry.php

namespace Accounting\Domain\Model;

class LedgerEntry
{
    private string $id;
    private string $accountId;
    private float $amount;
    private bool $isDebit;
    private ?string $note;

    public function __construct(string $id, string $accountId, float $amount, bool $isDebit, ?string $note = null)
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->isDebit = $isDebit;
        $this->note = $note;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function isDebit(): bool
    {
        return $this->isDebit;
    }

    public function isCredit(): bool
    {
        return !$this->isDebit;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}