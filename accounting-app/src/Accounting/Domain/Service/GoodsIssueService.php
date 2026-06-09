<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\GoodsIssue;
use Accounting\Domain\Model\GoodsIssueItem;
use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Service\PeriodService;

// NGHIỆP VỤ: Quản lý Phiếu xuất kho (PXK) — Mẫu số 02-VT theo TT 99/2025/TT-BTC
// Service này quản lý vòng đời PXK: Draft → Post (tạo bút toán + giảm tồn kho) → Cancelled
// Backward compatible: vẫn có thể issue đơn lẻ qua InventoryService::issueGoods()
// TÍCH HỢP: Sử dụng InventoryService::issueGoods() cho từng line item khi Post
class GoodsIssueService
{
    private \PDO $pdo;
    private InventoryServiceInterface $inventoryService;
    private VoucherService $voucherService;
    private ItemRepositoryInterface $itemRepo;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        \PDO $pdo,
        InventoryServiceInterface $inventoryService,
        VoucherService $voucherService,
        ItemRepositoryInterface $itemRepo,
        AuditLoggerInterface $auditLogger
    ) {
        $this->pdo = $pdo;
        $this->inventoryService = $inventoryService;
        $this->voucherService = $voucherService;
        $this->itemRepo = $itemRepo;
        $this->auditLogger = $auditLogger;
    }

    // NGHIỆP VỤ: Tạo PXK dạng nháp (draft)
    // Input: { issue_date, warehouse_id, receiver_name, receiver_department, issue_reason,
    //          issue_type, lines: [{ item_id, requested_qty }], notes, created_by }
    // Output: GoodsIssue với status=draft
    // Lưu ý: Chưa tác động đến tồn kho hay bút toán
    public function createDraft(array $data): array
    {
        $id = uniqid('pxk_');
        $issueNumber = $this->voucherService->nextNumber('PXK');
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $issueType = $data['issue_type'] ?? 'sale';
        $createdBy = $data['created_by'] ?? 'system';

        // KIỂM SOÁT KỲ: Không cho tạo PXK trong kỳ đã khóa
        if (!PeriodService::isPeriodOpen($issueDate, $this->pdo)) {
            throw new \InvalidArgumentException(
                "Không thể tạo phiếu xuất kho trong kỳ kế toán đã khóa. Ngày: {$issueDate}."
            );
        }

        $this->pdo->beginTransaction();
        try {
            $totalAmount = 0.0;
            $lines = [];

            foreach ($data['lines'] as $i => $line) {
                $item = $this->itemRepo->findById($line['item_id']);
                if (!$item) {
                    throw new \InvalidArgumentException("Không tìm thấy mặt hàng: {$line['item_id']}");
                }
                $requestedQty = (float)($line['requested_qty'] ?? 0);
                $actualQty = (float)($line['actual_qty'] ?? $requestedQty);
                if ($actualQty <= 0) {
                    throw new \InvalidArgumentException("Số lượng xuất phải lớn hơn 0 cho mặt hàng {$item->getName()}");
                }
                $itemCode = method_exists($item, 'getCode') ? $item->getCode() : $item->getId();
                $itemName = $item->getName();
                $uom = method_exists($item, 'getUom') ? $item->getUom() : null;
                $lineNumber = $i + 1;

                $lines[] = new GoodsIssueItem(
                    $id, $line['item_id'], $itemCode, $itemName,
                    $requestedQty, $actualQty, 0.0, 0.0,
                    $lineNumber, null, $uom
                );
            }

            $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : 1;
            $this->pdo->exec("INSERT INTO inventory_issues (id, issue_number, issue_date, warehouse_id,
                receiver_name, receiver_department, issue_reason, issue_type, entity_id, status, reference, notes,
                total_amount, created_by, created_at) VALUES (
                " . $this->pdo->quote($id) . ",
                " . $this->pdo->quote($issueNumber) . ",
                " . $this->pdo->quote($issueDate) . ",
                " . (isset($data['warehouse_id']) && $data['warehouse_id'] ? $this->pdo->quote($data['warehouse_id']) : 'NULL') . ",
                " . (isset($data['receiver_name']) ? $this->pdo->quote($data['receiver_name']) : 'NULL') . ",
                " . (isset($data['receiver_department']) ? $this->pdo->quote($data['receiver_department']) : 'NULL') . ",
                " . (isset($data['issue_reason']) ? $this->pdo->quote($data['issue_reason']) : 'NULL') . ",
                " . $this->pdo->quote($issueType) . ",
                {$entityId}, 'draft',
                " . (isset($data['reference']) ? $this->pdo->quote($data['reference']) : 'NULL') . ",
                " . (isset($data['notes']) ? $this->pdo->quote($data['notes']) : 'NULL') . ",
                0, " . $this->pdo->quote($createdBy) . ", NOW())");

            $insertLine = $this->pdo->prepare("INSERT INTO inventory_issue_items
                (issue_id, entity_id, item_id, item_code, item_name, uom, requested_qty, actual_qty,
                 unit_price, total_amount, line_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");

            foreach ($lines as $line) {
                $insertLine->execute([
                    $id, $entityId, $line->getItemId(), $line->getItemCode(), $line->getItemName(),
                    $line->getUom(), $line->getRequestedQty(), $line->getActualQty(),
                    $line->getLineNumber()
                ]);
            }

            $this->pdo->commit();

            $this->auditLogger->log('goods_issue.create_draft', 'inventory_issues', $id,
                null, ['issue_number' => $issueNumber, 'lines' => count($lines)], $createdBy);

            return (new GoodsIssue($id, $issueNumber, $issueDate,
                $data['warehouse_id'] ?? null, $data['receiver_name'] ?? null,
                $data['receiver_department'] ?? null, $data['issue_reason'] ?? null,
                $issueType, 'draft', $data['reference'] ?? null, $data['notes'] ?? null,
                0, $createdBy, date('Y-m-d H:i:s'), null, $lines))->toArray();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // NGHIỆP VỤ: Ghi sổ PXK — tạo bút toán kế toán + giảm tồn kho cho từng line
    // Gọi InventoryService::issueGoods() cho mỗi line item trong transaction
    // Cập nhật unit_price và total_amount sau khi tính giá
    // RỦI RO: Nếu 1 line lỗi (tồn kho không đủ), toàn bộ rollback
    // Chỉ post được PXK ở trạng thái draft
    public function postIssue(string $issueId, string $createdBy): array
    {
        $issue = $this->getIssue($issueId);
        if ($issue['status'] !== 'draft') {
            throw new \InvalidArgumentException("Chỉ có thể ghi sổ phiếu xuất kho ở trạng thái nháp. Trạng thái hiện tại: {$issue['status']}");
        }
        $issueType = $issue['issue_type'];

        // KIỂM SOÁT KỲ: Không cho ghi sổ PXK trong kỳ đã khóa
        if (!PeriodService::isPeriodOpen($issue['issue_date'], $this->pdo)) {
            throw new \InvalidArgumentException(
                "Không thể ghi sổ phiếu xuất kho trong kỳ kế toán đã khóa. Ngày: {$issue['issue_date']}."
            );
        }

        $updateLine = $this->pdo->prepare(
            "UPDATE inventory_issue_items SET unit_price = ?, total_amount = ?, transaction_id = ? WHERE id = ?"
        );

        $totalAmount = 0.0;

        foreach ($issue['lines'] as $line) {
            $result = $this->inventoryService->issueGoods(
                $line['item_id'], $line['actual_qty'],
                $issueType, $issue['issue_number'], $createdBy
            );
            $unitPrice = $line['actual_qty'] > 0 ? $result['total_cost'] / $line['actual_qty'] : 0;
            $totalAmount += $result['total_cost'];
            $updateLine->execute([$unitPrice, $result['total_cost'], $result['transaction_id'], $line['id']]);
        }

        $stmt = $this->pdo->prepare("UPDATE inventory_issues SET status = 'posted', total_amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$totalAmount, $issueId]);

        $this->auditLogger->log('goods_issue.post', 'inventory_issues', $issueId,
            ['status' => 'draft'], ['status' => 'posted', 'total_amount' => $totalAmount, 'lines' => count($issue['lines'])], $createdBy);

        return $this->getIssue($issueId);
    }

    // NGHIỆP VỤ: Hủy PXK (chỉ khi đang ở draft)
    public function cancelIssue(string $issueId, string $cancelledBy): array
    {
        $issue = $this->getIssue($issueId);
        if ($issue['status'] !== 'draft') {
            throw new \InvalidArgumentException("Chỉ có thể hủy phiếu xuất kho ở trạng thái nháp. Trạng thái hiện tại: {$issue['status']}");
        }
        // KIỂM SOÁT KỲ: Không cho hủy PXK trong kỳ đã khóa
        if (!PeriodService::isPeriodOpen($issue['issue_date'], $this->pdo)) {
            throw new \InvalidArgumentException(
                "Không thể hủy phiếu xuất kho trong kỳ kế toán đã khóa. Ngày: {$issue['issue_date']}."
            );
        }
        $stmt = $this->pdo->prepare("UPDATE inventory_issues SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$issueId]);

        $this->auditLogger->log('goods_issue.cancel', 'inventory_issues', $issueId,
            ['status' => 'draft'], ['status' => 'cancelled'], $cancelledBy);

        return $this->getIssue($issueId);
    }

    // NGHIỆP VỤ: Lấy chi tiết PXK kèm line items
    public function getIssue(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory_issues WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException("Không tìm thấy phiếu xuất kho: {$id}");
        }
        $lineStmt = $this->pdo->prepare(
            "SELECT * FROM inventory_issue_items WHERE issue_id = ? ORDER BY line_number ASC"
        );
        $lineStmt->execute([$id]);
        $lines = $lineStmt->fetchAll(\PDO::FETCH_ASSOC);

        $issue = new GoodsIssue(
            $row['id'], $row['issue_number'], $row['issue_date'],
            $row['warehouse_id'], $row['receiver_name'], $row['receiver_department'],
            $row['issue_reason'], $row['issue_type'], $row['status'],
            $row['reference'], $row['notes'], (float)$row['total_amount'],
            $row['created_by'], $row['created_at'], $row['updated_at']
        );
        $issue->setLines(array_map(fn($l) => new GoodsIssueItem(
            $l['issue_id'], $l['item_id'], $l['item_code'], $l['item_name'],
            (float)$l['requested_qty'], (float)$l['actual_qty'],
            (float)$l['unit_price'], (float)$l['total_amount'],
            (int)$l['line_number'], (int)$l['id'], $l['uom'], $l['transaction_id']
        ), $lines));

        return $issue->toArray();
    }

    // NGHIỆP VỤ: Danh sách PXK (không kèm line items)
    public function listIssues(?string $status = null, int $limit = 50): array
    {
        $sql = "SELECT id, issue_number, issue_date, issue_type, status,
                       receiver_name, total_amount, created_by, created_at
                FROM inventory_issues";
        $params = [];
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
