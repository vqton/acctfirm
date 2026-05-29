<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\FctService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class FctController
{
    private FctService $fct;

    public function __construct(FctService $fct) { $this->fct = $fct; }

    public function listContracts(): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->fct->getContracts());
    }

    public function getContract(string $id): void
    {
        Auth::requirePermission('tax', 'read');
        $c = $this->fct->getContract($id);
        if (!$c) { JsonResponse::error('Không tìm thấy hợp đồng nhà thầu', 404); return; }
        JsonResponse::ok($c);
    }

    public function calculate(): void
    {
        Auth::requirePermission('tax', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        $serviceType = $data['service_type'] ?? '';
        $contractValue = (float)($data['contract_value'] ?? 0);
        try {
            JsonResponse::ok($this->fct->calculateWithholding($serviceType, $contractValue));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function record(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->fct->recordWithholding(
                $data['contract_no'] ?? '',
                $data['contractor_name'] ?? '',
                $data['contractor_country'] ?? '',
                $data['service_type'] ?? '',
                (float)($data['contract_value'] ?? 0),
                $data['currency'] ?? 'VND',
                (float)($data['exchange_rate'] ?? 1),
                $data['notes'] ?? '',
                $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function cancel(string $id): void
    {
        Auth::requirePermission('tax', 'update');
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->fct->cancelContract($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function listDeclarations(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->fct->getDeclarations());
    }

    public function getDeclaration(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $d = $this->fct->getDeclaration($id);
        if (!$d) { JsonResponse::error('Không tìm thấy tờ khai FCT', 404); return; }
        JsonResponse::ok($d);
    }

    public function prepareDeclaration(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            JsonResponse::ok($this->fct->prepareDeclaration($period, $_SESSION['user_id'] ?? 'system'), 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function finaliseDeclaration(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->fct->finalise($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/fct_declarations.php';
    }
}
