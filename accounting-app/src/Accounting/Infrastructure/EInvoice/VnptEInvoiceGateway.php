<?php
namespace Accounting\Infrastructure\EInvoice;

use Accounting\Domain\Contract\EInvoiceGatewayInterface;
use Accounting\Domain\Contract\PublishResult;
use Accounting\Domain\Contract\AdjustResult;
use Accounting\Domain\Contract\ReplaceResult;
use Accounting\Domain\Contract\CancelResult;
use Accounting\Domain\Contract\InvoiceDetail;

/**
 * CỔNG HÓA ĐƠN ĐIỆN TỬ VNPT (VNPT-Invoice).
 *
 * Kết nối với hệ thống VNPT Invoice qua SOAP WebService.
 * Tuân thủ Thông tư 32/2025/TT-BTC (thay TT 78/2021).
 *
 * API functions: ImportAndPublishInv, replaceInv, adjustInv, cancelInv,
 * confirmPayment, UpdateCus, downloadInv.
 *
 * THAM SỐ CẤU HÌNH (từ tvan_providers table):
 *   api_url, username/password, account/acpass, pattern/serial.
 *
 * RỦI RO: API thay đổi theo phiên bản (cần version check).
 * Timeout 30s — nếu quá, cần retry queue.
 * T-VAN downtime → fallback sang provider khác.
 */
class VnptEInvoiceGateway implements EInvoiceGatewayInterface
{
    private string $apiUrl;
    private string $username;
    private string $password;
    private string $account;
    private string $acPass;
    private string $pattern;
    private string $serial;
    private DigitalSignatureService $signer;

    /**
     * @param array $config Cấu hình T-VAN: api_url, username, password, account, acpass, pattern, serial.
     * @param DigitalSignatureService $signer Service ký số.
     */
    public function __construct(
        array $config,
        DigitalSignatureService $signer,
    ) {
        $this->apiUrl = $config['api_url'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->account = $config['account'] ?? '';
        $this->acPass = $config['acpass'] ?? '';
        $this->pattern = $config['pattern'] ?? '1';
        $this->serial = $config['serial'] ?? '01GTKT0/001';
        $this->signer = $signer;
    }

    /**
     * Phát hành hóa đơn.
     *
     * 1. Gửi XML đã ký lên SOAP service.
     * 2. Xử lý kết quả.
     *
     * @param string $signedXml XML hóa đơn đã ký số.
     * @param string $pattern Mẫu số hóa đơn (đè config nếu có).
     * @param string $serial Ký hiệu hóa đơn (đè config nếu có).
     * @return PublishResult Kết quả phát hành.
     */
    public function publish(string $signedXml, string $pattern = '', string $serial = ''): PublishResult
    {
        $pattern = $pattern ?: $this->pattern;
        $serial = $serial ?: $this->serial;

        try {
            $response = $this->soapCall('ImportAndPublishInv', [
                'Account' => $this->account,
                'ACpass' => $this->acPass,
                'xmlInvData' => base64_encode($signedXml),
                'username' => $this->username,
                'pass' => $this->password,
                'pattern' => $pattern,
                'serial' => $serial,
                'convert' => 0,
            ]);

            return PublishResult::fromResponse($response);
        } catch (\Throwable $e) {
            return new PublishResult('', '', '', '', false, $e->getMessage());
        }
    }

    /**
     * Điều chỉnh hóa đơn.
     *
     * @param string $fkey FKey hóa đơn gốc.
     * @param string $signedXml XML hóa đơn điều chỉnh đã ký.
     * @return AdjustResult Kết quả điều chỉnh.
     */
    public function adjust(string $fkey, string $signedXml): AdjustResult
    {
        try {
            $response = $this->soapCall('adjustInv', [
                'Account' => $this->account,
                'ACpass' => $this->acPass,
                'xmlInvData' => base64_encode($signedXml),
                'username' => $this->username,
                'password' => $this->password,
                'fkey' => $fkey,
                'convert' => 0,
            ]);

            $data = json_decode($response, true);
            return new AdjustResult(
                $data['success'] ?? false,
                $data['newFkey'] ?? '',
                $data['error'] ?? '',
            );
        } catch (\Throwable $e) {
            return new AdjustResult(false, '', $e->getMessage());
        }
    }

    /**
     * Thay thế hóa đơn.
     *
     * @param string $fkey FKey hóa đơn gốc.
     * @param string $signedXml XML hóa đơn thay thế đã ký.
     * @return ReplaceResult Kết quả thay thế.
     */
    public function replace(string $fkey, string $signedXml): ReplaceResult
    {
        try {
            $response = $this->soapCall('replaceInv', [
                'Account' => $this->account,
                'ACpass' => $this->acPass,
                'xmlInvData' => base64_encode($signedXml),
                'username' => $this->username,
                'password' => $this->password,
                'fkey' => $fkey,
                'convert' => 0,
            ]);

            $data = json_decode($response, true);
            return new ReplaceResult(
                $data['success'] ?? false,
                $data['newFkey'] ?? '',
                $data['newInvToken'] ?? '',
                $data['error'] ?? '',
            );
        } catch (\Throwable $e) {
            return new ReplaceResult(false, '', '', $e->getMessage());
        }
    }

    /**
     * Hủy hóa đơn.
     *
     * @param string $fkey FKey hóa đơn cần hủy.
     * @param string $reason Lý do hủy.
     * @return CancelResult Kết quả hủy.
     */
    public function cancel(string $fkey, string $reason): CancelResult
    {
        try {
            $response = $this->soapCall('cancelInv', [
                'Account' => $this->account,
                'ACpass' => $this->acPass,
                'fkey' => $fkey,
                'username' => $this->username,
                'password' => $this->password,
            ]);

            $data = json_decode($response, true);
            return new CancelResult(
                $data['success'] ?? false,
                $data['error'] ?? '',
            );
        } catch (\Throwable $e) {
            return new CancelResult(false, $e->getMessage());
        }
    }

    /**
     * Xác nhận thanh toán hóa đơn.
     *
     * @param string $fkey FKey hóa đơn.
     * @return bool True nếu xác nhận thành công.
     */
    public function confirmPayment(string $fkey): bool
    {
        try {
            $response = $this->soapCall('confirmPayment', [
                'lstFkey' => $fkey,
                'userName' => $this->username,
                'userPass' => $this->password,
            ]);

            $data = json_decode($response, true);
            return $data['success'] ?? false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lấy thông tin hóa đơn.
     *
     * @param string $fkey FKey hóa đơn.
     * @return InvoiceDetail Chi tiết hóa đơn.
     */
    public function getInvoice(string $fkey): InvoiceDetail
    {
        $response = $this->soapCall('getInvViewFkey', [
            'fkey' => $fkey,
            'userName' => $this->username,
            'userPass' => $this->password,
        ]);

        $data = json_decode($response, true);
        return new InvoiceDetail(
            $fkey,
            $data['invToken'] ?? '',
            $data['status'] ?? 'unknown',
            $data['cqtCode'] ?? '',
            $data['pdfUrl'] ?? '',
            $data['xmlUrl'] ?? '',
        );
    }

    /**
     * Tải XML hóa đơn.
     *
     * @param string $fkey FKey hóa đơn.
     * @return string Nội dung XML.
     */
    public function downloadXml(string $fkey): string
    {
        return $this->soapCall('downloadInvFkey', [
            'fkey' => $fkey,
            'userName' => $this->username,
            'userPass' => $this->password,
        ]);
    }

    /**
     * Tải PDF hóa đơn.
     *
     * @param string $fkey FKey hóa đơn.
     * @return string Nội dung PDF.
     */
    public function downloadPdf(string $fkey): string
    {
        return $this->soapCall('downloadInvPDFFkey', [
            'fkey' => $fkey,
            'userName' => $this->username,
            'userPass' => $this->password,
        ]);
    }

    /**
     * Đồng bộ khách hàng lên VNPT.
     *
     * @param string $cusCode Mã khách hàng.
     * @param string $name Tên khách hàng.
     * @param string $taxCode MST khách hàng.
     * @param string $address Địa chỉ.
     * @param string $email Email.
     * @return bool True nếu đồng bộ thành công.
     */
    public function syncCustomer(string $cusCode, string $name, string $taxCode, string $address, string $email): bool
    {
        $xml = <<<XML
<CusData>
  <CusCode>{$cusCode}</CusCode>
  <CusName>{$this->escape($name)}</CusName>
  <CusTaxCode>{$taxCode}</CusTaxCode>
  <CusAddress>{$this->escape($address)}</CusAddress>
  <CusEmail>{$email}</CusEmail>
  <CusType>1</CusType>
</CusData>
XML;

        try {
            $response = $this->soapCall('UpdateCus', [
                'xmlCusData' => $xml,
                'username' => $this->username,
                'pass' => $this->password,
                'convert' => 0,
            ]);

            $data = json_decode($response, true);
            return $data['success'] ?? false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Gán chứng chỉ số cho khách hàng.
     *
     * @param string $cusCode Mã khách hàng.
     * @param string $certSerial Số serial chứng chỉ.
     * @return bool True nếu gán thành công.
     */
    public function assignCertificate(string $cusCode, string $certSerial): bool
    {
        try {
            $response = $this->soapCall('setCusCert', [
                'certSerial' => $certSerial,
                'certString' => '',
                'cusCode' => $cusCode,
                'username' => $this->username,
                'pass' => $this->password,
            ]);

            $data = json_decode($response, true);
            return $data['success'] ?? false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Báo cáo tình hình sử dụng hóa đơn.
     *
     * @param int $year Năm báo cáo.
     * @param int $quarter Quý báo cáo.
     * @return array Dữ liệu báo cáo.
     */
    public function reportUsage(int $year, int $quarter): array
    {
        $response = $this->soapCall('reportInvUsed', [
            'year' => $year,
            'quarter' => $quarter,
            'username' => $this->username,
            'pass' => $this->password,
        ]);

        $data = json_decode($response, true);
        return $data['data'] ?? [];
    }

    // === SOAP CLIENT ===

    /**
     * Gọi SOAP WebService của VNPT.
     *
     * Xây dựng SOAP envelope, gửi qua cURL, parse response.
     *
     * @param string $function Tên function SOAP.
     * @param array $params Tham số gửi lên.
     * @return string Kết quả JSON-encoded.
     * @throws \RuntimeException Nếu API URL chưa cấu hình hoặc HTTP lỗi.
     */
    private function soapCall(string $function, array $params): string
    {
        if (empty($this->apiUrl)) {
            throw new \RuntimeException('T-VAN API URL chưa được cấu hình.');
        }

        // Xây dựng XML SOAP envelope
        $soapBody = '';
        foreach ($params as $key => $value) {
            $soapBody .= "      <{$key}>" . htmlspecialchars((string)$value, ENT_XML1) . "</{$key}>\n";
        }

        $soapXml = <<<SOAP
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <{$function} xmlns="http://vnpt-invoice.vn/">
{$soapBody}    </{$function}>
  </soap:Body>
</soap:Envelope>
SOAP;

        // Gọi SOAP service
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapXml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Content-Length: ' . strlen($soapXml),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException("T-VAN error (HTTP {$httpCode}): {$error}");
        }

        // Parse SOAP response
        $dom = new \DOMDocument();
        $dom->loadXML($response);
        $result = $dom->textContent ?? '';

        return json_encode([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Escape XML — chống XXE và XML injection.
     *
     * @param string $value Chuỗi cần escape.
     * @return string Chuỗi đã escape.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
