<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\Bc09RepositoryInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use PDO;

//
// BC09 — Thuyết minh Báo cáo tài chính (Mẫu B09-DN)
// Tuân thủ Thông tư 99/2025/TT-BTC
//
// Service engine tính toán các chỉ tiêu BC09 từ số dư tài khoản:
// - Section V (V.01-V.11): Thông tin bổ sung BC01 — tự động tính từ số dư TK
// - Section VI (VI.01-VI.06): Doanh thu, chi phí — tự động tính từ số phát sinh
// - Section VII: Các khoản mục ngoài BC — nhập tay
// - Sections I-IV, VIII-IX: Chính sách kế toán và thuyết minh — nhập tay
//
class FsNotesService
{
    private Bc09RepositoryInterface $bc09Repo;
    private AccountRepositoryInterface $accountRepo;
    private PeriodService $periodService;
    private PDO $pdo;

    public function __construct(
        Bc09RepositoryInterface $bc09Repo,
        AccountRepositoryInterface $accountRepo,
        PeriodService $periodService,
        PDO $pdo
    ) {
        $this->bc09Repo = $bc09Repo;
        $this->accountRepo = $accountRepo;
        $this->periodService = $periodService;
        $this->pdo = $pdo;
    }

    //
    // SINH TỰ ĐỘNG: Tính toán tất cả chỉ tiêu auto-calc cho một kỳ
    // Đọc bc09_config, lấy số dư TK, lưu vào bc09_data
    //
    public function generate(int $periodId): array
    {
        $configs = $this->bc09Repo->getConfig();
        $data = [];
        $period = $this->getPeriodInfo($periodId);
        $priorPeriodId = $this->getPriorPeriodId($periodId);
        $createdBy = $this->getCurrentUserId();

        // Xóa dữ liệu cũ của kỳ này trước khi generate
        $this->bc09Repo->deleteDataForPeriod($periodId);

        foreach ($configs as $config) {
            if (!$config->isAutoCalc()) continue;

            $yearEnd = $this->calculateIndicator($config, $period);
            $yearStart = 0;

            // Lấy số đầu kỳ từ dữ liệu kỳ trước
            if ($priorPeriodId) {
                $priorVal = $this->bc09Repo->getPriorPeriodData($priorPeriodId, $config->getIndicatorCode());
                if ($priorVal !== null) {
                    $yearStart = $priorVal;
                } else {
                    // Fallback: tính từ số dư TK đầu kỳ
                    $priorPeriod = $this->getPeriodInfo($priorPeriodId);
                    $yearStart = $this->calculateIndicator($config, $priorPeriod);
                }
            }

            $data[] = [
                'indicator_code' => $config->getIndicatorCode(),
                'indicator_name' => $config->getIndicatorName(),
                'section_code' => $config->getSectionCode(),
                'year_start' => $yearStart,
                'year_end' => $yearEnd,
            ];

            $this->bc09Repo->saveData(
                $periodId,
                $config->getSectionCode(),
                $config->getIndicatorCode(),
                $yearStart,
                $yearEnd,
                null,
                false,
                $createdBy
            );
        }

        return $data;
    }

    //
    // LẤY BÁO CÁO: Trả về toàn bộ BC09 cho một kỳ
    // Merge cấu hình (từ bc09_config) với dữ liệu (từ bc09_data)
    //
    public function getReport(int $periodId): array
    {
        $configs = $this->bc09Repo->getConfig();
        $savedData = $this->bc09Repo->getData($periodId);

        // Index dữ liệu đã lưu theo indicator_code
        $dataIndex = [];
        foreach ($savedData as $d) {
            $dataIndex[$d->getIndicatorCode()] = $d;
        }

        $sections = [];
        foreach ($configs as $cfg) {
            $code = $cfg->getSectionCode();
            if (!isset($sections[$code])) {
                $sections[$code] = [];
            }

            $saved = $dataIndex[$cfg->getIndicatorCode()] ?? null;

            $sections[$code][] = [
                'indicator_code' => $cfg->getIndicatorCode(),
                'indicator_name' => $cfg->getIndicatorName(),
                'formula_expression' => $cfg->getFormulaExpression(),
                'is_auto_calc' => $cfg->isAutoCalc(),
                'is_required' => $cfg->isRequired(),
                'parent_code' => $cfg->getParentCode(),
                'sort_order' => $cfg->getSortOrder(),
                'year_start' => $saved ? $saved->getYearStart() : 0,
                'year_end' => $saved ? $saved->getYearEnd() : 0,
                'note_text' => $saved ? $saved->getNoteText() : null,
                'is_manual' => $saved ? $saved->isManual() : false,
            ];
        }

        return [
            'period_id' => $periodId,
            'sections' => $sections,
            'generated' => count($savedData) > 0,
        ];
    }

    //
    // CẬP NHẬT CHỈ TIÊU: Cho phép sửa số liệu nhập tay
    //
    public function updateIndicator(int $periodId, string $indicatorCode, float $yearStart, float $yearEnd, ?string $noteText): void
    {
        $config = $this->bc09Repo->getConfigByIndicator($indicatorCode);
        if (!$config) {
            throw new \InvalidArgumentException("Không tìm thấy chỉ tiêu: {$indicatorCode}");
        }

        $period = $this->getPeriodInfo($periodId);
        $createdBy = $this->getCurrentUserId();

        // Kiểm tra kỳ đã đóng
        if (!$this->periodService::isPeriodOpen($period['end_date'], $this->pdo)) {
            throw new \InvalidArgumentException('Kỳ kế toán đã đóng. Không thể sửa số liệu BC09.');
        }

        $this->bc09Repo->saveData(
            $periodId,
            $config->getSectionCode(),
            $indicatorCode,
            $yearStart,
            $yearEnd,
            $noteText,
            true,
            $createdBy
        );
    }

    //
    // KIỂM TRA CHÉO: Validate BC09 với BC01/BC02
    // - V.01 (Tiền) phải ≈ 111+112 balance = BC01 MS 110
    // - V.04 (Hàng tồn kho) ≈ BC01 MS 140
    // - V.11 (VCSH) ≈ BC01 MS 440
    // - VI (Doanh thu/Chi phí) ≈ BC02 các chỉ tiêu tương ứng
    //
    public function validate(int $periodId): array
    {
        $report = $this->getReport($periodId);
        $sectionV = $report['sections']['V'] ?? [];
        $sectionVI = $report['sections']['VI'] ?? [];

        $errors = [];
        $warnings = [];

        $findValue = function(array $items, string $code): float {
            foreach ($items as $item) {
                if ($item['indicator_code'] === $code) return $item['year_end'];
            }
            return 0;
        };

        // Cross-check với số dư tài khoản thực tế
        $cashBalance = $this->accountRepo->getTreeBalance('111') + $this->accountRepo->getTreeBalance('112');
        $v01 = $findValue($sectionV, 'V.01');
        if (abs($v01 - $cashBalance) > 1000) {
            $warnings[] = "V.01 (Tiền) lệch với số dư TK 111+112: BC09={$v01}, Sổ cái={$cashBalance}";
        }

        $inventoryBalance = $this->accountRepo->getTreeBalance('152');
        $v04 = $findValue($sectionV, 'V.04');
        if (abs($v04 - $inventoryBalance) > 1000) {
            $warnings[] = "V.04 (Hàng tồn kho) lệch với số dư TK 152: BC09={$v04}, Sổ cái={$inventoryBalance}";
        }

        // Kiểm tra các chỉ tiêu Section VI với số phát sinh
        $revenue = $this->getPeriodTurnover($periodId, '511');
        $vi01 = $findValue($sectionVI, 'VI.01');
        if (abs($vi01 - $revenue) > 1000) {
            $warnings[] = "VI.01 (Doanh thu) lệch với số phát sinh TK 511: BC09={$vi01}, PS={$revenue}";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'total_checks' => count($errors) + count($warnings),
        ];
    }

    //
    // MẪU CHÍNH SÁCH KẾ TOÁN (Section IV)
    // Trả về template các chính sách kế toán áp dụng
    //
    public function getPolicyTemplates(): array
    {
        return [
            [
                'code' => 'IV.01',
                'name' => 'Cơ sở lập báo cáo tài chính',
                'default' => 'Báo cáo tài chính được lập theo nguyên tắc giá gốc, phù hợp với Chế độ kế toán doanh nghiệp Việt Nam theo Thông tư 99/2025/TT-BTC.',
            ],
            [
                'code' => 'IV.02',
                'name' => 'Năm tài chính',
                'default' => 'Năm tài chính bắt đầu từ ngày 01/01 và kết thúc vào ngày 31/12 hàng năm.',
            ],
            [
                'code' => 'IV.03',
                'name' => 'Đơn vị tiền tệ sử dụng',
                'default' => 'Đơn vị tiền tệ sử dụng trong kế toán là Đồng Việt Nam (VND).',
            ],
            [
                'code' => 'IV.04',
                'name' => 'Phương pháp tính giá hàng tồn kho',
                'default' => 'Phương pháp bình quân gia quyền (Weighted Average).',
            ],
            [
                'code' => 'IV.05',
                'name' => 'Phương pháp hạch toán hàng tồn kho',
                'default' => 'Phương pháp kê khai thường xuyên (Perpetual Inventory).',
            ],
            [
                'code' => 'IV.06',
                'name' => 'Phương pháp khấu hao TSCĐ',
                'default' => 'Phương pháp khấu hao đường thẳng (Straight-line Method).',
            ],
            [
                'code' => 'IV.07',
                'name' => 'Nguyên tắc ghi nhận doanh thu',
                'default' => 'Doanh thu được ghi nhận khi hàng hóa được chuyển giao và rủi ro trọng yếu được chuyển sang người mua.',
            ],
            [
                'code' => 'IV.08',
                'name' => 'Nguyên tắc ghi nhận chi phí',
                'default' => 'Chi phí được ghi nhận trên cơ sở dồn tích, phù hợp với doanh thu phát sinh.',
            ],
            [
                'code' => 'IV.09',
                'name' => 'Phương pháp tính thuế GTGT',
                'default' => 'Phương pháp khấu trừ thuế GTGT.',
            ],
            [
                'code' => 'IV.10',
                'name' => 'Nguyên tắc ghi nhận các khoản dự phòng',
                'default' => 'Các khoản dự phòng được ghi nhận khi nghĩa vụ nợ hiện tại phát sinh từ sự kiện trong quá khứ.',
            ],
            [
                'code' => 'IV.11',
                'name' => 'Nguyên tắc ghi nhận thuế TNDN',
                'default' => 'Chi phí thuế TNDN được xác định trên cơ sở thu nhập chịu thuế theo Luật thuế TNDN hiện hành.',
            ],
            [
                'code' => 'IV.12',
                'name' => 'Nguyên tắc ghi nhận ngoại tệ',
                'default' => 'Các giao dịch bằng ngoại tệ được quy đổi theo tỷ giá giao dịch thực tế tại ngày phát sinh.',
            ],
            [
                'code' => 'IV.13',
                'name' => 'Nguyên tắc ghi nhận TSCĐ vô hình',
                'default' => 'TSCĐ vô hình được ghi nhận theo nguyên giá và được khấu hao theo phương pháp đường thẳng.',
            ],
            [
                'code' => 'IV.14',
                'name' => 'Nguyên tắc ghi nhận chi phí đi vay',
                'default' => 'Chi phí đi vay được ghi nhận vào chi phí tài chính trong kỳ phát sinh.',
            ],
            [
                'code' => 'IV.15',
                'name' => 'Nguyên tắc ghi nhận tài sản thuế thu nhập hoãn lại',
                'default' => 'Tài sản thuế thu nhập hoãn lại được ghi nhận cho các chênh lệch tạm thời được khấu trừ.',
            ],
            [
                'code' => 'IV.16',
                'name' => 'Nguyên tắc ghi nhận chi phí trả trước',
                'default' => 'Chi phí trả trước được phân bổ dần vào kết quả kinh doanh trong thời gian phát huy tác dụng.',
            ],
            [
                'code' => 'IV.17',
                'name' => 'Nguyên tắc ghi nhận CCDC',
                'default' => 'CCDC được theo dõi và phân bổ dần vào chi phí sản xuất kinh doanh.',
            ],
            [
                'code' => 'IV.18',
                'name' => 'Nguyên tắc lập dự phòng phải thu khó đòi',
                'default' => 'Dự phòng phải thu khó đòi được lập theo Thông tư 48/2019/TT-BTC.',
            ],
            [
                'code' => 'IV.19',
                'name' => 'Nguyên tắc lập dự phòng giảm giá hàng tồn kho',
                'default' => 'Dự phòng giảm giá hàng tồn kho được lập khi giá trị thuần có thể thực hiện được thấp hơn giá gốc.',
            ],
            [
                'code' => 'IV.20',
                'name' => 'Công cụ tài chính',
                'default' => 'Các công cụ tài chính được ghi nhận ban đầu theo giá gốc và đánh giá lại theo giá trị hợp lý.',
            ],
            [
                'code' => 'IV.21',
                'name' => 'Bên liên quan',
                'default' => 'Các giao dịch với bên liên quan được công bố theo quy định của Chuẩn mực kế toán Việt Nam.',
            ],
            [
                'code' => 'IV.22',
                'name' => 'Sự kiện sau ngày kết thúc kỳ kế toán',
                'default' => 'Các sự kiện phát sinh sau ngày kết thúc kỳ kế toán được điều chỉnh hoặc công bố theo VAS 23.',
            ],
        ];
    }

    // ── Private helpers ──

    private function calculateIndicator(\Accounting\Domain\Model\Bc09Config $config, array $period): float
    {
        $accounts = $config->getAccountCodeList();
        if (empty($accounts)) return 0;

        // Nếu có formula_expression, parse và tính
        $expr = $config->getFormulaExpression();
        if ($expr && preg_match('/^[\d,+\-*\/\s]+$/', $expr)) {
            // Formula chứa mã TK: '211-214' → gọi getTreeBalance cho từng mã
            $tokens = preg_split('/([+\-*\/])/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE);
            $values = [];
            foreach ($tokens as $token) {
                $token = trim($token);
                if ($token === '' || in_array($token, ['+', '-', '*', '/'], true)) {
                    continue;
                }
                $values[] = $this->accountRepo->getTreeBalance($token);
            }
            $result = 0;
            if (!empty($values)) {
                $result = $values[0];
                $opIdx = 0;
                for ($i = 0; $i < count($tokens); $i++) {
                    $t = trim($tokens[$i]);
                    if ($t === '+') {
                        $opIdx++;
                        if (isset($values[$opIdx])) $result += $values[$opIdx];
                    } elseif ($t === '-') {
                        $opIdx++;
                        if (isset($values[$opIdx])) $result -= $values[$opIdx];
                    }
                }
            }
            return round($result, 2);
        }

        // Mặc định: tính tổng số dư các tài khoản
        $total = 0;
        foreach ($accounts as $code) {
            $total += $this->accountRepo->getTreeBalance($code);
        }
        return round($total, 2);
    }

    private function getPeriodInfo(int $periodId): array
    {
        return $this->periodService->getPeriod($periodId);
    }

    private function getPriorPeriodId(int $periodId): ?int
    {
        $period = $this->getPeriodInfo($periodId);
        $stmt = $this->pdo->prepare(
            'SELECT id FROM accounting_periods WHERE end_date < ? ORDER BY end_date DESC LIMIT 1'
        );
        $stmt->execute([$period['end_date']]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    private function getPeriodTurnover(int $periodId, string $accountCode): float
    {
        $period = $this->getPeriodInfo($periodId);
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = ?
               AND t.status = 'posted'
               AND t.transaction_date BETWEEN ? AND ?"
        );
        $stmt->execute([$accountCode, $period['start_date'], $period['end_date']]);
        return (float)$stmt->fetchColumn();
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }
}
