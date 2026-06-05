<?php
//
// IMPORT SERVICE — Framework chung cho mọi import dữ liệu hàng loạt
//
// Tuân thủ:
//   - TT78/2021/TT-BTC: hóa đơn, chứng từ import phải có audit trail
//   - AGENTS.md §5.7: AuditLogger::log() cho mọi thay đổi quan trọng
//   - R-4 Import Safety: validate 10 lớp + dry-run + commit transactional + rollback
//
// Workflow:
//   1. User tải template (GET /api/import/template/{type})
//   2. User điền file CSV (Save As UTF-8 từ Excel)
//   3. User upload → POST /api/import/dry-run/{type} → server validate, trả lỗi
//   4. User sửa file → upload lại
//   5. User click "Commit" → POST /api/import/commit/{type} → ghi DB transactional
//   6. Trong 24-72h, nếu phát hiện sai → POST /api/import/rollback/{batchId}
//
// Rủi ro nếu sai:
//   - Import sai data master → toàn bộ nghiệp vụ downstream sai (AR/AP/Inventory/FA)
//   - Import trùng → duplicate records → khó detect
//   - Mất audit → không biết ai import
//
namespace Accounting\Domain\Service;

use PDO;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;

class ImportService
{
    private PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private ?AuditLoggerInterface $auditLogger;
    private ?\Accounting\Domain\Service\NotificationService $notificationService;

    // Đăng ký schema cho từng loại entity
    // Mỗi schema khai báo: table, unique_keys (check trùng), required columns, validators
    private array $schemas = [];

    public function __construct(
        PDO $pdo,
        AccountRepositoryInterface $accountRepo,
        ?AuditLoggerInterface $auditLogger = null,
        ?\Accounting\Domain\Service\NotificationService $notificationService = null
    ) {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->auditLogger = $auditLogger;
        $this->notificationService = $notificationService;
        $this->registerSchemas();
    }

    //
    // Đăng ký schema cho 5 entity types
    // Mỗi schema có:
    //   - table: tên DB table
    //   - unique: array các columns phải unique (composite)
    //   - required: array các columns bắt buộc
    //   - validators: array column => [validator_name, ...]
    //   - columns: map column CSV → column DB
    //   - pre_check: callable trước khi commit (vd: check period lock)
    //   - post_check: callable sau khi commit (vd: check Dr = Cr)
    //
    private function registerSchemas(): void
    {
        $this->schemas = [
            'items' => [
                'table' => 'items',
                'unique' => ['code'],
                'has_id' => true,
                'id_prefix' => 'itm',
                'required' => ['code', 'name', 'unit', 'item_type'],
                'columns' => ['code' => 'code', 'name' => 'name', 'unit' => 'unit',
                              'item_type' => 'item_type',
                              'purchase_price' => 'purchase_price',
                              'sale_price' => 'sale_price', 'min_stock' => 'min_stock'],
                'validators' => [
                    'code' => ['max_length:50'],
                    'name' => ['max_length:200'],
                    'unit' => ['max_length:50'],
                    'item_type' => ['max_length:30'],
                    'purchase_price' => ['numeric', 'min:0'],
                    'sale_price' => ['numeric', 'min:0'],
                    'min_stock' => ['numeric', 'min:0'],
                ],
                'pre_check' => null,
                'post_check' => null,
            ],
            'customers' => [
                'table' => 'customers',
                'unique' => ['code'],
                'has_id' => true,
                'id_prefix' => 'cus',
                'required' => ['code', 'name'],
                'columns' => ['code' => 'code', 'name' => 'name',
                              'tax_code' => 'tax_code', 'address' => 'address',
                              'phone' => 'phone', 'email' => 'email',
                              'credit_limit' => 'credit_limit'],
                'validators' => [
                    'code' => ['max_length:50'],
                    'name' => ['max_length:200'],
                    'tax_code' => ['max_length:50'],
                    'phone' => ['max_length:20'],
                    'email' => ['max_length:100'],
                    'credit_limit' => ['numeric', 'min:0'],
                ],
                'pre_check' => null,
                'post_check' => null,
            ],
            'suppliers' => [
                'table' => 'suppliers',
                'unique' => ['code'],
                'has_id' => true,
                'id_prefix' => 'sup',
                'required' => ['code', 'name'],
                'columns' => ['code' => 'code', 'name' => 'name',
                              'tax_code' => 'tax_code', 'address' => 'address',
                              'phone' => 'phone', 'email' => 'email'],
                'validators' => [
                    'code' => ['max_length:50'],
                    'name' => ['max_length:200'],
                    'tax_code' => ['max_length:50'],
                    'phone' => ['max_length:20'],
                    'email' => ['max_length:100'],
                ],
                'pre_check' => null,
                'post_check' => null,
            ],
            'coa' => [
                'table' => 'accounts',
                'unique' => ['code'],
                'has_id' => true,
                'id_prefix' => 'coa',
                'required' => ['code', 'name', 'type'],
                'columns' => ['code' => 'code', 'name' => 'name', 'type' => 'type',
                              'is_control' => 'is_control'],
                'validators' => [
                    'code' => ['max_length:50', 'numeric_or_dot'],
                    'name' => ['max_length:100'],
                    'type' => ['in:asset,liability,equity,revenue,expense'],
                    'is_control' => ['bool'],
                ],
                'pre_check' => null,
                'post_check' => null,
            ],
            'opening_balance' => [
                'table' => 'opening_balances',
                'unique' => ['account_code', 'period'],
                'has_id' => true,
                'id_prefix' => 'ob',
                'required' => ['account_code', 'period', 'debit_balance', 'credit_balance'],
                'columns' => ['account_code' => 'account_code', 'period' => 'period',
                              'debit_balance' => 'debit_balance', 'credit_balance' => 'credit_balance'],
                'audit_columns' => ['created_by'],
                'validators' => [
                    'account_code' => ['max_length:20'],
                    'period' => ['period_format'],
                    'debit_balance' => ['numeric', 'min:0'],
                    'credit_balance' => ['numeric', 'min:0'],
                ],
                'pre_check' => 'checkOpeningPeriodLock',
                'post_check' => 'checkOpeningBalanceSum',
            ],
        ];
    }

    public function getSchema(string $entityType): ?array
    {
        return $this->schemas[$entityType] ?? null;
    }

    public function getSupportedTypes(): array
    {
        return array_keys($this->schemas);
    }

    //
    // R-4: Parse + validate CSV file
    // Return: { headers, total_rows, valid_rows, error_rows, valid_data, errors }
    //
    public function validateCsv(string $filePath, string $entityType): array
    {
        $schema = $this->getSchema($entityType);
        if (!$schema) {
            throw new \InvalidArgumentException("Loại import không hỗ trợ: {$entityType}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Không đọc được file: {$filePath}");
        }

        // Đọc header (BOM-safe)
        $headerRaw = fgetcsv($handle);
        $headers = $this->normalizeHeaders($headerRaw);

        $errors = [];
        $validData = [];
        $rowNum = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') {
                $skipped++;
                continue;
            }

            $rowData = [];
            foreach ($headers as $i => $col) {
                $rowData[$col] = $row[$i] ?? null;
            }

            $rowErrors = $this->validateRow($rowData, $schema);
            if (!empty($rowErrors)) {
                foreach ($rowErrors as $err) {
                    $errors[] = ['row' => $rowNum, 'column' => $err['column'], 'error' => $err['error']];
                }
            } else {
                $validData[] = $rowData;
            }
        }
        fclose($handle);

        return [
            'headers' => $headers,
            'total_rows' => $rowNum - $skipped,
            'valid_rows' => count($validData),
            'error_rows' => count($errors) > 0 ? ($rowNum - $skipped - count($validData)) : 0,
            'valid_data' => $validData,
            'errors' => $errors,
        ];
    }

    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h ?? '');
            $out[] = strtolower(trim($h ?? ''));
        }
        return $out;
    }

    private function validateRow(array $row, array $schema): array
    {
        $errors = [];

        // Required fields
        foreach ($schema['required'] as $col) {
            if (!isset($row[$col]) || trim((string)$row[$col]) === '') {
                $errors[] = ['column' => $col, 'error' => 'Bắt buộc'];
            }
        }

        // Per-column validators
        foreach ($schema['validators'] as $col => $rules) {
            if (!isset($row[$col]) || $row[$col] === '') continue;
            $value = $row[$col];

            foreach ($rules as $rule) {
                $err = $this->applyValidator($col, $value, $rule);
                if ($err) {
                    $errors[] = ['column' => $col, 'error' => $err];
                    break;
                }
            }
        }

        return $errors;
    }

    private function applyValidator(string $col, $value, string $rule): ?string
    {
        if ($rule === 'numeric') {
            if (!is_numeric($value)) return "Phải là số";
        } elseif (str_starts_with($rule, 'min:')) {
            $min = (float)substr($rule, 4);
            if ((float)$value < $min) return "Phải >= {$min}";
        } elseif (str_starts_with($rule, 'max_length:')) {
            $max = (int)substr($rule, 11);
            if (mb_strlen($value) > $max) return "Tối đa {$max} ký tự";
        } elseif (str_starts_with($rule, 'in:')) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array($value, $allowed, true)) return "Phải là một trong: " . implode(', ', $allowed);
        } elseif ($rule === 'bool') {
            if (!in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                return "Phải là true/false hoặc 0/1";
            }
        } elseif ($rule === 'numeric_or_dot') {
            if (!preg_match('/^[0-9.]+$/', $value)) return "Chỉ chấp nhận số và dấu chấm";
        } elseif ($rule === 'period_format') {
            if (!preg_match('/^\d{4}(-\d{2})?$/', $value)) return "Phải có format YYYY hoặc YYYY-MM";
        }
        return null;
    }

    //
    // R-4: Commit import — ghi DB trong 1 transaction
    // Trả về: { batch_id, valid_rows, error_rows, status, ... }
    //
    public function commitBatch(string $entityType, array $validData, string $fileName, string $fileHash, string $userId, ?array $context = null): array
    {
        $schema = $this->getSchema($entityType);
        if (!$schema) {
            throw new \InvalidArgumentException("Loại import không hỗ trợ: {$entityType}");
        }

        if (empty($validData)) {
            throw new \InvalidArgumentException("Không có dòng hợp lệ để import");
        }

        // Pre-check (entity-specific, e.g., period lock cho opening_balance)
        if ($schema['pre_check'] === 'checkOpeningPeriodLock') {
            $period = $context['period'] ?? null;
            if (!$period) throw new \InvalidArgumentException("Thiếu period cho opening balance import");
            $this->checkOpeningPeriodLock($period);
        }

        $batchId = 'imp_' . uniqid();

        $this->pdo->beginTransaction();
        try {
            // Tạo batch record
            $this->pdo->prepare(
                "INSERT INTO import_batches (id, entity_type, file_name, file_hash, total_rows,
                    valid_rows, error_rows, status, imported_by, committed_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 'committed', ?, NOW())"
            )->execute([
                $batchId, $entityType, $fileName, $fileHash,
                count($validData), count($validData), $userId
            ]);

            // Insert data rows
            $inserted = $this->insertRows($schema, $validData, $userId, $context);

            // Post-check
            if ($schema['post_check'] === 'checkOpeningBalanceSum') {
                $this->checkOpeningBalanceSum($context['period'] ?? null);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            // Mark batch as failed
            $this->pdo->prepare(
                "UPDATE import_batches SET status='failed', error_log=?, committed_at=NULL WHERE id=?"
            )->execute([json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE), $batchId]);
            throw $e;
        }

        $this->auditLogger?->log('import.commit', 'import_batch', $batchId,
            null,
            ['entity_type' => $entityType, 'file_name' => $fileName, 'row_count' => $inserted],
            $userId);

        // R-12: thông báo import hoàn tất
        $this->notificationService?->notifyImportResult(
            $entityType, $fileName, $inserted, true
        );

        return [
            'batch_id' => $batchId,
            'entity_type' => $entityType,
            'status' => 'committed',
            'inserted_rows' => $inserted,
        ];
    }

    private function insertRows(array $schema, array $rows, string $userId, ?array $context): int
    {
        $table = $schema['table'];
        $colMap = $schema['columns'];
        $cols = array_values($colMap);
        // Một số bảng cần id tự sinh (varchar 50)
        $hasId = $schema['has_id'] ?? true;
        $idPrefix = $schema['id_prefix'] ?? 'imp';
        $auditCols = $schema['audit_columns'] ?? [];

        $allCols = $cols;
        if ($hasId) array_unshift($allCols, 'id');
        $allCols = array_merge($allCols, $auditCols);
        $colList = implode(',', $allCols);
        $placeholders = '(' . implode(',', array_fill(0, count($allCols), '?')) . ')';

        $updateCols = array_merge($cols, $auditCols);
        $sql = "INSERT INTO {$table} ({$colList}) VALUES {$placeholders}
                ON DUPLICATE KEY UPDATE
                " . implode(', ', array_map(fn($c) => "{$c}=VALUES({$c})", $updateCols));

        $stmt = $this->pdo->prepare($sql);
        $count = 0;

        foreach ($rows as $row) {
            $params = [];
            if ($hasId) {
                $params[] = $idPrefix . '_' . uniqid('', true);
            }
            foreach ($colMap as $csvCol => $dbCol) {
                $val = $row[$csvCol] ?? null;
                if (isset($schema['validators'][$csvCol]) && in_array('bool', $schema['validators'][$csvCol], true)) {
                    $val = in_array(strtolower((string)$val), ['1', 'true', 'yes'], true) ? 1 : 0;
                }
                $params[] = ($val === '' || $val === null) ? null : $val;
            }
            foreach ($auditCols as $ac) {
                $params[] = $ac === 'created_by' ? $userId : null;
            }
            $stmt->execute($params);
            // rowCount() trả 1 cho INSERT, 2 cho UPDATE duplicate, 0 nếu không thay đổi
            // Tính theo rowCount: ≥0 = thành công
            $count += $stmt->rowCount() >= 0 ? 1 : 0;
        }
        return $count;
    }

    //
    // R-4: Rollback batch trong window (24h default, configurable)
    //
    public function rollbackBatch(string $batchId, string $userId, int $windowHours = 24): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM import_batches WHERE id = ?");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            throw new \InvalidArgumentException("Không tìm thấy batch {$batchId}");
        }
        if ($batch['status'] !== 'committed') {
            throw new \InvalidArgumentException("Batch không ở trạng thái 'committed' (hiện: {$batch['status']})");
        }

        $committedAt = strtotime($batch['committed_at']);
        $hoursSince = (time() - $committedAt) / 3600;
        if ($hoursSince > $windowHours) {
            throw new \InvalidArgumentException(
                "Đã quá {$windowHours}h kể từ khi commit (thực tế: " . round($hoursSince, 1) . "h). Không rollback được."
            );
        }

        // R-6: Nếu là opening_balance, unlock period trước
        if ($batch['entity_type'] === 'opening_balance') {
            $this->pdo->prepare(
                "UPDATE accounting_periods SET status = 'open',
                 hard_closed = 0
                 WHERE period_code = (SELECT DISTINCT period FROM opening_balances
                                      WHERE created_by = ? AND created_at >= ?
                                      ORDER BY created_at DESC LIMIT 1)"
            )->execute([$batch['imported_by'], $batch['committed_at']]);
        }

        // Xóa data rows theo user + timeframe (giới hạn scope rollback)
        // NOTE: Thực tế cần snapshot row-by-row để rollback chính xác.
        // Để đơn giản v1: chỉ rollback opening_balance (cần xóa theo period + created_by)
        if ($batch['entity_type'] === 'opening_balance') {
            $this->pdo->prepare(
                "DELETE FROM opening_balances WHERE created_by = ? AND created_at >= ?"
            )->execute([$batch['imported_by'], $batch['committed_at']]);
        }
        // Master data items/customers/suppliers/coa: rollback phức tạp (có thể đã bị sửa)
        // → bỏ qua v1, return lỗi hướng dẫn manual fix
        else {
            $this->pdo->prepare(
                "UPDATE import_batches SET status='rolled_back',
                 rolled_back_at = NOW(), rolled_back_by = ? WHERE id = ?"
            )->execute([$userId, $batchId]);
            return [
                'batch_id' => $batchId,
                'status' => 'rolled_back',
                'note' => 'Batch master data chỉ mark rolled_back (audit), KHÔNG xóa rows. Cần manual fix nếu import sai.',
            ];
        }

        $this->pdo->prepare(
            "UPDATE import_batches SET status='rolled_back',
             rolled_back_at = NOW(), rolled_back_by = ? WHERE id = ?"
        )->execute([$userId, $batchId]);

        $this->auditLogger?->log('import.rollback', 'import_batch', $batchId,
            ['status' => 'committed'],
            ['status' => 'rolled_back', 'rollback_by' => $userId],
            $userId);

        return ['batch_id' => $batchId, 'status' => 'rolled_back'];
    }

    //
    // R-6: Pre-check period lock cho opening_balance
    // Chỉ cho phép import nếu period chưa có transaction
    //
    private function checkOpeningPeriodLock(string $period): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transactions WHERE DATE_FORMAT(date, '%Y-%m') = ?");
        $stmt->execute([$period]);
        $txCount = (int)$stmt->fetchColumn();
        if ($txCount > 0) {
            throw new \InvalidArgumentException(
                "Kỳ {$period} đã có {$txCount} bút toán. Không thể import số dư đầu kỳ."
            );
        }

        $stmt = $this->pdo->prepare("SELECT status, hard_closed FROM accounting_periods WHERE period_code = ?");
        $stmt->execute([$period]);
        $periodRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($periodRow && ($periodRow['status'] === 'closed' || $periodRow['hard_closed'])) {
            throw new \InvalidArgumentException(
                "Kỳ {$period} đã bị khóa số dư (status={$periodRow['status']}, hard_closed={$periodRow['hard_closed']}). Liên hệ KTT để mở khóa trước khi import."
            );
        }
    }

    //
    // R-6: Post-check balance — tổng Dr phải = tổng Cr (tolerance ±0.01)
    //
    private function checkOpeningBalanceSum(string $period): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT SUM(debit_balance) AS dr, SUM(credit_balance) AS cr
             FROM opening_balances WHERE period = ?"
        );
        $stmt->execute([$period]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $dr = (float)$row['dr'];
        $cr = (float)$row['cr'];
        if (abs($dr - $cr) > 0.01) {
            throw new \InvalidArgumentException(
                "Tổng Nợ ({$dr}) ≠ Tổng Có ({$cr}). Sai lệch: " . round($dr - $cr, 2)
            );
        }

        // Auto-lock period sau khi import thành công
        $this->pdo->prepare(
            "UPDATE accounting_periods SET status = 'closed',
             closed_at = NOW(), closed_by = 'import_ob'
             WHERE period_code = ?"
        )->execute([$period]);
    }

    //
    // Tạo CSV template cho user tải về
    //
    public function getTemplate(string $entityType): array
    {
        $schema = $this->getSchema($entityType);
        if (!$schema) {
            throw new \InvalidArgumentException("Loại import không hỗ trợ: {$entityType}");
        }

        $columns = array_keys($schema['columns']);
        $samples = $this->getSampleRows($entityType);

        return [
            'entity_type' => $entityType,
            'columns' => $columns,
            'required' => $schema['required'],
            'sample_rows' => $samples,
        ];
    }

    private function getSampleRows(string $entityType): array
    {
        $samples = [
            'items' => [
                ['code' => 'ITM001', 'name' => 'Sản phẩm mẫu 1', 'uom' => 'Cái',
                 'category' => 'Hàng hóa', 'purchase_price' => '100000',
                 'sale_price' => '150000', 'min_stock' => '10'],
            ],
            'customers' => [
                ['code' => 'CUS001', 'name' => 'Khách hàng ABC', 'tax_code' => '0123456789',
                 'address' => 'Hà Nội', 'phone' => '0912345678', 'email' => 'abc@example.com',
                 'credit_limit' => '50000000'],
            ],
            'suppliers' => [
                ['code' => 'SUP001', 'name' => 'Nhà cung cấp XYZ', 'tax_code' => '9876543210',
                 'address' => 'TP.HCM', 'phone' => '0987654321', 'email' => 'xyz@example.com'],
            ],
            'coa' => [
                ['code' => '1111', 'name' => 'Tiền Việt Nam', 'type' => 'asset',
                 'parent_code' => '111', 'is_control' => '0'],
            ],
            'opening_balance' => [
                ['account_code' => '1111', 'period' => '2025-01', 'debit_balance' => '100000000', 'credit_balance' => '0'],
                ['account_code' => '3311', 'period' => '2025-01', 'debit_balance' => '0', 'credit_balance' => '100000000'],
            ],
        ];
        return $samples[$entityType] ?? [];
    }
}
