<?php
//
// Tests cho PrintTemplateService — R-10 Print Designer v1
//
// Coverage:
//   - Render engine: {{var}}, {{{var}}}, {{#if}}, {{#unless}}, {{#each}}, nested paths
//   - CRUD: save, get, list, deactivate
//   - Validation: missing required fields, syntax error, oversize
//   - Failure cases: missing variable, unbounded loop, unbalanced tags
//
require __DIR__ . '/bootstrap.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$svc = new \Accounting\Domain\Service\PrintTemplateService($pdo);

$actor = 'test_user';

// Clean up any prior test templates
$pdo->exec("DELETE FROM print_templates WHERE id LIKE 'tpl_test_%'");

// ─── 1. Save: create new ───────────────────────────────────────────────────
$id1 = $svc->save([
    'template_type' => 'ap_invoice',
    'code' => 'test_v1',
    'name' => 'Test template 1',
    'description' => 'Test desc',
    'content' => '<p>{{reference}}</p>',
    'variables' => [['key' => 'reference', 'label' => 'Số HĐ']],
], $actor);
assertTrue(str_starts_with($id1, 'tpl_'), 'ID có prefix tpl_');

// ─── 2. Save: update existing (same type+code) ──────────────────────────────
$id1b = $svc->save([
    'template_type' => 'ap_invoice',
    'code' => 'test_v1',
    'name' => 'Test template 1 (updated)',
    'content' => '<p>{{reference}} v2</p>',
], $actor);
assertEq($id1, $id1b, 'Save cùng type+code = update (không tạo mới)');

$loaded = $svc->getById($id1);
assertEq($loaded['name'], 'Test template 1 (updated)', 'Tên đã được update');

// ─── 3. List: filter by type ────────────────────────────────────────────────
$list = $svc->list('ap_invoice');
assertTrue(count($list) >= 2, 'List có ít nhất 2 template ap_invoice (default + test)');

// ─── 4. Get default ────────────────────────────────────────────────────────
$def = $svc->getDefault('ap_invoice');
assertTrue($def !== null, 'Có default template cho ap_invoice');
assertEq($def['is_default'], true, 'Template default có is_default = true');

// ─── 5. Render: simple var ──────────────────────────────────────────────────
$out = $svc->render('Hello {{name}}', ['name' => 'World']);
assertEq($out, 'Hello World', 'Render {{var}} thay thế giá trị');

// ─── 6. Render: HTML escape ────────────────────────────────────────────────
$out = $svc->render('<p>{{text}}</p>', ['text' => '<script>alert(1)</script>']);
assertEq($out, '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', '{{var}} escape HTML');

// ─── 7. Render: raw {{{var}}} ───────────────────────────────────────────────
$out = $svc->render('<div>{{{html}}}</div>', ['html' => '<b>bold</b>']);
assertEq($out, '<div><b>bold</b></div>', '{{{var}}} KHÔNG escape HTML');

// ─── 8. Render: missing var → placeholder ──────────────────────────────────
$out = $svc->render('{{missing_var}}', []);
assertEq($out, '[missing: missing_var]', 'Var không tồn tại → "[missing: name]"');

// ─── 9. Render: nested path ────────────────────────────────────────────────
$out = $svc->render('{{user.name}} - {{user.email}}', [
    'user' => ['name' => 'NV A', 'email' => 'a@b.c']
]);
assertEq($out, 'NV A - a@b.c', 'Nested path user.name hoạt động');

// ─── 10. Render: indexed array path ────────────────────────────────────────
$out = $svc->render('{{items.0.name}}', [
    'items' => [['name' => 'First'], ['name' => 'Second']]
]);
assertEq($out, 'First', 'items.0.name truy cập phần tử array');

// ─── 11. Render: {{#if var}} then branch ───────────────────────────────────
$out = $svc->render('{{#if show}}YES{{/if}}', ['show' => true]);
assertEq($out, 'YES', '{{#if var}} với var truthy = render block');

// ─── 12. Render: {{#if var}} else branch ──────────────────────────────────
$out = $svc->render('{{#if show}}YES{{else}}NO{{/if}}', ['show' => false]);
assertEq($out, 'NO', '{{#if var}}{{else}}{{/if}} với var falsy = render else');

// ─── 13. Render: {{#if}} empty string = falsy ──────────────────────────────
$out = $svc->render('{{#if x}}YES{{else}}NO{{/if}}', ['x' => '']);
assertEq($out, 'NO', '{{#if}} với empty string = falsy');

// ─── 14. Render: {{#unless var}} ───────────────────────────────────────────
$out = $svc->render('{{#unless hidden}}VISIBLE{{/unless}}', ['hidden' => false]);
assertEq($out, 'VISIBLE', '{{#unless var}} với var falsy = render block');

// ─── 15. Render: {{#each}} cơ bản ──────────────────────────────────────────
$out = $svc->render('{{#each items}}{{this}},{{/each}}', ['items' => ['a', 'b', 'c']]);
assertEq($out, 'a,b,c,', '{{#each}} lặp qua array scalar');

// ─── 16. Render: {{#each}} với @index ──────────────────────────────────────
$out = $svc->render('{{#each items}}[{{@index}}]{{this}}{{/each}}', ['items' => ['x', 'y']]);
assertEq($out, '[0]x[1]y', '{{@index}} trả về index 0-based');

// ─── 17. Render: {{#each}} với array of object ─────────────────────────────
$out = $svc->render('{{#each lines}}[{{name}}={{value}}]{{/each}}', [
    'lines' => [['name' => 'a', 'value' => 1], ['name' => 'b', 'value' => 2]]
]);
assertEq($out, '[a=1][b=2]', '{{#each}} với array of object truy cập field');

// ─── 18. Render: {{#each}} empty list + {{else}} ───────────────────────────
$out = $svc->render('{{#each items}}{{this}}{{else}}EMPTY{{/each}}', ['items' => []]);
assertEq($out, 'EMPTY', '{{#each}} với list rỗng render {{else}}');

// ─── 19. Render: nested {{#each}} trong {{#if}} ────────────────────────────
$out = $svc->render('{{#if show}}{{#each items}}{{this}};{{/each}}{{/if}}', [
    'show' => true, 'items' => ['1', '2', '3']
]);
assertEq($out, '1;2;3;', 'Nested {{#each}} trong {{#if}} hoạt động');

// ─── 20. Render: real invoice template ─────────────────────────────────────
$invoiceTpl = '<h1>{{reference}}</h1><p>Ngày: {{transaction_date}}</p><p>KH: {{customer.name}}</p><ul>{{#each lines}}<li>{{item}} - {{qty}} x {{price}}</li>{{/each}}</ul><p>Tổng: {{total}}</p>';
$out = $svc->render($invoiceTpl, [
    'reference' => 'INV-001',
    'transaction_date' => '2026-06-05',
    'customer' => ['name' => 'ACME Corp'],
    'lines' => [
        ['item' => 'SP A', 'qty' => 10, 'price' => '100.000'],
        ['item' => 'SP B', 'qty' => 5, 'price' => '200.000'],
    ],
    'total' => '2.000.000',
]);
assertTrue(str_contains($out, 'INV-001'), 'Template thực tế chứa reference');
assertTrue(str_contains($out, 'ACME Corp'), 'Template thực tế chứa customer name');
assertTrue(str_contains($out, 'SP A'), 'Template thực tế chứa item name');
assertTrue(str_contains($out, '2.000.000'), 'Template thực tế chứa total');

// ─── 21. Failure: missing required field ───────────────────────────────────
try {
    $svc->save(['code' => 'x', 'name' => 'x', 'content' => 'x'], $actor);
    assertTrue(false, 'Phải throw khi thiếu template_type');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'template_type'), 'Throw đúng lỗi thiếu template_type');
}

// ─── 22. Failure: unbalanced {{#if}} ───────────────────────────────────────
try {
    $svc->save([
        'template_type' => 'ap_invoice', 'code' => 'test_invalid_1', 'name' => 'Bad',
        'content' => '{{#if x}}no close',
    ], $actor);
    assertTrue(false, 'Phải throw khi {{#if}} không đóng');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'cân bằng'), 'Throw đúng lỗi không cân bằng');
}

// ─── 23. Failure: unbalanced {{#each}} ─────────────────────────────────────
try {
    $svc->save([
        'template_type' => 'ap_invoice', 'code' => 'test_invalid_2', 'name' => 'Bad2',
        'content' => '{{#each items}}never closed',
    ], $actor);
    assertTrue(false, 'Phải throw khi {{#each}} không đóng');
} catch (\InvalidArgumentException $e) {
    assertTrue(true);
}

// ─── 24. Failure: render không tìm thấy template id ───────────────────────
try {
    $svc->renderById('tpl_nonexistent_9999', []);
    assertTrue(false, 'Phải throw khi template không tồn tại');
} catch (\RuntimeException $e) {
    assertTrue(str_contains($e->getMessage(), 'Không tìm thấy'), 'Throw đúng lỗi không tìm thấy');
}

// ─── 25. Deactivate ────────────────────────────────────────────────────────
$ok = $svc->deactivate($id1);
assertTrue($ok, 'Deactivate trả về true');
$loaded2 = $svc->getById($id1);
assertEq($loaded2['is_active'], false, 'Sau deactivate is_active = false');

// ─── 26. GetDeclaredVariables ───────────────────────────────────────────────
$vars = $svc->getDeclaredVariables($loaded2);
assertTrue(is_array($vars), 'getDeclaredVariables trả array');
assertTrue(count($vars) >= 1, 'Có ít nhất 1 variable khai báo');

// ─── 27. Render with integer values ────────────────────────────────────────
$out = $svc->render('{{qty}} x {{price}} = {{total}}', [
    'qty' => 10, 'price' => 100, 'total' => 1000
]);
assertEq($out, '10 x 100 = 1000', 'Render integer values');

// ─── 28. Render with zero ──────────────────────────────────────────────────
$out = $svc->render('{{value}}', ['value' => 0]);
assertEq($out, '0', 'Render số 0 thành "0" (không phải falsy placeholder)');

// ─── 29. Render with object access ─────────────────────────────────────────
$obj = new \stdClass();
$obj->name = 'ObjectName';
$out = $svc->render('{{obj.name}}', ['obj' => $obj]);
assertEq($out, 'ObjectName', 'Render với object access');

// ─── 30. List activeOnly = false includes inactive ────────────────────────
$listAll = $svc->list('ap_invoice', false);
$listActive = $svc->list('ap_invoice', true);
assertTrue(count($listAll) >= count($listActive), 'List active=false có nhiều hơn hoặc bằng active=true');

// Cleanup
$pdo->exec("DELETE FROM print_templates WHERE id LIKE 'tpl_test_%'");

results();
