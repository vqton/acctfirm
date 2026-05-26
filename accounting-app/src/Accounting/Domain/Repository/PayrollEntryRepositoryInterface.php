<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PayrollEntry;
use Accounting\Domain\Model\PayrollDetail;

interface PayrollEntryRepositoryInterface
{
    public function findById(string $id): ?PayrollEntry;
    public function findByPeriod(string $periodId): array;
    public function findAll(): array;
    public function save(PayrollEntry $e): void;
    public function delete(string $id): void;

    // Chi tiet bang luong
    public function findDetailsByEntry(string $entryId): array;
    public function findDetailById(string $id): ?PayrollDetail;
    public function saveDetail(PayrollDetail $d): void;
    public function deleteDetail(string $id): void;
    public function deleteDetailsByEntry(string $entryId): void;
}
