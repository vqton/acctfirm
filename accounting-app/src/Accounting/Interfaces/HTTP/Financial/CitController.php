<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CitService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class CitController
{
    private CitService $cit;

    public function __construct(CitService $cit) { $this->cit = $cit; }

    public function list(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->cit->getCalculations());
    }

    public function get(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $calc = $this->cit->getCalculation($id);
        if (!$calc) { JsonResponse::error('Không tìm thấy quyết toán TNDN', 404); return; }
        JsonResponse::ok($calc);
    }

    public function prepare(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->cit->prepareCalculation($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function finalise(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->cit->finalise($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function scanNonDeductible(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        // Auto-detect revenue from ledger for scan
        $pdo = $GLOBALS['container']['pdo'] ?? null;
        $revenue = 0;
        if ($pdo) {
            $periodStart = $period . '-01';
            $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
            $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));
            $stmtRev = $pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0) FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = '511' AND t.status = 'posted'
                 AND t.transaction_date BETWEEN ? AND ? AND le.is_debit = 0"
            );
            $stmtRev->execute([$periodStart, $periodEnd]);
            $revenue = (float)$stmtRev->fetchColumn();
        }
        JsonResponse::ok($this->cit->scanNonDeductibleExpenses($period, $revenue));
    }

    public function lossCarryforward(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->cit->getLossCarryforward($period));
    }

    public function exportXml(string $id): void
    {
        Auth::requirePermission('tax', 'read');
        try {
            $calc = $this->cit->getCalculation($id);
            if (!$calc) { JsonResponse::error('Không tìm thấy quyết toán', 404); return; }
            $pdo = $GLOBALS['container']['pdo'] ?? null;
            if (!$pdo) { JsonResponse::error('DB not available', 500); return; }
            $engine = new \Accounting\Domain\Service\CitDeclarationEngine($pdo);
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="03-TNDN-' . $id . '.xml"');
            echo $engine->exportToXml($id);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/cit_calculations.php';
    }
}
