<?php
// SỬA LỖI: TK 911 (Xác định kết quả kinh doanh) bị gán sai type='expense' thay vì 'equity'
// và normal_balance='D' thay vì 'C'.
//
// Hậu quả: Khi kết chuyển cuối kỳ (executeClosingEntries), vòng lặp chi phí
// (filter type='expense') vô tình lấy cả TK 911, tạo bút toán kết chuyển sai
// Dr 911 / Cr 911 (tự kết chuyển vào chính nó) làm inflate totalExpense,
// dẫn đến netProfit sai và 911 không được kết chuyển hết về 0.
//
// Chi tiết: https://github.com/anomalyco/bookwise/issues/??? (P&L clearing bug)
return function (PDO $pdo) {
    $stmt = $pdo->prepare("UPDATE accounts SET type = 'equity', normal_balance = 'C' WHERE code = '911' AND type = 'expense'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    error_log("[Migration 060] Fixed 911 account type: {$affected} rows updated (expense->equity, D->C)");
};
