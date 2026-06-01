<?php
namespace Accounting\Domain\Contract;

interface InventoryServiceInterface
{
    public function receiveGoods(
        string $itemId, float $qty, float $unitPrice, array $addonCosts,
        string $reference, string $createdBy,
        ?string $batchCode = null, ?string $expiryDate = null
    ): array;

    public function issueGoods(
        string $itemId, float $qty, string $issueType,
        string $reference, string $createdBy
    ): array;

    public function transferGoods(
        string $itemId, float $qty, ?string $fromWarehouseId,
        string $toWarehouseId, string $reference, string $createdBy
    ): array;

    public function recordInTransit(
        string $itemId, float $qty, float $unitPrice, array $addonCosts,
        string $reference, string $createdBy
    ): array;

    public function receiveFromTransit(
        string $transitId, float $qty, string $reference, string $createdBy
    ): array;

    public function consignGoods(
        string $itemId, float $qty, string $consignee,
        string $reference, string $createdBy
    ): array;

    public function sellConsigned(
        string $consignmentId, float $qty, string $reference, string $createdBy
    ): array;

    public function returnConsigned(
        string $consignmentId, float $qty, string $reference, string $createdBy
    ): array;

    public function adjustPhysicalCount(
        string $itemId, float $actualQty, string $reference, string $createdBy
    ): array;

    public function createCountSession(
        array $lines, string $reference, string $notes, string $createdBy
    ): array;

    public function recordImpairment(
        string $itemId, float $amount, string $reference, string $notes, string $createdBy
    ): array;

    public function reverseImpairment(
        string $impairmentId, float $amount, string $reference, string $createdBy
    ): array;

    public function issuePromotional(
        string $itemId, float $qty, string $reference, string $createdBy,
        ?float $deemedSaleValue = null, float $vatRate = 0
    ): array;

    public function issueFromBatch(
        string $itemId, float $qty, string $batchCode, string $issueType,
        string $reference, string $createdBy
    ): array;

    public function getExchangeRate(string $currencyCode): float;

    public function receiveGoodsFC(
        string $itemId, float $qty, float $unitPriceFC, array $addonCosts,
        string $currencyCode, ?float $exchangeRate,
        string $reference, string $createdBy
    ): array;

    public function returnFromCustomer(
        string $itemId, float $qty, string $reference, string $createdBy
    ): array;

    public function returnToSupplier(
        string $itemId, float $qty, string $reference, string $createdBy
    ): array;

    public function writeOffGoods(
        string $itemId, float $qty, string $reason, string $expenseAccount,
        string $reference, string $createdBy, string $notes = ''
    ): array;

    public function closePeriodicInventory(
        string $itemId, float $closingQty, float $closingUnitCost,
        string $reference, string $createdBy
    ): array;

    public function closeInventoryForPeriod(
        int $periodId, string $periodCode, string $startDate, string $endDate, string $closedBy
    ): array;

    public function rollbackInventoryForPeriod(int $periodId, string $rolledBackBy): array;

    public function getAgingReport(?string $itemId = null, ?string $warehouseId = null): array;

    public function getTurnoverRatio(
        string $periodStart, string $periodEnd, ?string $itemId = null
    ): array;

    public function getValuationReport(
        ?string $itemId = null, ?string $warehouseId = null,
        ?string $fromDate = null, ?string $toDate = null
    ): array;
}
