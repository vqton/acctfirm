<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SupplierPerformance;

/**
 * Giao diện repository cho đánh giá hiệu suất nhà cung cấp.
 *
 * Cung cấp các phương thức truy xuất và thao tác với dữ liệu đánh giá
 * chất lượng và hiệu suất của nhà cung cấp.
 */
interface SupplierPerformanceRepositoryInterface
{
    /**
     * Tìm đánh giá theo ID.
     *
     * @param string $id ID của đánh giá
     * @return SupplierPerformance|null Đối tượng SupplierPerformance nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?SupplierPerformance;

    /**
     * Tìm đánh giá theo nhà cung cấp.
     *
     * @param string $supplierId ID của nhà cung cấp
     * @return SupplierPerformance[] Danh sách đánh giá
     */
    public function findBySupplier(string $supplierId): array;

    /**
     * Lấy tất cả đánh giá.
     *
     * @return SupplierPerformance[] Danh sách tất cả đánh giá
     */
    public function findAll(): array;

    /**
     * Lưu đánh giá (thêm mới hoặc cập nhật).
     *
     * @param SupplierPerformance $performance Đối tượng SupplierPerformance cần lưu
     * @return void
     */
    public function save(SupplierPerformance $performance): void;
}
