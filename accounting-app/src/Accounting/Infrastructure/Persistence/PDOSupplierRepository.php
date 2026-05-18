<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Supplier;
use Accounting\Domain\Repository\SupplierRepositoryInterface;
use PDO;

class PDOSupplierRepository implements SupplierRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Supplier
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Supplier
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM suppliers ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Supplier $supplier): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers (id, code, name, tax_code, phone, email, address, contact_person, payment_terms, credit_limit, balance, notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), tax_code=VALUES(tax_code),
             phone=VALUES(phone), email=VALUES(email), address=VALUES(address),
             contact_person=VALUES(contact_person), payment_terms=VALUES(payment_terms),
             credit_limit=VALUES(credit_limit), balance=VALUES(balance), notes=VALUES(notes), status=VALUES(status)'
        );
        $stmt->execute([
            $supplier->getId(), $supplier->getCode(), $supplier->getName(), $supplier->getTaxCode(),
            $supplier->getPhone(), $supplier->getEmail(), $supplier->getAddress(),
            $supplier->getContactPerson(), $supplier->getPaymentTerms(),
            $supplier->getCreditLimit(), $supplier->getBalance(), $supplier->getNotes(),
            $supplier->isStatus() ? 1 : 0, $supplier->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Supplier
    {
        $s = new Supplier(
            $row['id'], $row['code'], $row['name'], $row['tax_code'],
            $row['phone'], $row['email'], $row['address'],
            $row['contact_person'], $row['payment_terms'],
            (float)$row['credit_limit'], $row['notes']
        );
        $s->setBalance((float)$row['balance']);
        $s->setStatus((bool)$row['status']);
        return $s;
    }
}