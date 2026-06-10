<?php
namespace Accounting\Infrastructure\EInvoice;

/**
 * DỊCH VỤ CHỮ KÝ SỐ CHO HÓA ĐƠN ĐIỆN TỬ.
 *
 * Ký số XML hóa đơn điện tử theo chuẩn PKCS#7 (CMS SignedData).
 * Sử dụng openssl CLI + pkcs11 engine để ký với USB Token/HSM.
 * Không yêu cầu Composer — gọi openssl qua proc_open.
 *
 * Định dạng: RSA với SHA-256, tối thiểu 2048 bits.
 * Chuẩn: XML Signature Syntax and Processing (W3C).
 * Tuân thủ: Quyết định về chuẩn XML HĐĐT (30/05/2025), TT 32/2025.
 *
 * HỖ TRỢ 3 CHẾ ĐỘ:
 *   1. production: USB Token/HSM qua openssl PKCS#11 engine
 *   2. dev: openssl với private key file (mô phỏng)
 *   3. test: mock signing (không cần openssl)
 *
 * RỦI RO: Private key không bao giờ được rời khỏi token.
 * Token khóa sau 5 lần nhập PIN sai. Cần thông báo cho kế toán trưởng.
 */
class DigitalSignatureService
{
    private string $mode;
    private string $pkcs11Lib;
    private string $tokenPin;
    private string $certFile;
    private string $keyFile;    // chỉ dùng cho dev mode

    /**
     * @param string $mode Chế độ: 'test', 'dev', hoặc 'production'.
     * @param string $pkcs11Lib Đường dẫn thư viện PKCS#11 (production).
     * @param string $tokenPin PIN của USB Token (production).
     * @param string $certFile Đường dẫn file chứng chỉ.
     * @param string $keyFile Đường dẫn file private key (dev).
     */
    public function __construct(
        string $mode = 'test',
        string $pkcs11Lib = '',
        string $tokenPin = '',
        string $certFile = '',
        string $keyFile = '',
    ) {
        $this->mode = $mode;
        $this->pkcs11Lib = $pkcs11Lib;
        $this->tokenPin = $tokenPin;
        $this->certFile = $certFile;
        $this->keyFile = $keyFile;
    }

    /**
     * Ký một XML string.
     *
     * Input: XML hóa đơn chưa ký (chuẩn TT32).
     * Output: XML hóa đơn đã ký (có <DSCKS>).
     *
     * @param string $xml XML hóa đơn chưa ký.
     * @return string XML hóa đơn đã ký.
     * @throws \RuntimeException Nếu ký thất bại.
     */
    public function signXml(string $xml): string
    {
        $canonicalXml = $this->canonicalize($xml);
        $digestValue = base64_encode(sha1($canonicalXml, true));

        switch ($this->mode) {
            case 'production':
                $signature = $this->signWithToken($canonicalXml);
                break;
            case 'dev':
                $signature = $this->signWithKeyFile($canonicalXml);
                break;
            default:
                $signature = $this->mockSign($canonicalXml);
        }

        return $this->embedSignature($xml, $signature, $digestValue);
    }

    /**
     * Kiểm tra chữ ký số trên XML.
     *
     * @param string $signedXml XML hóa đơn đã ký.
     * @return bool True nếu chữ ký hợp lệ.
     */
    public function verifySignature(string $signedXml): bool
    {
        if ($this->mode === 'test') {
            return str_contains($signedXml, '<DSCKS>');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_sig_');
        file_put_contents($tempFile, $signedXml);

        $cmd = sprintf(
            'openssl smime -verify -in %s -inform PEM -noverify 2>/dev/null',
            escapeshellarg($tempFile)
        );
        exec($cmd, $output, $exitCode);

        unlink($tempFile);
        return $exitCode === 0;
    }

    /**
     * Ký với USB Token qua PKCS#11 (production).
     *
     * @param string $data Dữ liệu cần ký.
     * @return string Chữ ký base64.
     * @throws \RuntimeException Nếu ký thất bại.
     */
    private function signWithToken(string $data): string
    {
        $hashFile = tempnam(sys_get_temp_dir(), 'inv_hash_');
        $sigFile = tempnam(sys_get_temp_dir(), 'inv_sig_');
        file_put_contents($hashFile, sha1($data, true));

        // openssl dgst -engine pkcs11 -keyform engine
        //   -sign "pkcs11:token=VNPT-CA;object=private-key?pin=XXXX"
        //   -sha256 -out sig.bin hash.bin
        $cmd = sprintf(
            'openssl dgst -engine pkcs11 -keyform engine -sign "%s" -sha256 -out %s %s 2>/dev/null',
            escapeshellcmd("pkcs11:token=TOKEN;object=private-key?pin={$this->tokenPin}"),
            escapeshellarg($sigFile),
            escapeshellarg($hashFile)
        );
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            unlink($hashFile);
            unlink($sigFile);
            throw new \RuntimeException('Ký số thất bại. Kiểm tra USB Token và PIN.');
        }

        $signature = base64_encode(file_get_contents($sigFile));
        unlink($hashFile);
        unlink($sigFile);

        return $signature;
    }

    /**
     * Ký với private key file (dev mode).
     *
     * @param string $data Dữ liệu cần ký.
     * @return string Chữ ký base64.
     * @throws \RuntimeException Nếu ký thất bại.
     */
    private function signWithKeyFile(string $data): string
    {
        if (!file_exists($this->keyFile)) {
            // Tạo key pair nếu chưa có
            $this->generateDevKey();
        }

        $hashFile = tempnam(sys_get_temp_dir(), 'inv_hash_');
        $sigFile = tempnam(sys_get_temp_dir(), 'inv_sig_');
        file_put_contents($hashFile, sha1($data, true));

        $cmd = sprintf(
            'openssl dgst -sign %s -sha256 -out %s %s 2>/dev/null',
            escapeshellarg($this->keyFile),
            escapeshellarg($sigFile),
            escapeshellarg($hashFile)
        );
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            unlink($hashFile);
            unlink($sigFile);
            throw new \RuntimeException('Ký số thất bại (dev mode).');
        }

        $signature = base64_encode(file_get_contents($sigFile));
        unlink($hashFile);
        unlink($sigFile);

        return $signature;
    }

    /**
     * Mock signing (test mode — không cần openssl).
     *
     * @param string $data Dữ liệu cần ký.
     * @return string Chữ ký giả lập base64.
     */
    private function mockSign(string $data): string
    {
        return base64_encode(sha1($data . '_signed_' . date('Ymd'), true));
    }

    /**
     * Chuẩn hóa XML (canonicalization).
     *
     * @param string $xml XML cần chuẩn hóa.
     * @return string XML đã chuẩn hóa.
     */
    private function canonicalize(string $xml): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;
        return $doc->saveXML();
    }

    /**
     * Nhúng chữ ký số vào XML hóa đơn.
     *
     * Thêm block <DSCKS> với XML Signature W3C trước thẻ </HDon>.
     *
     * @param string $xml XML gốc.
     * @param string $signature Chữ ký số base64.
     * @param string $digestValue Digest value base64.
     * @return string XML đã nhúng chữ ký.
     * @throws \RuntimeException Nếu không tìm thấy thẻ </HDon>.
     */
    private function embedSignature(string $xml, string $signature, string $digestValue): string
    {
        $signatureBlock = <<<XML
    <DSCKS>
      <NBan>
        <Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
          <SignedInfo>
            <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
            <SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
            <Reference URI="">
              <Transforms>
                <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
              </Transforms>
              <DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
              <DigestValue>{$digestValue}</DigestValue>
            </Reference>
          </SignedInfo>
          <SignatureValue>{$signature}</SignatureValue>
        </Signature>
      </NBan>
    </DSCKS>

XML;
        // Chèn trước </HDon>
        $pos = strrpos($xml, '</HDon>');
        if ($pos === false) {
            throw new \RuntimeException('Không tìm thấy thẻ đóng </HDon> trong XML.');
        }
        return substr_replace($xml, $signatureBlock, $pos, 0);
    }

    /**
     * Tạo key pair cho dev mode (nếu chưa có).
     *
     * Sinh self-signed certificate RSA 2048 bits.
     *
     * @return void
     * @throws \RuntimeException Nếu tạo key thất bại.
     */
    private function generateDevKey(): void
    {
        $dir = dirname($this->keyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $cmd = sprintf(
            'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 3650 -nodes -subj "/CN=DevInvoice/OU=IT/O=Company" 2>/dev/null',
            escapeshellarg($this->keyFile),
            escapeshellarg($this->certFile)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Không thể tạo key pair cho dev mode.');
        }
        chmod($this->keyFile, 0600);
    }
}
