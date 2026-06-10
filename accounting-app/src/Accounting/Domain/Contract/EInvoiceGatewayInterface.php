<?php
namespace Accounting\Domain\Contract;

/**
 * CỔNG HÓA ĐƠN ĐIỆN TỬ — E-Invoice Gateway Interface.
 *
 * Nghiệp vụ: Interface cho kết nối với các nhà cung cấp T-VAN (VNPT, Viettel, MISA...)
 * để phát hành hóa đơn điện tử theo quy định tại Thông tư 32/2025/TT-BTC
 * (thay thế TT 78/2021 từ 01/06/2025).
 *
 * Mỗi implementation kết nối với một T-VAN provider cụ thể.
 * Hệ thống hỗ trợ multi-provider để dự phòng khi một provider gặp sự cố.
 *
 * NGUỒN DỮ LIỆU:
 *   - Hóa đơn được tạo từ giao dịch bán hàng (AR) hoặc nghiệp vụ phát sinh
 *   - Dữ liệu khách hàng đồng bộ từ Customer repository
 *   - Chứng thư số từ USB Token/HSM
 *
 * LUỒNG XỬ LÝ:
 *   syncCustomer → publish → (adjust|replace|cancel)
 *   Kết quả: invToken + CQT code → lưu vào e_invoices table + audit trail
 */
interface EInvoiceGatewayInterface
{
    // ==================== VÒNG ĐỜI HÓA ĐƠN ====================

    /**
     * Phát hành hóa đơn điện tử mới.
     *
     * @param string $signedXml Nội dung XML hóa đơn đã ký số
     * @param string $pattern   Mẫu hóa đơn (ký hiệu mẫu số)
     * @param string $serial    Ký hiệu hóa đơn (seri)
     * @return PublishResult   Kết quả phát hành (invToken, cqtCode, signedXml)
     * @throws \RuntimeException Nếu T-VAN không phản hồi
     *
     * RỦI RO: Nếu phát hành thất bại sau khi đã lưu XML, cần retry queue
     */
    public function publish(string $signedXml, string $pattern, string $serial): PublishResult;

    /**
     * Điều chỉnh hóa đơn (thay đổi thông tin, giữ nguyên số hóa đơn).
     * Sử dụng khi sai sót nhỏ (tên hàng, đơn giá).
     *
     * @param string $fkey      Mã tra cứu hóa đơn (fkey) cần điều chỉnh
     * @param string $signedXml Nội dung XML điều chỉnh đã ký số
     * @return AdjustResult     Kết quả điều chỉnh
     */
    public function adjust(string $fkey, string $signedXml): AdjustResult;

    /**
     * Thay thế hóa đơn (hủy hóa đơn cũ + tạo hóa đơn mới).
     * Sử dụng khi sai sót lớn (sai MST, sai số tiền).
     *
     * @param string $fkey      Mã tra cứu hóa đơn (fkey) cần thay thế
     * @param string $signedXml Nội dung XML hóa đơn thay thế đã ký số
     * @return ReplaceResult    Kết quả thay thế (newFkey, newInvToken)
     */
    public function replace(string $fkey, string $signedXml): ReplaceResult;

    /**
     * Hủy hóa đơn điện tử (không thay thế, không điều chỉnh).
     *
     * @param string $fkey    Mã tra cứu hóa đơn (fkey) cần hủy
     * @param string $reason  Lý do hủy hóa đơn
     * @return CancelResult   Kết quả hủy hóa đơn
     */
    public function cancel(string $fkey, string $reason): CancelResult;

    /**
     * Cập nhật trạng thái thanh toán hóa đơn trên hệ thống T-VAN.
     *
     * @param string $fkey Mã tra cứu hóa đơn (fkey) cần cập nhật
     * @return bool        true nếu cập nhật thành công, false nếu thất bại
     */
    public function confirmPayment(string $fkey): bool;

    // ==================== TRUY VẤN ====================

    /**
     * Lấy thông tin chi tiết hóa đơn điện tử.
     *
     * @param string $fkey       Mã tra cứu hóa đơn (fkey)
     * @return InvoiceDetail     Đối tượng chứa thông tin chi tiết hóa đơn
     */
    public function getInvoice(string $fkey): InvoiceDetail;

    /**
     * Tải xuống file XML hóa đơn điện tử.
     *
     * @param string $fkey  Mã tra cứu hóa đơn (fkey)
     * @return string       Nội dung file XML hóa đơn
     */
    public function downloadXml(string $fkey): string;

    /**
     * Tải xuống file PDF hóa đơn điện tử (có mã QR code).
     *
     * @param string $fkey  Mã tra cứu hóa đơn (fkey)
     * @return string       Nội dung file PDF hóa đơn
     */
    public function downloadPdf(string $fkey): string;

    // ==================== DỮ LIỆU CHỦ ====================

    /**
     * Đồng bộ thông tin khách hàng lên hệ thống T-VAN.
     * PHẢI gọi trước khi phát hành hóa đơn cho khách hàng mới.
     *
     * @param string $cusCode  Mã khách hàng
     * @param string $name     Tên khách hàng
     * @param string $taxCode  Mã số thuế khách hàng
     * @param string $address  Địa chỉ khách hàng
     * @param string $email    Email khách hàng
     * @return bool            true nếu đồng bộ thành công, false nếu thất bại
     */
    public function syncCustomer(string $cusCode, string $name, string $taxCode, string $address, string $email): bool;

    /**
     * Gán chứng thư số cho khách hàng (trường hợp khách hàng tự ký số).
     *
     * @param string $cusCode    Mã khách hàng
     * @param string $certSerial Serial number của chứng thư số
     * @return bool              true nếu gán thành công, false nếu thất bại
     */
    public function assignCertificate(string $cusCode, string $certSerial): bool;

    // ==================== BÁO CÁO ====================

    /**
     * Báo cáo tình hình sử dụng hóa đơn điện tử (theo quý).
     *
     * @param int $year    Năm báo cáo
     * @param int $quarter Quý báo cáo (1–4)
     * @return array       Dữ liệu báo cáo tình hình sử dụng hóa đơn
     */
    public function reportUsage(int $year, int $quarter): array;
}

/**
 * DTO: Kết quả phát hành hóa đơn điện tử.
 *
 * Chứa thông tin trả về từ T-VAN sau khi phát hành thành công hoặc thất bại.
 */
class PublishResult
{
    /**
     * @param string $fkey      Mã tra cứu hóa đơn duy nhất
     * @param string $invToken  Mã token hóa đơn từ CQT
     * @param string $cqtCode   Mã cơ quan thuế
     * @param string $signedXml Nội dung XML đã ký số
     * @param bool   $success   true nếu phát hành thành công
     * @param string $error     Thông báo lỗi nếu thất bại
     */
    public function __construct(
        public readonly string $fkey,
        public readonly string $invToken,
        public readonly string $cqtCode,
        public readonly string $signedXml,
        public readonly bool   $success,
        public readonly string $error = '',
    ) {}

    /**
     * Tạo đối tượng PublishResult từ phản hồi JSON từ T-VAN.
     *
     * @param string $response Chuỗi JSON phản hồi từ T-VAN
     * @return self            Đối tượng PublishResult tương ứng
     */
    public static function fromResponse(string $response): self
    {
        $data = json_decode($response, true);
        if (!$data || !($data['success'] ?? false)) {
            return new self('', '', '', '', false, $data['error'] ?? 'Unknown error');
        }
        return new self(
            $data['fkey'] ?? uniqid('inv_'),
            $data['invToken'] ?? '',
            $data['cqtCode'] ?? '',
            $data['signedXml'] ?? '',
            true,
        );
    }
}

/**
 * DTO: Kết quả điều chỉnh hóa đơn điện tử.
 */
class AdjustResult
{
    /**
     * @param bool   $success true nếu điều chỉnh thành công
     * @param string $newFkey Mã tra cứu mới sau điều chỉnh (nếu có)
     * @param string $error   Thông báo lỗi nếu thất bại
     */
    public function __construct(
        public readonly bool   $success,
        public readonly string $newFkey = '',
        public readonly string $error = '',
    ) {}
}

/**
 * DTO: Kết quả thay thế hóa đơn điện tử.
 */
class ReplaceResult
{
    /**
     * @param bool   $success     true nếu thay thế thành công
     * @param string $newFkey     Mã tra cứu hóa đơn mới
     * @param string $newInvToken Mã token hóa đơn mới từ CQT
     * @param string $error       Thông báo lỗi nếu thất bại
     */
    public function __construct(
        public readonly bool   $success,
        public readonly string $newFkey = '',
        public readonly string $newInvToken = '',
        public readonly string $error = '',
    ) {}
}

/**
 * DTO: Kết quả hủy hóa đơn điện tử.
 */
class CancelResult
{
    /**
     * @param bool   $success true nếu hủy thành công
     * @param string $error   Thông báo lỗi nếu thất bại
     */
    public function __construct(
        public readonly bool   $success,
        public readonly string $error = '',
    ) {}
}

/**
 * DTO: Thông tin chi tiết hóa đơn điện tử.
 */
class InvoiceDetail
{
    /**
     * @param string $fkey     Mã tra cứu hóa đơn
     * @param string $invToken Mã token hóa đơn từ CQT
     * @param string $status   Trạng thái hóa đơn
     * @param string $cqtCode  Mã cơ quan thuế
     * @param string $pdfUrl   URL tải file PDF hóa đơn
     * @param string $xmlUrl   URL tải file XML hóa đơn
     */
    public function __construct(
        public readonly string $fkey,
        public readonly string $invToken,
        public readonly string $status,
        public readonly string $cqtCode,
        public readonly string $pdfUrl,
        public readonly string $xmlUrl,
    ) {}
}
