<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Database\AuditLogger;

class FsService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    public function getLineItems(string $statement): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fs_line_items WHERE statement = ? ORDER BY display_order');
        $stmt->execute([$statement]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function generateBC01(?string $periodCode = null): array
    {
        return $this->generateStatement('BC01', $periodCode);
    }

    public function generateBC02(?string $periodCode = null): array
    {
        return $this->generateStatement('BC02', $periodCode);
    }

    private function generateStatement(string $statement, ?string $periodCode = null): array
    {
        $periodCode = $periodCode ?? date('Y');
        $items = $this->getLineItems($statement);
        $values = [];
        $signs = [];

        foreach ($items as $item) {
            $signs[$item['ma_so']] = $item['sign_convention'];
            $maSo = $item['ma_so'];

            switch ($item['formula_type']) {
                case 'account':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $bal = $a->getBalance();
                            if ($item['sign_convention'] === 'positive') $total += $bal;
                            else $total -= $bal;
                        }
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'sum':
                    $children = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($children as $c) {
                        $total += $values[$c] ?? 0;
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'calculated':
                    $expr = $item['formula_detail'] ?? '';
                    $result = $this->evaluateExpression($expr, $values);
                    $values[$maSo] = round($result);
                    break;

                case 'manual':
                    $values[$maSo] = 0;
                    break;
            }
        }

        // Build result rows
        $result = [];
        foreach ($items as $item) {
            $maSo = $item['ma_so'];
            $val = $values[$maSo] ?? 0;
            $result[] = [
                'ma_so' => $maSo,
                'parent_ma_so' => $item['parent_ma_so'],
                'name_vi' => $item['name_vi'],
                'value' => $val,
                'is_control' => (bool)$item['is_control'],
                'is_total' => (bool)$item['is_total'],
                'display_order' => (int)$item['display_order'],
            ];
        }

        // Save snapshot
        $data = json_encode($values);
        $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        )->execute([$statement, $periodCode, $data, $_SESSION['user']['username'] ?? 'system']);

        AuditLogger::log('fs.generate', 'fs_statement', "{$statement}_{$periodCode}",
            null, ['statement' => $statement, 'period' => $periodCode, 'items' => count($result)],
            $_SESSION['user']['username'] ?? 'system');

        return $result;
    }

    public function getPriorPeriodValues(string $statement, string $currentPeriodCode): ?array
    {
        // Determine prior period
        $year = (int)$currentPeriodCode;
        $priorYear = $year - 1;

        $stmt = $this->pdo->prepare('SELECT data FROM fs_snapshots WHERE statement = ? AND period_code = ?');
        $stmt->execute([$statement, (string)$priorYear]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        return json_decode($row['data'], true);
    }

    public function validateBC01(array $bc01Data): array
    {
        $values = [];
        foreach ($bc01Data as $r) $values[$r['ma_so']] = $r['value'];

        $errors = [];
        if (abs($values[280] - $values[440]) > 1) {
            $errors[] = "Assets ({$values[280]}) != Liabilities + Equity ({$values[440]}). Diff: " . ($values[280] - $values[440]);
        }
        return $errors;
    }

    public function validateBC02(array $bc02Data): array
    {
        $values = [];
        foreach ($bc02Data as $r) $values[$r['ma_so']] = $r['value'];

        $errors = [];
        // 50 = 30+40
        $calc50 = ($values[30] ?? 0) + ($values[40] ?? 0);
        if (abs(($values[50] ?? 0) - $calc50) > 1) {
            $errors[] = "Pre-tax profit (50) should be {$calc50}, got {$values[50]}";
        }
        // 60 = 50-(51+52)
        $calc60 = ($values[50] ?? 0) - (($values[51] ?? 0) + ($values[52] ?? 0));
        if (abs(($values[60] ?? 0) - $calc60) > 1) {
            $errors[] = "Net profit (60) should be {$calc60}, got {$values[60]}";
        }
        return $errors;
    }

    private function evaluateExpression(string $expr, array $values): float
    {
        // Replace mã số with actual values
        $evalStr = $expr;
        foreach ($values as $k => $v) {
            $evalStr = str_replace($k, (string)$v, $evalStr);
        }
        // Basic arithmetic evaluation
        $result = @eval("return {$evalStr};");
        if ($result === false) return 0;
        return (float)$result;
    }

    public function getPeriods(): array
    {
        $rows = $this->pdo->query(
            "SELECT DISTINCT period_code, period_end_date FROM fs_snapshots WHERE statement = 'BC01' ORDER BY period_code DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }
}
