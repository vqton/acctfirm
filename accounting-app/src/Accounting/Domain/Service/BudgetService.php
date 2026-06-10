<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use PDO;

/**
 * BudgetService — Dịch vụ lập và theo dõi dự toán ngân sách.
 *
 * Nghiệp vụ: Cho phép tạo kịch bản dự toán, thiết lập số dự toán cho từng tài khoản theo kỳ,
 * so sánh dự toán với thực tế (phân tích chênh lệch), và xuất báo cáo dự toán ra CSV.
 *
 * Ràng buộc:
 * - Mỗi kịch bản thuộc một năm và một loại (operating, investment, cashflow).
 * - Một kịch bản ở trạng thái "draft" có thể sửa; khi "active" dùng cho dashboard.
 * - Số dự toán được lưu theo cặp (scenario_id, period_code, account_code) — upsert.
 * - Báo cáo chênh lệch lấy số thực tế từ ledger_entries theo kỳ.
 * - Dashboard tổng hợp theo tháng cho kịch bản đang active.
 */
class BudgetService
{
    private PDO $pdo;
    private ReportExportService $export;

    /**
     * Khởi tạo service với các dependency.
     *
     * @param PDO $pdo Kết nối PDO đến MySQL.
     * @param ReportExportService $export Dịch vụ xuất báo cáo CSV.
     */
    public function __construct(PDO $pdo, ReportExportService $export)
    {
        $this->pdo = $pdo;
        $this->export = $export;
    }

    /**
     * Tạo một kịch bản dự toán mới.
     *
     * Nghiệp vụ: Lập kế hoạch ngân sách cho một năm cụ thể.
     * Kịch bản mới tạo ở trạng thái "draft" để cho phép chỉnh sửa trước khi kích hoạt.
     *
     * @param string $name Tên kịch bản dự toán (ví dụ: "Dự toán 2025 - Phương án 1").
     * @param int $year Năm áp dụng dự toán.
     * @param string $type Loại kịch bản: 'operating' (hoạt động), 'investment' (đầu tư), 'cashflow' (dòng tiền). Mặc định 'operating'.
     * @param string|null $notes Ghi chú bổ sung cho kịch bản.
     * @param string|null $createdBy Mã người tạo (user_id).
     *
     * @return array Mảng chứa 'id', 'name', 'year' của kịch bản vừa tạo.
     */
    public function createScenario(string $name, int $year, string $type = 'operating', ?string $notes = null, ?string $createdBy = null): array
    {
        $id = uniqid('bsc_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO budget_scenarios (id,name,year,type,status,notes,created_by,created_at) VALUES (?,?,?,?,"draft",?,?,NOW())'
        );
        $stmt->execute([$id, $name, $year, $type, $notes, $createdBy]);
        return ['id' => $id, 'name' => $name, 'year' => $year];
    }

    /**
     * Kích hoạt một kịch bản dự toán.
     *
     * Chuyển trạng thái từ "draft" sang "active".
     * Kịch bản active được sử dụng trong dashboard tổng hợp ngân sách.
     *
     * @param string $id Mã kịch bản (bsc_...).
     *
     * @return void
     */
    public function activateScenario(string $id): void
    {
        $this->pdo->prepare("UPDATE budget_scenarios SET status='active' WHERE id=?")->execute([$id]);
    }

    /**
     * Lấy danh sách tất cả kịch bản dự toán của một năm.
     *
     * Kết quả sắp xếp theo thời gian tạo giảm dần (mới nhất lên đầu).
     *
     * @param int $year Năm cần lấy danh sách kịch bản.
     *
     * @return array Danh sách kịch bản dưới dạng mảng các dòng (PDO::FETCH_ASSOC).
     */
    public function getScenarios(int $year): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM budget_scenarios WHERE year = ? ORDER BY created_at DESC');
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Thiết lập hoặc cập nhật số dự toán cho một tài khoản trong một kỳ.
     *
     * Nghiệp vụ: Ghi nhận số dự toán theo cặp (kịch bản, kỳ, tài khoản).
     * Nếu bản ghi đã tồn tại (cùng scenario_id, period_code, account_code) thì cập nhật số tiền và ghi chú.
     *
     * @param string $scenarioId Mã kịch bản dự toán.
     * @param string $periodCode Mã kỳ kế toán (định dạng 'YYYY-MM').
     * @param string $accountCode Mã tài khoản kế toán (ví dụ: 511, 642).
     * @param float $amount Số tiền dự toán.
     * @param string|null $notes Ghi chú cho dòng dự toán.
     *
     * @return void
     */
    public function setBudget(string $scenarioId, string $periodCode, string $accountCode, float $amount, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO budget_plans (scenario_id,period_code,account_code,budget_amount,notes,created_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE budget_amount=VALUES(budget_amount),notes=VALUES(notes)'
        );
        $stmt->execute([$scenarioId, $periodCode, $accountCode, $amount, $notes]);
    }

    /**
     * Lấy tất cả dòng dự toán của một kịch bản.
     *
     * Kết quả sắp xếp theo period_code và account_code để dễ đọc.
     *
     * @param string $scenarioId Mã kịch bản dự toán.
     *
     * @return array Danh sách dòng dự toán dưới dạng mảng các dòng (PDO::FETCH_ASSOC).
     */
    public function getBudgetLines(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM budget_plans WHERE scenario_id = ? ORDER BY period_code, account_code');
        $stmt->execute([$scenarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Báo cáo phân tích chênh lệch giữa dự toán và thực tế.
     *
     * Nghiệp vụ: So sánh số dự toán (budget_amount) với số phát sinh thực tế
     * (lấy từ ledger_entries theo kỳ) cho từng tài khoản.
     * Chênh lệch = budget_amount - actual_debit (dương: chi tiêu ít hơn dự toán, âm: vượt dự toán).
     *
     * Rủi ro: Nếu ledger_entries chưa được ghi nhận đầy đủ cho kỳ, số thực tế có thể thiếu.
     * LEFT JOIN đảm bảo dòng dự toán không có số thực tế vẫn hiển thị.
     *
     * @param string $scenarioId Mã kịch bản dự toán.
     *
     * @return array Báo cáo chênh lệch — mỗi dòng gồm period_code, account_code, account_name,
     *               budget_amount, actual_debit, actual_credit, variance.
     */
    public function getVarianceReport(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bp.period_code, bp.account_code, a.name as account_name,
                   bp.budget_amount,
                   COALESCE(SUM(CASE WHEN le.is_debit=1 THEN le.amount ELSE 0 END), 0) as actual_debit,
                   COALESCE(SUM(CASE WHEN le.is_debit=0 THEN le.amount ELSE 0 END), 0) as actual_credit,
                   (bp.budget_amount - COALESCE(SUM(CASE WHEN le.is_debit=1 THEN le.amount ELSE 0 END), 0)) as variance
            FROM budget_plans bp
            JOIN accounts a ON bp.account_code = a.code
            LEFT JOIN ledger_entries le ON le.account_id = a.id
                AND DATE_FORMAT(le.created_at, '%Y-%m') = bp.period_code
            WHERE bp.scenario_id = ?
            GROUP BY bp.period_code, bp.account_code, a.name, bp.budget_amount
            ORDER BY bp.period_code, bp.account_code
        ");
        $stmt->execute([$scenarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tổng hợp dự toán theo kịch bản.
     *
     * Nghiệp vụ: Tính tổng số dự toán, tổng doanh thu (khoản mục loại revenue/income),
     * và tổng chi phí (khoản mục loại expense/cost) cho một kịch bản.
     * Dùng để hiển thị thông tin nhanh trên giao diện.
     *
     * @param string $scenarioId Mã kịch bản dự toán.
     *
     * @return array Mảng chứa: total_lines (số dòng), total_budget (tổng dự toán),
     *               total_revenue_budget (tổng doanh thu), total_expense_budget (tổng chi phí).
     */
    public function getSummary(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_lines,
                   COALESCE(SUM(budget_amount),0) as total_budget,
                   COALESCE(SUM(CASE WHEN a.type IN ('revenue','income') THEN budget_amount ELSE 0 END),0) as total_revenue_budget,
                   COALESCE(SUM(CASE WHEN a.type IN ('expense','cost') THEN budget_amount ELSE 0 END),0) as total_expense_budget
            FROM budget_plans bp
            JOIN accounts a ON bp.account_code = a.code
            WHERE bp.scenario_id = ?
        ");
        $stmt->execute([$scenarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Dashboard dự toán theo năm.
     *
     * Nghiệp vụ: Tổng hợp số dự toán và số thực tế theo tháng cho kịch bản active của năm.
     * Trả về danh sách kịch bản và số liệu tổng hợp theo tháng.
     *
     * @param int $year Năm cần xem dashboard.
     *
     * @return array Mảng chứa:
     *               - 'scenarios': danh sách kịch bản trong năm.
     *               - 'summary_by_month': số dự toán và thực tế nhóm theo tháng.
     */
    public function getDashboard(int $year): array
    {
        $scenarios = $this->getScenarios($year);
        $stmt = $this->pdo->prepare("
            SELECT bp.period_code,
                   SUM(bp.budget_amount) as budget,
                   COALESCE(SUM(le.amount),0) as actual
            FROM budget_plans bp
            JOIN budget_scenarios bs ON bp.scenario_id = bs.id
            LEFT JOIN accounts a ON bp.account_code = a.code
            LEFT JOIN ledger_entries le ON le.account_id = a.id
                AND DATE_FORMAT(le.created_at, '%Y-%m') = bp.period_code
                AND le.is_debit = 1
            WHERE bs.year = ? AND bs.status = 'active'
            GROUP BY bp.period_code
            ORDER BY bp.period_code
        ");
        $stmt->execute([$year]);
        return [
            'scenarios' => $scenarios,
            'summary_by_month' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Xuất báo cáo chênh lệch dự toán ra file CSV.
     *
     * Nghiệp vụ: Lấy báo cáo chênh lệch từ getVarianceReport() và xuất thành file CSV
     * với các cột: Kỳ, TK, Diễn giải, Dự toán, Thực tế Nợ, Thực tế Có, Chênh lệch.
     * Tên file tự động sinh theo ngày: ngan_sach_YYYYMMDD.csv.
     *
     * @param string $scenarioId Mã kịch bản dự toán.
     *
     * @return array Kết quả trả về từ ReportExportService::exportCsv().
     */
    public function exportVarianceReport(string $scenarioId): array
    {
        $report = $this->getVarianceReport($scenarioId);
        $headers = ['Kỳ', 'TK', 'Diễn giải', 'Dự toán', 'Thực tế Nợ', 'Thực tế Có', 'Chênh lệch'];
        $data = [];
        foreach ($report as $r) {
            $data[] = [$r['period_code'], $r['account_code'], $r['account_name'],
                $r['budget_amount'], $r['actual_debit'], $r['actual_credit'], $r['variance']];
        }
        return $this->export->exportCsv($headers, $data, 'ngan_sach_' . date('Ymd') . '.csv');
    }
}
