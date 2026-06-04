<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;

//
// BÁO CÁO TÀI CHÍNH DẠNG XBRL: Generator sinh file XBRL theo Taxonomy GDT
// Tuân thủ Thông tư 99/2025/TT-BTC + chuẩn XBRL 2.1
// Hỗ trợ: BC01 (Bảng CĐKT), BC02 (KQKD), BC03 (LCTT)
//
// Nguyên tắc:
//   - Không phụ thuộc thư viện ngoài (no Composer) — dùng DOMDocument built-in
//   - Namespace GDT: http://www.gdt.gov.vn/2025/btc
//   - Schema reference: phải khai báo trong <xbrl> root
//   - Đơn vị tiền: VND, scale = 0 (raw value, không nhân 1000)
//
// Rủi ro: Sai namespace → GDT từ chối. Sai contextRef → sai kỳ báo cáo.
//   Mitigation: sử dụng fixed namespace + period dimension từ GDT spec.
//
class XbrlGenerator
{
    private const NS_GDT = 'http://www.gdt.gov.vn/2025/btc';
    private const NS_XBRLI = 'http://www.xbrl.org/2003/instance';
    private const NS_XBRLDI = 'http://xbrl.org/2006/xbrldi';
    private const NS_LINK = 'http://www.xbrl.org/2003/linkbase';
    private const NS_XLINK = 'http://www.w3.org/1999/xlink';
    private const NS_ISO4217 = 'http://www.xbrl.org/2003/iso4217';

    //
    // Bảng mapping ma_so (TT99) → tên phần tử XBRL (GDT Taxonomy)
    // Chỉ map các chỉ tiêu bắt buộc — mở rộng khi GDT công bố Taxonomy chi tiết
    //
    private const BC01_MAP = [
        '100' => 'TienVaCacKhoanTuongDuongTien',
        '110' => 'Tien',
        '120' => 'DauTuTaiChinhNganHan',
        '130' => 'CacKhoanPhaiThuNganHan',
        '140' => 'HangTonKho',
        '150' => 'TaiSanNganHanKhac',
        '200' => 'TONGTAISAN_NGANHAN',
        '210' => 'PhaiThuDaiHan',
        '220' => 'TaiSanCoDinh',
        '230' => 'BatDongSanDauTu',
        '240' => 'TaiSanDoDangDaiHan',
        '250' => 'DauTuTaiChinhDaiHan',
        '260' => 'TaiSanDaiHanKhac',
        '270' => 'TongTaiSanDaiHan',
        '280' => 'TONGTAISAN',
        '300' => 'NoPhaiTraNganHan',
        '310' => 'PhaiTraNguoiBan',
        '320' => 'NguoiMuaTraTienTruoc',
        '330' => 'TONGNOPHAITRA',
        '340' => 'VayVaNoThueTaiChinh',
        '400' => 'NoPhaiTraDaiHan',
        '410' => 'VayVaNoDaiHan',
        '430' => 'TongNoDaiHan',
        '440' => 'VONCHUSOHUU',
    ];

    private const BC02_MAP = [
        '01' => 'DoanhThuBanHangVaCungCapDichVu',
        '02' => 'CacKhoanGiamTruDoanhThu',
        '10' => 'DoanhThuThuan',
        '11' => 'GiaVonHangBan',
        '20' => 'LoiNhuanGop',
        '21' => 'DoanhThuHoatDongTaiChinh',
        '22' => 'ChiPhiHoatDongTaiChinh',
        '23' => 'ChiPhiBanHang',
        '24' => 'ChiPhiQuanLyDoanhNghiep',
        '25' => 'LoiNhuanTuHoatDongKinhDoanh',
        '30' => 'LoiNhuanGop_HDKD',
        '40' => 'LoiNhuanTuHoatDongTaiChinhVaThuNhapKhac',
        '50' => 'LoiNhuanTruocThue',
        '51' => 'ThueTNDNHienHanh',
        '52' => 'ThueTNDNHoanLai',
        '60' => 'LoiNhuanSauThue',
    ];

    private const BC03_MAP = [
        '01' => 'LoiNhuanTruocThue_BC03',
        '02' => 'DieuChinhChoCacKhoan',
        '20' => 'Tien_DauKy',
        '30' => 'LuuChuyenTienThu_TuHDKD',
        '50' => 'LuuChuyenTienThu_TuHDDT',
        '60' => 'LuuChuyenTienThu_TuHDTC',
        '70' => 'Tien_CuoiKy',
    ];

    private \PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, ?AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    //
    // Sinh XBRL cho BC01 (Bảng cân đối kế toán)
    // Input: $bc01Data từ FsService::generateBC01() — mảng các dòng chỉ tiêu
    // Output: string XML — nội dung file .xbrl sẵn sàng gửi GDT
    //
    public function generateBC01(array $bc01Data, string $periodCode, string $entityTaxCode, string $entityName): string
    {
        return $this->generate('BC01', $bc01Data, $periodCode, $entityTaxCode, $entityName, self::BC01_MAP);
    }

    public function generateBC02(array $bc02Data, string $periodCode, string $entityTaxCode, string $entityName): string
    {
        return $this->generate('BC02', $bc02Data, $periodCode, $entityTaxCode, $entityName, self::BC02_MAP);
    }

    public function generateBC03(array $bc03Data, string $periodCode, string $entityTaxCode, string $entityName): string
    {
        return $this->generate('BC03', $bc03Data, $periodCode, $entityTaxCode, $entityName, self::BC03_MAP);
    }

    //
    // Hàm generate chung — xây dựng document XBRL hoàn chỉnh
    //
    private function generate(
        string $statement,
        array $data,
        string $periodCode,
        string $entityTaxCode,
        string $entityName,
        array $tagMap
    ): string {
        //
        // RỦI RO: Nếu entityTaxCode/entityName chứa ký tự XML đặc biệt → malformed XML
        // Biện pháp: DOMDocument::createTextNode tự động escape khi serialize XML
        //   - KHÔNG dùng htmlspecialchars() trước (sẽ bị double-escape thành &amp;lt;)
        //   - CHỈ dùng khi data có thể chứa control chars không hợp lệ XML
        //   - Ở đây data từ user nhập (MST, tên DN) — chỉ cần loại bỏ control chars
        //
        $entityTaxCode = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $entityTaxCode);
        $entityName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $entityName);

        [$startDate, $endDate] = $this->periodToDateRange($periodCode);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        //
        // Root element <xbrl> với các namespace declarations
        // GDT yêu cầu schemaRef trỏ đến file Taxonomy GDT (.xsd)
        //
        $xbrl = $dom->createElementNS(self::NS_XBRLI, 'xbrl');
        $dom->appendChild($xbrl);
        $xbrl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gdt', self::NS_GDT);
        $xbrl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xbrldi', self::NS_XBRLDI);
        $xbrl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso4217', self::NS_ISO4217);
        $xbrl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:link', self::NS_LINK);
        $xbrl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', self::NS_XLINK);

        //
        // Schema reference — GDT yêu cầu trỏ đến Taxonomy file
        // URL: http://www.gdt.gov.vn/2025/btc/btc-taxonomy.xsd (placeholder — thay bằng URL thật khi GDT công bố)
        //
        $schemaRef = $dom->createElementNS(self::NS_LINK, 'link:schemaRef');
        $schemaRef->setAttributeNS(self::NS_XLINK, 'xlink:type', 'simple');
        $schemaRef->setAttributeNS(self::NS_XLINK, 'xlink:href', 'http://www.gdt.gov.vn/2025/btc/' . strtolower($statement) . '.xsd');
        $xbrl->appendChild($schemaRef);

        //
        // Context — định danh thực thể báo cáo + kỳ báo cáo
        // GDT yêu cầu: entity identifier = MST, period = Instant cho BC01 / Duration cho BC02, BC03
        //
        $contextId = "ctx_{$statement}_{$periodCode}";

        $context = $dom->createElementNS(self::NS_XBRLI, 'xbrli:context');
        $context->setAttribute('id', $contextId);

        $entity = $dom->createElementNS(self::NS_XBRLI, 'xbrli:entity');
        $identifier = $dom->createElementNS(self::NS_XBRLI, 'xbrli:identifier');
        $identifier->setAttribute('scheme', 'http://www.gdt.gov.vn/mst');
        $identifier->appendChild($dom->createTextNode($entityTaxCode));
        $entity->appendChild($identifier);
        $context->appendChild($entity);

        $segment = $dom->createElementNS(self::NS_XBRLI, 'xbrli:segment');
        $entityOut = $dom->createElementNS(self::NS_XBRLDI, 'xbrldi:explicitMember');
        $entityOut->setAttribute('dimension', self::NS_GDT . ':EntityAxis');
        $entityOut->appendChild($dom->createTextNode($entityName));
        $segment->appendChild($entityOut);
        $context->appendChild($segment);

        //
        // Period: BC01 dùng "instant" (thời điểm), BC02/BC03 dùng "duration" (khoảng thời gian)
        //
        $periodEl = $dom->createElementNS(self::NS_XBRLI, 'xbrli:period');
        if ($statement === 'BC01') {
            $instant = $dom->createElementNS(self::NS_XBRLI, 'xbrli:instant');
            $instant->appendChild($dom->createTextNode($endDate));
            $periodEl->appendChild($instant);
        } else {
            $start = $dom->createElementNS(self::NS_XBRLI, 'xbrli:startDate');
            $start->appendChild($dom->createTextNode($startDate));
            $periodEl->appendChild($start);
            $end = $dom->createElementNS(self::NS_XBRLI, 'xbrli:endDate');
            $end->appendChild($dom->createTextNode($endDate));
            $periodEl->appendChild($end);
        }
        $context->appendChild($periodEl);

        $xbrl->appendChild($context);

        //
        // Unit — VND (Vietnamese Dong, ISO 4217)
        //
        $unitId = "VND";
        $unit = $dom->createElementNS(self::NS_XBRLI, 'xbrli:unit');
        $unit->setAttribute('id', $unitId);
        $measure = $dom->createElementNS(self::NS_XBRLI, 'xbrli:measure');
        $measure->appendChild($dom->createTextNode('iso4217:VND'));
        $unit->appendChild($measure);
        $xbrl->appendChild($unit);

        //
        // Facts — từng chỉ tiêu trong báo cáo
        // Mỗi fact: <gdt:TenChiTieu contextRef="..." unitRef="VND" decimals="0">giá trị</gdt:TenChiTieu>
        //
        foreach ($data as $row) {
            $maSo = (string)$row['ma_so'];
            if (!isset($tagMap[$maSo])) {
                continue;
            }
            $tagName = $tagMap[$maSo];
            $value = $row['value'] ?? 0;

            //
            // RỦI RO: Nếu value không phải số → malformed XBRL
            // Biện pháp: ép kiểu int, bỏ qua nếu NaN
            //
            if (!is_numeric($value)) {
                continue;
            }
            $value = (int)round($value);

            $fact = $dom->createElementNS(self::NS_GDT, "gdt:{$tagName}");
            $fact->setAttribute('contextRef', $contextId);
            $fact->setAttribute('unitRef', $unitId);
            $fact->setAttribute('decimals', '0');
            $fact->appendChild($dom->createTextNode((string)$value));
            $xbrl->appendChild($fact);
        }

        //
        // Ghi audit log — quan trọng cho tuân thủ kiểm toán
        //
        $this->auditLogger?->log(
            'xbrl.generate',
            'xbrl_report',
            "{$statement}_{$periodCode}",
            null,
            ['statement' => $statement, 'period' => $periodCode, 'entity' => $entityTaxCode, 'facts' => count($data)],
            $_SESSION['user']['username'] ?? 'system'
        );

        return $dom->saveXML();
    }

    //
    // Chuyển periodCode → khoảng ngày
    //   '2025' → ['2025-01-01', '2025-12-31']
    //   '2025-06' → ['2025-06-01', '2025-06-30']
    //   '2025-Q1' → ['2025-01-01', '2025-03-31']
    //
    private function periodToDateRange(string $periodCode): array
    {
        if (preg_match('/^(\d{4})$/', $periodCode, $m)) {
            return ["{$m[1]}-01-01", "{$m[1]}-12-31"];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodCode, $m)) {
            $y = $m[1]; $mo = (int)$m[2];
            $lastDay = date('t', strtotime("{$y}-{$mo}-01"));
            return [sprintf('%s-%02d-01', $y, $mo), sprintf('%s-%02d-%s', $y, $mo, $lastDay)];
        }
        if (preg_match('/^(\d{4})-Q(\d)$/', $periodCode, $m)) {
            $y = $m[1]; $q = (int)$m[2];
            $startMonth = ($q - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $lastDay = date('t', strtotime("{$y}-{$endMonth}-01"));
            return [sprintf('%s-%02d-01', $y, $startMonth), sprintf('%s-%02d-%s', $y, $endMonth, $lastDay)];
        }
        return [date('Y-01-01'), date('Y-m-d')];
    }

    //
    // Validate XBRL — kiểm tra XML well-formed + có namespace GDT + có context
    // Trả về array errors (rỗng nếu hợp lệ)
    //
    public function validate(string $xbrlXml): array
    {
        $errors = [];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xbrlXml);
        libxml_clear_errors();

        if (!$loaded) {
            $errors[] = 'XML không well-formed';
            return $errors;
        }

        $xbrl = $dom->documentElement;
        if ($xbrl === null || $xbrl->localName !== 'xbrl') {
            $errors[] = 'Root element phải là <xbrl>';
            return $errors;
        }

        $hasGdtNs = str_contains($xbrl->lookupNamespaceURI('gdt') ?? '', 'gdt.gov.vn');
        if (!$hasGdtNs) {
            $errors[] = 'Thiếu namespace GDT (http://www.gdt.gov.vn/2025/btc)';
        }

        $contexts = $xbrl->getElementsByTagNameNS(self::NS_XBRLI, 'context');
        if ($contexts->length === 0) {
            $errors[] = 'Thiếu context element';
        }

        $units = $xbrl->getElementsByTagNameNS(self::NS_XBRLI, 'unit');
        if ($units->length === 0) {
            $errors[] = 'Thiếu unit element';
        }

        $facts = $xbrl->getElementsByTagNameNS(self::NS_GDT, '*');
        if ($facts->length === 0) {
            $errors[] = 'Thiếu fact nào trong namespace GDT';
        }

        return $errors;
    }
}
