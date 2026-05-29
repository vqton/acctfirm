<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\VatService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class VatController
{
    private VatService $vat;

    public function __construct(VatService $vat) { $this->vat = $vat; }

    public function list(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->vat->getDeclarations());
    }

    public function get(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $decl = $this->vat->getDeclaration($id);
        if (!$decl) { JsonResponse::error('Không tìm thấy tờ khai', 404); return; }
        JsonResponse::ok($decl);
    }

    public function prepare(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->vat->prepareDeclaration($period, $_SESSION['user_id'] ?? 'system');
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
            JsonResponse::ok($this->vat->finalise($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function scanNonDeductible(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->vat->scanNonDeductibleVat($period));
    }

    public function reconcile(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->vat->reconcileVatDeclaration($period));
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/vat_declarations.php';
    }
}
