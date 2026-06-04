<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\XbrlGenerator;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Báo cáo Tài chính (Financial Statements)
 *
 * Mục đích nghiệp vụ:
 *   - BC 01: Bảng Cân đối kế toán (Balance Sheet)
 *   - BC 02: Báo cáo Kết quả Hoạt động Kinh doanh (Income Statement)
 *   - BC 03: Báo cáo Lưu chuyển Tiền tệ (Cash Flow Statement)
 *   - Tuân thủ Circular 99/2025/TT-BTC theo chỉ tiêu quy định
 *   - Kiểm tra tính hợp lệ (validate) trước khi xuất báo cáo
 *
 * API endpoints:
 *   GET /api/fs/bc01 — Bảng Cân đối kế toán (theo kỳ)
 *   GET /api/fs/bc02 — Báo cáo KQKD
 *   GET /api/fs/bc03 — Báo cáo Lưu chuyển Tiền tệ
 *   GET /api/fs/tt99 — Báo cáo tổng hợp TT99 (cả 3 BC + validation)
 *
 * Rủi ro:
 *   - R005: Sai tài khoản → sai chỉ tiêu BC → sai BCTC → phạt thuế
 *   - BC01: Tổng Tài sản (280) phải = Tổng Nguồn vốn (440)
 *   - BC02: Lợi nhuận gộp (20) = 511 - 632 - 333
 *   - BC03: Phải khớp với BC01 và BC02 (nguồn tiền = chênh lệch tiền)
 *   - Dữ liệu kỳ trước (prior period) cần so sánh để phát hiện bất thường
 *
 * Tích hợp:
 *   - FsService đọc từ AccountRepository số dư cuối kỳ
 *   - PeriodService cung cấp thông tin kỳ kế toán
 *   - Số liệu BC02 ảnh hưởng đến chỉ tiêu thuế TNDN
 */
class FsController
{
    private FsService $fs;
    private XbrlGenerator $xbrl;

    public function __construct(FsService $fs, XbrlGenerator $xbrl) { $this->fs = $fs; $this->xbrl = $xbrl; }

    // NGHIỆP VỤ: BC 01 — Bảng Cân đối kế toán (Balance Sheet)
    // Input: GET ?period=2025
    // Output: { items: [{ ma_so, name_vi, value }], total_assets, total_equity, errors }
    // Service: FsService.generateBC01() — đọc số dư từ AccountRepository
    // Kiểm tra: Tổng Tài sản (280) phải = Tổng Nguồn vốn (440) — nếu không = errors
    // Rủi ro: R005 — Sai tài khoản → sai chỉ tiêu BC. Sai số dư đầu kỳ → sai toàn bộ BC
    // Tuân thủ: Circular 99/2025/TT-BTC — Mẫu số B01-DN
    public function bc01(): void
    {
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC01($period);
        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $this->fs->validateBC01($data),
            'total_assets' => $this->findValue($data, '280'),
            'total_equity' => $this->findValue($data, '440'),
        ]);
    }

    // NGHIỆP VỤ: BC 02 — Báo cáo Kết quả Hoạt động Kinh doanh (Income Statement)
    // Input: GET ?period=2025
    // Output: { items, net_profit (60), errors }
    // Service: FsService.generateBC02() — tổng hợp doanh thu, chi phí, lợi nhuận
    // Kiểm tra: Lợi nhuận gộp (20) = 511 - 632 - 333. LNST (60) = tổng thu nhập - tổng chi phí
    // Rủi ro: Sai kết chuyển 511,632,641,642,635,811 → lợi nhuận sai → thuế TNDN sai
    // Tuân thủ: TT 99 — Mẫu số B02-DN. Chỉ tiêu 60 = cơ sở tính thuế TNDN
    public function bc02(): void
    {
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC02($period);
        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $this->fs->validateBC02($data),
            'net_profit' => $this->findValue($data, '60'),
        ]);
    }

    // NGHIỆP VỤ: Báo cáo tổng hợp TT99 — xuất đồng thời BC01 + BC02 + BC03 + validation
    // Input: GET ?period=2025
    // Output: { items (BC01+BC02 merged), cash_flow (BC03), total_assets, total_equity, net_profit, closing_cash, errors }
    // Service: Gọi generateBC01, generateBC02, generateBC03, validate mỗi BC, prior period values
    // Kiểm tra tổng thể: BC01 tổng TS=NV, BC03 closing cash = BC01 cash
    // Rủi ro: Prior period values dùng để phát hiện biến động bất thường
    // Mục đích: Giao diện tổng hợp cho Kế toán trưởng kiểm tra nhanh trước khi nộp
    public function tt99(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $bc01 = $this->fs->generateBC01($period);
        $bc02 = $this->fs->generateBC02($period);
        $bc03 = $this->fs->generateBC03($period);
        $errors = array_merge($this->fs->validateBC01($bc01), $this->fs->validateBC02($bc02), $this->fs->validateBC03($bc03));
        $prior = $this->fs->getPriorPeriodValues('BC01', $period);
        $priorIncome = $this->fs->getPriorPeriodValues('BC02', $period);
        JsonResponse::ok([
            'period' => $period,
            'items' => array_merge(
                array_map(fn($r) => ['ma_so' => 'BC01_'.$r['ma_so'], 'name_vi' => $r['name_vi'], 'value' => $r['value'], 'prior' => $prior[$r['ma_so']] ?? 0], $bc01),
                array_map(fn($r) => ['ma_so' => 'BC02_'.$r['ma_so'], 'name_vi' => $r['name_vi'], 'value' => $r['value'], 'prior' => $priorIncome[$r['ma_so']] ?? 0], $bc02)
            ),
            'cash_flow' => $bc03,
            'errors' => $errors,
            'total_assets' => $this->findValue($bc01, '280'),
            'total_equity' => $this->findValue($bc01, '440'),
            'net_profit' => $this->findValue($bc02, '60'),
            'closing_cash' => $this->findValue($bc03, '70'),
        ]);
    }

    public function viewBC01(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc01.php';
    }

    public function viewBC02(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc02.php';
    }

    // NGHIỆP VỤ: BC 03 — Báo cáo Lưu chuyển Tiền tệ (Cash Flow Statement)
    // Input: GET ?period=2025
    // Output: { items, net_cash_flow (50), closing_cash (70), bc01_closing_cash, errors }
    // Service: FsService.generateBC03()
    // Kiểm tra chéo: Số dư tiền cuối kỳ (70) phải = Tiền trên BC01 (chỉ tiêu 110)
    // Sai lệch > 1 VND → errors. So sánh với prior period values
    // Rủi ro: BC03 không khớp BC01 → BCTC không hợp lệ → audit fail
    // Tuân thủ: TT 99 — Mẫu số B03-DN, VAS 24
    public function bc03(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC03($period);
        $bc01 = $this->fs->generateBC01($period);
        $bc01Cash = $this->findValue($bc01, '110');

        $errors = $this->fs->validateBC03($data);
        $ms70 = $this->findValue($data, '70');
        if (abs($ms70 - $bc01Cash) > 1) {
            $errors[] = "Tiền cuối kỳ trên BC 03 ({$ms70}) không khớp với Tiền trên BC 01 ({$bc01Cash})";
        }
        $prior = $this->fs->getPriorPeriodValues('BC03', $period);

        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $errors,
            'net_cash_flow' => $this->findValue($data, '50'),
            'closing_cash' => $ms70,
            'bc01_closing_cash' => $bc01Cash,
            'prior' => $prior,
        ]);
    }

    // NGHIỆP VỤ: BC 03 — Phương pháp Trực tiếp (Direct Method)
    // Input: GET ?period=2025
    // Output: { items, net_cash_flow (50), closing_cash (70), errors }
    // Service: FsService.generateBC03Direct()
    // Kiểm tra chéo: Số dư tiền cuối kỳ (70) phải = Tiền trên BC01 (chỉ tiêu 110)
    // Phân loại: Cash receipt/payment từ đối ứng TK tiền (111/112) với opponent account
    public function bc03Direct(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC03Direct($period);
        $bc01 = $this->fs->generateBC01($period);
        $bc01Cash = $this->findValue($bc01, '110');

        $errors = $this->fs->validateBC03Direct($data);
        $ms70 = $this->findValue($data, '70');
        if (abs($ms70 - $bc01Cash) > 1) {
            $errors[] = "Tiền cuối kỳ trên BC 03 (trực tiếp) ({$ms70}) không khớp với Tiền trên BC 01 ({$bc01Cash})";
        }

        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $errors,
            'net_cash_flow' => $this->findValue($data, '50'),
            'closing_cash' => $ms70,
            'bc01_closing_cash' => $bc01Cash,
        ]);
    }

    public function viewBC03(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc03.php';
    }

    public function viewTT99(): void
    {
        require __DIR__ . '/../../../../../public/views/bc09.php';
    }

    //
    // NGHIỆP VỤ: Xuất BC01 dạng XBRL theo Taxonomy GDT
    // Input: GET ?period=2025
    // Output: file .xbrl (Content-Type: application/xml)
    // Service: XbrlGenerator.generateBC01() — map fs_line_items → tags GDT
    // Tuân thủ: XBRL 2.1 + GDT namespace http://www.gdt.gov.vn/2025/btc
    // Rủi ro: Sai namespace → GDT từ chối. Sai period → BC kỳ khác.
    // Ảnh hưởng: Bắt buộc cho DN >₫10B doanh thu (Thông tư 99/2025/TT-BTC)
    //
    public function exportXbrlBC01(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC01($period);

        $errors = $this->fs->validateBC01($data);
        if (!empty($errors)) {
            JsonResponse::error('BC01 mất cân đối, không thể xuất XBRL: ' . implode('; ', $errors), 422);
            return;
        }

        $xml = $this->xbrl->generateBC01($data, $period, '0123456789', 'Đơn vị báo cáo');
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="BC01_' . $period . '.xbrl"');
        echo $xml;
    }

    public function exportXbrlBC02(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC02($period);

        $errors = $this->fs->validateBC02($data);
        if (!empty($errors)) {
            JsonResponse::error('BC02 không hợp lệ, không thể xuất XBRL: ' . implode('; ', $errors), 422);
            return;
        }

        $xml = $this->xbrl->generateBC02($data, $period, '0123456789', 'Đơn vị báo cáo');
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="BC02_' . $period . '.xbrl"');
        echo $xml;
    }

    public function exportXbrlBC03(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC03($period);

        $errors = $this->fs->validateBC03($data);
        if (!empty($errors)) {
            JsonResponse::error('BC03 không hợp lệ, không thể xuất XBRL: ' . implode('; ', $errors), 422);
            return;
        }

        $xml = $this->xbrl->generateBC03($data, $period, '0123456789', 'Đơn vị báo cáo');
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="BC03_' . $period . '.xbrl"');
        echo $xml;
    }

    private function findValue(array $items, string $maSo): float
    {
        foreach ($items as $r) {
            if ($r['ma_so'] === $maSo) return $r['value'];
        }
        return 0;
    }
}
