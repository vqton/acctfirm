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
 *   - BC 01: Bảng Cân đối kế toán
 *   - BC 02: Báo cáo KQKD
 *   - BC 03: Báo cáo Lưu chuyển Tiền tệ
 *   - Tuân thủ Circular 99/2025/TT-BTC
 *   - Xuất XBRL theo Taxonomy GDT
 *
 * API endpoints:
 *   GET /api/fs/bc01 — BC01
 *   GET /api/fs/bc02 — BC02
 *   GET /api/fs/bc03 — BC03
 *   GET /api/fs/bc03/direct — BC03 trực tiếp
 *   GET /api/fs/tt99 — Tổng hợp TT99
 *   POST /api/fs/manual-values — Lưu chỉ tiêu nhập tay
 *   GET /api/fs/xbrl/bc01 — Xuất XBRL BC01
 *   GET /api/fs/xbrl/bc02 — Xuất XBRL BC02
 *   GET /api/fs/xbrl/bc03 — Xuất XBRL BC03
 *
 * Rủi ro:
 *   - R005: Sai tài khoản → sai chỉ tiêu
 *   - BC01: Tổng TS (280) = Tổng NV (440)
 *
 * Tích hợp:
 *   - FsService đọc AccountRepository
 *   - PeriodService
 */
class FsController
{
    private FsService $fs;
    private XbrlGenerator $xbrl;

    public function __construct(FsService $fs, XbrlGenerator $xbrl) { $this->fs = $fs; $this->xbrl = $xbrl; }

    /**
     * BC 01 — Bảng Cân đối kế toán
     *
     * @return void
     */
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

    /**
     * BC 02 — Báo cáo KQKD
     *
     * @return void
     */
    public function bc02(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $manualValues = $this->fs->getManualValues('BC02', $period);
        $data = $this->fs->generateBC02($period, $manualValues);
        $prior = $this->fs->getPriorPeriodValues('BC02', $period);
        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'manual' => $manualValues,
            'errors' => $this->fs->validateBC02($data),
            'warnings' => $this->fs->getBC02Warnings($data),
            'net_profit' => $this->findValue($data, '60'),
            'prior' => $prior,
        ]);
    }

    /**
     * Báo cáo tổng hợp TT99 — BC01+BC02+BC03+validation
     *
     * @return void
     */
    public function tt99(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $bc01 = $this->fs->generateBC01($period);
        $bc02 = $this->fs->generateBC02($period);
        $manualValues = $this->fs->getManualValues('BC03', $period);
        $bc03 = $this->fs->generateBC03($period, $manualValues);
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

    /**
     * View BC01
     *
     * @return void
     */
    public function viewBC01(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc01.php';
    }

    /**
     * View BC02
     *
     * @return void
     */
    public function viewBC02(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc02.php';
    }

    /**
     * BC 03 — Báo cáo Lưu chuyển Tiền tệ
     *
     * @return void
     */
    public function bc03(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $manualValues = $this->fs->getManualValues('BC03', $period);
        $data = $this->fs->generateBC03($period, $manualValues);
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

    /**
     * BC 03 — Phương pháp Trực tiếp
     *
     * @return void
     */
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

    /**
     * View BC03
     *
     * @return void
     */
    public function viewBC03(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc03.php';
    }

    /**
     * View TT99
     *
     * @return void
     */
    public function viewTT99(): void
    {
        require __DIR__ . '/../../../../../public/views/bc09.php';
    }

    /**
     * Xuất BC01 dạng XBRL theo Taxonomy GDT
     *
     * @return void
     */
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

    /**
     * Lưu giá trị nhập tay cho chỉ tiêu BC
     *
     * @return void
     */
    public function saveManualValues(): void
    {
        Auth::requirePermission('report', 'update');
        Auth::checkCsrf();
        $body = json_decode(file_get_contents('php://input'), true);
        $period = $body['period'] ?? date('Y');
        $statement = $body['statement'] ?? 'BC02';
        $values = $body['values'] ?? [];
        $user = $_SESSION['user']['username'] ?? 'system';
        $allowedMap = [
            'BC02' => ['21', '70', '71'],
            'BC03' => ['02', '04', '07', '14', '15', '16', '17', '22', '24', '26', '27', '35', '36', '61'],
        ];
        $allowed = $allowedMap[$statement] ?? $allowedMap['BC02'];
        $filtered = [];
        foreach ($values as $k => $v) {
            if (in_array((string)$k, $allowed, true)) {
                $filtered[(string)$k] = (float)$v;
            }
        }
        $this->fs->saveManualValues($statement, $period, $filtered, $user);
        JsonResponse::ok(['success' => true, 'manual' => $filtered]);
    }

    /**
     * Xuất BC02 dạng XBRL
     *
     * @return void
     */
    public function exportXbrlBC02(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y');
        $manualValues = $this->fs->getManualValues('BC02', $period);
        $data = $this->fs->generateBC02($period, $manualValues);

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

    /**
     * Xuất BC03 dạng XBRL
     *
     * @return void
     */
    public function exportXbrlBC03(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y');
        $manualValues = $this->fs->getManualValues('BC03', $period);
        $data = $this->fs->generateBC03($period, $manualValues);

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

    /**
     * Tìm giá trị theo mã số trong mảng items
     *
     * @param array $items Mảng items
     * @param string $maSo Mã số cần tìm
     * @return float
     */
    private function findValue(array $items, string $maSo): float
    {
        foreach ($items as $r) {
            if ($r['ma_so'] === $maSo) return $r['value'];
        }
        return 0;
    }
}
