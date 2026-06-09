<?php
// Màn hình in Phiếu chi (Mẫu 02-TT) theo TT 99/2025/TT-BTC
// NGHIỆP VỤ: In phiếu chi tiền mặt — mẫu bắt buộc theo Thông tư 99/2025/TT-BTC
//
// Mẫu số 02-TT: Phiếu chi
// Quy định:
//   - Bắt buộc có chữ ký: Người lập phiếu, Thủ quỹ, Người nhận tiền, Kế toán trưởng, Giám đốc
//   - Số tiền phải viết bằng số và bằng chữ
//   - Kèm theo chứng từ gốc: ghi rõ số lượng
//   - Ghi rõ tài khoản Có (111, 1111) để đối ứng
//
// RỦI RO: Nếu thiếu chữ ký Giám đốc, phiếu chi không hợp lệ theo Luật Kế toán
// Hậu quả: Kiểm toán từ chối, phạt hành chính từ 5-10 triệu (NĐ 41/2018/NĐ-CP)

$title = 'Phiếu chi - Mẫu 02-TT';
$txnId = $_GET['id'] ?? '';
if (!$txnId) { echo 'Thiếu mã phiếu chi'; exit; }

$pdo = $GLOBALS['container']['pdo'];

// Load transaction
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$txnId]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$txn) { echo 'Không tìm thấy phiếu chi'; exit; }

// Load ledger entries
$stmt = $pdo->prepare(
    "SELECT le.*, a.code AS account_code, a.name AS account_name
     FROM ledger_entries le LEFT JOIN accounts a ON le.account_id = a.id
     WHERE le.transaction_id = ? ORDER BY le.line_order"
);
$stmt->execute([$txnId]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAmount = 0;
$creditCode = '';
foreach ($lines as $l) {
    if ($l['is_debit']) {
        $totalAmount += (float)$l['amount'];
    } else {
        $creditCode = $l['account_code'];
    }
}
$amount = $totalAmount;

// Lấy thông tin công ty từ business_config (nếu có)
$companyName = 'CÔNG TY ...';
$companyAddress = 'Số ... phường ..., quận ..., TP ...';
$companyTaxCode = '';
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM business_config WHERE config_key IN ('company_name', 'company_address', 'company_tax_code')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['config_key'] === 'company_name') $companyName = $row['config_value'];
        if ($row['config_key'] === 'company_address') $companyAddress = $row['config_value'];
        if ($row['config_key'] === 'company_tax_code') $companyTaxCode = $row['config_value'];
    }
} catch (\Exception $e) {}

// Số tiền bằng chữ
$amountWords = \Accounting\Infrastructure\Helpers::toVnWords($amount);

$txnDate = $txn['transaction_date'] ? date('d/m/Y', strtotime($txn['transaction_date'])) : date('d/m/Y');
$txnDay = date('d', strtotime($txn['transaction_date']));
$txnMonth = date('m', strtotime($txn['transaction_date']));
$txnYear = date('Y', strtotime($txn['transaction_date']));
ob_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"><title>Phiếu chi - Mẫu 02-TT</title>
<style>
    @page { margin: 15mm 15mm; size: A4 portrait; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 13px; line-height: 1.5; color: #000; }
    .no-print { text-align:right; margin-bottom:10px; }
    .no-print button { padding:6px 24px; font-size:14px; cursor:pointer; margin-left:8px; }

    .company-header { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
    .company-header .left { text-align: left; }
    .company-header .right { text-align: right; }
    .company-header .right .tax-label { font-size: 11px; color: #555; }

    .form-code { text-align: center; margin-top: 8px; }
    .form-code h3 { margin: 2px 0; font-size: 15px; font-weight: bold; }
    .form-code p { margin: 0; font-size: 11px; color: #555; }

    .title { text-align: center; margin: 20px 0 4px; }
    .title h2 { margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
    .title .ref { font-size: 14px; margin-top: 2px; }

    .meta { text-align: right; font-size: 12px; margin: 2px 0; }

    table.detail { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
    table.detail td { padding: 4px 6px; vertical-align: top; }
    table.detail .label { width: 140px; font-weight: 600; white-space: nowrap; }

    .amount-row { text-align: right; font-size: 14px; font-weight: bold; margin: 8px 0; }

    .signatures { display: flex; justify-content: space-between; margin-top: 50px; font-size: 12px; text-align: center; }
    .signatures > div { width: 18%; }
    .signatures .title { font-weight: bold; margin: 0 0 2px; font-size: 12px; }
    .signatures .sub { font-size: 10px; color: #666; margin: 0 0 50px; }
    .signatures .name { margin-top: 40px; }

    .footer-note { font-size: 10px; color: #888; text-align: center; margin-top: 20px; }
    @media print { .no-print { display: none; } .signatures { page-break-inside: avoid; } }
</style>
</head><body>
<div class="no-print">
    <button onclick="window.print()"><i class="bi bi-printer"></i> In</button>
    <button onclick="window.close()">Đóng</button>
</div>

<div class="company-header">
    <div class="left">
        <strong><?= esc($companyName) ?></strong><br>
        <?= esc($companyAddress) ?><br>
        <?php if ($companyTaxCode): ?>MST: <?= esc($companyTaxCode) ?><?php endif; ?>
    </div>
    <div class="right">
        <strong>Mẫu số: 02-TT</strong><br>
        <span class="tax-label">(Ban hành theo TT 99/2025/TT-BTC)</span>
    </div>
</div>
<hr style="margin:4px 0;">

<div class="title">
    <h2>PHIẾU CHI</h2>
    <div class="ref">Số: <?= esc($txn['reference'] ?? $txnId) ?></div>
    <?php if ($txn['book_number'] ?? ''): ?>
    <div style="font-size:12px;color:#555;">Quyển số: <?= esc($txn['book_number']) ?></div>
    <?php endif; ?>
</div>

<p class="meta">Ngày <?= $txnDay ?> tháng <?= $txnMonth ?> năm <?= $txnYear ?></p>

<table class="detail">
    <tr><td class="label">Người nhận tiền:</td><td><?= esc($txn['payer_name'] ?? '') ?></td></tr>
    <tr><td class="label">Địa chỉ:</td><td><?= esc($txn['payer_address'] ?? '') ?></td></tr>
    <tr><td class="label">Nội dung chi:</td><td><?= esc($txn['description'] ?? '') ?></td></tr>
</table>

<table class="detail" style="border:1px solid #333;">
    <tr style="background:#f0f0f0;font-weight:bold;">
        <td style="width:40px;text-align:center;">STT</td>
        <td style="width:90px;text-align:center;">TK</td>
        <td style="text-align:center;">Diễn giải</td>
        <td style="width:140px;text-align:center;">Số tiền</td>
    </tr>
    <?php $stt = 0; foreach ($lines as $l): $stt++; ?>
    <tr>
        <td style="text-align:center;"><?= $stt ?></td>
        <td style="text-align:center;"><?= esc($l['account_code']) ?></td>
        <td><?= esc($l['note'] ?? ($l['is_debit'] ? 'Chi phí' : 'Tiền mặt')) ?></td>
        <td style="text-align:right;"><?= number_format((float)$l['amount'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p class="amount-row">Tổng số tiền: <u><?= number_format($amount, 0, ',', '.') ?></u> VNĐ</p>
<p style="font-size:12px;margin:4px 0 16px;">Bằng chữ: <em><?= esc($amountWords) ?></em></p>

<p style="font-size:12px;margin:4px 0;">Kèm theo: ................................ chứng từ gốc</p>
<p style="font-size:12px;margin:4px 0;">Có: <?= esc($creditCode ?: '1111') ?></p>

<div class="signatures">
    <div>
        <p class="title">Người lập phiếu</p>
        <p class="sub">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p class="title">Kế toán trưởng</p>
        <p class="sub">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p class="title">Thủ quỹ</p>
        <p class="sub">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p class="title">Người nhận tiền</p>
        <p class="sub">(Ký, họ tên)</p>
        <div class="name">&nbsp;</div>
    </div>
    <div>
        <p class="title">Giám đốc</p>
        <p class="sub">(Ký, họ tên, đóng dấu)</p>
        <div class="name">&nbsp;</div>
    </div>
</div>

<p class="footer-note">Ngày in: <?= date('d/m/Y H:i') ?> • Hệ thống kế toán BookWise</p>
</body></html>
<?php
$content = ob_get_clean();
echo $content;
