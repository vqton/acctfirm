<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class SupplierPerformance
{
    private ?string $id;
    private ?string $supplierId;
    private ?string $period;
    private ?float $onTimeRate;
    private ?float $qualityRejectRate;
    private ?float $priceCompetitiveness;
    private ?float $overallRating;
    private ?string $createdAt;

    public function __construct(
        ?string $id = null,
        ?string $supplierId = null,
        ?string $period = null,
        ?float $onTimeRate = null,
        ?float $qualityRejectRate = null,
        ?float $priceCompetitiveness = null,
        ?float $overallRating = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->supplierId = $supplierId;
        $this->period = $period;
        $this->onTimeRate = $onTimeRate;
        $this->qualityRejectRate = $qualityRejectRate;
        $this->priceCompetitiveness = $priceCompetitiveness;
        $this->overallRating = $overallRating;
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

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function setSupplierId(?string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): void
    {
        $this->period = $period;
    }

    public function getOnTimeRate(): ?float
    {
        return $this->onTimeRate;
    }

    public function setOnTimeRate(?float $onTimeRate): void
    {
        $this->onTimeRate = $onTimeRate;
    }

    public function getQualityRejectRate(): ?float
    {
        return $this->qualityRejectRate;
    }

    public function setQualityRejectRate(?float $qualityRejectRate): void
    {
        $this->qualityRejectRate = $qualityRejectRate;
    }

    public function getPriceCompetitiveness(): ?float
    {
        return $this->priceCompetitiveness;
    }

    public function setPriceCompetitiveness(?float $priceCompetitiveness): void
    {
        $this->priceCompetitiveness = $priceCompetitiveness;
    }

    public function getOverallRating(): ?float
    {
        return $this->overallRating;
    }

    public function setOverallRating(?float $overallRating): void
    {
        $this->overallRating = $overallRating;
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
            'supplier_id' => $this->supplierId,
            'period' => $this->period,
            'on_time_rate' => $this->onTimeRate,
            'quality_reject_rate' => $this->qualityRejectRate,
            'price_competitiveness' => $this->priceCompetitiveness,
            'overall_rating' => $this->overallRating,
            'created_at' => $this->createdAt,
        ];
    }
}
