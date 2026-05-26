<?php
// Quản lý dữ liệu: danh mục khách hàng
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Customer;
use Accounting\Domain\Repository\CustomerRepositoryInterface;
use PDO;

class PDOCustomerRepository implements CustomerRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Customer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Customer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM customers ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Customer $customer): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (id, code, name, tax_code, phone, email, address, contact_person, payment_terms, credit_limit, balance, notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), tax_code=VALUES(tax_code),
             phone=VALUES(phone), email=VALUES(email), address=VALUES(address),
             contact_person=VALUES(contact_person), payment_terms=VALUES(payment_terms),
             credit_limit=VALUES(credit_limit), balance=VALUES(balance), notes=VALUES(notes), status=VALUES(status)'
        );
        $stmt->execute([
            $customer->getId(), $customer->getCode(), $customer->getName(), $customer->getTaxCode(),
            $customer->getPhone(), $customer->getEmail(), $customer->getAddress(),
            $customer->getContactPerson(), $customer->getPaymentTerms(),
            $customer->getCreditLimit(), $customer->getBalance(), $customer->getNotes(),
            $customer->isStatus() ? 1 : 0, $customer->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Customer
    {
        $c = new Customer(
            $row['id'], $row['code'], $row['name'], $row['tax_code'],
            $row['phone'], $row['email'], $row['address'],
            $row['contact_person'], $row['payment_terms'],
            (float)$row['credit_limit'], $row['notes']
        );
        $c->setBalance((float)$row['balance']);
        $c->setStatus((bool)$row['status']);
        return $c;
    }
}