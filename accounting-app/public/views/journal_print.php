<?php
// Màn hình in chứng từ ghi sổ (Mẫu TT99)
// Nghiệp vụ: In bút toán kế toán theo mẫu quy định TT99/2025/TT-BTC
// Gồm: chữ ký người lập, kế toán trưởng, giám đốc
$title = 'In chứng từ ghi sổ';
$txnId = $_GET['id'] ?? '';
if (!$txnId) { echo 'Thiếu mã bút toán'; exit; }
$pdo = $GLOBALS['container']['pdo'];
// Load transaction
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$txnId]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$txn) { echo 'Không tìm thấy bút toán'; exit; }
// Load ledger entries
$stmt = $pdo->prepare("SELECT le.*, a.code AS account_code, a.name AS account_name
    FROM ledger_entries le LEFT JOIN accounts a ON le.account_id = a.id
    WHERE le.transaction_id = ? ORDER BY le.line_order");
$stmt->execute([$txnId]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalDr = 0; $totalCr = 0;
foreach ($lines as $l) {
    if ($l['is_debit']) $totalDr += (float)$l['amount'];
    else $totalCr += (float)$l['amount'];
}
$total = max($totalDr, $totalCr);
ob_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"><title>Chứng từ ghi sổ</title>
<style>
    @page { margin: 15mm 10mm; size: A4 portrait; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 13px; line-height: 1.5; color: #000; }
    .header { text-align: center; margin-bottom: 20px; }
    .header h2 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
    .header h3 { margin: 0 0 4px; font-size: 15px; }
    .header p { margin: 0; font-size: 12px; }
    .company-info { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 12px; }
    .title { text-align: center; font-weight: bold; font-size: 14px; margin: 15px 0; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
    th, td { border: 1px solid #333; padding: 5px 6px; text-align: center; vertical-align: middle; }
    th { background: #f0f0f0; font-weight: bold; }
    .text-left { text-align: left; }
    .text-right { text-align: right; }
    .total-row { font-weight: bold; background: #f9f9f9; }
    .signatures { display: flex; justify-content: space-between; margin-top: 40px; font-size: 12px; text-align: center; }
    .signatures > div { width: 30%; }
    .signatures .name { margin-top: 40px; }
    .meta { font-size: 11px; margin: 5px 0; color: #555; }
    .subtitle { font-size: 11px; color: #666; margin: 2px 0; }
    @media print { .no-print { display: none; } .signatures { page-break-inside: avoid; } }
</style>
</head><body>
<div class="no-print" style="text-align:right;margin-bottom:10px;">
    <button onclick="window.print()" class="btn btn-sm btn-primary">
        <i class="bi bi-printer"></i> In
    </button>
    <button onclick="window.close()" class="btn btn-sm btn-secondary">Đóng</button>
</div>

<div class="header">
    <h2>CÔNG TY ...</h2>
    <p>Số ... phường ..., quận ..., TP ...</p>
    <p class="subtitle">Mẫu số: Chứng từ ghi sổ</p>
    <p class="subtitle">(Ban hành theo TT 99/2025/TT-BTC)</p>
</div>

<h3 class="title">CHỨNG TỪ GHI SỔ</h3>
<p style="text-align:center;font-size:12px;margin:0;">Số: <?= esc($txn['reference'] ?? $txnId) ?></p>
<p style="text-align:center;font-size:12px;margin:0 0 15px;">Ngày <?= date('d', strtotime($txn['transaction_date'])) ?> tháng <?= date('m', strtotime($txn['transaction_date'])) ?> năm <?= date('Y', strtotime($txn['transaction_date'])) ?></p>

<table>
    <thead>
        <tr><th style="width:30px;">STT</th><th style="width:80px;">TK</th><th>Diễn giải</th><th style="width:120px;">Số tiền Nợ</th><th style="width:120px;">Số tiền Có</th></tr>
    </thead>
    <tbody>
        <?php $stt = 0; foreach ($lines as $l): $stt++; ?>
        <tr>
            <td><?= $stt ?></td>
            <td><?= esc($l['account_code']) ?></td>
            <td class="text-left"><?= esc($l['note'] ?? '') ?></td>
            <td class="text-right"><?= $l['is_debit'] ? number_format((float)$l['amount'], 0, ',', '.') : '' ?></td>
            <td class="text-right"><?= !$l['is_debit'] ? number_format((float)$l['amount'], 0, ',', '.') : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="3" class="text-right">Cộng: </td>
            <td class="text-right"><?= number_format($totalDr, 0, ',', '.') ?></td>
            <td class="text-right"><?= number_format($totalCr, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>

<p class="meta">Diễn giải: <?= esc($txn['description'] ?? '') ?></p>
<p class="meta">Kèm theo: ...... chứng từ gốc</p>

<div class="signatures">
    <div>
        <p><strong>Người lập</strong></p>
        <p style="font-size:11px;color:#666;">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p><strong>Kế toán trưởng</strong></p>
        <p style="font-size:11px;color:#666;">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p><strong>Giám đốc</strong></p>
        <p style="font-size:11px;color:#666;">(Ký, họ tên, đóng dấu)</p>
        <div class="name">&nbsp;</div>
    </div>
</div>
</body></html>
<?php
$content = ob_get_clean();
echo $content;
?>
