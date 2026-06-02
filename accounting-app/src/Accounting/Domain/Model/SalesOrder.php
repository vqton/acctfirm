<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class SalesOrder
{
    private string $id;
    private string $reference;
    private int $customerId;
    private string $orderDate;
    private ?string $deliveryDate;
    private ?string $paymentTerms;
    private ?string $paymentMethod;
    private string $status;
    private string $currency;
    private float $exchangeRate;
    private float $totalAmount;
    private float $discountAmount;
    private float $taxAmount;
    private float $grandTotal;
    private float $amountPaid;
    private float $amountInvoiced;
    private ?string $notes;
    private bool $isQuotationConverted;
    private ?string $quotationId;
    private string $createdBy;
    private ?string $approvedBy;
    private ?string $cancelledBy;
    private ?string $cancelReason;
    private ?string $cancelledAt;
    private string $createdAt;
    private ?string $updatedAt;
    private array $lines = [];

    private const VALID_STATUSES = [
        'draft', 'confirmed', 'pending_stock', 'partially_shipped', 'shipped',
        'partially_invoiced', 'invoiced', 'partially_paid', 'paid', 'completed', 'cancelled'
    ];

    private const VALID_TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['pending_stock', 'partially_shipped', 'cancelled'],
        'pending_stock' => ['partially_shipped', 'cancelled'],
        'partially_shipped' => ['shipped', 'partially_invoiced', 'cancelled'],
        'shipped' => ['partially_invoiced', 'completed', 'cancelled'],
        'partially_invoiced' => ['invoiced', 'partially_paid', 'cancelled'],
        'invoiced' => ['partially_paid', 'paid', 'completed'],
        'partially_paid' => ['paid', 'completed'],
        'paid' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        string $id, string $reference, int $customerId, string $orderDate,
        ?string $deliveryDate = null, ?string $paymentTerms = null, ?string $paymentMethod = null,
        string $status = 'draft', string $currency = 'VND', float $exchangeRate = 1.0,
        float $totalAmount = 0, float $discountAmount = 0, float $taxAmount = 0,
        float $grandTotal = 0, float $amountPaid = 0, float $amountInvoiced = 0,
        ?string $notes = null, bool $isQuotationConverted = false, ?string $quotationId = null,
        string $createdBy = '', ?string $approvedBy = null, ?string $cancelledBy = null,
        ?string $cancelReason = null, ?string $cancelledAt = null,
        string $createdAt = '', ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->reference = $reference;
        $this->customerId = $customerId;
        $this->orderDate = $orderDate;
        $this->deliveryDate = $deliveryDate;
        $this->paymentTerms = $paymentTerms;
        $this->paymentMethod = $paymentMethod;
        $this->status = $status;
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
        $this->totalAmount = $totalAmount;
        $this->discountAmount = $discountAmount;
        $this->taxAmount = $taxAmount;
        $this->grandTotal = $grandTotal;
        $this->amountPaid = $amountPaid;
        $this->amountInvoiced = $amountInvoiced;
        $this->notes = $notes;
        $this->isQuotationConverted = $isQuotationConverted;
        $this->quotationId = $quotationId;
        $this->createdBy = $createdBy;
        $this->approvedBy = $approvedBy;
        $this->cancelledBy = $cancelledBy;
        $this->cancelReason = $cancelReason;
        $this->cancelledAt = $cancelledAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getOrderDate(): string { return $this->orderDate; }
    public function getDeliveryDate(): ?string { return $this->deliveryDate; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function getStatus(): string { return $this->status; }
    public function getCurrency(): string { return $this->currency; }
    public function getExchangeRate(): float { return $this->exchangeRate; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getDiscountAmount(): float { return $this->discountAmount; }
    public function getTaxAmount(): float { return $this->taxAmount; }
    public function getGrandTotal(): float { return $this->grandTotal; }
    public function getAmountPaid(): float { return $this->amountPaid; }
    public function getAmountInvoiced(): float { return $this->amountInvoiced; }
    public function getNotes(): ?string { return $this->notes; }
    public function getIsQuotationConverted(): bool { return $this->isQuotationConverted; }
    public function getQuotationId(): ?string { return $this->quotationId; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function getApprovedBy(): ?string { return $this->approvedBy; }
    public function getCancelledBy(): ?string { return $this->cancelledBy; }
    public function getCancelReason(): ?string { return $this->cancelReason; }
    public function getCancelledAt(): ?string { return $this->cancelledAt; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function getLines(): array { return $this->lines; }
    public function setLines(array $lines): void { $this->lines = $lines; }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Trạng thái không hợp lệ: $status");
        }
        $this->status = $status;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? [], true);
    }

    public function updateAmounts(): void
    {
        $total = 0;
        $discount = 0;
        $tax = 0;
        foreach ($this->lines as $line) {
            $total += $line->getLineTotal();
            $discount += $line->getDiscountAmount();
            $tax += ($line->getLineTotal() * $line->getTaxRate() / 100);
        }
        $this->totalAmount = $total;
        $this->discountAmount = $discount;
        $this->taxAmount = $tax;
        $this->grandTotal = $total + $tax;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'customer_id' => $this->customerId,
            'order_date' => $this->orderDate,
            'delivery_date' => $this->deliveryDate,
            'payment_terms' => $this->paymentTerms,
            'payment_method' => $this->paymentMethod,
            'status' => $this->status,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchangeRate,
            'total_amount' => $this->totalAmount,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'grand_total' => $this->grandTotal,
            'amount_paid' => $this->amountPaid,
            'amount_invoiced' => $this->amountInvoiced,
            'notes' => $this->notes,
            'is_quotation_converted' => $this->isQuotationConverted,
            'quotation_id' => $this->quotationId,
            'created_by' => $this->createdBy,
            'approved_by' => $this->approvedBy,
            'cancelled_by' => $this->cancelledBy,
            'cancel_reason' => $this->cancelReason,
            'cancelled_at' => $this->cancelledAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'lines' => array_map(fn($l) => $l->toArray(), $this->lines),
        ];
    }
}
