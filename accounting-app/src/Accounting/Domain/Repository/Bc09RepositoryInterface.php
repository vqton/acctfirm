<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Bc09Config;
use Accounting\Domain\Model\Bc09Data;

/**
 * Giao diện repository cho báo cáo BC09 (Thuyết minh báo cáo tài chính).
 *
 * Cung cấp các phương thức truy xuất cấu hình chỉ tiêu BC09
 * và dữ liệu thuyết minh cho từng kỳ kế toán.
 */
interface Bc09RepositoryInterface
{
    /**
     * Lấy tất cả cấu hình chỉ tiêu BC09.
     *
     * @return array Danh sách cấu hình chỉ tiêu BC09
     */
    public function getConfig(): array;

    /**
     * Lấy cấu hình theo section (mục).
     *
     * @param string $sectionCode Mã section
     * @return array Danh sách cấu hình chỉ tiêu thuộc section
     */
    public function getSection(string $sectionCode): array;

    /**
     * Lấy cấu hình theo mã chỉ tiêu.
     *
     * @param string $indicatorCode Mã chỉ tiêu BC09
     * @return Bc09Config|null Đối tượng Bc09Config nếu tìm thấy, null nếu không
     */
    public function getConfigByIndicator(string $indicatorCode): ?Bc09Config;

    /**
     * Lấy dữ liệu BC09 cho một kỳ.
     *
     * @param int $periodId ID của kỳ kế toán
     * @return array Danh sách dữ liệu BC09 của kỳ
     */
    public function getData(int $periodId): array;

    /**
     * Lưu dữ liệu một chỉ tiêu cho một kỳ.
     *
     * @param int $periodId ID của kỳ kế toán
     * @param string $sectionCode Mã section
     * @param string $indicatorCode Mã chỉ tiêu
     * @param float $yearStart Số dư đầu năm
     * @param float $yearEnd Số dư cuối năm
     * @param string|null $noteText Ghi chú thuyết minh
     * @param bool $isManual Có phải nhập tay hay không
     * @param int|null $createdBy ID người tạo
     * @return void
     */
    public function saveData(int $periodId, string $sectionCode, string $indicatorCode, float $yearStart, float $yearEnd, ?string $noteText, bool $isManual, ?int $createdBy): void;

    /**
     * Xóa dữ liệu BC09 cho một kỳ (dùng khi generate lại).
     *
     * @param int $periodId ID của kỳ kế toán
     * @return void
     */
    public function deleteDataForPeriod(int $periodId): void;

    /**
     * Lấy số liệu prior period (kỳ trước).
     *
     * @param int $periodId ID của kỳ kế toán hiện tại
     * @param string $indicatorCode Mã chỉ tiêu
     * @return float|null Số liệu kỳ trước, null nếu không có
     */
    public function getPriorPeriodData(int $periodId, string $indicatorCode): ?float;
}
