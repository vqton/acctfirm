<?php
namespace Accounting\Domain\Service;

class ValuationService
{
    /**
     * Calculate weighted average unit cost from a list of receipt batches.
     *
     * @param array $batches Each item: ['qty' => float, 'unit_cost' => float]
     * @return float Weighted average unit cost
     */
    public function calculateWeightedAverage(array $batches): float
    {
        if (empty($batches)) return 0.0;

        $totalCost = 0.0;
        $totalQty = 0.0;

        foreach ($batches as $b) {
            $totalCost += $b['qty'] * $b['unit_cost'];
            $totalQty += $b['qty'];
        }

        if ($totalQty <= 0) return 0.0;

        return $totalCost / $totalQty;
    }
}