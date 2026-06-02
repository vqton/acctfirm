<?php
namespace Accounting\Domain\Contract;

//
// CỔNG HÓA ĐƠN ĐIỆN TỬ - E-Invoice Gateway Interface
//
// Nghiệp vụ: Interface cho kết nối với các nhà cung cấp T-VAN (VNPT, Viettel, MISA...)
// để phát hành hóa đơn điện tử theo quy định tại Thông tư 32/2025/TT-BTC
// (thay thế TT 78/2021 từ 01/06/2025).
//
// Mỗi implementation kết nối với một T-VAN provider cụ thể.
// Hệ thống hỗ trợ multi-provider để dự phòng khi một provider gặp sự cố.
//
// NGUỒN DỮ LIỆU:
//   - Hóa đơn được tạo từ giao dịch bán hàng (AR) hoặc nghiệp vụ phát sinh
//   - Dữ liệu khách hàng đồng bộ từ Customer repository
//   - Chứng thư số từ USB Token/HSM
//
// LUỒNG XỬ LÝ:
//   syncCustomer → publish → (adjust|replace|cancel)
//   Kết quả: invToken + CQT code → lưu vào e_invoices table + audit trail
//
interface EInvoiceGatewayInterface
{
    // === VÒNG ĐỜI HÓA ĐƠN ===

    // Phát hành hóa đơn mới
    // Input: invoiceData (đã ký số XML)
    // Output: PublishResult (invToken, cqtCode, signedXml)
    // LỗI: RuntimeException nếu T-VAN không phản hồi
    // RỦI RO: Nếu phát hành thất bại sau khi đã lưu XML, cần retry queue
    public function publish(string $signedXml, string $pattern, string $serial): PublishResult;

    // Điều chỉnh hóa đơn (thay đổi thông tin, giữ nguyên số)
    // Sử dụng khi sai sót nhỏ (tên hàng, đơn giá)
    public function adjust(string $fkey, string $signedXml): AdjustResult;

    // Thay thế hóa đơn (hủy + tạo mới)
    // Sử dụng khi sai sót lớn (sai MST, sai số tiền)
    public function replace(string $fkey, string $signedXml): ReplaceResult;

    // Hủy hóa đơn (không thay thế, không điều chỉnh)
    // Kèm lý do hủy
    public function cancel(string $fkey, string $reason): CancelResult;

    // Cập nhật trạng thái thanh toán hóa đơn
    public function confirmPayment(string $fkey): bool;

    // === TRUY VẤN ===

    // Lấy thông tin chi tiết hóa đơn
    public function getInvoice(string $fkey): InvoiceDetail;

    // Tải XML hóa đơn
    public function downloadXml(string $fkey): string;

    // Tải PDF hóa đơn (có QR code)
    public function downloadPdf(string $fkey): string;

    // === DỮ LIỆU CHỦ ===

    // Đồng bộ thông tin khách hàng lên hệ thống T-VAN
    // PHẢI gọi trước khi phát hành hóa đơn cho KH mới
    public function syncCustomer(string $cusCode, string $name, string $taxCode, string $address, string $email): bool;

    // Gán chứng thư số cho khách hàng (nếu KH tự ký)
    public function assignCertificate(string $cusCode, string $certSerial): bool;

    // === BÁO CÁO ===

    // Báo cáo tình hình sử dụng hóa đơn (theo quý)
    public function reportUsage(int $year, int $quarter): array;
}

//
// Data Transfer Objects
//
class PublishResult
{
    public function __construct(
        public readonly string $fkey,
        public readonly string $invToken,
        public readonly string $cqtCode,
        public readonly string $signedXml,
        public readonly bool   $success,
        public readonly string $error = '',
    ) {}

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

class AdjustResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $newFkey = '',
        public readonly string $error = '',
    ) {}
}

class ReplaceResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $newFkey = '',
        public readonly string $newInvToken = '',
        public readonly string $error = '',
    ) {}
}

class CancelResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $error = '',
    ) {}
}

class InvoiceDetail
{
    public function __construct(
        public readonly string $fkey,
        public readonly string $invToken,
        public readonly string $status,
        public readonly string $cqtCode,
        public readonly string $pdfUrl,
        public readonly string $xmlUrl,
    ) {}
}
