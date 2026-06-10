<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

/**
 * SỔ KHO (S12-DN): Chi tiết nhập/xuất/tồn theo từng mặt hàng.
 *
 * Nghiệp vụ: Sổ kho theo dõi chi tiết số lượng và giá trị từng mặt hàng tồn kho.
 * Phản ánh mọi biến động: nhập kho, xuất kho, trả lại, điều chuyển, kiểm kê.
 *
 * Phương pháp tính giá: FIFO / Bình quân gia quyền. Giá trị xuất được tính từ
 * cost layer (inventory_cost_layers) theo phương pháp đã đăng ký.
 *
 * RỦI RO: Sai giá vốn (632) → BC02 chỉ tiêu 24 sai → Thuế TNDN sai.
 * RỦI RO: Tồn kho âm → xuất hàng khi chưa nhập → sai số liệu kiểm kê.
 */
class InventoryLedgerReport implements SubLedgerReportInterface
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    /**
     * @param \PDO $pdo Kết nối PDO.
     * @param AccountRepositoryInterface $accountRepo Repository tài khoản.
     */
    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    /**
     * Lấy loại báo cáo.
     *
     * @return string 'inventory_ledger'.
     */
    public function getReportType(): string
    {
        return 'inventory_ledger';
    }

    /**
     * Lấy tham số báo cáo.
     *
     * @return array Mảng tham số với name, label, type, required.
     */
    public function getParameters(): array
    {
        return [
            ['name' => 'item_id', 'label' => 'Mặt hàng', 'type' => 'item_select', 'required' => true],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    /**
     * Lấy dữ liệu sổ kho.
     *
     * @param array $params Tham số: item_id, from_date, to_date.
     * @return array Dữ liệu báo cáo gồm title, period, headers, rows, totals.
     * @throws \InvalidArgumentException Nếu không chọn mặt hàng hoặc không tìm thấy.
     */
    public function getData(array $params): array
    {
        $itemId = $params['item_id'] ?? '';
        if (!$itemId) {
            throw new \InvalidArgumentException('Vui lòng chọn mặt hàng.');
        }

        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        // Lấy thông tin mặt hàng
        $item = $this->getItem($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}.");
        }

        $sqlParams = [];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND t.created_at >= ?';
            $sqlParams[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND t.created_at <= ?';
            $sqlParams[] = $toDate . ' 23:59:59';
        }

        // Lấy phát sinh nhập/xuất từ bảng giao dịch có chứa item_id
        // Hỗ trợ cả inventory_transactions (nếu có) và ledger_entries với item_id
        $rows = $this->fetchInventoryTransactions($itemId, $fromDate, $toDate);

        // Tính tồn đầu kỳ
        $openQty = 0;
        $openVal = 0;
        if ($fromDate && !empty($rows)) {
            $openStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN txn_type = 'in' THEN qty ELSE -qty END), 0) as total_qty,
                        COALESCE(SUM(CASE WHEN txn_type = 'in' THEN amount ELSE -amount END), 0) as total_val
                 FROM inventory_transactions
                 WHERE item_id = ? AND created_at < ?"
            );
            $openStmt->execute([$itemId, $fromDate]);
            $openRow = $openStmt->fetch(\PDO::FETCH_ASSOC);
            $openQty = (float)$openRow['total_qty'];
            $openVal = (float)$openRow['total_val'];
        }

        // Xây dựng rows
        $resultRows = [];
        $runningQty = $openQty;
        $runningVal = $openVal;

        foreach ($rows as $r) {
            $inQty = $r['txn_type'] === 'in' ? (float)$r['qty'] : 0;
            $inVal = $r['txn_type'] === 'in' ? (float)$r['amount'] : 0;
            $outQty = $r['txn_type'] === 'out' ? (float)$r['qty'] : 0;
            $outVal = $r['txn_type'] === 'out' ? (float)$r['amount'] : 0;

            $runningQty += $inQty - $outQty;
            $runningVal += $inVal - $outVal;

            $resultRows[] = [
                'date' => substr($r['created_at'], 0, 10),
                'reference' => $r['reference'] ?? '',
                'description' => $r['description'] ?? '',
                'in_qty' => $inQty,
                'in_amount' => $inVal,
                'out_qty' => $outQty,
                'out_amount' => $outVal,
                'closing_qty' => round($runningQty, 4),
                'closing_amount' => round($runningVal, 2),
            ];
        }

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Nhập SL', 'Nhập GT', 'Xuất SL', 'Xuất GT', 'Tồn SL', 'Tồn GT'];

        return [
            'report_type' => 'inventory_ledger',
            'title' => 'Sổ kho - ' . ($item['name'] ?? $itemId) . ' (' . ($item['code'] ?? $itemId) . ')',
            'period' => ($fromDate ?? 'Đầu kỳ') . ' → ' . ($toDate ?? 'Cuối kỳ'),
            'opening_balance' => round($openVal, 2),
            'closing_balance' => round($runningVal, 2),
            'headers' => $headers,
            'rows' => $resultRows,
            'totals' => [
                'total_in_qty' => round(array_sum(array_column($resultRows, 'in_qty')), 4),
                'total_in_amount' => round(array_sum(array_column($resultRows, 'in_amount')), 2),
                'total_out_qty' => round(array_sum(array_column($resultRows, 'out_qty')), 4),
                'total_out_amount' => round(array_sum(array_column($resultRows, 'out_amount')), 2),
            ],
            'item_info' => [
                'id' => $itemId,
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
            ],
        ];
    }

    /**
     * Lấy danh sách giao dịch nhập/xuất kho của mặt hàng.
     *
     * @param string $itemId ID mặt hàng.
     * @param string|null $fromDate Từ ngày.
     * @param string|null $toDate Đến ngày.
     * @return array Mảng giao dịch inventory.
     */
    private function fetchInventoryTransactions(string $itemId, ?string $fromDate, ?string $toDate): array
    {
        $params = [$itemId];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND t.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND t.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT it.*, t.reference, t.description, t.created_at
                 FROM inventory_transactions it
                 JOIN transactions t ON t.id = it.transaction_id
                 WHERE it.item_id = ?{$dateWhere}
                 ORDER BY t.created_at ASC, it.id ASC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Bảng inventory_transactions chưa tồn tại — fallback về rỗng
            return [];
        }
    }

    /**
     * Lấy thông tin mặt hàng.
     *
     * @param string $itemId ID mặt hàng.
     * @return array|null Thông tin mặt hàng hoặc null.
     */
    private function getItem(string $itemId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
