<?php
$title = $order ? 'Đơn hàng: ' . $order->getReference() : 'Tạo đơn hàng mới';
$activeMenu = 'sales_orders';
ob_start();
$o = $order ? $order->toArray() : null;
?>
<div class="toolbar">
    <h5><?= $o ? 'Đơn hàng: ' . $o['reference'] : 'Tạo đơn hàng mới' ?></h5>
    <div>
        <?php if ($o && $o['status'] === 'draft'): ?>
            <button class="btn btn-success btn-sm" onclick="confirmOrder()"><i class="bi bi-check-lg"></i> Xác nhận</button>
            <button class="btn btn-danger btn-sm ms-1" onclick="cancelOrder()"><i class="bi bi-x-lg"></i> Hủy</button>
        <?php endif; ?>
        <?php if ($o && $o['status'] === 'confirmed'): ?>
            <button class="btn btn-primary btn-sm" onclick="shipOrder()"><i class="bi bi-truck"></i> Xuất kho</button>
            <button class="btn btn-info btn-sm ms-1" onclick="invoiceOrder()"><i class="bi bi-file-text"></i> Xuất HĐ</button>
        <?php endif; ?>
        <?php if ($o && ($o['status'] === 'shipped' || $o['status'] === 'partially_invoiced' || $o['status'] === 'invoiced')): ?>
            <button class="btn btn-warning btn-sm" onclick="receivePayment()"><i class="bi bi-cash"></i> Thu tiền</button>
        <?php endif; ?>
        <a href="/ban/don-dat-hang" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-arrow-left"></i> DS đơn hàng</a>
    </div>
</div>

<div class="card-table" style="padding:16px;">
    <?php if ($o): ?>
    <div class="row mb-3">
        <div class="col-md-3"><label>Số đơn hàng</label><div class="fw-bold"><?= $o['reference'] ?></div></div>
        <div class="col-md-3"><label>Ngày</label><div><?= $o['order_date'] ?></div></div>
        <div class="col-md-3"><label>Trạng thái</label><div><span class="badge-status badge-<?= statusClass($o['status']) ?>"><?= statusLabel($o['status']) ?></span></div></div>
        <div class="col-md-3"><label>Phương thức TT</label><div><?= $o['payment_method'] ?? '—' ?></div></div>
    </div>
    <table class="table table-hover">
        <thead><tr><th>STT</th><th>Mặt hàng</th><th>ĐVT</th><th>Số lượng</th><th>Đơn giá</th><th>CK %</th><th>Thuế</th><th>Thành tiền</th></tr></thead>
        <tbody>
        <?php foreach ($o['lines'] as $l): ?>
            <tr><td><?= $l['line_no'] ?></td><td><?= $l['item_name'] ?></td><td><?= $l['unit'] ?? '' ?></td>
                <td class="text-end"><?= $l['qty_ordered'] ?></td>
                <td class="text-end"><?= number_format($l['unit_price']) ?></td>
                <td class="text-end"><?= $l['discount_pct'] ?>%</td>
                <td class="text-end"><?= $l['tax_rate'] ?>%</td>
                <td class="text-end"><strong><?= number_format($l['line_total']) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="7" class="text-end">Tiền hàng:</td><td class="text-end"><?= number_format($o['total_amount']) ?></td></tr>
            <tr><td colspan="7" class="text-end">Thuế GTGT:</td><td class="text-end"><?= number_format($o['tax_amount']) ?></td></tr>
            <tr><td colspan="7" class="text-end"><strong>Tổng cộng:</strong></td><td class="text-end"><strong><?= number_format($o['grand_total']) ?></strong></td></tr>
            <tr><td colspan="7" class="text-end">Đã thanh toán:</td><td class="text-end"><?= number_format($o['amount_paid']) ?></td></tr>
            <tr><td colspan="7" class="text-end">Còn phải thu:</td><td class="text-end"><strong><?= number_format($o['grand_total'] - $o['amount_paid']) ?></strong></td></tr>
        </tfoot>
    </table>
    <?php if ($o['notes']): ?><div class="mt-2"><label>Ghi chú:</label><p><?= $o['notes'] ?></p></div><?php endif; ?>
    <?php else: ?>
    <form id="orderForm">
        <div class="row mb-3">
            <div class="col-md-4"><label>Khách hàng *</label>
                <select class="form-select" id="customerId" required>
                    <option value="">Chọn khách hàng</option>
                </select>
            </div>
            <div class="col-md-2"><label>Ngày đặt</label><input type="date" class="form-control" id="orderDate" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-2"><label>Ngày giao</label><input type="date" class="form-control" id="deliveryDate"></div>
            <div class="col-md-2"><label>Hình thức TT</label>
                <select class="form-select" id="paymentMethod">
                    <option value="">—</option><option value="cash">Tiền mặt</option>
                    <option value="bank">Chuyển khoản</option><option value="cod">COD</option>
                    <option value="transfer">CK ngân hàng</option>
                </select>
            </div>
            <div class="col-md-2"><label>Điều khoản</label>
                <select class="form-select" id="paymentTerms">
                    <option value="">—</option><option value="net_15">Net 15</option>
                    <option value="net_30">Net 30</option><option value="net_60">Net 60</option>
                    <option value="cod">COD</option><option value="deposit_50">Đặt cọc 50%</option>
                </select>
            </div>
        </div>
        <h6 class="mt-3">Danh sách mặt hàng</h6>
        <table class="table table-hover" id="itemsTable">
            <thead><tr><th>Mặt hàng</th><th>ĐVT</th><th>SL</th><th>Đơn giá</th><th>CK %</th><th>Thuế %</th><th>Thành tiền</th><th></th></tr></thead>
            <tbody id="itemsBody">
                <tr><td colspan="8" class="empty-state">Chưa có mặt hàng. <a href="#" onclick="addItemRow();return false;">Thêm mặt hàng</a></td></tr>
            </tbody>
            <tfoot><tr><td colspan="8"><button type="button" class="btn btn-outline-primary btn-sm" onclick="addItemRow()"><i class="bi bi-plus"></i> Thêm dòng</button></td></tr></tfoot>
        </table>
        <div class="row mt-3">
            <div class="col-md-6"><label>Ghi chú</label><textarea class="form-control" id="notes" rows="2"></textarea></div>
            <div class="col-md-3 offset-md-3">
                <table class="table table-sm"><tr><td>Tiền hàng:</td><td class="text-end" id="totTotal">0</td></tr>
                <tr><td>Thuế GTGT:</td><td class="text-end" id="totTax">0</td></tr>
                <tr><td><strong>Tổng cộng:</strong></td><td class="text-end"><strong id="totGrand">0</strong></td></tr></table>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Lưu đơn hàng</button>
            <a href="/ban/don-dat-hang" class="btn btn-outline-secondary ms-1">Hủy</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<select id="vatRateTemplate" style="display:none"></select>
<script>
function populateItemTax(sel) {
    var tmpl = document.getElementById('vatRateTemplate');
    sel.innerHTML = tmpl.innerHTML;
}
function addItemRow() {
    const tbody = $('#itemsBody');
    tbody.find('.empty-state').parent().remove();
    const idx = tbody.find('tr').length;
    const row = '<tr>'
        + '<td><input class="form-control form-control-sm item-name" placeholder="Tên mặt hàng" required></td>'
        + '<td><input class="form-control form-control-sm item-unit" style="width:60px"></td>'
        + '<td><input type="number" class="form-control form-control-sm item-qty" value="1" min="0.01" step="1" style="width:70px" onchange="calcLine(this)"></td>'
        + '<td><input type="number" class="form-control form-control-sm item-price" value="0" min="0" step="1000" style="width:110px" onchange="calcLine(this)"></td>'
        + '<td><input type="number" class="form-control form-control-sm item-disc" value="0" min="0" max="100" step="0.1" style="width:60px" onchange="calcLine(this)"></td>'
        + '<td><select class="form-select form-select-sm item-tax" style="width:100px" onchange="calcLine(this)"></select></td>'
        + '<td class="text-end item-total pt-2">0</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove();calcTotals();"><i class="bi bi-trash"></i></button></td></tr>';
    tbody.append(row);
    populateItemTax(tbody.find('tr:last .item-tax')[0]);
}
function calcLine(el) {
    const tr = $(el).closest('tr');
    const qty = parseFloat(tr.find('.item-qty').val()) || 0;
    const price = parseFloat(tr.find('.item-price').val()) || 0;
    const disc = parseFloat(tr.find('.item-disc').val()) || 0;
    const amount = qty * price;
    const lineTotal = amount - (amount * disc / 100);
    tr.find('.item-total').text(lineTotal.toLocaleString('vi-VN'));
    calcTotals();
}
function calcTotals() {
    let total = 0, tax = 0;
    $('#itemsBody tr').each(function() {
        const tr = $(this);
        const qty = parseFloat(tr.find('.item-qty').val()) || 0;
        const price = parseFloat(tr.find('.item-price').val()) || 0;
        const disc = parseFloat(tr.find('.item-disc').val()) || 0;
        const taxRate = parseFloat(tr.find('.item-tax').val()) || 0;
        const amount = qty * price;
        const lineTotal = amount - (amount * disc / 100);
        total += lineTotal;
        tax += lineTotal * taxRate / 100;
    });
    $('#totTotal').text(total.toLocaleString('vi-VN'));
    $('#totTax').text(tax.toLocaleString('vi-VN'));
    $('#totGrand').text((total + tax).toLocaleString('vi-VN'));
}
$(function() {
    $.get('/api/customers', function(data) {
        const sel = $('#customerId');
        if (data.data) data.data.forEach(function(c) {
            sel.append('<option value="' + c.id + '">' + (c.code ? c.code + ' - ' : '') + c.name + '</option>');
        });
    });
    $('#orderForm').submit(function(e) {
        e.preventDefault();
        const lines = [];
        $('#itemsBody tr').each(function() {
            const tr = $(this);
            const qty = parseFloat(tr.find('.item-qty').val()) || 0;
            const price = parseFloat(tr.find('.item-price').val()) || 0;
            if (qty > 0 && price > 0) {
                const itemName = tr.find('.item-name').val() || '';
                const amount = qty * price;
                const discPct = parseFloat(tr.find('.item-disc').val()) || 0;
                const discAmt = amount * discPct / 100;
                const lineTotal = amount - discAmt;
                lines.push({
                    item_name: itemName,
                    qty_ordered: qty,
                    unit_price: price,
                    discount_pct: discPct,
                    tax_rate: parseFloat(tr.find('.item-tax').val()) || 10,
                    unit: tr.find('.item-unit').val() || '',
                });
            }
        });
        if (lines.length === 0) { alert('Vui lòng thêm ít nhất 1 mặt hàng'); return; }
        $.ajax({
            url: '/api/sales/orders',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': csrf },
            data: JSON.stringify({
                customer_id: parseInt($('#customerId').val()),
                order_date: $('#orderDate').val(),
                delivery_date: $('#deliveryDate').val(),
                payment_method: $('#paymentMethod').val(),
                payment_terms: $('#paymentTerms').val(),
                notes: $('#notes').val(),
                lines: lines,
            }),
            success: function(res) {
                if (res.data) {
                    location.href = '/ban/don-dat-hang/' + res.data.id;
                } else {
                    alert('Lỗi: ' + (res.error || 'Không xác định'));
                }
            },
            error: function(xhr) {
                const res = xhr.responseJSON || {};
                alert('Lỗi: ' + (res.error || 'Không thể tạo đơn hàng'));
            }
        });
    });
});
function confirmOrder() { doAction('confirm', 'Xác nhận đơn hàng?'); }
function cancelOrder() { const r = prompt('Lý do hủy:'); if (r) doAction('cancel', 'Hủy đơn hàng?', {reason: r}); }
function shipOrder() { doAction('ship', 'Xuất kho đơn hàng?'); }
function invoiceOrder() { doAction('invoice', 'Xuất hóa đơn cho đơn hàng?'); }
function receivePayment() {
    const amount = prompt('Số tiền thu:');
    if (amount && parseFloat(amount) > 0) {
        const method = confirm('Thu bằng tiền mặt?') ? 'cash' : 'bank';
        doAction('payment', 'Ghi nhận thanh toán?', {amount: parseFloat(amount), method: method});
    }
}
function doAction(action, msg, extra) {
    if (!confirm(msg)) return;
    const url = '/api/sales/orders/' + <?= $o ? "'" . $o['id'] . "'" : "''" ?> + '/' + action;
    $.ajax({
        url: url, method: 'POST',
        headers: { 'X-CSRF-Token': csrf },
        contentType: 'application/json',
        data: extra ? JSON.stringify(extra) : '{}',
        success: function() { location.reload(); },
        error: function(xhr) { const r = xhr.responseJSON || {}; alert('Lỗi: ' + (r.error || 'Thất bại')); }
    });
}
$(document).ready(function(){loadVatRates('#vatRateTemplate',10);});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
