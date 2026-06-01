<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;

// Dịch vụ kiểm soát ngân sách Mua hàng
//
// Theo dõi ngân sách theo phòng ban/tháng:
//   - budget_amount: ngân sách được duyệt
//   - committed_amount: ngân sách đã cam kết (PR đã duyệt)
//   - actual_amount: ngân sách đã thực hiện (GR đã nhập)
//   - remaining: ngân sách còn lại = budget - committed - actual
//
// Ngưỡng cảnh báo:
//   - 80% → yellow alert
//   - 95% → red alert (block PR creation, CFO override required)
class BudgetControlService
{
    private \PDO $pdo;
    private AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, AuditLoggerInterface $auditLogger)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    // NGHIỆP VỤ: Kiểm tra ngân sách trước khi tạo PR
    // Trả về: { allowed, warning, message }
    // Nếu vượt 95% → allowed=false, cần CFO override
    public function checkBudget(string $departmentId, string $period, float $amount): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM purchase_budgets WHERE department_id = ? AND period = ?");
        $stmt->execute([$departmentId, $period]);
        $budget = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$budget) {
            // Không có ngân sách → cho phép nhưng cảnh báo
            return ['allowed' => true, 'warning' => 'Không tìm thấy ngân sách cho phòng ban này.', 'remaining' => null];
        }

        $budgetAmount = (float)$budget['budget_amount'];
        $committed = (float)$budget['committed_amount'];
        $actual = (float)$budget['actual_amount'];
        $remaining = $budgetAmount - $committed - $actual - $amount;

        $usageRate = $budgetAmount > 0 ? (($committed + $actual + $amount) / $budgetAmount) * 100 : 0;

        if ($usageRate >= 95) {
            return [
                'allowed' => false,
                'warning' => "Ngân sách đã sử dụng {$usageRate}% (>= 95%). Vui lòng liên hệ Kế toán trưởng để được duyệt bổ sung.",
                'remaining' => $remaining,
                'usage_rate' => $usageRate,
            ];
        }

        if ($usageRate >= 80) {
            return [
                'allowed' => true,
                'warning' => "Ngân sách đã sử dụng {$usageRate}% (>= 80%). Cần theo dõi sát.",
                'remaining' => $remaining,
                'usage_rate' => $usageRate,
            ];
        }

        return ['allowed' => true, 'warning' => '', 'remaining' => $remaining, 'usage_rate' => $usageRate];
    }

    // NGHIỆP VỤ: Cam kết ngân sách (khi PR được duyệt)
    public function commitBudget(string $departmentId, string $period, float $amount): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM purchase_budgets WHERE department_id = ? AND period = ?");
        $stmt->execute([$departmentId, $period]);
        $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($exists) {
            $this->pdo->prepare("UPDATE purchase_budgets SET committed_amount = committed_amount + ? WHERE id = ?")
                ->execute([$amount, $exists['id']]);
        }
    }

    // NGHIỆP VỤ: Ghi nhận thực tế (khi GR được tạo)
    public function recordActual(string $departmentId, string $period, float $amount): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM purchase_budgets WHERE department_id = ? AND period = ?");
        $stmt->execute([$departmentId, $period]);
        $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($exists) {
            $this->pdo->prepare("UPDATE purchase_budgets SET actual_amount = actual_amount + ?, committed_amount = GREATEST(committed_amount - ?, 0) WHERE id = ?")
                ->execute([$amount, $amount, $exists['id']]);
        }
    }

    // NGHIỆP VỤ: Thiết lập ngân sách
    public function setBudget(string $departmentId, string $period, float $amount, string $createdBy): array
    {
        $id = uniqid('bgt_');
        $this->pdo->prepare(
            "INSERT INTO purchase_budgets (id, department_id, period, budget_amount) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE budget_amount = VALUES(budget_amount)"
        )->execute([$id, $departmentId, $period, $amount]);

        $this->auditLogger->log('purchase.budget.set', 'purchase_budget', $id, null, ['department_id' => $departmentId, 'period' => $period, 'amount' => $amount], $createdBy);
        return ['id' => $id, 'department_id' => $departmentId, 'period' => $period, 'amount' => $amount];
    }

    // Báo cáo ngân sách
    public function getBudgetReport(string $departmentId = ''): array
    {
        $sql = "SELECT pb.*, d.name as department_name
                FROM purchase_budgets pb
                JOIN departments d ON d.id = pb.department_id";
        $params = [];
        if ($departmentId) {
            $sql .= " WHERE pb.department_id = ?";
            $params[] = $departmentId;
        }
        $sql .= " ORDER BY pb.period DESC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
