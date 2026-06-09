<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ReportBuilderService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class ReportBuilderController
{
    private ReportBuilderService $service;

    public function __construct(ReportBuilderService $service) { $this->service = $service; }

    public function tables(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->service->getAvailableTables());
    }

    public function save(): void
    {
        Auth::requirePermission('report', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name']) || !isset($data['source_table']) || !isset($data['fields'])) {
            JsonResponse::error('Vui lòng nhập tên, bảng và trường', 400); return;
        }
        $data['created_by'] = Auth::currentUser();
        $id = $this->service->saveReport($data);
        JsonResponse::ok(['id' => $id], 201);
    }

    public function list(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->service->getSavedReports(Auth::currentUser()));
    }

    public function get(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $def = $this->service->getReportDefinition($id);
        if (!$def) { JsonResponse::error('Không tìm thấy báo cáo', 404); return; }
        JsonResponse::ok($def);
    }

    public function run(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $def = $this->service->getReportDefinition($id);
        if (!$def) { JsonResponse::error('Không tìm thấy báo cáo', 404); return; }
        try {
            $data = $this->service->executeReport($def);
            JsonResponse::ok(['definition' => $def, 'data' => $data, 'count' => count($data)]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function runAdhoc(): void
    {
        Auth::requirePermission('report', 'read');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Vui lòng nhập cấu hình báo cáo', 400); return; }
        try {
            $result = $this->service->executeReport($data);
            JsonResponse::ok(['data' => $result, 'count' => count($result)]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function delete(string $id): void
    {
        Auth::requirePermission('report', 'delete');
        Auth::checkCsrf();
        $this->service->deleteReport($id);
        JsonResponse::ok(['message' => 'Đã xóa báo cáo']);
    }

    public function export(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $def = $this->service->getReportDefinition($id);
        if (!$def) { JsonResponse::error('Không tìm thấy báo cáo', 404); return; }
        $result = $this->service->executeAndExport($def, $_GET['format'] ?? 'csv');
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    public function exportAdhoc(): void
    {
        Auth::requirePermission('report', 'read');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) return;
        $result = $this->service->executeAndExport($data, $_GET['format'] ?? 'csv');
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    public function viewIndex(): void
    {
        Auth::requirePermission('report', 'read');
        require __DIR__ . '/../../../../public/views/report-builder.php';
    }
}
