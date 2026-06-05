<?php
//
// NGHIỆP VỤ: Print Templates (R-10 Print Designer v1)
//
// Bối cảnh: Mỗi DN có format hóa đơn/PĐ/BC khác nhau. Hệ thống cần cho phép
// admin tùy chỉnh template in mà không cần deploy code mới.
//
// v1: Code-based template + variable system (KHÔNG WYSIWYG)
//   - Template lưu dạng text trong DB với cú pháp {{var}}, {{#if}}, {{#each}}
//   - Mỗi template type khai báo variables (var name, label, type, source)
//   - Render: thay thế biến bằng data thực từ DB
//   - Preview: hiển thị HTML rendered
//   - Export: hook vào ExportService (PDF/Excel/HTML) đã có sẵn
//
// Quyết định: BỎ WYSIWYG v1 vì:
//   - TinyMCE/CKEditor cho template = project riêng (~2-4 tuần)
//   - User Việt thường yêu cầu chỉnh layout rất cụ thể → code review với KTT
//   - Code-based template dễ test, dễ review, dễ rollback
//   - v2 sau có thể thêm WYSIWYG nếu user yêu cầu
//
// Rủi ro:
//   - User sửa template sai → in ra format hỏng → sandbox render trước khi lưu?
//   - Template chứa JS/HTML nguy hiểm → escape output
//   - Performance: cache rendered template nếu data lớn
//
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS print_templates (
        id VARCHAR(20) PRIMARY KEY,
        template_type VARCHAR(50) NOT NULL COMMENT 'ap_invoice/ar_invoice/sales_order/payment/receipt/financial_report',
        code VARCHAR(50) NOT NULL COMMENT 'unique code (vd: ap_invoice_default)',
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        content LONGTEXT NOT NULL COMMENT 'Template HTML với {{var}} substitution',
        variables JSON NULL COMMENT 'JSON array of {key, label, type, source, required}',
        is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = default template cho type này',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_type_code (template_type, code),
        INDEX idx_type_default (template_type, is_default, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed 3 default templates (AP invoice, AR invoice, sales order) nếu chưa có
    $check = $pdo->query("SELECT COUNT(*) FROM print_templates WHERE is_default = 1")->fetchColumn();
    if ((int)$check === 0) {
        $now = date('Y-m-d H:i:s');
        $defaults = [
            [
                'id' => 'tpl_ap_default',
                'type' => 'ap_invoice',
                'code' => 'default',
                'name' => 'Hóa đơn mua hàng (mặc định)',
                'desc' => 'Template mặc định cho in hóa đơn từ nhà cung cấp',
                'content' => <<<'HTML'
<div class="invoice">
<h1 style="text-align:center">HÓA ĐƠN MUA HÀNG</h1>
<p><strong>Số:</strong> {{reference}}</p>
<p><strong>Ngày:</strong> {{transaction_date}}</p>
<p><strong>Nhà cung cấp:</strong> {{supplier_name}}</p>
<p><strong>Mã số thuế:</strong> {{supplier_tax_code}}</p>
<hr>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<thead><tr><th>Mô tả</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
<tbody>
{{#each lines}}
<tr>
    <td>{{description}}</td>
    <td style="text-align:right">{{quantity}}</td>
    <td style="text-align:right">{{unit_price}}</td>
    <td style="text-align:right">{{amount}}</td>
</tr>
{{/each}}
</tbody>
</table>
<p style="text-align:right"><strong>Tổng cộng:</strong> {{total_amount}}</p>
{{#if vat_amount}}
<p style="text-align:right"><strong>VAT ({{vat_rate}}%):</strong> {{vat_amount}}</p>
<p style="text-align:right"><strong>Thanh toán:</strong> {{grand_total}}</p>
{{/if}}
<p style="text-align:right"><em>Ngày in: {{print_date}}</em></p>
</div>
HTML,
                'vars' => [
                    ['key' => 'reference', 'label' => 'Số chứng từ', 'type' => 'string', 'source' => 'transaction.reference', 'required' => true],
                    ['key' => 'transaction_date', 'label' => 'Ngày', 'type' => 'date', 'source' => 'transaction.transaction_date', 'required' => true],
                    ['key' => 'supplier_name', 'label' => 'Tên NCC', 'type' => 'string', 'source' => 'supplier.name', 'required' => true],
                    ['key' => 'supplier_tax_code', 'label' => 'MST NCC', 'type' => 'string', 'source' => 'supplier.tax_code', 'required' => false],
                    ['key' => 'lines', 'label' => 'Dòng hàng', 'type' => 'array', 'source' => 'transaction.lines', 'required' => true],
                    ['key' => 'total_amount', 'label' => 'Tổng tiền', 'type' => 'number', 'source' => 'computed.total', 'required' => true],
                    ['key' => 'vat_amount', 'label' => 'VAT', 'type' => 'number', 'source' => 'computed.vat', 'required' => false],
                    ['key' => 'vat_rate', 'label' => '% VAT', 'type' => 'number', 'source' => 'config.vat_rate', 'required' => false],
                    ['key' => 'grand_total', 'label' => 'Tổng thanh toán', 'type' => 'number', 'source' => 'computed.grand_total', 'required' => false],
                    ['key' => 'print_date', 'label' => 'Ngày in', 'type' => 'datetime', 'source' => 'now()', 'required' => true],
                ],
            ],
            [
                'id' => 'tpl_ar_default',
                'type' => 'ar_invoice',
                'code' => 'default',
                'name' => 'Hóa đơn bán hàng (mặc định)',
                'desc' => 'Template mặc định cho in hóa đơn bán hàng',
                'content' => <<<'HTML'
<div class="invoice">
<h1 style="text-align:center">HÓA ĐƠN BÁN HÀNG</h1>
<p><strong>Số:</strong> {{reference}}</p>
<p><strong>Ngày:</strong> {{transaction_date}}</p>
<p><strong>Khách hàng:</strong> {{customer_name}}</p>
<hr>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<thead><tr><th>Mô tả</th><th>SL</th><th>Đơn giá</th><th>Tiền</th></tr></thead>
<tbody>
{{#each lines}}
<tr><td>{{description}}</td><td style="text-align:right">{{quantity}}</td><td style="text-align:right">{{unit_price}}</td><td style="text-align:right">{{amount}}</td></tr>
{{/each}}
</tbody>
</table>
<p style="text-align:right"><strong>Tổng cộng:</strong> {{total_amount}}</p>
{{#if vat_amount}}<p style="text-align:right"><strong>VAT:</strong> {{vat_amount}}</p>
<p style="text-align:right"><strong>Thanh toán:</strong> {{grand_total}}</p>{{/if}}
</div>
HTML,
                'vars' => [
                    ['key' => 'reference', 'label' => 'Số HĐ', 'type' => 'string', 'source' => 'transaction.reference', 'required' => true],
                    ['key' => 'transaction_date', 'label' => 'Ngày', 'type' => 'date', 'source' => 'transaction.transaction_date', 'required' => true],
                    ['key' => 'customer_name', 'label' => 'Khách hàng', 'type' => 'string', 'source' => 'customer.name', 'required' => true],
                    ['key' => 'lines', 'label' => 'Dòng hàng', 'type' => 'array', 'source' => 'transaction.lines', 'required' => true],
                    ['key' => 'total_amount', 'label' => 'Tổng', 'type' => 'number', 'source' => 'computed.total', 'required' => true],
                    ['key' => 'vat_amount', 'label' => 'VAT', 'type' => 'number', 'source' => 'computed.vat', 'required' => false],
                    ['key' => 'grand_total', 'label' => 'Tổng TT', 'type' => 'number', 'source' => 'computed.grand_total', 'required' => false],
                ],
            ],
            [
                'id' => 'tpl_so_default',
                'type' => 'sales_order',
                'code' => 'default',
                'name' => 'Đơn bán hàng (mặc định)',
                'desc' => 'Template mặc định cho in đơn bán hàng',
                'content' => <<<'HTML'
<div><h1 style="text-align:center">ĐƠN BÁN HÀNG</h1>
<p><strong>Số ĐH:</strong> {{order_no}}</p>
<p><strong>Ngày:</strong> {{order_date}}</p>
<p><strong>Khách hàng:</strong> {{customer_name}}</p>
<p><strong>Ghi chú:</strong> {{notes}}</p>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<thead><tr><th>#</th><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Tiền</th></tr></thead>
<tbody>
{{#each items}}
<tr><td>{{line_no}}</td><td>{{item_name}}</td><td>{{quantity}}</td><td>{{unit_price}}</td><td>{{amount}}</td></tr>
{{/each}}
</tbody>
</table>
<p style="text-align:right"><strong>Tổng cộng:</strong> {{total}}</p></div>
HTML,
                'vars' => [
                    ['key' => 'order_no', 'label' => 'Số ĐH', 'type' => 'string', 'source' => 'order.order_no', 'required' => true],
                    ['key' => 'order_date', 'label' => 'Ngày', 'type' => 'date', 'source' => 'order.order_date', 'required' => true],
                    ['key' => 'customer_name', 'label' => 'KH', 'type' => 'string', 'source' => 'customer.name', 'required' => true],
                    ['key' => 'notes', 'label' => 'Ghi chú', 'type' => 'string', 'source' => 'order.notes', 'required' => false],
                    ['key' => 'items', 'label' => 'SP', 'type' => 'array', 'source' => 'order.items', 'required' => true],
                    ['key' => 'total', 'label' => 'Tổng', 'type' => 'number', 'source' => 'computed.total', 'required' => true],
                ],
            ],
        ];

        $insertStmt = $pdo->prepare(
            "INSERT INTO print_templates (id, template_type, code, name, description, content, variables, is_default, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 'system', ?, ?)"
        );
        foreach ($defaults as $t) {
            $insertStmt->execute([
                $t['id'], $t['type'], $t['code'], $t['name'], $t['desc'],
                $t['content'], json_encode($t['vars'], JSON_UNESCAPED_UNICODE),
                $now, $now,
            ]);
        }
    }

    // Seed RBAC cho print module: admin/chief_accountant full, accountant read+update
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
                SELECT id, 'print', 1, 0, 1, 0, 0, 1 FROM roles WHERE name IN ('admin', 'chief_accountant')");
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
                SELECT id, 'print', 1, 0, 1, 0, 0, 0 FROM roles WHERE name = 'accountant'");
};
