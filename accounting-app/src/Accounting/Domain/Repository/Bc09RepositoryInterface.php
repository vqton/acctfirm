<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Bc09Config;
use Accounting\Domain\Model\Bc09Data;

interface Bc09RepositoryInterface
{
    // Lấy tất cả cấu hình chỉ tiêu BC09
    public function getConfig(): array;

    // Lấy cấu hình theo section
    public function getSection(string $sectionCode): array;

    // Lấy cấu hình theo mã chỉ tiêu
    public function getConfigByIndicator(string $indicatorCode): ?Bc09Config;

    // Lấy dữ liệu BC09 cho một kỳ
    public function getData(int $periodId): array;

    // Lưu dữ liệu một chỉ tiêu cho một kỳ
    public function saveData(int $periodId, string $sectionCode, string $indicatorCode, float $yearStart, float $yearEnd, ?string $noteText, bool $isManual, ?int $createdBy): void;

    // Xóa dữ liệu BC09 cho một kỳ (dùng khi generate lại)
    public function deleteDataForPeriod(int $periodId): void;

    // Lấy số liệu prior period
    public function getPriorPeriodData(int $periodId, string $indicatorCode): ?float;
}
