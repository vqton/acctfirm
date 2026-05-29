<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

// Dịch vụ đối chiếu ngân hàng (Bank Reconciliation)
//
// Nghiệp vụ: Cuối mỗi kỳ, kế toán phải đối chiếu số dư TK 112 (Tiền gửi ngân hàng)
// trên sổ kế toán với sao kê ngân hàng. Mục đích:
//   - Phát hiện chênh lệch do séc chưa thanh toán, tiền gửi đang chuyển
//   - Phát hiện sai sót hạch toán (ghi nhầm số tiền, tài khoản)
//   - Điều chỉnh bút toán nếu có chênh lệch thực tế
//
// Quy trình: startSession → (manualMatch | autoMatch | addAdjustingEntry) → complete
//   - startSession: tạo phiên đối chiếu, load dữ liệu từ sổ phụ và hệ thống
//   - autoMatch: tự động so khớp dựa trên số tiền, số tham chiếu, ngày tháng
//   - addAdjustingEntry: ghi nhận chênh lệch và tạo bút toán điều chỉnh
//   - complete: kiểm tra cân đối và đóng phiên
//
// RỦI RO: Nếu không đối chiếu định kỳ, sai số dư tiền gửi → BC01 chỉ tiêu 112 sai
// → quyết toán thuế sai lệch. Sai lệch kéo dài có thể là dấu hiệu gian lận.
class BankReconciliationService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private JournalService $journal;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        JournalService $journal,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    // Khởi tạo phiên đối chiếu ngân hàng mới
    //
    // Input: bankAccountCode (TK 112*), statementDate, statementBalance (từ sao kê NH)
    // Output: Session object với book_balance và statement_balance
    //
    // Quy trình:
    //   1. Lấy số dư trên sổ (bookBalance) từ AccountRepository
    //   2. Lưu session với status = 'in_progress'
    //   3. Load tất cả giao dịch sổ (ledger entries) vào bảng reconciliation_items
    //
    // RỦI RO: Không kiểm tra session đã tồn tại cho cùng kỳ/tk
    //   - Có thể tạo nhiều session cho cùng bank account + cùng ngày
    //   - Dẫn đến nhầm lẫn: không biết phiên nào là phiên cuối cùng
    //   - Cần check: "đã có session in_progress cho account này chưa?"
    //
    // RỦI RO DỮ LIỆU LỚN:
    //   loadBookItems load TOÀN BỘ ledger_entries cho tài khoản
    //   Với tài khoản có 10,000+ giao dịch → có thể memory overflow
    //   Cần giới hạn theo kỳ/tháng hoặc pagination
    // Nhập sao kê ngân hàng từ file CSV
    //
    // Input: sessionId, csvContent (raw string), delimiter (default: comma)
    // Output: { imported: N, errors: [...] }
    //
    // Định dạng CSV mặc định (có thể cấu hình):
    //   Header row: bỏ qua dòng đầu nếu chứa chữ
    //   Cột: Date, Reference, Description, Amount, Type (credit/debit)
    //   Amount dương = thu (credit), âm = chi (debit)
    //
    // Hỗ trợ các định dạng phổ biến của ngân hàng Việt Nam:
    //   - Vietcombank, Techcombank, BIDV, ACB, MB Bank, VPBank
    //   - Tự động phát hiện cột dựa trên header pattern matching
    //
    // RỦI RO: File CSV từ ngân hàng có encoding khác (windows-1258, UTF-8 BOM)
    //   → chạy qua mb_convert_encoding nếu cần
    public function importStatementCsv(int $sessionId, string $csvContent, string $createdBy, string $delimiter = ','): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");

        $lines = explode("\n", $csvContent);
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        if (empty($lines)) throw new \InvalidArgumentException('File CSV rỗng');

        $parsed = [];
        $errors = [];
        $hasHeader = false;

        // Phát hiện header row — nếu dòng đầu chứa chữ và không bắt đầu bằng số
        $firstLine = str_getcsv($lines[0], $delimiter);
        if (!preg_match('/^\d/', $firstLine[0])) {
            $hasHeader = true;
            $headerCols = array_map('strtolower', $firstLine);
            // Map header cột
            $colDate = null;
            $colRef = null;
            $colDesc = null;
            $colAmount = null;
            foreach ($headerCols as $i => $h) {
                if (preg_match('/ngày|date|posted|transaction|giao dich/i', $h)) $colDate = $i;
                if (preg_match('/ref|reference|số|so ct|chung tu|mã/i', $h)) $colRef = $i;
                if (preg_match('/desc|nội dung|diễn giải|content|detail|chi tiết/i', $h)) $colDesc = $i;
                if (preg_match('/amount|số tiền|tien|giá trị|value|so du|balance/i', $h)) $colAmount = $i;
            }
            if ($colAmount === null) {
                // Fallback: cột cuối cùng là amount
                $colAmount = count($headerCols) - 1;
            }
            $lines2 = array_slice($lines, 1);
        } else {
            $lines2 = $lines;
            // Mặc định: [0]=date, [1]=ref, [2]=desc, [3]=amount, [4]=type
            $colDate = 0; $colRef = 1; $colDesc = 2; $colAmount = 3;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $imported = 0;
        foreach ($lines2 as $lineIdx => $line) {
            $cols = str_getcsv($line, $delimiter);
            $cols = array_map('trim', $cols);

            // Bỏ qua dòng trống hoặc không đủ dữ liệu
            if (count($cols) < 2) continue;

            try {
                // Xác định ngày
                $date = $colDate !== null && isset($cols[$colDate]) ? $this->parseDate($cols[$colDate]) : date('Y-m-d');

                // Xác định reference
                $ref = $colRef !== null && isset($cols[$colRef]) ? $cols[$colRef] : '';

                // Xác định mô tả
                $desc = $colDesc !== null && isset($cols[$colDesc]) ? $cols[$colDesc] : ($ref ?: '');

                // Xác định số tiền và loại
                $amountStr = isset($cols[$colAmount]) ? $cols[$colAmount] : '0';
                $amountVal = $this->parseAmount($amountStr);

                // Xác định type
                if ($amountVal >= 0) {
                    $type = 'receipt';
                } else {
                    $type = 'payment';
                    $amountVal = abs($amountVal);
                }

                $insert->execute([$sessionId, 'statement', $type, $amountVal, $desc, $ref, $date, $createdBy]);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Dòng " . ($lineIdx + ($hasHeader ? 2 : 1)) . ": " . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    // Phân tích cú pháp ngày từ CSV (hỗ trợ nhiều định dạng)
    private function parseDate(string $value): string
    {
        $value = trim($value);
        // dd/mm/yyyy hoặc d/m/yyyy
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            return sprintf('%s-%02s-%02s', $m[3], $m[2], $m[1]);
        }
        // dd-mm-yyyy
        if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $value, $m)) {
            return sprintf('%s-%02s-%02s', $m[3], $m[2], $m[1]);
        }
        // yyyy-mm-dd (ISO)
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $value)) {
            return $value;
        }
        return date('Y-m-d', strtotime($value)) ?: date('Y-m-d');
    }

    // Phân tích cú pháp số tiền từ CSV (hỗ trợ dấu phân cách)
    private function parseAmount(string $value): float
    {
        $value = trim($value);
        // Xóa ký tự tiền tệ
        $value = str_replace(['₫', 'đ', 'VND', 'vnd', '₫', '$', '€'], '', $value);
        // Xóa dấu ngoặc đơn cho số âm: (1,000,000) = -1000000
        $negative = false;
        if (preg_match('/^\((.+)\)$/', $value, $m)) {
            $negative = true;
            $value = $m[1];
        }
        // Xóa dấu chấm phân cách nghìn (Việt Nam)
        $value = str_replace('.', '', $value);
        // Thay dấu phẩy thập phân bằng dấu chấm
        $value = str_replace(',', '.', $value);
        $amount = (float)$value;
        if ($negative) $amount = -$amount;
        return $amount;
    }

    public function startSession(string $bankAccountCode, string $statementDate, float $statementBalance, string $createdBy): array
    {
        $bank = $this->accountRepo->findByCode($bankAccountCode);
        if (!$bank) throw new \InvalidArgumentException("Không tìm thấy tài khoản ngân hàng: {$bankAccountCode}");

        $bookBalance = $bank->getBalance();

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_sessions (bank_account_code, statement_date, statement_balance, book_balance, status, started_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$bankAccountCode, $statementDate, $statementBalance, $bookBalance, 'in_progress', $createdBy]);

        $sessionId = (int)$this->pdo->lastInsertId();

        $this->loadBookItems($sessionId, $bankAccountCode);

        return $this->getSession($sessionId);
    }

    // Load giao dịch từ sổ kế toán (ledger_entries) vào phiên đối chiếu
    //
    // Internal — gọi từ startSession
    // Chuyển đổi: ledger_entry (is_debit) → reconciliation_item (receipt/payment)
    //
    // HẠN CHẾ: Load TOÀN BỘ giao dịch từ đầu, không filter theo kỳ
    //   - Mỗi phiên mới load lại tất cả → không phân biệt đã đối chiếu ở phiên trước
    //   - Cần filter theo ngày hoặc đánh dấu reconciled từ phiên trước
    //
    // RỦI RO TRÙNG LẶP: Nếu startSession gọi nhiều lần → items bị duplicate
    //   → autoMatch match sai (match với duplicate) → complete tính sai
    private function loadBookItems(int $sessionId, string $bankAccountCode): void
    {
        $bank = $this->accountRepo->findByCode($bankAccountCode);
        if (!$bank) return;

        $stmt = $this->pdo->prepare(
            'SELECT le.amount, le.is_debit, t.description, t.reference, t.created_at
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             WHERE le.account_id = ?
             ORDER BY t.created_at ASC'
        );
        $stmt->execute([$bank->getId()]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $insert = $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($rows as $r) {
            $type = $r['is_debit'] ? 'receipt' : 'payment';
            $insert->execute([
                $sessionId, 'book', $type,
                (float)$r['amount'], $r['description'], $r['reference'],
                substr($r['created_at'], 0, 10)
            ]);
        }
    }

    public function addStatementEntry(int $sessionId, float $amount, string $description, string $reference, string $date, string $type): int
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");
        if ($session['status'] !== 'in_progress') throw new \InvalidArgumentException('Phiên đối chiếu không ở trạng thái đang xử lý');

        $stmtType = in_array($type, ['receipt', 'payment']) ? $type : 'receipt';

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$sessionId, 'statement', $stmtType, $amount, $description, $reference, $date]);

        return (int)$this->pdo->lastInsertId();
    }

    // Tự động so khớp giao dịch sổ và sao kê ngân hàng
    //
    // Input: sessionId | Output: { matched, unmatched }
    //
    // Thuật toán matching:
    //   1. Lấy book items & statement items (unmatched)
    //   2. Với mỗi statement item, tìm book item theo:
    //      a. BẮT BUỘC: amount ±1 VND + type trùng
    //      b. ƯU TIÊN: reference trùng
    //      c. DATE window: ±3 ngày nếu date trùng hoặc reference trùng
    //   3. Nếu (a) + (b hoặc c) → match
    //
    // FALSE POSITIVE: amount + type + date gần giống nhưng khác giao dịch
    // FALSE NEGATIVE: amount lệch do phí NH, tỷ giá
    // HIỆU NĂNG: O(n*m) — chậm nếu >1000 items mỗi bên
    public function autoMatch(int $sessionId): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");

        $bookItems = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND source = ? AND match_status = ? ORDER BY id'
        );
        $bookItems->execute([$sessionId, 'book', 'unmatched']);
        $bookRows = $bookItems->fetchAll(\PDO::FETCH_ASSOC);

        $stmtItems = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND source = ? AND match_status = ? ORDER BY id'
        );
        $stmtItems->execute([$sessionId, 'statement', 'unmatched']);
        $stmtRows = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

        $matched = 0;

        $updateStmt = $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ?'
        );

        // Vòng lặp matching: mỗi statement item so với tất cả book item
        //
        // Điều kiện match:
        //   1. amount ±1 VND (dung sai do phí NH, làm tròn)
        //   2. Cùng type (receipt/payment)
        //   3. reference trùng khớp (ưu tiên cao — số tham chiếu từ NH)
        //      HOẶC date trùng khớp (chính xác)
        //      HOẶC date trong vòng 3 ngày (chênh lệch NH báo)
        //
        // Sau khi match: cập nhật match_status='matched' + matched_item_id
        // Cập nhật cả 2 chiều (statement item + book item)
        //
        // RỦI RO: Nếu book item không liên tiếp trong vòng lặp lồng
        // (statement items order không khớp book items order)
        // → false negative tạm thời — nhưng item sẽ được match ở vòng lặp tiếp theo
        foreach ($stmtRows as $s) {
            foreach ($bookRows as $bk) {
                if ($bk['match_status'] !== 'unmatched') continue;

                $amountMatch = abs((float)$s['amount'] - (float)$bk['amount']) < 1;
                $typeMatch = $s['type'] === $bk['type'];
                if (!$amountMatch || !$typeMatch) continue;

                $refMatch = $s['reference'] && $bk['reference'] && $s['reference'] === $bk['reference'];
                $dateMatch = $s['transaction_date'] && $bk['transaction_date'] && $s['transaction_date'] === $bk['transaction_date'];
                $closeDateMatch = $s['transaction_date'] && $bk['transaction_date'] && abs(strtotime($s['transaction_date']) - strtotime($bk['transaction_date'])) <= 86400 * 3;

                if ($refMatch || $dateMatch || $closeDateMatch) {
                    $updateStmt->execute(['matched', $bk['id'], $s['id']]);
                    $updateStmt->execute(['matched', $s['id'], $bk['id']]);
                    $bk['match_status'] = 'matched';
                    $matched++;
                    break;
                }
            }
        }

        $totalUnmatched = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ?'
        );
        $totalUnmatched->execute([$sessionId, 'unmatched']);
        $unmatchedCount = (int)$totalUnmatched->fetchColumn();

        return ['matched' => $matched, 'unmatched' => $unmatchedCount];
    }

    public function manualMatch(int $sessionId, int $statementItemId, int $bookItemId): void
    {
        $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ? AND session_id = ?'
        )->execute(['matched', $bookItemId, $statementItemId, $sessionId]);

        $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ? AND session_id = ?'
        )->execute(['matched', $statementItemId, $bookItemId, $sessionId]);
    }

    // Thêm bút toán điều chỉnh trong phiên đối chiếu
    //
    // Input: sessionId, debitAccount, creditAccount, amount, description
    // Output: { transaction_id, amount }
    //
    // Sử dụng khi: Phát hiện chênh lệch cần điều chỉnh trên sổ kế toán
    //   - Phí ngân hàng chưa hạch toán (Nợ 6425/Có 112)
    //   - Lãi tiền gửi chưa ghi nhận (Nợ 112/Có 515)
    //   - Sai sót hạch toán (ghi sai số tiền, nhầm tài khoản)
    //
    // Quy trình:
    //   1. postEntry qua JournalService — ghi nhận bút toán kép
    //   2. Thêm item vào cả 2 bên: book + statement (đều matched)
    //      → Đảm bảo chênh lệch được loại bỏ khi complete
    //
    // RỦI RO: Không xác thực debitAccount/creditAccount
    //   Có thể nhập account_code không tồn tại → postEntry throw exception
    //   Tuy nhiên nếu nhập account_code sai nhưng tồn tại → hạch toán sai
    //
    // Hạch toán phổ biến:
    //   - Phí NH: Nợ 6425 / Có 112
    //   - Lãi NH: Nợ 112 / Có 515
    //   - Điều chỉnh tỷ giá: Nợ/Có 112 / Có/Nợ 413
    public function addAdjustingEntry(int $sessionId, string $debitAccount, string $creditAccount, float $amount, string $description, string $createdBy): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");

        $txn = $this->journal->postEntry("Bank recon adj: {$description}", "RECON-ADJ-{$sessionId}", [
            ['account_code' => $debitAccount, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => $creditAccount, 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date, match_status)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)'
        )->execute([$sessionId, 'book', $debitAccount === '112' ? 'receipt' : 'payment', $amount, $description, "ADJ-{$txn->getId()}", 'matched']);

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date, match_status)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)'
        )->execute([$sessionId, 'statement', $debitAccount === '112' ? 'receipt' : 'payment', $amount, $description, "ADJ-{$txn->getId()}", 'matched']);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount];
    }

    // Hoàn tất phiên đối chiếu — kiểm tra cân đối và đóng phiên
    //
    // Input: sessionId
    // Output: { completed, balanced, book_balance, statement_balance, adjusted_book, ... }
    //
    // Công thức đối chiếu:
    //   Adjusted Book Balance = Book Balance + Unmatched Receipts - Unmatched Payments
    //   Yêu cầu: |Adjusted Book - Statement Balance| ≤ 1 VND
    //
    // Nếu cân đối:
    //   - Set status = 'completed'
    //   - Ghi audit log với đầy đủ thông tin (balances, chênh lệch)
    //
    // Nếu KHÔNG cân đối:
    //   - Throw InvalidArgumentException — không đóng phiên
    //   - Kế toán phải xử lý unmatched items, thêm adjusting entries
    //     hoặc kiểm tra lại sao kê ngân hàng
    //
    // RỦI RO KHÔNG THẤY:
    //   - Chỉ kiểm tra unmatched từ statement items
    //   - KHÔNG kiểm tra unmatched book items có ảnh hưởng không
    //   - Nếu có book item unmatched nhưng không ảnh hưởng đến adjusted_book
    //     → phiên vẫn đóng (đúng) nhưng không thông báo cho kế toán
    //
    // RỦI RO COMPLETE NHIỀU LẦN:
    //   - Nếu gọi complete lần 2 → throw (vì status !== 'in_progress')
    //   → OK, idempotent
    public function complete(int $sessionId): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");
        if ($session['status'] !== 'in_progress') throw new \InvalidArgumentException('Phiên đối chiếu đã hoàn tất');

        $items = $this->pdo->prepare(
            'SELECT source, type, amount FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ?'
        );
        $items->execute([$sessionId, 'unmatched']);
        $unmatched = $items->fetchAll(\PDO::FETCH_ASSOC);

        $bookBalance = (float)$session['book_balance'];
        $stmtBalance = (float)$session['statement_balance'];

        $statementReceipts = 0;
        $statementPayments = 0;
        $items->execute([$sessionId, 'matched']);
        $matchedItems = $items->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($matchedItems as $mi) {
            if ($mi['source'] === 'statement') {
                if ($mi['type'] === 'receipt') $statementReceipts += (float)$mi['amount'];
                else $statementPayments += (float)$mi['amount'];
            }
        }

        $unmatchedReceipts = 0;
        $unmatchedPayments = 0;
        foreach ($unmatched as $u) {
            if ($u['source'] === 'statement') {
                if ($u['type'] === 'receipt') $unmatchedReceipts += (float)$u['amount'];
                else $unmatchedPayments += (float)$u['amount'];
            }
        }

        $adjustedBook = $bookBalance + $unmatchedReceipts - $unmatchedPayments;

        if (abs($adjustedBook - $stmtBalance) > 1) {
            throw new \InvalidArgumentException(
                "Đối chiếu mất cân đối: sổ sau điều chỉnh ({$adjustedBook}) != số dư sao kê ({$stmtBalance}). Chênh lệch: " . round($stmtBalance - $adjustedBook, 0)
            );
        }

        $this->pdo->prepare(
            'UPDATE bank_reconciliation_sessions SET status = ?, completed_by = ?, completed_at = NOW() WHERE id = ?'
        )->execute(['completed', 'system', $sessionId]);

        $this->auditLogger?->log('recon.complete', 'reconciliation_session', (string)$sessionId,
            ['book_balance' => $bookBalance, 'statement_balance' => $stmtBalance],
            ['adjusted_book' => $adjustedBook, 'deposits_in_transit' => $unmatchedReceipts, 'outstanding_cheques' => $unmatchedPayments],
            'system');

        return [
            'completed' => true,
            'balanced' => true,
            'status' => 'completed',
            'book_balance' => $bookBalance,
            'statement_balance' => $stmtBalance,
            'adjusted_book' => $adjustedBook,
            'deposits_in_transit' => $unmatchedReceipts,
            'outstanding_cheques' => $unmatchedPayments,
        ];
    }

    public function getSession(int $sessionId): array
    {
        $row = $this->getSessionRaw($sessionId);
        if (!$row) throw new \InvalidArgumentException("Không tìm thấy phiên đối chiếu: {$sessionId}");
        return [
            'id' => (int)$row['id'],
            'bank_account_code' => $row['bank_account_code'],
            'statement_date' => $row['statement_date'],
            'statement_balance' => (float)$row['statement_balance'],
            'book_balance' => (float)$row['book_balance'],
            'status' => $row['status'],
            'started_by' => $row['started_by'],
            'completed_by' => $row['completed_by'],
            'completed_at' => $row['completed_at'],
            'created_at' => $row['created_at'],
        ];
    }

    private function getSessionRaw(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bank_reconciliation_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSessionItems(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? ORDER BY id'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUnmatchedItems(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ? ORDER BY source, id'
        );
        $stmt->execute([$sessionId, 'unmatched']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSessions(): array
    {
        $rows = $this->pdo->query('SELECT * FROM bank_reconciliation_sessions ORDER BY created_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'bank_account_code' => $r['bank_account_code'],
            'statement_date' => $r['statement_date'],
            'statement_balance' => (float)$r['statement_balance'],
            'book_balance' => (float)$r['book_balance'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
        ], $rows);
    }
}
