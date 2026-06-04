<?php
// Test: XBRL Generator — chuyển BC tài chính sang định dạng XBRL cho GDT
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\XbrlGenerator;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$gen = new XbrlGenerator($pdo);

// TEST 1: Happy path — BC01 với data mẫu, phải sinh XML well-formed
$bc01Sample = [
    ['ma_so' => '100', 'name_vi' => 'Tài sản ngắn hạn', 'value' => 500000000],
    ['ma_so' => '110', 'name_vi' => 'Tiền và tương đương tiền', 'value' => 200000000],
    ['ma_so' => '140', 'name_vi' => 'Hàng tồn kho', 'value' => 300000000],
    ['ma_so' => '200', 'name_vi' => 'Tài sản dài hạn', 'value' => 1000000000],
    ['ma_so' => '280', 'name_vi' => 'TỔNG TÀI SẢN', 'value' => 1500000000],
    ['ma_so' => '300', 'name_vi' => 'Nợ ngắn hạn', 'value' => 400000000],
    ['ma_so' => '330', 'name_vi' => 'Tổng nợ phải trả', 'value' => 600000000],
    ['ma_so' => '440', 'name_vi' => 'VỐN CHỦ SỞ HỮU', 'value' => 900000000],
];

$xml = $gen->generateBC01($bc01Sample, '2025', '0123456789', 'Công ty ABC');
assertTrue(!empty($xml), 'BC01 sinh ra XML không rỗng');
assertTrue(str_contains($xml, '<?xml'), 'XML có declaration');
assertTrue(str_contains($xml, 'http://www.gdt.gov.vn/2025/btc'), 'Có namespace GDT');
assertTrue(str_contains($xml, 'gdt:Tien'), 'Có fact gdt:Tien (ma_so 110)');
assertTrue(str_contains($xml, 'gdt:TONGTAISAN'), 'Có fact gdt:TONGTAISAN (ma_so 280)');
assertTrue(str_contains($xml, '200000000'), 'Có giá trị Tiền = 200 triệu');
assertTrue(str_contains($xml, '1500000000'), 'Có giá trị Tổng TS = 1.5 tỷ');
assertTrue(str_contains($xml, 'contextRef'), 'Có contextRef trên mỗi fact');
assertTrue(str_contains($xml, 'unitRef="VND"'), 'Có unitRef VND');

// TEST 2: BC01 dùng instant period (1 thời điểm), BC02/BC03 dùng duration (khoảng)
$xmlBc01 = $gen->generateBC01($bc01Sample, '2025', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlBc01, '<xbrli:instant>'), 'BC01 dùng xbrli:instant');
assertTrue(!str_contains($xmlBc01, '<xbrli:startDate>'), 'BC01 không dùng startDate');

$bc02Sample = [
    ['ma_so' => '01', 'name_vi' => 'Doanh thu', 'value' => 2000000000],
    ['ma_so' => '11', 'name_vi' => 'Giá vốn', 'value' => 1200000000],
    ['ma_so' => '20', 'name_vi' => 'Lợi nhuận gộp', 'value' => 800000000],
    ['ma_so' => '50', 'name_vi' => 'LN trước thuế', 'value' => 500000000],
    ['ma_so' => '60', 'name_vi' => 'LN sau thuế', 'value' => 400000000],
];
$xmlBc02 = $gen->generateBC02($bc02Sample, '2025', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlBc02, '<xbrli:startDate>'), 'BC02 dùng xbrli:startDate');
assertTrue(str_contains($xmlBc02, '<xbrli:endDate>'), 'BC02 dùng xbrli:endDate');
assertTrue(str_contains($xmlBc02, 'gdt:DoanhThuBanHangVaCungCapDichVu'), 'Có fact DoanhThu BC02');

// TEST 3: Period code formats — năm, tháng, quý đều parse được (dùng BC02/BC03 vì dùng duration period)
$xmlYear = $gen->generateBC02($bc02Sample, '2025', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlYear, '2025-01-01') && str_contains($xmlYear, '2025-12-31'), 'Period năm: 2025-01-01 → 2025-12-31');

$xmlMonth = $gen->generateBC02($bc02Sample, '2025-06', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlMonth, '2025-06-01') && str_contains($xmlMonth, '2025-06-30'), 'Period tháng: 2025-06-01 → 2025-06-30');

$xmlQuarter = $gen->generateBC02($bc02Sample, '2025-Q2', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlQuarter, '2025-04-01') && str_contains($xmlQuarter, '2025-06-30'), 'Period quý Q2: 2025-04-01 → 2025-06-30');

// TEST 4: Escape XML ký tự đặc biệt trong entity name (chống injection)
$xmlEscape = $gen->generateBC01($bc01Sample, '2025', '0123456789', 'Công ty <script>alert("XSS")</script> & "test"');
assertTrue(!str_contains($xmlEscape, '<script>alert("XSS")</script>'), 'Không còn chuỗi <script> raw trong output');
assertTrue(str_contains($xmlEscape, '&lt;script&gt;'), 'Chuyển <script> thành &lt;script&gt; trong XML');
assertTrue(str_contains($xmlEscape, '&amp;'), 'Escape ký tự & thành &amp;');

// TEST 5: Bỏ qua ma_so không có trong taxonomy map
$bc01Unknown = [
    ['ma_so' => '999', 'name_vi' => 'Unknown', 'value' => 100],
    ['ma_so' => '280', 'name_vi' => 'Tổng TS', 'value' => 1500000000],
];
$xmlUnknown = $gen->generateBC01($bc01Unknown, '2025', '0123456789', 'Công ty ABC');
assertTrue(!str_contains($xmlUnknown, 'gdt:Unknown'), 'Bỏ qua ma_so không có trong taxonomy map');
assertTrue(str_contains($xmlUnknown, 'gdt:TONGTAISAN'), 'Vẫn sinh fact cho ma_so hợp lệ');

// TEST 6: Bỏ qua value không phải số (NaN, null, string)
$bc01Invalid = [
    ['ma_so' => '280', 'name_vi' => 'Tổng TS', 'value' => 'NaN'],
    ['ma_so' => '110', 'name_vi' => 'Tiền', 'value' => 200000000],
    ['ma_so' => '440', 'name_vi' => 'VCSH', 'value' => null],
];
$xmlInvalid = $gen->generateBC01($bc01Invalid, '2025', '0123456789', 'Công ty ABC');
assertTrue(!str_contains($xmlInvalid, 'NaN'), 'Bỏ qua value NaN');
assertTrue(str_contains($xmlInvalid, 'gdt:Tien'), 'Vẫn sinh fact cho value hợp lệ');

// TEST 7: Validate XBRL — happy path
$validXml = $gen->generateBC01($bc01Sample, '2025', '0123456789', 'Công ty ABC');
$errors = $gen->validate($validXml);
assertTrue(empty($errors), 'Validate XBRL hợp lệ: ' . implode(', ', $errors));

// TEST 8: Validate XBRL — XML không well-formed
$errors = $gen->validate('<invalid><xml>');
assertTrue(!empty($errors), 'Validate XBRL phát hiện XML malformed');
assertTrue(in_array('XML không well-formed', $errors, true), 'Có lỗi "XML không well-formed"');

// TEST 9: Validate XBRL — thiếu namespace GDT
$xmlNoGdt = '<?xml version="1.0"?>
<xbrl xmlns="http://www.xbrl.org/2003/instance" xmlns:link="http://www.xbrl.org/2003/linkbase">
  <xbrli:context id="x"><xbrli:entity><xbrli:identifier scheme="x">x</xbrli:identifier></xbrli:entity><xbrli:period><xbrli:instant>2025-12-31</xbrli:instant></xbrli:period></xbrli:context>
  <xbrli:unit id="VND"><xbrli:measure>iso4217:VND</xbrli:measure></xbrli:unit>
</xbrl>';
$errors = $gen->validate($xmlNoGdt);
assertTrue(!empty($errors), 'Validate phát hiện thiếu namespace GDT');
assertTrue(in_array('Thiếu namespace GDT (http://www.gdt.gov.vn/2025/btc)', $errors, true), 'Có lỗi thiếu namespace GDT');

// TEST 10: BC03 — sinh XBRL với cấu trúc cash flow
$bc03Sample = [
    ['ma_so' => '01', 'name_vi' => 'LN trước thuế', 'value' => 500000000],
    ['ma_so' => '20', 'name_vi' => 'Tiền đầu kỳ', 'value' => 100000000],
    ['ma_so' => '30', 'name_vi' => 'LCTT từ HĐKD', 'value' => 400000000],
    ['ma_so' => '50', 'name_vi' => 'LCTT từ HĐĐT', 'value' => -100000000],
    ['ma_so' => '60', 'name_vi' => 'LCTT từ HĐTC', 'value' => 200000000],
    ['ma_so' => '70', 'name_vi' => 'Tiền cuối kỳ', 'value' => 600000000],
];
$xmlBc03 = $gen->generateBC03($bc03Sample, '2025', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xmlBc03, 'gdt:Tien_CuoiKy'), 'BC03 có fact Tiền cuối kỳ');
assertTrue(str_contains($xmlBc03, 'gdt:LuuChuyenTienThu_TuHDKD'), 'BC03 có fact LCTT HĐKD');
assertTrue(str_contains($xmlBc03, '600000000'), 'BC03 có giá trị Tiền cuối kỳ');

results();
