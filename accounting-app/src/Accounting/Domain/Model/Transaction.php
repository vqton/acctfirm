<?php
namespace Accounting\Domain\Model;

class Transaction
{
    private string $id;
    private \DateTimeImmutable $date;
    private string $description;
    private string $reference;
    private array $ledgerEntries;
    private string $status;
    private ?string $createdBy;
    private ?string $voucherType;
    private ?string $sourceModule;
    private string $currency;
    private float $exchangeRate;
    private bool $isCorrection;
    private ?string $correctionType;
    private ?string $originalTransactionId;
    private ?string $correctionReason;
    private ?string $reversedBy = null;
    private ?\DateTimeImmutable $reversedAt = null;

    public function __construct(
        string $id,
        \DateTimeImmutable $date,
        string $description,
        string $reference,
        ?string $voucherType = null,
        ?string $sourceModule = null,
        string $currency = 'VND',
        float $exchangeRate = 1.0,
        bool $isCorrection = false,
        ?string $correctionType = null,
        ?string $originalTransactionId = null,
        ?string $correctionReason = null
    ) {
        $this->id = $id;
        $this->date = $date;
        $this->description = $description;
        $this->reference = $reference;
        $this->voucherType = $voucherType;
        $this->sourceModule = $sourceModule;
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
        $this->isCorrection = $isCorrection;
        $this->correctionType = $correctionType;
        $this->originalTransactionId = $originalTransactionId;
        $this->correctionReason = $correctionReason;
        $this->ledgerEntries = [];
        $this->status = 'pending';
    }

    public function isValidTransition(string $newStatus): bool
    {
        $allowed = [
            'pending' => ['submitted', 'posted'],
            'submitted' => ['approved', 'rejected'],
            'approved' => ['posted'],
            'rejected' => ['pending'],
            'posted' => ['reversed'],
            'reversed' => [],
        ];
        return in_array($newStatus, $allowed[$this->status] ?? [], true);
    }

    public function submit(): void
    {
        if (!$this->isValidTransition('submitted')) {
            throw new \InvalidArgumentException("Không thể trình duyệt: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'submitted';
    }

    public function approve(): void
    {
        if (!$this->isValidTransition('approved')) {
            throw new \InvalidArgumentException("Không thể phê duyệt: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'approved';
    }

    public function reject(): void
    {
        if (!$this->isValidTransition('rejected')) {
            throw new \InvalidArgumentException("Không thể từ chối: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'rejected';
    }

    public function returnToDraft(): void
    {
        if ($this->status !== 'rejected') {
            throw new \InvalidArgumentException("Không thể quay lại trạng thái nháp: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'pending';
    }

    public function getId(): string { return $this->id; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string { return $this->reference; }
    public function getLedgerEntries(): array { return $this->ledgerEntries; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getVoucherType(): ?string { return $this->voucherType; }
    public function getSourceModule(): ?string { return $this->sourceModule; }
    public function getCurrency(): string { return $this->currency; }
    public function getExchangeRate(): float { return $this->exchangeRate; }
    public function isCorrection(): bool { return $this->isCorrection; }
    public function getCorrectionType(): ?string { return $this->correctionType; }
    public function getReversedBy(): ?string { return $this->reversedBy; }
    public function getReversedAt(): ?\DateTimeImmutable { return $this->reversedAt; }
    public function setReversedBy(?string $v): void { $this->reversedBy = $v; }
    public function setReversedAt(?\DateTimeImmutable $v): void { $this->reversedAt = $v; }
    public function getOriginalTransactionId(): ?string { return $this->originalTransactionId; }
    public function getCorrectionReason(): ?string { return $this->correctionReason; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }
    public function setIsCorrection(bool $v): void { $this->isCorrection = $v; }
    public function setCorrectionType(?string $v): void { $this->correctionType = $v; }
    public function setOriginalTransactionId(?string $v): void { $this->originalTransactionId = $v; }
    public function setCorrectionReason(?string $v): void { $this->correctionReason = $v; }

    public function addLedgerEntry(LedgerEntry $entry): void
    {
        $this->ledgerEntries[] = $entry;
    }

    public function post(string $createdBy): void
    {
        if (!in_array($this->status, ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException('Bút toán không thể ghi sổ từ trạng thái hiện tại: ' . $this->status . '.');
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
            throw new \InvalidArgumentException('Tổng Nợ và tổng Có phải cân bằng. Vui lòng kiểm tra lại.');
        }

        $this->status = 'posted';
        $this->createdBy = $createdBy;
    }

    public function reverse(string $reversedBy): void
    {
        if ($this->status !== 'posted') {
            throw new \InvalidArgumentException('Chỉ có thể hoàn nhập bút toán đã ghi sổ.');
        }
        $this->status = 'reversed';
        $this->reversedBy = $reversedBy;
        $this->reversedAt = new \DateTimeImmutable();
    }
}
