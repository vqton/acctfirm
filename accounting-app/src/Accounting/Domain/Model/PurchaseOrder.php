<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseOrder
{
    private ?string $id;
    private ?string $poNumber;
    private string $status;
    private ?string $supplierId;
    private ?string $contractId;
    private ?string $buyerId;
    private ?string $paymentTerms;
    private ?string $deliveryTerms;
    private ?float $totalAmount;
    private ?float $taxAmount;
    private ?string $expectedDelivery;
    private ?string $note;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?string $id = null,
        ?string $poNumber = null,
        string $status = 'draft',
        ?string $supplierId = null,
        ?string $contractId = null,
        ?string $buyerId = null,
        ?string $paymentTerms = null,
        ?string $deliveryTerms = null,
        ?float $totalAmount = null,
        ?float $taxAmount = null,
        ?string $expectedDelivery = null,
        ?string $note = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->poNumber = $poNumber;
        $this->status = $status;
        $this->supplierId = $supplierId;
        $this->contractId = $contractId;
        $this->buyerId = $buyerId;
        $this->paymentTerms = $paymentTerms;
        $this->deliveryTerms = $deliveryTerms;
        $this->totalAmount = $totalAmount;
        $this->taxAmount = $taxAmount;
        $this->expectedDelivery = $expectedDelivery;
        $this->note = $note;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getPoNumber(): ?string
    {
        return $this->poNumber;
    }

    public function setPoNumber(?string $poNumber): void
    {
        $this->poNumber = $poNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getContractId(): ?string
    {
        return $this->contractId;
    }

    public function setContractId(?string $contractId): void
    {
        $this->contractId = $contractId;
    }

    public function getBuyerId(): ?string
    {
        return $this->buyerId;
    }

    public function setBuyerId(?string $buyerId): void
    {
        $this->buyerId = $buyerId;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): void
    {
        $this->paymentTerms = $paymentTerms;
    }

    public function getDeliveryTerms(): ?string
    {
        return $this->deliveryTerms;
    }

    public function setDeliveryTerms(?string $deliveryTerms): void
    {
        $this->deliveryTerms = $deliveryTerms;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?float $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?float $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    public function getExpectedDelivery(): ?string
    {
        return $this->expectedDelivery;
    }

    public function setExpectedDelivery(?string $expectedDelivery): void
    {
        $this->expectedDelivery = $expectedDelivery;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->poNumber,
            'status' => $this->status,
            'supplier_id' => $this->supplierId,
            'contract_id' => $this->contractId,
            'buyer_id' => $this->buyerId,
            'payment_terms' => $this->paymentTerms,
            'delivery_terms' => $this->deliveryTerms,
            'total_amount' => $this->totalAmount,
            'tax_amount' => $this->taxAmount,
            'expected_delivery' => $this->expectedDelivery,
            'note' => $this->note,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
