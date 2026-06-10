<?php
//
// IMPORT CONTROLLER — Endpoints cho R-4/5/6 Import cluster
//
// Endpoints:
//   GET  /api/import/template/{type}        — tải CSV template
//   GET  /api/import/supported-types        — list entity types
//   POST /api/import/dry-run/{type}         — validate không ghi DB
//   POST /api/import/commit/{type}          — ghi DB transactional
//   POST /api/import/rollback/{batchId}     — undo trong window
//   GET  /api/import/batches                — list history
//   GET  /api/import/batches/{id}           — chi tiết batch
//
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ImportService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use PDO;

class ImportController
{
    private ImportService $importService;
    private PDO $pdo;

    public function __construct(ImportService $importService, PDO $pdo)
    {
        $this->importService = $importService;
        $this->pdo = $pdo;
    }

    public function supportedTypes(): void
    {
        Auth::requirePermission('import', 'read');
        JsonResponse::ok(['types' => $this->importService->getSupportedTypes()]);
    }

    //
    // GET /api/import/template/{type} → trả CSV file để user tải về
    //
    public function template(string $type): void
    {
        Auth::requirePermission('import', 'read');
        try {
            $tpl = $this->importService->getTemplate($type);
            $csv = implode(',', $tpl['columns']) . "\n";
            foreach ($tpl['sample_rows'] as $row) {
                $values = array_map(fn($c) => $row[$c] ?? '', $tpl['columns']);
                $csv .= implode(',', $values) . "\n";
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="template_' . $type . '.csv"');
            echo "\xEF\xBB\xBF" . $csv; // BOM for Excel
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    //
    // POST /api/import/dry-run/{type}
    // Input: multipart form 'file' (CSV)
    // Output: { headers, total_rows, valid_rows, error_rows, errors, file_hash }
    //
    public function dryRun(string $type): void
    {
        Auth::requirePermission('import', 'create');
        if (empty($_FILES['file']['tmp_name'])) {
            JsonResponse::error('Vui lòng upload file CSV', 400);
            return;
        }

        $tmpFile = $_FILES['file']['tmp_name'];
        try {
            $result = $this->importService->validateCsv($tmpFile, $type);
            $result['file_hash'] = hash_file('sha256', $tmpFile);
            $result['file_name'] = $_FILES['file']['name'] ?? 'uploaded.csv';
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            JsonResponse::error('Lỗi xử lý file: ' . $e->getMessage(), 500);
        }
    }

    //
    // POST /api/import/commit/{type}
    // Input: multipart form 'file' (CSV) + optional 'period' (for opening_balance)
    // Output: { batch_id, status, inserted_rows }
    //
    public function commit(string $type): void
    {
        Auth::requirePermission('import', 'create');
        if (empty($_FILES['file']['tmp_name'])) {
            JsonResponse::error('Vui lòng upload file CSV', 400);
            return;
        }

        $tmpFile = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'] ?? 'uploaded.csv';
        $fileHash = hash_file('sha256', $tmpFile);
        $userId = Auth::getCurrentUserId() ?? 'system';
        $context = !empty($_POST['period']) ? ['period' => $_POST['period']] : null;

        try {
            $validation = $this->importService->validateCsv($tmpFile, $type);
            if (count($validation['errors']) > 0) {
                JsonResponse::error('File có lỗi validation. Vui lòng chạy dry-run trước.', 422, $validation);
                return;
            }
            $result = $this->importService->commitBatch(
                $type, $validation['valid_data'], $fileName, $fileHash, $userId, $context
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            JsonResponse::error('Lỗi commit: ' . $e->getMessage(), 500);
        }
    }

    //
    // POST /api/import/rollback/{batchId}
    // Body: { reason: string }
    //
    public function rollback(string $batchId): void
    {
        Auth::requirePermission('import', 'delete');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = $data['reason'] ?? '';
        if (!$reason) {
            JsonResponse::error('Vui lòng nhập lý do rollback (audit trail)', 400);
            return;
        }

        try {
            // Lấy window từ config (default 24h)
            $windowHours = 24;
            try {
                $stmt = $this->pdo->prepare("SELECT config_value FROM business_config WHERE config_key = 'import.rollback_window_hours'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val) $windowHours = (int)$val;
            } catch (\Exception $e) {}

            $result = $this->importService->rollbackBatch(
                $batchId, Auth::getCurrentUserId() ?? 'system', $windowHours
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function listBatches(): void
    {
        Auth::requirePermission('import', 'read');
        $stmt = $this->pdo->query(
            "SELECT id, entity_type, file_name, status, total_rows, valid_rows,
                    imported_by, imported_at, committed_at, rolled_back_at
             FROM import_batches
             ORDER BY imported_at DESC LIMIT 100"
        );
        JsonResponse::ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getBatch(string $batchId): void
    {
        Auth::requirePermission('import', 'read');
        $stmt = $this->pdo->prepare("SELECT * FROM import_batches WHERE id = ?");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            JsonResponse::error('Không tìm thấy batch', 404);
            return;
        }
        $batch['error_log'] = $batch['error_log'] ? json_decode($batch['error_log'], true) : null;
        JsonResponse::ok($batch);
    }
}
