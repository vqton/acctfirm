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

    public function approve(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            $approvedBy = $_SESSION['user_id'] ?? 'system';
            JsonResponse::ok($this->vat->approveDeclaration($id, $approvedBy));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function reject(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $rejectedBy = $_SESSION['user_id'] ?? 'system';
            $reason = $data['reason'] ?? 'Kế toán trưởng từ chối';
            JsonResponse::ok($this->vat->rejectDeclaration($id, $reason, $rejectedBy));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function createAdjustment(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $period = $data['period'] ?? date('Y-m');
            $result = $this->vat->createAdjustment($period, $data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function exportHtkkXml(string $id): void
    {
        Auth::requirePermission('tax', 'read');
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="01-GTGT-' . $id . '.xml"');
        echo $this->vat->exportHtkkXml($id);
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

    public function reconcileWithEInvoice(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->vat->reconcileWithEInvoice($period));
    }

    public function getNonDeductibleInvoices(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->vat->getNonDeductibleInvoices($period));
    }

    public function getInputVatChecklist(string $period): void
    {
        Auth::requirePermission('tax', 'read');
        JsonResponse::ok($this->vat->getInputVatChecklist($period));
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/vat_declarations.php';
    }
}
