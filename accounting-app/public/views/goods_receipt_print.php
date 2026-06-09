<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Phiếu nhập kho: <?= htmlspecialchars($data['gr_number'] ?? '') ?></title>
<style>
@page { size: A4 portrait; margin: 15mm 15mm 20mm; }
body { font-family: 'Times New Roman', Times, serif; font-size: 13px; line-height: 1.4; color: #000; margin: 0; padding: 0; }
.print-container { max-width: 190mm; margin: 0 auto; padding: 10px; }
.header { text-align: center; margin-bottom: 8px; }
.header .company-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
.header .company-address { font-size: 12px; }
.header .dept-name { font-size: 12px; }
.form-ref { text-align: center; font-size: 11px; margin: 4px 0; }
.form-ref .form-code { font-weight: bold; }
.title { text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; margin: 8px 0; }
.date-line { text-align: center; font-size: 13px; margin: 4px 0 10px; }
.info-row { display: flex; justify-content: space-between; margin: 2px 0; }
.info-row .left { flex: 1; }
.info-row .right { text-align: right; }
.field-label { font-weight: 600; }
.field-value { margin-left: 4px; }
.accounts { font-weight: bold; margin: 6px 0; font-size: 13px; }
.detail-row { margin: 3px 0; font-size: 13px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 11px; }
table th, table td { border: 1px solid #000; padding: 4px 3px; text-align: center; vertical-align: middle; }
table th { font-weight: 600; background: #f0f0f0; font-size: 10px; }
table td.text-left { text-align: left; }
table td.text-right { text-align: right; }
.total-row td { font-weight: bold; }
.amount-words { font-size: 13px; font-weight: bold; margin: 6px 0; }
.attach-doc { font-size: 12px; margin: 4px 0; }
.signatures { display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid; }
.sig-col { text-align: center; width: 18%; }
.sig-col .sig-title { font-weight: bold; font-size: 11px; margin-bottom: 2px; }
.sig-col .sig-label { font-size: 10px; color: #555; }
.sig-col .sig-space { height: 50px; }
.copy-note { font-size: 10px; font-style: italic; margin-top: 8px; text-align: center; }
.footer-line { border-top: 1px solid #000; margin-top: 10px; padding-top: 4px; font-size: 10px; text-align: center; }
.qty-warning { background: #fff3cd; border: 1px solid #ffc107; padding: 4px 8px; margin: 4px 0; font-size: 11px; }
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none; }
}
.no-print { text-align: center; margin: 10px 0; }
.no-print button { padding: 8px 20px; font-size: 14px; cursor: pointer; }
</style>
</head>
<body>
<div class="print-container">
    <div class="no-print">
        <button onclick="window.print()">🖨 In phiếu này</button>
        <button onclick="window.close()">✖ Đóng</button>
    </div>

    <!-- Header: Tên đơn vị + bộ phận -->
    <div class="header">
        <div class="company-name">ĐƠN VỊ: CÔNG TY TNHH ABC</div>
        <div class="company-address">Địa chỉ: .............................</div>
        <?php if ($data['department'] ?? null): ?>
        <div class="dept-name">Bộ phận: <?= htmlspecialchars($data['department']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Mẫu số 01-VT -->
    <div class="form-ref">
        Mẫu số: <span class="form-code">01 - VT</span><br>
        (Kèm theo TT 99/2025/TT-BTC)
    </div>

    <!-- Title -->
    <div class="title">PHIẾU NHẬP KHO</div>

    <!-- Date -->
    <div class="date-line">
        Ngày <span class="field-value"><?= date('d', strtotime($data['received_date'])) ?></span>
        tháng <span class="field-value"><?= date('m', strtotime($data['received_date'])) ?></span>
        năm <span class="field-value"><?= date('Y', strtotime($data['received_date'])) ?></span>
    </div>

    <div class="info-row">
        <div class="left">Số: <span class="field-value"><?= htmlspecialchars($data['gr_number'] ?? '') ?></span></div>
        <div class="right">Nợ: <span class="field-value"><?php
            $deb = $data['debit_accounts'] ?? [];
            echo $deb ? htmlspecialchars(implode(', ', array_keys($deb))) : '15x';
        ?></span></div>
    </div>
    <div class="info-row">
        <div></div>
        <div class="right">Có: <span class="field-value"><?= htmlspecialchars($data['credit_account'] ?? '331') ?></span></div>
    </div>

    <!-- Họ tên người giao -->
    <div class="detail-row">
        <span class="field-label">- Họ và tên người giao:</span>
        <span class="field-value"><?= htmlspecialchars($data['deliverer_name'] ?? $data['supplier_name'] ?? '.....................................................') ?></span>
    </div>

    <!-- Theo hóa đơn/lệnh nhập -->
    <div class="detail-row">
        <span class="field-label">- Theo</span>
        <span class="field-value">
            <?php if ($data['invoice_ref'] ?? null): ?>
                Hóa đơn/lệnh nhập số <?= htmlspecialchars($data['invoice_ref']) ?>
                <?= $data['invoice_date'] ? 'ngày ' . htmlspecialchars($data['invoice_date']) : '' ?>
            <?php else: ?>
                ............................................. số ................. ngày ......... tháng ......... năm ................
            <?php endif; ?>
        </span>
    </div>

    <!-- Nhập tại kho -->
    <div class="detail-row">
        <span class="field-label">- Nhập tại kho:</span>
        <span class="field-value"><?= htmlspecialchars($data['warehouse_id'] ?? '..................................') ?></span>
        <span class="field-label"> Địa điểm:</span>
        <span class="field-value"><?= htmlspecialchars($data['warehouse_location'] ?? '..................................') ?></span>
    </div>

    <!-- Qty warning if any -->
    <?php if ($data['qty_warning'] ?? null): ?>
    <div class="qty-warning">⚠ <?= htmlspecialchars($data['qty_warning']) ?></div>
    <?php endif; ?>

    <!-- Lines grid — 8 cột A-D + 1-2-3-4 -->
    <table>
        <thead>
            <tr>
                <th style="width:20px">A<br>STT</th>
                <th>B — Tên, nhãn hiệu, quy cách, phẩm chất vật tư, dụng cụ, sản phẩm, hàng hóa</th>
                <th style="width:50px">C<br>Mã số</th>
                <th style="width:30px">D<br>ĐVT</th>
                <th style="width:55px">1 — Số lượng theo CT</th>
                <th style="width:55px">2 — Số lượng thực nhập</th>
                <th style="width:65px">3 — Đơn giá</th>
                <th style="width:75px">4 — Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php $lineNo = 0; $totalAmount = 0; ?>
            <?php foreach (($data['lines'] ?? []) as $line): $lineNo++; $totalAmount += (float)($line['total'] ?? 0); ?>
            <tr>
                <td><?= $lineNo ?></td>
                <td class="text-left"><?= htmlspecialchars($line['item_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($line['item_code'] ?? '') ?></td>
                <td><?= htmlspecialchars($line['uom'] ?? '') ?></td>
                <td class="text-right"><?= number_format((float)($line['qty_in_document'] ?? 0)) ?></td>
                <td class="text-right"><?= number_format((float)($line['qty_received'] ?? 0)) ?></td>
                <td class="text-right"><?= number_format((float)($line['unit_price'] ?? 0)) ?></td>
                <td class="text-right"><?= number_format((float)($line['total'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="7" class="text-right">Cộng</td>
                <td class="text-right"><?= number_format($totalAmount) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Tổng số tiền bằng chữ -->
    <div class="amount-words">
        Tổng số tiền (viết bằng chữ): <?= htmlspecialchars($data['amount_in_words'] ?? '...........................................................................') ?>
    </div>

    <!-- Số chứng từ gốc kèm theo -->
    <div class="attach-doc">
        Số chứng từ gốc kèm theo: <?= htmlspecialchars($data['attach_doc'] ?? '...........................................................................') ?>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-col">
            <div class="sig-space"></div>
            <div class="sig-title">Người lập phiếu</div>
            <div class="sig-label">(Ký, họ tên)</div>
        </div>
        <div class="sig-col">
            <div class="sig-space"></div>
            <div class="sig-title">Người giao hàng</div>
            <div class="sig-label">(Ký, họ tên)</div>
        </div>
        <div class="sig-col">
            <div class="sig-space"></div>
            <div class="sig-title">Thủ kho</div>
            <div class="sig-label">(Ký, họ tên)</div>
        </div>
        <div class="sig-col">
            <div class="sig-space"></div>
            <div class="sig-title">Kế toán trưởng</div>
            <div class="sig-label">(Ký, họ tên)</div>
        </div>
        <div class="sig-col">
            <div class="sig-space"></div>
            <div class="sig-title">Giám đốc</div>
            <div class="sig-label">(Ký, họ tên)</div>
        </div>
    </div>

    <!-- Copy note -->
    <div class="copy-note">
        (Liên 2: Thủ kho giữ — Ghi Thẻ kho và chuyển Phòng Kế toán)
    </div>

    <div class="footer-line">
        Phần mềm kế toán BookWise — Mẫu 01-VT theo TT 99/2025/TT-BTC
    </div>
</div>
</body>
</html>
