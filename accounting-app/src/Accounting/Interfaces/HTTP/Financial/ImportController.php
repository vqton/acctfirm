<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ImportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Import dữ liệu hàng loạt (Bulk Import)
 *
 * Mục đích nghiệp vụ:
 *   - Import master data: items, customers, suppliers, COA
 *   - Import số dư đầu kỳ (opening balance)
 *   - Xác thực dữ liệu trước khi commit
 *   - Rollback nếu có lỗi
 *
 * API endpoints:
 *   GET  /api/import/supported-types — Danh sách loại import hỗ trợ
 *   GET  /api/import/template/{type} — Template CSV
 *   POST /api/import/dry-run — Import thử (validate)
 *   POST /api/import/commit — Xác nhận import
 *   POST /api/import/{batchId}/rollback — Rollback
 *   GET  /api/import/batches — Danh sách batch
 *   GET  /api/import/batches/{id} — Chi tiết batch
 *
 * Rủi ro:
 *   - Import sai -> dữ liệu lỗi hàng loạt
 *   - Trùng mã -> conflict
 *   - Rollback không hoàn toàn -> dữ liệu rác
 *   - File sai encoding -> lỗi hiển thị tiếng Việt
 *
 * Tích hợp:
 *   - ImportService xử lý validate và commit
 *   - Các repository được gọi để persist
 *   - AuditLogger ghi lại mọi batch import
 */
class ImportController
{
    private ImportService $service;

    public function __construct(ImportService $service) { $this->service = $service; }

    /**
     * Danh sách loại import được hỗ trợ
     *
     * @return void
     */
    public function supportedTypes(): void
    {
        Auth::requirePermission('master_data', 'create');
        JsonResponse::ok($this->service->getSupportedTypes());
    }

    /**
     * Template CSV cho một loại import
     *
     * @param string $type Loại import
     * @return void
     */
    public function template(string $type): void
    {
        Auth::requirePermission('master_data', 'create');
        try {
            $result = $this->service->getTemplate($type);
            header('Content-Type: ' . $result['mime']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Import thử (dry-run) — validate dữ liệu không commit
     *
     * @return void
     */
    public function dryRun(): void
    {
        Auth::requirePermission('master_data', 'create');
        Auth::checkCsrf();
        $entityType = $_POST['entity_type'] ?? '';
        $file = $_FILES['file'] ?? null;
        if (!$entityType || !$file) {
            JsonResponse::error('Vui lòng chọn loại dữ liệu và file CSV', 400);
            return;
        }
        try {
            $result = $this->service->validateCsv($entityType, file_get_contents($file['tmp_name']));
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Xác nhận import — commit dữ liệu
     *
     * @return void
     */
    public function commit(): void
    {
        Auth::requirePermission('master_data', 'create');
        Auth::checkCsrf();
        $entityType = $_POST['entity_type'] ?? '';
        $file = $_FILES['file'] ?? null;
        if (!$entityType || !$file) {
            JsonResponse::error('Vui lòng chọn loại dữ liệu và file CSV', 400);
            return;
        }
        try {
            $result = $this->service->commitBatch($entityType, file_get_contents($file['tmp_name']), $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Rollback một batch import
     *
     * @param string $batchId ID batch
     * @return void
     */
    public function rollback(string $batchId): void
    {
        Auth::requirePermission('master_data', 'delete');
        Auth::checkCsrf();
        try {
            $this->service->rollbackBatch($batchId);
            JsonResponse::ok(['message' => 'Đã rollback batch']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Danh sách các batch import
     *
     * @return void
     */
    public function batches(): void
    {
        Auth::requirePermission('master_data', 'read');
        JsonResponse::ok($this->service->getBatches());
    }

    /**
     * Chi tiết một batch import
     *
     * @param string $id ID batch
     * @return void
     */
    public function getBatch(string $id): void
    {
        Auth::requirePermission('master_data', 'read');
        try {
            JsonResponse::ok($this->service->getBatch($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}
