<?php
namespace Accounting\Domain\Contract;

use Accounting\Domain\Model\ExportResult;

/**
 * Interface cho driver xuất file — mỗi định dạng (CSV, XLS, PDF) implement interface này.
 *
 * Được ExportService sử dụng theo strategy pattern — dễ dàng thêm driver mới
 * mà không cần sửa đổi code hiện có.
 */
interface ExportDriverInterface
{
    /**
     * Kiểm tra driver có hỗ trợ định dạng yêu cầu không.
     *
     * @param string $format Định dạng file cần kiểm tra (csv, xls, pdf)
     * @return bool true nếu driver hỗ trợ định dạng này, false nếu không
     */
    public function supports(string $format): bool;

    /**
     * Thực hiện xuất dữ liệu ra file theo định dạng của driver.
     *
     * @param string $title   Tiêu đề báo cáo
     * @param array  $headers Mảng tên các cột dữ liệu
     * @param array  $rows    Mảng các dòng dữ liệu
     * @param array  $options Tham số bổ sung (orientation, page_size, ...)
     * @return ExportResult Đối tượng chứa nội dung file đã render
     */
    public function export(string $title, array $headers, array $rows, array $options = []): ExportResult;
}
