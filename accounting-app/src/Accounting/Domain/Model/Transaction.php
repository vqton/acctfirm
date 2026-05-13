<?php
// src/Accounting/Domain/Model/Transaction.php

namespace Accounting\Domain\Model;

class Transaction
{
    private string $id;
    private \DateTimeImmutable $date;
    private string $description;
    private string $reference;
    private array $ledgerEntries;
    private string $status; // pending, posted, reversed
    private ?string $createdBy;

    public function __construct(string $id, \DateTimeImmutable $date, string $description, string $reference)
    {
        $this->id = $id;
        $this->date = $date;
        $this->description = $description;
        $this->reference = $reference;
        $this->ledgerEntries = [];
        $this->status = 'pending';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getLedgerEntries(): array
    {
        return $this->ledgerEntries;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function addLedgerEntry(LedgerEntry $entry): void
    {
        $this->ledgerEntries[] = $entry;
    }

    public function post(string $createdBy): void
    {
        if ($this->status !== 'pending') {
            throw new \InvalidArgumentException('Transaction already posted or reversed');
        }

        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($this->ledgerEntries as $entry) {
            if ($entry->isDebit()) {
                $debitTotal += $entry->getAmount();
            } else {
                $creditTotal += $entry->getAmount();
            }
        }

        if ($debitTotal !== $creditTotal) {
            throw new \InvalidArgumentException('Debit and credit totals must balance');
        }

        $this->status = 'posted';
        $this->createdBy = $createdBy;
    }

    public function reverse(string $reversedBy): void
    {
        if ($this->status !== 'posted') {
            throw new \InvalidArgumentException('Only posted transactions can be reversed');
        }

        $this->status = 'reversed';
    }
}