<?php
namespace Accounting\Domain\Contract;

/**
 * Giao diện chuẩn cho tất cả báo cáo sổ chi tiết (Sổ cái, Sổ quỹ, Sổ kho, Sổ công nợ).
 *
 * Nghiệp vụ: Mỗi loại sổ chi tiết implements interface này để đảm bảo:
 *   - Cấu trúc dữ liệu đồng nhất giữa các module
 *   - Controller không cần biết chi tiết từng loại sổ
 *   - Export (CSV/HTML) dùng chung một format
 *
 * getReportType(): Trả về mã loại báo cáo (general_ledger, cash_book, bank_book,
 *   inventory_ledger, ar_ledger, ap_ledger)
 *
 * getParameters(): Trả về danh sách params mà report này hỗ trợ
 *   (account_code, from_date, to_date, customer_id, supplier_id, item_id, ...)
 *
 * getData(): Thực thi báo cáo — nhận params, trả về dữ liệu chuẩn hóa
 */
interface SubLedgerReportInterface
{
    /**
     * Lấy mã loại báo cáo sổ chi tiết.
     *
     * @return string Mã loại báo cáo (general_ledger, cash_book, bank_book, inventory_ledger, ar_ledger, ap_ledger)
     */
    public function getReportType(): string;

    /**
     * Lấy danh sách tham số mà báo cáo này hỗ trợ.
     *
     * @return array Mảng các tham số (account_code, from_date, to_date, customer_id, supplier_id, item_id, ...)
     */
    public function getParameters(): array;

    /**
     * Thực thi báo cáo và trả về dữ liệu chuẩn hóa.
     *
     * @param array $params Mảng các tham số truy vấn (account_code, from_date, to_date, ...)
     * @return array Dữ liệu báo cáo đã chuẩn hóa
     */
    public function getData(array $params): array;
}
