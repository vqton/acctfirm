<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

// Dịch vụ quản lý quỹ tiền mặt tạm ứng (Petty Cash / Quỹ tạm ứng)
//
// Nghiệp vụ: Doanh nghiệp có thể thành lập quỹ tạm ứng (imprest fund) cho các
// chi tiêu nhỏ lẻ thường xuyên như mua văn phòng phẩm, tiếp khách, công tác phí.
// Trình tự hạch toán:
//   - establishPettyCash: Cấp quỹ - Nợ TK 111 / Có TK 111 (chuyển quỹ chính → quỹ TM)
//   - disbursePettyCash: Tạm ứng - Nợ TK chi phí / Có TK 111
//   - replenishPettyCash: Hoàn ứng - Nợ TK chi phí / Có TK 111 (đưa quỹ về mức ấn định)
//   - closePettyCash: Đóng quỹ - Thu hồi tiền thừa về quỹ chính
//
// RỦI RO: Nếu không kiểm soát quỹ tạm ứng định kỳ, tiền có thể bị chi sai mục đích
// hoặc thất thoát. Quỹ tạm ứng phải được đối chiếu và hoàn ứng cuối mỗi kỳ.
//
// Hạch toán: Sử dụng TK 111 (có TK con riêng) cho các giao dịch quỹ tạm ứng
class PettyCashService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private JournalService $journal;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        JournalService $journal,
        ?\PDO $pdo = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
    }

    public function establishPettyCash(string $fundName, float $imprestAmount, string $createdBy): array
    {
        if ($imprestAmount <= 0) throw new \InvalidArgumentException('Số tiền tạm ứng phải lớn hơn 0');

        $fundId = uniqid('pc_');
        if ($this->pdo) {
            $this->pdo->prepare(
                'INSERT INTO petty_cash_funds (id, fund_name, imprest_amount, current_balance, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$fundId, $fundName, $imprestAmount, $imprestAmount, 'active', $createdBy]);
        }

        return ['fund_id' => $fundId, 'fund_name' => $fundName, 'imprest_amount' => $imprestAmount, 'current_balance' => $imprestAmount];
    }

    // Chi tiền từ quỹ tạm ứng (Petty Cash Disbursement)
    //
    // Input: fundId, amount, description, reference, createdBy
    // Output: { transaction_id, amount, type }
    //
    // Quy trình:
    //   1. Kiểm tra quỹ tồn tại, đang active
    //   2. Kiểm tra current_balance >= amount — không cho quỹ âm
    //   3. Ghi nhận giao dịch chi (type = 'disbursement')
    //   4. Giảm current_balance
    //
    // RỦI RO: Không có transaction wrapping!
    //   - INSERT và UPDATE là 2 câu lệnh riêng biệt (không beginTransaction)
    //   - Nếu INSERT thành công, UPDATE thất bại → mất tiền (balance không giảm)
    //   - Nếu UPDATE thành công, INSERT thất bại → mất dấu vết giao dịch
    //
    // RỦI RO THẤT THOÁT: Không có yêu cầu chứng từ/chứng minh khi chi
    //   - description và reference là text tự nhập — không kiểm tra hóa đơn
    //   - Cần cơ chế đối chiếu: chi tiêu phải có hóa đơn/chứng từ kèm theo
    //
    // Hạch toán hiện tại: KHÔNG hạch toán kép — chỉ ghi nhận nghiệp vụ chi
    // Hạch toán kép sẽ được thực hiện khi replenish (khi có chứng từ chi tiết)
    public function disbursePettyCash(string $fundId, float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0');

        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Không tìm thấy quỹ tạm ứng: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Quỹ tạm ứng không ở trạng thái hoạt động');
        if ($fund['current_balance'] < $amount) {
            throw new \InvalidArgumentException("Số dư quỹ không đủ: hiện có {$fund['current_balance']}, cần {$amount}");
        }

        $txId = uniqid('pctx_');
        if ($this->pdo) {
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, reference, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $amount, 'disbursement', $description, $reference, $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = current_balance - ? WHERE id = ?'
            )->execute([$amount, $fundId]);
        }

        return ['transaction_id' => $txId, 'amount' => $amount, 'type' => 'disbursement'];
    }

    // Hoàn ứng quỹ tạm ứng (Replenish / Top-up)
    //
    // Input: fundId, expenseAccount, totalAmount, description, reference, createdBy
    // Output: { transaction_id, amount, type }
    //
    // Quy trình:
    //   1. Kiểm tra quỹ tồn tại, active
    //   2. Gọi JournalService::postEntry — hạch toán kép:
    //      - Nợ TK chi phí (theo expenseAccount) — ghi nhận chi phí thực tế
    //      - Có TK 111 (tiền mặt) — giảm quỹ chính
    //   3. Reset current_balance = imprest_amount (imprest system)
    //   4. Ghi nhận giao dịch hoàn ứng
    //
    // Imprest System: Sau khi hoàn ứng, số dư quỹ được đưa về mức ấn định ban đầu
    //   (imprest_amount). Tổng chi = tổng hoàn ứng trong kỳ.
    //
    // RỦI RO NHIỀU LẦN HOÀN ỨNG:
    //   - Nếu hoàn ứng nhiều lần trước khi hoàn tất đối chiếu → chi phí bị ghi nhận nhiều lần
    //   - Ví dụ: Chi 5tr, hoàn ứng 5tr (quỹ về 10tr), chi 3tr, hoàn ứng 3tr (quỹ về 10tr)
    //   → Tổng chi phí ghi nhận = 8tr (đúng), quỹ luôn đầy = 10tr (đúng)
    //   → OK nếu chứng từ đầy đủ
    //
    // Hạch toán:
    //   Nợ TK chi phí (6428, 6418, 6278...) — tùy bản chất chi
    //   Có TK 111 — giảm tiền mặt tại quỹ chính
    public function replenishPettyCash(string $fundId, string $expenseAccount, float $totalAmount, string $description, string $reference, string $createdBy): array
    {
        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Không tìm thấy quỹ tạm ứng: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Quỹ tạm ứng không ở trạng thái hoạt động');

        if ($totalAmount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0');

        $txn = $this->journal->postEntry("Petty cash replenishment: {$description}", $reference, [
            ['account_code' => $expenseAccount, 'amount' => $totalAmount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $totalAmount, 'is_debit' => false],
        ], $createdBy);

        if ($this->pdo) {
            $txId = uniqid('pctx_');
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, reference, expense_account, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $totalAmount, 'replenishment', $description, $reference, $expenseAccount, $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = imprest_amount WHERE id = ?'
            )->execute([$fundId]);
        }

        return ['transaction_id' => $txn->getId(), 'amount' => $totalAmount, 'type' => 'replenishment'];
    }

    // Đóng quỹ tạm ứng — thu hồi tiền thừa về quỹ chính
    //
    // Input: fundId, returnAmount, createdBy
    // Output: { transaction_id, fund_id, type }
    //
    // Quy trình:
    //   1. Kiểm tra quỹ tồn tại, đang active
    //   2. Gọi JournalService::postEntry — chuyển tiền từ quỹ TM về quỹ chính:
    //      - Nợ TK 111 (quỹ chính) — tăng tiền quỹ chính
    //      - Có TK 111 (quỹ TM) — giảm quỹ tạm ứng
    //      (Cần TK con riêng biệt — hiện tại đều là '111' → thiếu chi tiết)
    //   3. Set status = 'closed', current_balance = 0
    //
    // RỦI RO — TK 111 cho cả 2 bên:
    //   postEntry với ['account_code' => '111'] cho cả Nợ và Có
    //   → JournalService phải xử lý đúng: đây là chuyển tiền nội bộ
    //   → Nếu không có TK con (1111, 1112) → khó phân biệt quỹ chính/quỹ TM
    //   → Cần tách biệt hoặc có sub-account riêng cho petty cash
    //
    // RỦI RO: returnAmount không được kiểm tra so với số dư thực tế
    //   Kế toán viên có thể nhập returnAmount sai lệch so với current_balance
    //   → thất thoát hoặc sai lệch số dư
    public function closePettyCash(string $fundId, float $returnAmount, string $createdBy): array
    {
        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Không tìm thấy quỹ tạm ứng: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Quỹ tạm ứng không ở trạng thái hoạt động');

        $txn = $this->journal->postEntry("Petty cash fund closure: {$fund['fund_name']}", "CLOSE-{$fundId}", [
            ['account_code' => '111', 'amount' => $returnAmount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $returnAmount, 'is_debit' => false],
        ], $createdBy);

        if ($this->pdo) {
            $txId = uniqid('pctx_');
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $returnAmount, 'closure', 'Fund closed, cash returned', $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = 0, status = ? WHERE id = ?'
            )->execute(['closed', $fundId]);
        }

        return ['transaction_id' => $txn->getId(), 'fund_id' => $fundId, 'type' => 'closure'];
    }

    public function getPettyCashFunds(): array
    {
        if (!$this->pdo) return [];
        $rows = $this->pdo->query('SELECT * FROM petty_cash_funds ORDER BY created_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => $r['id'], 'fund_name' => $r['fund_name'],
            'imprest_amount' => (float)$r['imprest_amount'],
            'current_balance' => (float)$r['current_balance'],
            'status' => $r['status'], 'created_by' => $r['created_by'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    public function getPettyCashTransactions(string $fundId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare('SELECT * FROM petty_cash_transactions WHERE fund_id = ? ORDER BY created_at DESC');
        $stmt->execute([$fundId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPettyCashFundById(string $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare('SELECT * FROM petty_cash_funds WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}
