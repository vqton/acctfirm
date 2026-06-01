<?php
namespace Accounting\Domain\Model;

class Settlement
{
    private ?int $id;
    private int $queueId;
    private ?int $approvalId;
    private float $originalBalance;
    private float $settlementAmount;
    private float $discountAmount;
    private float $discountPercent;
    private string $paymentType;
    private int $installmentCount;
    private ?string $installmentFrequency;
    private string $agreementDate;
    private string $dueByDate;
    private string $status;
    private float $amountPaid;
    private ?string $lastPaymentDate;
    private string $createdBy;

    public function __construct(
        int $queueId,
        float $originalBalance,
        float $settlementAmount,
        float $discountAmount,
        float $discountPercent,
        string $agreementDate,
        string $dueByDate,
        string $createdBy,
        ?int $approvalId = null,
        string $paymentType = 'lump_sum',
        int $installmentCount = 1,
        ?string $installmentFrequency = null,
        string $status = 'active',
        float $amountPaid = 0,
        ?int $id = null
    ) {
        $this->queueId = $queueId;
        $this->originalBalance = $originalBalance;
        $this->settlementAmount = $settlementAmount;
        $this->discountAmount = $discountAmount;
        $this->discountPercent = $discountPercent;
        $this->agreementDate = $agreementDate;
        $this->dueByDate = $dueByDate;
        $this->createdBy = $createdBy;
        $this->approvalId = $approvalId;
        $this->paymentType = $paymentType;
        $this->installmentCount = $installmentCount;
        $this->installmentFrequency = $installmentFrequency;
        $this->status = $status;
        $this->amountPaid = $amountPaid;
        $this->id = $id;
    }

    public function getId(): ?int { return $this->id; }
    public function getQueueId(): int { return $this->queueId; }
    public function getApprovalId(): ?int { return $this->approvalId; }
    public function getOriginalBalance(): float { return $this->originalBalance; }
    public function getSettlementAmount(): float { return $this->settlementAmount; }
    public function getDiscountAmount(): float { return $this->discountAmount; }
    public function getDiscountPercent(): float { return $this->discountPercent; }
    public function getPaymentType(): string { return $this->paymentType; }
    public function getInstallmentCount(): int { return $this->installmentCount; }
    public function getInstallmentFrequency(): ?string { return $this->installmentFrequency; }
    public function getAgreementDate(): string { return $this->agreementDate; }
    public function getDueByDate(): string { return $this->dueByDate; }
    public function getStatus(): string { return $this->status; }
    public function getAmountPaid(): float { return $this->amountPaid; }
    public function getLastPaymentDate(): ?string { return $this->lastPaymentDate; }
    public function getCreatedBy(): string { return $this->createdBy; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setAmountPaid(float $v): void { $this->amountPaid = $v; }
    public function setLastPaymentDate(?string $v): void { $this->lastPaymentDate = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue_id' => $this->queueId,
            'approval_id' => $this->approvalId,
            'original_balance' => $this->originalBalance,
            'settlement_amount' => $this->settlementAmount,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'payment_type' => $this->paymentType,
            'installment_count' => $this->installmentCount,
            'installment_frequency' => $this->installmentFrequency,
            'agreement_date' => $this->agreementDate,
            'due_by_date' => $this->dueByDate,
            'status' => $this->status,
            'amount_paid' => $this->amountPaid,
            'last_payment_date' => $this->lastPaymentDate,
            'created_by' => $this->createdBy,
        ];
    }

    public static function fromRow(array $row): self
    {
        $s = new self(
            (int)$row['queue_id'],
            (float)$row['original_balance'],
            (float)$row['settlement_amount'],
            (float)$row['discount_amount'],
            (float)$row['discount_percent'],
            $row['agreement_date'],
            $row['due_by_date'],
            $row['created_by'],
            isset($row['approval_id']) ? (int)$row['approval_id'] : null,
            $row['payment_type'] ?? 'lump_sum',
            (int)($row['installment_count'] ?? 1),
            $row['installment_frequency'] ?? null,
            $row['status'] ?? 'active',
            (float)($row['amount_paid'] ?? 0),
            isset($row['id']) ? (int)$row['id'] : null
        );
        $s->lastPaymentDate = $row['last_payment_date'] ?? null;
        return $s;
    }
}
