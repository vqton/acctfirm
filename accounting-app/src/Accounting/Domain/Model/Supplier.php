<?php
namespace Accounting\Domain\Model;

class Supplier
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $taxCode;
    private ?string $phone;
    private ?string $email;
    private ?string $address;
    private ?string $contactPerson;
    private ?string $paymentTerms;
    private float $creditLimit;
    private float $balance;
    private ?string $notes;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, ?string $taxCode = null,
        ?string $phone = null, ?string $email = null, ?string $address = null,
        ?string $contactPerson = null, ?string $paymentTerms = null,
        float $creditLimit = 0, ?string $notes = null
    ) {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->taxCode = $taxCode; $this->phone = $phone; $this->email = $email;
        $this->address = $address; $this->contactPerson = $contactPerson;
        $this->paymentTerms = $paymentTerms; $this->creditLimit = $creditLimit;
        $this->balance = 0; $this->notes = $notes; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getTaxCode(): ?string { return $this->taxCode; }
    public function getPhone(): ?string { return $this->phone; }
    public function getEmail(): ?string { return $this->email; }
    public function getAddress(): ?string { return $this->address; }
    public function getContactPerson(): ?string { return $this->contactPerson; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function getCreditLimit(): float { return $this->creditLimit; }
    public function getBalance(): float { return $this->balance; }
    public function getNotes(): ?string { return $this->notes; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setTaxCode(?string $v): void { $this->taxCode = $v; }
    public function setPhone(?string $v): void { $this->phone = $v; }
    public function setEmail(?string $v): void { $this->email = $v; }
    public function setAddress(?string $v): void { $this->address = $v; }
    public function setContactPerson(?string $v): void { $this->contactPerson = $v; }
    public function setPaymentTerms(?string $v): void { $this->paymentTerms = $v; }
    public function setCreditLimit(float $v): void { $this->creditLimit = $v; }
    public function setBalance(float $v): void { $this->balance = $v; }
    public function setNotes(?string $v): void { $this->notes = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'tax_code' => $this->taxCode, 'phone' => $this->phone,
            'email' => $this->email, 'address' => $this->address,
            'contact_person' => $this->contactPerson,
            'payment_terms' => $this->paymentTerms,
            'credit_limit' => $this->creditLimit, 'balance' => $this->balance,
            'notes' => $this->notes, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}