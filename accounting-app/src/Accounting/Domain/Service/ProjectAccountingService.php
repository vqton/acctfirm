<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\ProjectRepositoryInterface;
use PDO;

// Quản lý kế toán dự án — theo dõi ngân sách, chi phí thực tế, doanh thu theo tiến độ
// và xuất hóa đơn tạm ứng cho từng dự án.
//
// Nghiệp vụ chính:
// - Phân bổ chi phí từ ledger_entries vào dự án (allocateCost)
// - Ghi nhận doanh thu theo tỷ lệ hoàn thành (recognizeRevenue — percentage of completion)
// - Quản lý ngân sách chi tiết theo tài khoản (setBudgetLine/updateBudgetSpent)
// - Tạo chứng từ xuất hóa đơn theo tiến độ (createProgressBilling)
// - Kết thúc dự án khi hoàn thành (finalizeProject)
// - Báo cáo tổng hợp (getDashboardStats, getProjectReport, getActiveProjectsList)
// - Xuất báo cáo CSV (exportProjectReport)
//
// Ảnh hưởng:
// - TK 154 (CPSXKD dở dang) khi phân bổ chi phí
// - TK 632 (Giá vốn) khi ghi nhận doanh thu
// - BC01 chỉ tiêu "Chi phí SXKD dở dang" thay đổi
// - BC02 chỉ tiêu "Doanh thu" và "Giá vốn" thay đổi
//
// RỦI RO: Nếu allocateCost không tìm thấy bút toán phù hợp → throw InvalidArgumentException
// RỦI RO: Nếu finalizeProject khi dự án không active → throw InvalidArgumentException
// RỦI RO: Nếu recognizeRevenue không tìm thấy dự án → throw InvalidArgumentException
//
class ProjectAccountingService
{
    private ProjectRepositoryInterface $projectRepo;
    private PDO $pdo;
    private ReportExportService $export;

    /**
     * Khởi tạo ProjectAccountingService với các dependency
     *
     * @param ProjectRepositoryInterface $projectRepo Repository quản lý dự án — thao tác với bảng projects
     * @param PDO $pdo Kết nối PDO đến MySQL — dùng cho truy vấn trực tiếp không qua repository
     * @param ReportExportService $export Dịch vụ xuất báo cáo CSV/HTML — dùng trong exportProjectReport
     */
    public function __construct(ProjectRepositoryInterface $projectRepo, PDO $pdo, ReportExportService $export)
    {
        $this->projectRepo = $projectRepo;
        $this->pdo = $pdo;
        $this->export = $export;
    }

    /**
     * Lấy thống kê tổng quan cho dashboard dự án
     *
     * Nghiệp vụ: Tổng hợp số lượng dự án, ngân sách, chi phí và doanh thu đã xuất hóa đơn
     * từ bảng projects. Dùng cho màn hình dashboard tổng quan.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     completed: int,
     *     total_budget: float,
     *     total_cost: float,
     *     total_billed: float
     * } Mảng chứa tổng số dự án, số đang hoạt động, số hoàn thành, tổng ngân sách,
     *   tổng chi phí thực tế và tổng đã xuất hóa đơn
     */
    public function getDashboardStats(): array
    {
        return $this->pdo->query("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                   COALESCE(SUM(budget),0) as total_budget,
                   COALESCE(SUM(actual_cost),0) as total_cost,
                   COALESCE(SUM(billed_amount),0) as total_billed
            FROM projects
        ")->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy báo cáo chi tiết cho một dự án
     *
     * Nghiệp vụ: Tổng hợp tất cả thông tin dự án bao gồm thông tin chung, chi phí thực tế,
     * chênh lệch ngân sách (variance), tỷ lệ hoàn thành, chi phí theo tài khoản,
     * danh sách bút toán và chứng từ xuất hóa đơn theo tiến độ.
     *
     * @param string $projectId Mã định danh dự án (uniqid)
     * @return array{
     *     project: array,
     *     actual_debit: float,
     *     actual_credit: float,
     *     variance: float,
     *     completion_pct: float,
     *     cost_summary: array,
     *     transactions: array,
     *     billings: array
     * } Mảng chứa thông tin chi tiết và báo cáo tài chính của dự án
     * @throws \InvalidArgumentException Nếu không tìm thấy dự án với projectId đã cho
     */
    public function getProjectReport(string $projectId): array
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');

        [$actualDebit, $actualCredit] = $this->getActualTotals($projectId);
        return [
            'project' => $project->toArray(),
            'actual_debit' => $actualDebit,
            'actual_credit' => $actualCredit,
            'variance' => $project->getBudget() - $actualDebit,
            'completion_pct' => $project->getBudget() > 0
                ? round($actualDebit / $project->getBudget() * 100, 2) : 0,
            'cost_summary' => $this->projectRepo->getCostSummary($projectId),
            'transactions' => $this->projectRepo->getProjectTransactions($projectId),
            'billings' => $this->projectRepo->getProgressBillings($projectId),
        ];
    }

    /**
     * Phân bổ chi phí từ bút toán vào dự án
     *
     * Nghiệp vụ: Gán project_id cho một ledger_entry cụ thể dựa trên transaction_id,
     * account_code, chiều Nợ/Có và số tiền. Nếu là bút toán Nợ, cập nhật actual_cost
     * của dự án tương ứng.
     *
     * RỦI RO: Transaction wrap + rollback nếu bất kỳ bước nào lỗi.
     * Nếu không tìm thấy bút toán phù hợp → throw với mã lỗi rõ ràng.
     *
     * @param string $projectId Mã định danh dự án cần phân bổ chi phí
     * @param string $transactionId Mã định danh bút toán chứa ledger_entry cần phân bổ
     * @param string $accountCode Mã tài khoản kế toán (ví dụ: 621, 622, 627)
     * @param float $amount Số tiền cần phân bổ — phải khớp chính xác với amount trong ledger_entry
     * @param bool $isDebit True nếu phân bổ cho bên Nợ, False nếu phân bổ cho bên Có
     * @return void
     * @throws \InvalidArgumentException Nếu không tìm thấy bút toán phù hợp để phân bổ
     * @throws \Exception Nếu transaction database thất bại — rollback tự động
     */
    public function allocateCost(string $projectId, string $transactionId, string $accountCode, float $amount, bool $isDebit): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE ledger_entries le
                 JOIN accounts a ON le.account_id = a.id
                 SET le.project_id = ?
                 WHERE le.transaction_id = ? AND a.code = ? AND le.is_debit = ? AND le.amount = ?"
            );
            $stmt->execute([$projectId, $transactionId, $accountCode, $isDebit ? 1 : 0, $amount]);

            if ($stmt->rowCount() === 0) throw new \InvalidArgumentException('Không tìm thấy bút toán phù hợp để phân bổ');

            if ($isDebit) {
                $this->pdo->prepare('UPDATE projects SET actual_cost = actual_cost + ? WHERE id = ?')
                    ->execute([$amount, $projectId]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Tạo chứng từ xuất hóa đơn theo tiến độ dự án
     *
     * Nghiệp vụ: Tạo bản ghi project_progress_billing với trạng thái "draft"
     * để theo dõi các lần xuất hóa đơn tạm ứng/theo tiến độ cho khách hàng.
     * Chứng từ ở trạng thái draft — chưa ảnh hưởng đến doanh thu.
     *
     * @param string $projectId Mã định danh dự án
     * @param string $billingDate Ngày xuất hóa đơn (định dạng YYYY-MM-DD)
     * @param float $amount Số tiền xuất hóa đơn (VNĐ)
     * @param float $pctComplete Tỷ lệ hoàn thành tại thời điểm xuất hóa đơn (0-100)
     * @param string $description Diễn giải nội dung xuất hóa đơn
     * @param string $createdBy Mã định danh người tạo chứng từ (user_id)
     * @return string Mã định danh của chứng từ vừa tạo (uniqid với prefix "bill_")
     */
    public function createProgressBilling(string $projectId, string $billingDate, float $amount, float $pctComplete, string $description, string $createdBy): string
    {
        $id = uniqid('bill_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_progress_billing (id,project_id,billing_date,amount,pct_complete,description,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,"draft",?,NOW())'
        );
        $stmt->execute([$id, $projectId, $billingDate, $amount, $pctComplete, $description, $createdBy]);
        return $id;
    }

    /**
     * Ghi nhận doanh thu theo tỷ lệ hoàn thành (Percentage of Completion)
     *
     * Nghiệp vụ: Tính doanh thu cần ghi nhận dựa trên chi phí thực tế / ngân sách.
     * Công thức: Doanh thu = Ngân sách × min(Tổng chi phí / Ngân sách, 1.0)
     * Cập nhật revenue_recognized và estimated_completion_pct trong bảng projects.
     *
     * RỦI RO: Nếu dự án không tồn tại → throw InvalidArgumentException
     * RỦI RO: Nếu budget = 0 → completion_pct = 0%, revenue = 0 (không chia cho 0)
     *
     * @param string $projectId Mã định danh dự án
     * @param string $userId Mã định danh người thực hiện ghi nhận
     * @return float Số tiền doanh thu đã ghi nhận
     * @throws \InvalidArgumentException Nếu không tìm thấy dự án
     */
    public function recognizeRevenue(string $projectId, string $userId): float
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');

        $totalCost = $this->getActualTotals($projectId)[0];
        $pct = $project->getBudget() > 0 ? $totalCost / $project->getBudget() : 0;
        $revenue = round($project->getBudget() * min($pct, 1.0), 2);

        $this->pdo->prepare('UPDATE projects SET revenue_recognized = ?, estimated_completion_pct = ? WHERE id = ?')
            ->execute([$revenue, round($pct * 100, 2), $projectId]);

        return $revenue;
    }

    /**
     * Kết thúc dự án — chuyển trạng thái sang "completed"
     *
     * Nghiệp vụ: Đánh dấu dự án hoàn thành với tỷ lệ 100%.
     * Chỉ cho phép kết thúc dự án đang ở trạng thái "active".
     *
     * RỦI RO: Nếu dự án không tồn tại → throw InvalidArgumentException
     * RỦI RO: Nếu dự án không ở trạng thái active → throw InvalidArgumentException
     *
     * @param string $projectId Mã định danh dự án cần kết thúc
     * @return void
     * @throws \InvalidArgumentException Nếu không tìm thấy dự án hoặc dự án không ở trạng thái active
     */
    public function finalizeProject(string $projectId): void
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');
        if ($project->getStatus() !== 'active') throw new \InvalidArgumentException('Chỉ có thể kết thúc dự án đang hoạt động');

        $this->pdo->prepare("UPDATE projects SET status='completed', estimated_completion_pct=100 WHERE id=?")
            ->execute([$projectId]);
    }

    /**
     * Thiết lập hoặc cập nhật dòng ngân sách cho một tài khoản của dự án
     *
     * Nghiệp vụ: Tạo hoặc cập nhật (upsert) một dòng ngân sách chi tiết
     * theo tài khoản kế toán. Dùng ON DUPLICATE KEY UPDATE để đảm bảo
     * idempotent — nếu đã tồn tại cặp (project_id, account_code) thì cập nhật
     * budget_amount và notes, nếu chưa có thì thêm mới.
     *
     * @param string $projectId Mã định danh dự án
     * @param string $accountCode Mã tài khoản kế toán (ví dụ: 621, 622, 627)
     * @param float $amount Số tiền ngân sách dự kiến cho tài khoản này
     * @param string|null $notes Ghi chú bổ sung cho dòng ngân sách (có thể null)
     * @return void
     */
    public function setBudgetLine(string $projectId, string $accountCode, float $amount, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_budgets (project_id,account_code,budget_amount,notes,created_at)
             VALUES (?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE budget_amount=VALUES(budget_amount),notes=VALUES(notes)'
        );
        $stmt->execute([$projectId, $accountCode, $amount, $notes]);
    }

    /**
     * Cập nhật số tiền đã chi thực tế cho từng dòng ngân sách
     *
     * Nghiệp vụ: Tính tổng phát sinh Nợ từ ledger_entries theo từng tài khoản
     * và cập nhật spent_amount tương ứng trong bảng project_budgets.
     * Chỉ tính các bút toán Nợ (is_debit = 1) đã được gán project_id.
     *
     * @param string $projectId Mã định danh dự án cần cập nhật
     * @return void
     */
    public function updateBudgetSpent(string $projectId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE project_budgets pb
            JOIN (
                SELECT a.code, SUM(le.amount) as spent
                FROM ledger_entries le
                JOIN accounts a ON le.account_id = a.id
                WHERE le.project_id = ? AND le.is_debit = 1
                GROUP BY a.code
            ) s ON pb.account_code = s.code
            SET pb.spent_amount = s.spent
            WHERE pb.project_id = ?
        ");
        $stmt->execute([$projectId, $projectId]);
    }

    /**
     * Lấy danh sách dự án đang hoạt động kèm chi phí thực tế
     *
     * Nghiệp vụ: Truy vấn tất cả dự án active, kết hợp thông tin khách hàng
     * và tổng chi phí thực tế từ ledger_entries (bút toán Nợ).
     * Sắp xếp theo mã dự án (code).
     *
     * @return array Danh sách dự án active với các trường:
     *               p.*, customer_name, actual_cost (tổng chi phí thực tế)
     */
    public function getActiveProjectsList(): array
    {
        $stmt = $this->pdo->query("
            SELECT p.*, c.name as customer_name,
                   COALESCE(SUM(le.amount),0) as actual_cost
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            LEFT JOIN ledger_entries le ON le.project_id = p.id AND le.is_debit = 1
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY p.code
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Xuất báo cáo dự án ra file CSV
     *
     * Nghiệp vụ: Tạo báo cáo tổng hợp dự án gồm thông tin chung
     * (mã, tên, ngân sách, chi phí, doanh thu, tỷ lệ hoàn thành)
     * và chi tiết số dư theo từng tài khoản kế toán.
     * Kết quả trả về mảng để controller set header + echo.
     *
     * @param string $format Định dạng xuất (hiện tại chỉ hỗ trợ 'csv')
     * @param string $projectId Mã định danh dự án cần xuất báo cáo
     * @return array{
     *     content: string,
     *     filename: string,
     *     mime: string
     * } Mảng chứa nội dung CSV, tên file và MIME type
     */
    public function exportProjectReport(string $format, string $projectId): array
    {
        $report = $this->getProjectReport($projectId);
        $p = $report['project'];
        $headers = ['Hạng mục', 'Giá trị'];
        $data = [
            ['Mã dự án', $p['code']], ['Tên', $p['name']],
            ['Ngân sách', $p['budget']], ['Chi phí thực tế', $p['actual_cost']],
            ['Đã xuất hóa đơn', $p['billed_amount']],
            ['Doanh thu đã ghi nhận', $p['revenue_recognized']],
            ['Tỷ lệ hoàn thành', $p['estimated_completion_pct'] . '%'],
        ];
        foreach ($report['cost_summary'] as $c) {
            $data[] = ['TK ' . $c['code'] . ' ' . $c['name'], $c['debit'] - $c['credit']];
        }
        return $this->export->exportCsv($headers, $data, 'du_an_' . $p['code'] . '_' . date('Ymd') . '.csv');
    }

    /**
     * Lấy tổng phát sinh Nợ và Có thực tế cho dự án
     *
     * Nghiệp vụ: Tính tổng số tiền bên Nợ và bên Có từ ledger_entries
     * đã được gán project_id. Dùng để tính chi phí thực tế, variance
     * và tỷ lệ hoàn thành trong báo cáo dự án.
     *
     * @param string $projectId Mã định danh dự án
     * @return array{0: float, 1: float} Mảng hai phần tử:
     *               [0] = tổng phát sinh Nợ (debit),
     *               [1] = tổng phát sinh Có (credit)
     */
    private function getActualTotals(string $projectId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(CASE WHEN is_debit=1 THEN amount ELSE 0 END) as debit,
                   SUM(CASE WHEN is_debit=0 THEN amount ELSE 0 END) as credit
            FROM ledger_entries WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return [(float)$r['debit'], (float)$r['credit']];
    }
}
