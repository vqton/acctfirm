<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;

//
// DỊCH VỤ SỐ DƯ ĐẦU KỲ: Quản lý số dư đầu kỳ của các tài khoản kế toán
//
// Nghiệp vụ: Khi bắt đầu sử dụng hệ thống mới, kế toán cần nhập số dư đầu kỳ
// cho các tài khoản. Số dư này được nhập tay và đối chiếu với sổ kế toán cũ.
//
// Quy trình:
//   1. Nhập số dư đầu kỳ cho từng tài khoản (setOpeningBalance)
//   2. Đối chiếu số dư với sổ cũ (verify)
//   3. Chuyển số dư đầu kỳ thành bút toán mở sổ (convertToJournalEntry)
//
class OpeningBalanceService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    //
    // Nhập hoặc cập nhật số dư đầu kỳ cho một tài khoản
    // Sử dụng INSERT ... ON DUPLICATE KEY UPDATE để đảm bảo idempotent
    //
    public function setOpeningBalance(string $accountCode, string $period, float $debitBalance, float $creditBalance, string $createdBy): array
    {
        $id = uniqid('ob_');
        $this->pdo->prepare(
            "INSERT INTO opening_balances (id, account_code, period, debit_balance, credit_balance, created_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             debit_balance = VALUES(debit_balance),
             credit_balance = VALUES(credit_balance),
             created_by = VALUES(created_by)"
        )->execute([$id, $accountCode, $period, $debitBalance, $creditBalance, $createdBy]);

        return $this->getOpeningBalance($accountCode, $period);
    }

    //
    // Lấy số dư đầu kỳ của một tài khoản
    //
    public function getOpeningBalance(string $accountCode, string $period): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ob.*, a.name as account_name, a.type as account_type
             FROM opening_balances ob
             LEFT JOIN accounts a ON a.code = ob.account_code
             WHERE ob.account_code = ? AND ob.period = ?"
        );
        $stmt->execute([$accountCode, $period]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['debit_balance'] = (float)$row['debit_balance'];
        $row['credit_balance'] = (float)$row['credit_balance'];
        $row['is_verified'] = (bool)$row['is_verified'];
        return $row;
    }

    //
    // Lấy danh sách số dư đầu kỳ, có thể lọc theo kỳ
    //
    public function getOpeningBalances(?string $period = null): array
    {
        if ($period) {
            $stmt = $this->pdo->prepare(
                "SELECT ob.*, a.name as account_name, a.type as account_type
                 FROM opening_balances ob
                 LEFT JOIN accounts a ON a.code = ob.account_code
                 WHERE ob.period = ?
                 ORDER BY ob.account_code"
            );
            $stmt->execute([$period]);
        } else {
            $rows = $this->pdo->query(
                "SELECT ob.*, a.name as account_name, a.type as account_type
                 FROM opening_balances ob
                 LEFT JOIN accounts a ON a.code = ob.account_code
                 ORDER BY ob.period DESC, ob.account_code"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => $this->formatRow($r), $rows);
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->formatRow($r), $rows);
    }

    //
    // Đánh dấu đã đối chiếu số dư đầu kỳ
    //
    public function verify(string $accountCode, string $period, string $verifiedBy): array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE opening_balances SET is_verified = 1, verified_by = ?, verified_at = NOW()
             WHERE account_code = ? AND period = ?"
        );
        $stmt->execute([$verifiedBy, $accountCode, $period]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Không tìm thấy số dư đầu kỳ cho tài khoản này');
        }
        return $this->getOpeningBalance($accountCode, $period);
    }

    //
    // Chuyển số dư đầu kỳ thành bút toán mở sổ
    // Chỉ thực hiện cho kỳ chưa có bút toán mở sổ
    //
    public function convertToJournalEntry(string $period, string $createdBy): array
    {
        // Kiểm tra đã có bút toán mở sổ cho kỳ này chưa
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM transactions
             WHERE description LIKE ? AND status = 'posted'"
        );
        $stmt->execute(['Số dư đầu kỳ ' . $period . '%']);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \RuntimeException("Kỳ {$period} đã có bút toán mở sổ. Không thể tạo thêm.");
        }

        // Lấy tất cả số dư đầu kỳ cho kỳ
        $balances = $this->getOpeningBalances($period);
        if (empty($balances)) {
            throw new \RuntimeException("Không có số dư đầu kỳ cho kỳ {$period}");
        }

        // Tạo các dòng bút toán
        // Tài sản (asset, expense): số dư Nợ = debit_balance
        // Nguồn vốn (liability, equity, revenue): số dư Có = credit_balance
        $lines = [];
        $totalDr = 0;
        $totalCr = 0;

        foreach ($balances as $bal) {
            if ((float)$bal['debit_balance'] > 0) {
                $lines[] = [
                    'account_code' => $bal['account_code'],
                    'amount' => (float)$bal['debit_balance'],
                    'is_debit' => true,
                ];
                $totalDr += (float)$bal['debit_balance'];
            }
            if ((float)$bal['credit_balance'] > 0) {
                $lines[] = [
                    'account_code' => $bal['account_code'],
                    'amount' => (float)$bal['credit_balance'],
                    'is_debit' => false,
                ];
                $totalCr += (float)$bal['credit_balance'];
            }
        }

        if (empty($lines)) {
            throw new \RuntimeException('Không có số dư để chuyển thành bút toán');
        }

        // Kiểm tra tổng Nợ = tổng Có
        if (abs($totalDr - $totalCr) > 10) {
            throw new \InvalidArgumentException(
                "Tổng số dư Nợ ({$totalDr}) không bằng tổng số dư Có ({$totalCr}). Vui lòng kiểm tra lại số dư đầu kỳ."
            );
        }

        // Gọi JournalService để tạo bút toán
        $container = $GLOBALS['container'];
        /** @var JournalService $journalService */
        $journalService = $container['journalService'];

        $journalService->postEntry(
            description: "Số dư đầu kỳ {$period}",
            reference: '',
            lines: $lines,
            createdBy: $createdBy,
            allowControl: true,
            module: 'opening',
            date: $period . '-01',
            voucherType: 'JV'
        );

        return [
            'period' => $period,
            'total_dr' => $totalDr,
            'total_cr' => $totalCr,
            'lines_count' => count($lines),
            'message' => "Đã tạo bút toán mở sổ cho kỳ {$period}",
        ];
    }

    private function formatRow(array $r): array
    {
        return [
            'id' => $r['id'],
            'account_code' => $r['account_code'],
            'account_name' => $r['account_name'] ?? '',
            'account_type' => $r['account_type'] ?? '',
            'period' => $r['period'],
            'debit_balance' => (float)$r['debit_balance'],
            'credit_balance' => (float)$r['credit_balance'],
            'is_verified' => (bool)$r['is_verified'],
            'verified_by' => $r['verified_by'],
            'verified_at' => $r['verified_at'],
            'created_by' => $r['created_by'],
        ];
    }
}
