<?php
namespace Accounting\Infrastructure\EInvoice;

use Accounting\Domain\Contract\EInvoiceGatewayInterface;
use Accounting\Domain\Contract\PublishResult;
use Accounting\Domain\Contract\AdjustResult;
use Accounting\Domain\Contract\ReplaceResult;
use Accounting\Domain\Contract\CancelResult;
use Accounting\Domain\Contract\InvoiceDetail;

//
// CỔNG HÓA ĐƠN ĐIỆN TỬ VNPT (VNPT-Invoice)
//
// Kết nối với hệ thống VNPT Invoice qua SOAP WebService
// Tuân thủ Thông tư 32/2025/TT-BTC (thay TT 78/2021)
//
// API functions:
//   ImportAndPublishInv — phát hành hóa đơn
//   replaceInv — thay thế hóa đơn
//   adjustInv — điều chỉnh hóa đơn
//   cancelInv — hủy hóa đơn
//   confirmPayment — cập nhật thanh toán
//   UpdateCus — đồng bộ khách hàng
//   downloadInv — tải XML/PDF
//
// THAM SỐ CẤU HÌNH (từ tvan_providers table):
//   api_url — endpoint SOAP
//   username/password — tài khoản gọi service
//   account/acpass — tài khoản phát hành hóa đơn
//   pattern/serial — mẫu số và ký hiệu hóa đơn
//
// RỦI RO:
//   - API thay đổi theo phiên bản (cần version check)
//   - Timeout 30s — nếu quá, cần retry queue
//   - T-VAN downtime → fallback sang provider khác
//
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

    // Phát hành hóa đơn
    // 1. Kiểm tra XML hợp lệ
    // 2. Ký số với USB Token
    // 3. Gửi lên SOAP service
    // 4. Xử lý kết quả
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

    public function downloadXml(string $fkey): string
    {
        return $this->soapCall('downloadInvFkey', [
            'fkey' => $fkey,
            'userName' => $this->username,
            'userPass' => $this->password,
        ]);
    }

    public function downloadPdf(string $fkey): string
    {
        return $this->soapCall('downloadInvPDFFkey', [
            'fkey' => $fkey,
            'userName' => $this->username,
            'userPass' => $this->password,
        ]);
    }

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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
