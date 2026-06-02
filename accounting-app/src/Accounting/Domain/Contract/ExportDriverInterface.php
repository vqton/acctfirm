<?php
namespace Accounting\Domain\Contract;

use Accounting\Domain\Model\ExportResult;

// Interface cho driver xuất file — mỗi định dạng (CSV, XLS, PDF) implement interface này
// Được ExportService sử dụng theo strategy pattern — dễ dàng thêm driver mới
interface ExportDriverInterface
{
    // Kiểm tra driver có hỗ trợ định dạng không — dựa trên format string (csv, xls, pdf)
    public function supports(string $format): bool;

    // Thực hiện xuất dữ liệu — title là tiêu đề báo cáo, headers là tên cột, rows là dữ liệu
    // options chứa tham số bổ sung (orientation, page_size, v.v.)
    // Trả về ExportResult chứa nội dung file đã render
    public function export(string $title, array $headers, array $rows, array $options = []): ExportResult;
}
