<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseInvoiceMatch
{
    private ?string $id;
    private ?string $poId;
    private ?string $grId;
    private ?string $supplierInvoiceNo;
    private ?string $invoiceDate;
    private ?float $invoiceAmount;
    private ?float $vatAmount;
    private string $matchStatus;
    private ?string $matchedBy;
    private ?string $matchedAt;
    private ?string $note;
    private ?string $createdAt;

    public function __construct(
        ?string $id = null,
        ?string $poId = null,
        ?string $grId = null,
        ?string $supplierInvoiceNo = null,
        ?string $invoiceDate = null,
        ?float $invoiceAmount = null,
        ?float $vatAmount = null,
        string $matchStatus = 'draft',
        ?string $matchedBy = null,
        ?string $matchedAt = null,
        ?string $note = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->poId = $poId;
        $this->grId = $grId;
        $this->supplierInvoiceNo = $supplierInvoiceNo;
        $this->invoiceDate = $invoiceDate;
        $this->invoiceAmount = $invoiceAmount;
        $this->vatAmount = $vatAmount;
        $this->matchStatus = $matchStatus;
        $this->matchedBy = $matchedBy;
        $this->matchedAt = $matchedAt;
        $this->note = $note;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getPoId(): ?string
    {
        return $this->poId;
    }

    public function setPoId(?string $poId): void
    {
        $this->poId = $poId;
    }

    public function getGrId(): ?string
    {
        return $this->grId;
    }

    public function setGrId(?string $grId): void
    {
        $this->grId = $grId;
    }

    public function getSupplierInvoiceNo(): ?string
    {
        return $this->supplierInvoiceNo;
    }

    public function setSupplierInvoiceNo(?string $supplierInvoiceNo): void
    {
        $this->supplierInvoiceNo = $supplierInvoiceNo;
    }

    public function getInvoiceDate(): ?string
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?string $invoiceDate): void
    {
        $this->invoiceDate = $invoiceDate;
    }

    public function getInvoiceAmount(): ?float
    {
        return $this->invoiceAmount;
    }

    public function setInvoiceAmount(?float $invoiceAmount): void
    {
        $this->invoiceAmount = $invoiceAmount;
    }

    public function getVatAmount(): ?float
    {
        return $this->vatAmount;
    }

    public function setVatAmount(?float $vatAmount): void
    {
        $this->vatAmount = $vatAmount;
    }

    public function getMatchStatus(): string
    {
        return $this->matchStatus;
    }

    public function setMatchStatus(string $matchStatus): void
    {
        $this->matchStatus = $matchStatus;
    }

    public function getMatchedBy(): ?string
    {
        return $this->matchedBy;
    }

    public function setMatchedBy(?string $matchedBy): void
    {
        $this->matchedBy = $matchedBy;
    }

    public function getMatchedAt(): ?string
    {
        return $this->matchedAt;
    }

    public function setMatchedAt(?string $matchedAt): void
    {
        $this->matchedAt = $matchedAt;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'po_id' => $this->poId,
            'gr_id' => $this->grId,
            'supplier_invoice_no' => $this->supplierInvoiceNo,
            'invoice_date' => $this->invoiceDate,
            'invoice_amount' => $this->invoiceAmount,
            'vat_amount' => $this->vatAmount,
            'match_status' => $this->matchStatus,
            'matched_by' => $this->matchedBy,
            'matched_at' => $this->matchedAt,
            'note' => $this->note,
            'created_at' => $this->createdAt,
        ];
    }
}
