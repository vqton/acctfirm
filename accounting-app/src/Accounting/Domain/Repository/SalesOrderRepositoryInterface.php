<?php
declare(strict_types=1);
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalesOrder;

interface SalesOrderRepositoryInterface
{
    public function findById(string $id): ?SalesOrder;
    public function findByReference(string $reference): ?SalesOrder;
    public function findByCustomer(int $customerId): array;
    public function findByStatus(string $status): array;
    public function findAll(int $limit = 50, int $offset = 0): array;
    public function save(SalesOrder $order): void;
    public function delete(string $id): void;
    public function countByStatus(string $status): int;
    public function saveLink(string $orderId, string $linkedType, string $linkedId, ?string $linkedRef, float $amount, string $createdBy): void;
    public function getLinks(string $orderId): array;
}
