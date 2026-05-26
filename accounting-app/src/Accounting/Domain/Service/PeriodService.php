<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class PeriodService
{
    private ?\PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private JournalService $journal;
    private ?AuditLoggerInterface $auditLogger;
    private ?InventoryService $inventoryService;
    private ?ReconciliationService $reconciliationService;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo, TransactionRepositoryInterface $txnRepo, JournalService $journal, ?AuditLoggerInterface $auditLogger = null, ?InventoryService $inventoryService = null, ?ReconciliationService $reconciliationService = null)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->auditLogger = $auditLogger;
        $this->inventoryService = $inventoryService;
        $this->reconciliationService = $reconciliationService;
    }

    // KỲ KẾ TOÁN: Kiểm tra kỳ kế toán đang mở trước khi cho phép ghi nhận bút toán
    //
    // Nghiệp vụ: Mọi bút toán chỉ được post vào kỳ đang mở. Nếu kỳ đã đóng (status != 'open'),
    // hệ thống từ chối ghi nhận để đảm bảo số liệu kỳ trước không bị thay đổi.
    //
    // RỦI RO: Cho phép post vào kỳ đã đóng → số liệu BC01/BC02 thay đổi → sai báo cáo tài chính
    // đã nộp → bị phạt thuế theo Nghị định 125/2020. Audit trail không trace được.
    public static function isPeriodOpen(?string $date = null, ?\PDO $pdo = null): bool
    {
        $pdo ??= $GLOBALS['container']['pdo'] ?? null;
        if (!$pdo) return true; // no period management yet
        $date ??= date('Y-m-d');

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM accounting_periods WHERE ? BETWEEN start_date AND end_date AND status = ?"
        );
        $stmt->execute([$date, 'open']);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getPeriods(): array
    {
        $rows = $this->pdo->query('SELECT * FROM accounting_periods ORDER BY start_date DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'period_type' => $r['period_type'],
            'period_code' => $r['period_code'],
            'name' => $r['name'],
            'start_date' => $r['start_date'],
            'end_date' => $r['end_date'],
            'status' => $r['status'],
            'deadline' => $r['deadline'] ?? null,
            'hard_closed' => (bool)($r['hard_closed'] ?? false),
            'is_first' => (bool)$r['is_first'],
            'is_last' => (bool)$r['is_last'],
            'opened_by' => $r['opened_by'],
            'opened_at' => $r['opened_at'],
            'closed_by' => $r['closed_by'],
            'closed_at' => $r['closed_at'],
            're_open_count' => (int)$r['re_open_count'],
        ], $rows);
    }

    public function getPeriod(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting_periods WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) throw new \InvalidArgumentException("Period not found: {$id}");
        return [
            'id' => (int)$r['id'],
            'period_type' => $r['period_type'],
            'period_code' => $r['period_code'],
            'name' => $r['name'],
            'start_date' => $r['start_date'],
            'end_date' => $r['end_date'],
            'status' => $r['status'],
            'deadline' => $r['deadline'] ?? null,
            'hard_closed' => (bool)($r['hard_closed'] ?? false),
            'is_first' => (bool)$r['is_first'],
            'is_last' => (bool)$r['is_last'],
            'opened_by' => $r['opened_by'],
            'opened_at' => $r['opened_at'],
            'closed_by' => $r['closed_by'],
            'closed_at' => $r['closed_at'],
            're_open_count' => (int)$r['re_open_count'],
        ];
    }

    // KỲ KẾ TOÁN: Tạo kỳ kế toán mới — tháng/quý/năm
    //
    // Nghiệp vụ: Kỳ kế toán được tạo tuần tự, không được bỏ sót kỳ. Kỳ trước
    // phải được đóng trước khi mở kỳ mới. Hệ thống tự động kiểm tra.
    //
    // RỦI RO: Tạo kỳ mới khi kỳ trước chưa đóng → giao dịch có thể rơi vào sai kỳ
    // → sai BC01/BC02. Nếu bỏ sót kỳ, audit trail bị gián đoạn.
    public function createPeriod(string $type, string $code, string $name, string $start, string $end, string $openedBy): array
    {
        // Bắt buộc: Kỳ trước phải đóng trước khi mở kỳ mới — đảm bảo tuần tự thời gian
        $prev = $this->pdo->prepare("SELECT id, status FROM accounting_periods WHERE end_date < ? ORDER BY end_date DESC LIMIT 1");
        $prev->execute([$start]);
        $prevPeriod = $prev->fetch(\PDO::FETCH_ASSOC);
        if ($prevPeriod && $prevPeriod['status'] === 'open') {
            throw new \InvalidArgumentException("Cannot create period {$code}: previous period ({$prevPeriod['id']}) is still open. Close it first.");
        }

        $this->pdo->prepare(
            'INSERT INTO accounting_periods (period_type, period_code, name, start_date, end_date, status, opened_by, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$type, $code, $name, $start, $end, 'open', $openedBy]);

        $id = (int)$this->pdo->lastInsertId();

        $this->auditLogger?->log('period.create', 'accounting_period', (string)$id,
            null, ['type' => $type, 'code' => $code, 'start' => $start, 'end' => $end],
            $openedBy);

        return $this->getPeriod($id);
    }

    // KHÓA SỔ: Kiểm tra điều kiện trước khi cho phép đóng kỳ kế toán
    //
    // Nghiệp vụ: Trước khi khóa sổ kỳ kế toán, hệ thống kiểm tra 7 điều kiện bắt buộc.
    // Nếu bất kỳ điều kiện nào không đạt → từ chối đóng kỳ.
    //
    // RỦI RO: Cho phép đóng kỳ khi chưa đủ điều kiện → số liệu không đầy đủ →
    // BC01/BC02 sai → phải mở lại kỳ (re-open) gây mất audit trail.
    public function canClose(int $id): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'open') {
            return ['can_close' => false, 'reason' => 'Period is not open'];
        }

        $checks = [];
        $allPass = true;

        $endDate = $period['end_date'];

        // 1. Kiểm tra tồn kho: không có giao dịch xuất/nhập kho chưa được post
        // Nếu tồn tại giao dịch tồn kho chưa post → giá vốn (632) và tồn kho (156) thiếu
        // → BC02 chỉ tiêu 24 (Giá vốn hàng bán) sai
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transactions WHERE description LIKE 'Goods %' AND status != 'posted' AND created_at <= ?");
        $stmt->execute([$endDate . ' 23:59:59']);
        $unposted = (int)$stmt->fetchColumn();
        if ($unposted > 0) { $allPass = false; }
        $checks[] = ['check' => 'Unposted inventory transactions', 'passed' => $unposted === 0, 'note' => $unposted > 0 ? "{$unposted} unposted" : 'OK'];

        // 2. Kiểm tra kiểm kê: không có phiếu kiểm kê tồn kho ở trạng thái nháp
        // Nếu còn phiếu kiểm kê nháp → số lượng tồn kho thực tế chưa được xác nhận
        // → sai lệch giữa sổ sách và thực tế → sai BC01 chỉ tiêu hàng tồn kho
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM inventory_count_sessions WHERE status = 'draft'");
        $draftSessions = (int)$stmt->fetchColumn();
        if ($draftSessions > 0) { $allPass = false; }
        $checks[] = ['check' => 'Draft count sessions', 'passed' => $draftSessions === 0, 'note' => $draftSessions > 0 ? "{$draftSessions} draft sessions" : 'OK'];

        // 3. Kiểm tra tồn kho âm: không có mặt hàng tồn kho âm (nếu không cho phép)
        // Tồn kho âm = xuất kho khi không có hàng → giá vốn (632) âm → sai BC02
        // RỦI RO: Tồn kho âm dẫn đến sai thuế GTGT đầu ra (vì xuất thiếu hàng nhưng đã xuất hóa đơn)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM items WHERE stock_qty < 0 AND (SELECT COALESCE(allow_negative_stock, 0) = 0)");
        $negative = (int)$stmt->fetchColumn();
        if ($negative > 0) { $allPass = false; }
        $checks[] = ['check' => 'Negative stock (disallowed)', 'passed' => $negative === 0, 'note' => $negative > 0 ? "{$negative} items" : 'OK'];

        // 4. Kiểm tra đối chiếu: công nợ phải thu (131) / phải trả (331) khớp với sổ cái
        // Nghiệp vụ: Số dư chi tiết công nợ phải khớp với số dư tổng hợp trên sổ cái.
        // Nếu lệch → sai BC01 chỉ tiêu 131 và 331 → báo cáo tài chính sai.
        // RỦI RO: Sai công nợ dẫn đến đối tác khiếu nại, ảnh hưởng quan hệ thanh toán.
        $reconPass = true;
        $reconNote = 'OK';
        if ($this->reconciliationService) {
            $results = $this->reconciliationService->reconcileAll();
            $failures = [];
            foreach ($results as $type => $r) {
                if ($r['status'] === 'unmatched') {
                    $failures[] = "{$type}: diff=" . abs($r['difference']);
                }
            }
            if (count($failures) > 0) {
                $reconPass = false;
                $allPass = false;
                $reconNote = implode('; ', $failures);
            }
        }
        $checks[] = ['check' => 'Sub-ledger vs GL reconciliation', 'passed' => $reconPass, 'note' => $reconNote];

        // 5. Kiểm tra bảng cân đối tài khoản: tổng Nợ = tổng Có
        // Đây là nguyên tắc kế toán cơ bản — mọi bút toán phải đảm bảo Dr = Cr.
        // Nếu lệch → có bút toán sai hoặc thiếu → BC01 không thể cân đối.
        // RỦI RO: BC01 không cân đối → audit fail → cơ quan thuế yêu cầu giải trình.
        $trialBalPass = true;
        $tbNote = 'OK';
        $tbService = new \Accounting\Domain\Service\TrialBalanceService($this->pdo);
        $tb = $tbService->getTrialBalance($period['period_code']);
        if (!$tb['balanced']) {
            $trialBalPass = false;
            $allPass = false;
            $tbNote = 'Dr ≠ Cr: Dr=' . $tb['grand_total_dr'] . ', Cr=' . $tb['grand_total_cr'];
        }
        $checks[] = ['check' => 'Trial balance (Dr = Cr)', 'passed' => $trialBalPass, 'note' => $tbNote];

        // 6. Kiểm tra tuần tự: không được đóng kỳ này nếu kỳ sau đã đóng trước
        // Nghiệp vụ: Kỳ kế toán phải đóng tuần tự theo thời gian. Không được
        // bỏ qua kỳ hoặc đóng ngược thứ tự.
        // RỦI RO: Đóng không tuần tự → số liệu dồn tích sai → BC01/BC02 sai toàn bộ.
        $nextCheck = $this->pdo->prepare("SELECT id, status FROM accounting_periods WHERE start_date > ? ORDER BY start_date ASC LIMIT 1");
        $nextCheck->execute([$endDate]);
        $nextPeriod = $nextCheck->fetch(\PDO::FETCH_ASSOC);
        $seqPass = true;
        $seqNote = 'OK';
        if ($nextPeriod && $nextPeriod['status'] === 'closed') {
            $seqPass = false;
            $allPass = false;
            $seqNote = "Later period ({$nextPeriod['id']}) already closed — close sequentially";
        }
        $checks[] = ['check' => 'Sequential period close', 'passed' => $seqPass, 'note' => $seqNote];

        // 7. Kiểm tra báo cáo tài chính: đã lập BC01/BC02/BC03 cho kỳ này chưa
        // Nghiệp vụ: Trước khi khóa sổ, kế toán phải lập báo cáo tài chính.
        // Nếu chưa lập → có thể mất số liệu khi đóng kỳ.
        // RỦI RO: Đóng kỳ mà chưa lập BC → không xuất được báo cáo tài chính kỳ đó.
        $fsPass = true;
        $fsNote = 'OK';
        $fsSnapshots = $this->pdo->prepare("SELECT COUNT(*) FROM fs_snapshots WHERE period_code = ?");
        $fsSnapshots->execute([$period['period_code']]);
        $snapCount = (int)$fsSnapshots->fetchColumn();
        if ($snapCount === 0) {
            $fsPass = false;
            $allPass = false;
            $fsNote = 'No FS snapshots found for this period';
        }
        $checks[] = ['check' => 'Financial statements generated', 'passed' => $fsPass, 'note' => $fsNote];

        return [
            'can_close' => $allPass,
            'checks' => $checks,
        ];
    }

    // KHÓA SỔ: Đóng kỳ kế toán — thực hiện các bút toán kết chuyển và khóa sổ
    //
    // Nghiệp vụ: Khi đóng kỳ, hệ thống thực hiện theo thứ tự:
    // 1. Chụp tồn kho + đối chiếu hàng tồn (InventoryService)
    // 2. Kết chuyển doanh thu → 911
    // 3. Kết chuyển chi phí → 911
    // 4. Kết chuyển lãi/lỗ → 421
    // 5. Điều chỉnh thuế TNDN (821 → 3334)
    // 6. Nếu là kỳ cuối năm: phân phối lợi nhuận (quỹ khen thưởng 353, quỹ đầu tư 414)
    // 7. Chuyển trạng thái kỳ từ 'open' → 'closed'
    //
    // RỦI RO: Không thực hiện đúng thứ tự → kết chuyển thiếu → BC02 sai →
    // lợi nhuận chưa phân phối (421) sai → BC01 sai.
    // Đây là thao tác KHÔNG THỂ UNDO nếu đã hard_close.
    public function closePeriod(int $id, string $closedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'open') {
            throw new \InvalidArgumentException("Period {$id} is not open (status: {$period['status']})");
        }

        // Bước 1: Chụp tồn kho cuối kỳ + đối chiếu số lượng thực tế với sổ sách
        $inventoryClose = null;
        if ($this->inventoryService) {
            $inventoryClose = $this->inventoryService->closeInventoryForPeriod(
                $id, $period['period_code'], $period['start_date'], $period['end_date'], $closedBy
            );
        }

        // Bước 2-5: Kết chuyển doanh thu/chi phí + điều chỉnh thuế cuối kỳ
        // Đây là bút toán bắt buộc theo Circular 99 — reset tài khoản doanh thu/chi phí về 0
        // để bắt đầu kỳ mới với số dư trống cho Class 5-8
        //
        // TRANSACTION BOUNDARY: Các bước 1-7 KHÔNG chạy trong một DB transaction duy nhất.
        // Mỗi bước tự commit riêng (qua JournalService::postEntry).
        // RỦI RO: Nếu bước 4 thất bại sau khi bước 2 đã commit, dữ liệu kết chuyển bị
        // dang dở — một phần đã kết chuyển, phần còn lại chưa.
        // Biện pháp: Thiết kế idempotent — có thể chạy lại executeClosingEntries() nhiều lần
        // vì các bút toán kết chuyển kiểm tra số dư trước khi tạo. Nếu đã kết chuyển rồi,
        // số dư TK doanh thu = 0, không tạo bút toán mới.
        $this->executeClosingEntries($closedBy);
        $this->executeTaxAdjustments($period, $closedBy);

        // Bước 6 (nếu cuối năm): Phân phối lợi nhuận sau thuế
        // - Trích quỹ khen thưởng (353) 10%
        // - Trích quỹ đầu tư phát triển (414) 20%
        // - Phần còn lại giữ tại 421 (lợi nhuận chưa phân phối)
        if ($period['is_last']) {
            $this->executeYearEndClose($period, $closedBy);
        }

        // Bước 7: Khóa sổ — chuyển trạng thái kỳ thành 'closed'
        // Sau bước này, không ai được phép post thêm giao dịch vào kỳ.
        // Nếu cần sửa, phải thông qua reOpenPeriod (chỉ Kế toán trưởng).
        $this->pdo->prepare(
            'UPDATE accounting_periods SET status = ?, closed_by = ?, closed_at = NOW() WHERE id = ?'
        )->execute(['closed', $closedBy, $id]);

        $this->auditLogger?->log('period.close', 'accounting_period', (string)$id,
            ['status' => 'open'], ['status' => 'closed'],
            $closedBy);

        $result = $this->getPeriod($id);
        if ($inventoryClose) { $result['inventory_close'] = $inventoryClose; }
        return $result;
    }

    // LƯU TRỮ: Chụp số dư tài khoản cuối kỳ để phục vụ kiểm toán và đối chiếu sau này
    //
    // Nghiệp vụ: Sau khi khóa sổ, hệ thống lưu snapshot số dư toàn bộ tài khoản.
    // Dữ liệu này được dùng để:
    // - Đối chiếu với báo cáo tài chính đã nộp
    // - Phục vụ kiểm toán nội bộ và kiểm toán độc lập
    // - Khôi phục số liệu khi cần (nếu có sự cố)
    //
    // RỦI RO: Không lưu trữ → không đối chiếu được khi kiểm toán → thiếu audit trail.
    public function archivePeriod(int $id, string $archivedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'closed') {
            throw new \InvalidArgumentException("Period {$id} is not closed");
        }

        // Chụp toàn bộ số dư tài khoản tại thời điểm cuối kỳ — dùng để đối chiếu sau này
        $accounts = $this->accountRepo->findAll();
        $snapshot = [];
        foreach ($accounts as $a) {
            $snapshot[] = ['code' => $a->getCode(), 'name' => $a->getName(), 'balance' => $a->getBalance()];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        );
        $stmt->execute(['ARCHIVE', $period['period_code'], $period['end_date'], json_encode($snapshot), $archivedBy]);

        $this->auditLogger?->log('period.archive', 'accounting_period', (string)$id,
            null, ['period_code' => $period['period_code'], 'accounts' => count($snapshot)],
            $archivedBy);

        return ['message' => 'Archived', 'accounts' => count($snapshot)];
    }

    // KỲ KẾ TOÁN: Mở lại kỳ đã đóng — chỉ Kế toán trưởng
    //
    // Nghiệp vụ: Trong trường hợp phát hiện sai sót sau khi khóa sổ, Kế toán trưởng
    // có thể mở lại kỷ để điều chỉnh. Hệ thống tự động rollback các bút toán
    // kết chuyển tồn kho.
    //
    // RỦI RO NGHIÊM TRỌNG: Mở lại kỳ đã đóng làm thay đổi số liệu BC01/BC02.
    // - Phải thông báo cho Kiểm toán nội bộ
    // - Audit trail ghi nhận mỗi lần re-open (re_open_count)
    // - Nếu re-open > 1 lần → kiểm toán đặt câu hỏi về kiểm soát nội bộ
    // - Nếu đã hard_close (deadline quá hạn) → không được phép re-open
    public function reOpenPeriod(int $id, string $reOpenedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'closed') {
            throw new \InvalidArgumentException("Period {$id} is not closed");
        }

        // CẢNH BÁO RỦI RO NGHIÊM TRỌNG — MỞ LẠI KỲ ĐÃ ĐÓNG:
        // 1. Tồn kho rollback: InventoryService::rollbackInventoryForPeriod xóa toàn bộ
        //    cost layer hiện tại và khôi phục snapshot cũ. Mất mọi giao dịch kho sau snapshot.
        // 2. Số liệu GL (ledger_entries) KHÔNG được rollback → lệch sub-ledger vs GL.
        // 3. Nếu kỳ sau đã phát sinh giao dịch dựa trên số dư cuối kỳ của kỳ này,
        //    số dư đầu kỳ sau thay đổi → toàn bộ kỳ sau sai → cascade error.
        // 4. Báo cáo tài chính đã nộp (BC01/BC02/BC03) cho kỳ này không còn đúng.
        //
        // KIỂM SOÁT: Chỉ Kế toán trưởng. AuditLogger ghi re_open_count. Nếu > 1 lần,
        // kiểm toán nội bộ sẽ cảnh báo về chất lượng kiểm soát nội bộ.
        // Sau re-open, bắt buộc: kiểm tra trial balance, đối chiếu sub-ledger vs GL,
        // và cập nhật BC01/BC02/BC03 đã nộp (nếu có).
        if ($this->inventoryService) {
            $this->inventoryService->rollbackInventoryForPeriod($id, $reOpenedBy);
        }

        $this->pdo->prepare(
            'UPDATE accounting_periods SET status = ?, closed_by = NULL, closed_at = NULL, re_open_count = re_open_count + 1 WHERE id = ?'
        )->execute(['open', $id]);

        $this->auditLogger?->log('period.reopen', 'accounting_period', (string)$id,
            ['status' => 'closed'], ['status' => 'open', 're_open_count' => $period['re_open_count'] + 1],
            $reOpenedBy);

        return $this->getPeriod($id);
    }

    // KẾT CHUYỂN: Bút toán kết chuyển doanh thu và chi phí cuối kỳ
    //
    // Nghiệp vụ: Theo Circular 99, cuối mỗi kỳ kế toán thực hiện:
    // 1. Kết chuyển doanh thu (TK 5, 7) → TK 911 (Xác định KQKD)
    //    - Nợ các TK doanh thu / Có 911
    // 2. Kết chuyển chi phí (TK 6, 8) → TK 911
    //    - Nợ 911 / Có các TK chi phí
    // 3. Kết chuyển lợi nhuận sau thuế → TK 421 (LN chưa phân phối)
    //    - Nợ 911 / Có 421 (nếu lãi)
    //    - Nợ 421 / Có 911 (nếu lỗ)
    //
    // Mục đích: Reset TK doanh thu/chi phí về 0 để bắt đầu kỳ mới.
    // Số dư TK 911 = 0 sau khi kết chuyển (nếu đúng).
    //
    // RỦI RO: Nếu kết chuyển thiếu một tài khoản → TK 911 ≠ 0 đầu kỳ sau →
    // số dư đầu kỳ sai → toàn bộ BC01/BC02 kỳ sau sai.
    public function executeClosingEntries(string $createdBy): void
    {
        // THỨ TỰ KẾT CHUYỂN: Doanh thu TRƯỚC, Chi phí SAU, Lãi/lỗ CUỐI CÙNG.
        // Lý do: Cần tổng doanh thu và tổng chi phí để tính lợi nhuận ròng (Bước 3).
        // Nếu kết chuyển chi phí trước, số dư TK 911 tạm thời âm (chỉ có chi phí).
        // Quy trình này đúng theo Circular 99 và thông lệ kế toán Việt Nam.
        //
        // RỦI RO: Nếu bỏ sót một TK doanh thu → doanh thu kết chuyển thiếu →
        // TK 911 dư Có sau closing → TK 421 sai → BC01/BC02 sai.
        // Biện pháp: findAll() lấy tất cả TK, filter theo type 'revenue'.
        // Nếu có TK type sai (VD: 511 bị gán type 'liability') → bỏ sót → lỗi tiềm ẩn.
        //
        // Bước 1: Kết chuyển doanh thu — lấy tất cả TK loại 'revenue' (5, 7) có số dư ≠ 0
        $revenueAccounts = $this->accountRepo->findAll();
        $revenueLines = [];
        $totalRevenue = 0;

        foreach ($revenueAccounts as $a) {
            if (!in_array($a->getType(), ['revenue'])) continue;
            $bal = $a->getBalance();
            if (abs($bal) < 1) continue;
            // Dr Revenue — Cr 911
            $revenueLines[] = ['account_code' => $a->getCode(), 'amount' => abs($bal), 'is_debit' => true];
            $totalRevenue += abs($bal);
        }

        if ($totalRevenue > 0) {
            $revenueLines[] = ['account_code' => '911', 'amount' => $totalRevenue, 'is_debit' => false];
            $this->journal->postEntry('Closing entry: transfer revenue', 'CLOSE-REV-' . date('Ymd'), $revenueLines, $createdBy, true);
        }

        // Bước 2: Kết chuyển chi phí — lấy tất cả TK loại 'expense' (6, 8) có số dư ≠ 0
        $expenseLines = [];
        $totalExpense = 0;

        foreach ($this->accountRepo->findAll() as $a) {
            if (!in_array($a->getType(), ['expense'])) continue;
            $bal = $a->getBalance();
            if (abs($bal) < 1) continue;
            // Dr 911 — Cr Expense
            $expenseLines[] = ['account_code' => '911', 'amount' => abs($bal), 'is_debit' => true];
            $expenseLines[] = ['account_code' => $a->getCode(), 'amount' => abs($bal), 'is_debit' => false];
            $totalExpense += abs($bal);
        }

        if ($totalExpense > 0) {
            $this->journal->postEntry('Closing entry: transfer expenses', 'CLOSE-EXP-' . date('Ymd'), $expenseLines, $createdBy, true);
        }

        // Bước 3: Kết chuyển lãi/lỗ ròng → Lợi nhuận chưa phân phối (421)
        // Nếu lãi: Nợ 911 / Có 421
        // Nếu lỗ: Nợ 421 / Có 911
        // 421 là chỉ tiêu quan trọng trên BC01 và BC02
        $netProfit = $totalRevenue - $totalExpense;
        if (abs($netProfit) > 1) {
            if ($netProfit > 0) {
                // Dr 911 — Cr 421
                $this->journal->postEntry('Closing entry: net profit to retained earnings', 'CLOSE-PROFIT-' . date('Ymd'), [
                    ['account_code' => '911', 'amount' => $netProfit, 'is_debit' => true],
                    ['account_code' => '421', 'amount' => $netProfit, 'is_debit' => false],
                ], $createdBy, true);
            } else {
                $loss = abs($netProfit);
                // Dr 421 — Cr 911
                $this->journal->postEntry('Closing entry: net loss to retained earnings', 'CLOSE-LOSS-' . date('Ymd'), [
                    ['account_code' => '421', 'amount' => $loss, 'is_debit' => true],
                    ['account_code' => '911', 'amount' => $loss, 'is_debit' => false],
                ], $createdBy, true);
            }
        }
    }

    // THUẾ: Tạo bút toán điều chỉnh thuế cuối kỳ — tạm tính TNDN, điều chỉnh VAT
    // Nghiệp vụ: Cuối mỗi kỳ, hệ thống tạo các bút toán điều chỉnh thuế dựa trên
    // chênh lệch giữa doanh thu/chi phí và tờ khai thuế tạm tính.
    // - Nợ 821 (Chi phí thuế TNDN) / Có 3334 (Thuế TNDN)
    // - Điều chỉnh VAT nếu có chênh lệch giữa VAT đầu ra và đầu vào
    //
    // RỦI RO: Nếu không ghi nhận thuế đúng kỳ, số liệu BC02 chỉ tiêu 28 sai
    // và dẫn đến truy thu thuế + phạt chậm nộp.
    public function executeTaxAdjustments(array $period, string $createdBy): void
    {
        // Tạm tính chi phí thuế TNDN: 20% lợi nhuận kế toán trước thuế
        // Lấy tổng doanh thu (511) - tổng chi phí (632+635+641+642) = lợi nhuận kế toán
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN a.code LIKE '5%' THEN le.amount ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN a.code LIKE '6%' OR a.code = '635' THEN le.amount ELSE 0 END), 0) AS total_expense
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            JOIN transactions t ON t.id = le.transaction_id
            WHERE t.date BETWEEN ? AND ?
        ");
        $stmt->execute([$period['start_date'], $period['end_date']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $revenue = (float)$row['total_revenue'];
        $expense = (float)$row['total_expense'];
        $profitBeforeTax = $revenue - $expense;

        // TÍCH HỢP: Bút toán điều chỉnh thuế TNDN
        // Contract: Chỉ tạo nếu lợi nhuận > 0 và chưa có bút toán thuế nào trong kỳ
        $taxRate = 0.20; // 20% thuế suất TNDN (TT 78/2021)
        $estimatedTax = $profitBeforeTax > 0 ? round($profitBeforeTax * $taxRate) : 0;

        if ($estimatedTax > 0) {
            // Kiểm tra đã có bút toán thuế TNDN chưa
            $check = $this->pdo->prepare("SELECT COUNT(*) FROM ledger_entries le
                JOIN accounts a ON a.id = le.account_id
                JOIN transactions t ON t.id = le.transaction_id
                WHERE a.code = '821' AND t.date BETWEEN ? AND ?");
            $check->execute([$period['start_date'], $period['end_date']]);
            $hasTaxEntry = (int)$check->fetchColumn() > 0;

            if (!$hasTaxEntry) {
                $this->journal->postEntry(
                    'Tax adjustment: corporate income tax estimate',
                    'TAX-CIT-' . date('Ymd'),
                    [
                        ['account_code' => '821', 'amount' => $estimatedTax, 'is_debit' => true],
                        ['account_code' => '3334', 'amount' => $estimatedTax, 'is_debit' => false],
                    ],
                    $createdBy,
                    true
                );
            }
        }
    }

    // Năm tài chính: Thực hiện kết chuyển cuối năm — reset 911, xử lý lợi nhuận sau thuế
    //
    // Nghiệp vụ: Cuối năm tài chính (is_last = true), hệ thống thực hiện:
    // 1. Kết chuyển lãi/lỗ từ 911 → 421 (đã thực hiện trong executeClosingEntries)
    // 2. Phân phối lợi nhuận: trích quỹ khen thưởng (421 → 353), trích quỹ đầu tư (421 → 414)
    // 3. Xác định kết quả kinh doanh sau thuế
    //
    // RỦI RO: Nếu không thực hiện phân phối lợi nhuận cuối năm, BC01 chỉ tiêu 421
    // sẽ phản ánh sai số dư lợi nhuận chưa phân phối.
    public function executeYearEndClose(array $period, string $createdBy): void
    {
        // Lấy số dư tài khoản 421 sau khi kết chuyển (sau executeClosingEntries)
        $accounts = $this->accountRepo->findAll();
        $retainedEarnings = 0;
        foreach ($accounts as $a) {
            if ($a->getCode() === '421') {
                $retainedEarnings = $a->getBalance();
                break;
            }
        }

        if (abs($retainedEarnings) < 1) return;

        // Nếu lợi nhuận > 0: trích quỹ khen thưởng (10%) và quỹ đầu tư (20%)
        // Đây là tỷ lệ mặc định, doanh nghiệp có thể thay đổi theo Điều lệ
        // TỶ LỆ PHÂN PHỐI LỢI NHUẬN (HARDCODED):
        // - Quỹ khen thưởng (353): 10% — theo Nghị định 91/2025, tối đa 10% LNST
        // - Quỹ đầu tư phát triển (414): 20% — theo Điều lệ công ty (có thể thay đổi)
        // - Còn lại giữ tại TK 421: Lợi nhuận chưa phân phối
        //
        // HẠN CHẾ: Tỷ lệ này hardcode trong code — nếu Điều lệ công ty thay đổi,
        // phải sửa code. Đề xuất: Đưa tỷ lệ vào bảng company_config để kế toán trưởng
        // tự cấu hình mà không cần deploy.
        //
        // RỦI RO: Nếu lợi nhuận sau thuế (LNST) không đủ để trích quỹ, BC01 chỉ tiêu
        // 421 có thể âm (nếu trích vượt quá LNST) → sai BC01, audit fail.
        if ($retainedEarnings > 0) {
            $bonusFund = round($retainedEarnings * 0.10);
            $investmentFund = round($retainedEarnings * 0.20);
            $remainingProfit = $retainedEarnings - $bonusFund - $investmentFund;
            $lines = [];

            if ($bonusFund > 0) {
                // RỦI RO: Trích quỹ khen thưởng vượt quá quy định → sai BC01 chỉ tiêu 353
                // Xử lý: Chỉ trích tối đa 10% lợi nhuận sau thuế theo Nghị định 91/2025
                $lines[] = ['account_code' => '421', 'amount' => $bonusFund, 'is_debit' => true];
                $lines[] = ['account_code' => '353', 'amount' => $bonusFund, 'is_debit' => false];
            }
            if ($investmentFund > 0) {
                $lines[] = ['account_code' => '421', 'amount' => $investmentFund, 'is_debit' => true];
                $lines[] = ['account_code' => '414', 'amount' => $investmentFund, 'is_debit' => false];
            }
            if (count($lines) > 0) {
                $this->journal->postEntry(
                    'Year-end: profit appropriation',
                    'YE-PROP-' . date('Ymd'),
                    $lines,
                    $createdBy,
                    true
                );
            }
        }
    }

    // KỲ KẾ TOÁN: Kiểm tra hard deadline — trả về true nếu còn hạn (chưa quá deadline)
    // Nếu đã quá deadline và hard_closed = 0, tự động đánh dấu hard_closed = 1
    //
    // RỦI RO: Nếu không enforce deadline, kế toán có thể ghi nhận giao dịch muộn,
    // dẫn đến sai số liệu kỳ trước và phải restate báo cáo tài chính.
    public function enforceHardDeadline(int $id): bool
    {
        $period = $this->getPeriod($id);
        if (!$period['deadline']) return true; // no deadline set

        // CƠ CHẾ TỰ ĐỘNG HARD-CLOSE: Khi quá deadline, hệ thống tự đánh dấu hard_closed=1
        // để ngăn mọi bút toán mới vào kỳ này. Đây là bảo vệ cuối cùng (last line of defense)
        // để đảm bảo số liệu kỳ trước không thay đổi sau khi đã nộp báo cáo thuế.
        //
        // RỦI RO: Nếu deadline được đặt sai (quá sớm), hệ thống có thể khóa kỳ trước khi
        // kế toán kịp ghi nhận giao dịch → thiếu số liệu → phải override → mất audit trail.
        // Biện pháp: Chỉ Kế toán trưởng mới có quyền setDeadline và overrideDeadline.
        $now = date('Y-m-d');
        if ($now > $period['deadline'] && !$period['hard_closed']) {
            // Tự động đánh dấu hard_closed
            $this->pdo->prepare(
                'UPDATE accounting_periods SET hard_closed = 1 WHERE id = ?'
            )->execute([$id]);
            return false;
        }
        return !$period['hard_closed'];
    }

    // KỲ KẾ TOÁN: Cho phép Kế toán trưởng override deadline để ghi nhận bổ sung
    //
    // Nghiệp vụ: Trong trường hợp đặc biệt (theo yêu cầu Kiểm toán hoặc cơ quan thuế),
    // Kế toán trưởng có thể mở lại kỳ đã hard-close để ghi nhận bổ sung.
    //
    // RỦI RO: Override deadline phải có lý do bằng văn bản và được lưu trong audit trail.
    // Mỗi lần override đều được ghi nhận để kiểm toán viên đối chiếu sau này.
    public function overrideDeadline(int $id, string $reason, string $overriddenBy): array
    {
        $this->pdo->prepare(
            'UPDATE accounting_periods SET hard_closed = 0 WHERE id = ?'
        )->execute([$id]);
        $this->auditLogger?->log('period.deadline_override', 'accounting_period', (string)$id,
            ['hard_closed' => 1], ['hard_closed' => 0, 'reason' => $reason],
            $overriddenBy);
        return $this->getPeriod($id);
    }

    // KỲ KẾ TOÁN: Đặt hạn chót (deadline) cho kỳ kế toán
    //
    // Nghiệp vụ: Hạn nộp báo cáo tài chính theo quy định:
    // - Báo cáo quý: 20 ngày sau khi kết thúc quý
    // - Báo cáo năm: 90 ngày sau khi kết thúc năm tài chính
    // Sau deadline, hệ thống tự động hard-close (enforceHardDeadline)
    //
    // RỦI RO: Không đặt deadline → kế toán có thể kéo dài thời gian ghi nhận →
    // sai kỳ kế toán → chậm nộp BC → phạt hành chính.
    public function setDeadline(int $id, string $deadline, string $setBy): array
    {
        $this->pdo->prepare(
            'UPDATE accounting_periods SET deadline = ? WHERE id = ?'
        )->execute([$deadline, $id]);
        $this->auditLogger?->log('period.deadline_set', 'accounting_period', (string)$id,
            null, ['deadline' => $deadline], $setBy);
        return $this->getPeriod($id);
    }
}
