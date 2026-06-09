<?php

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

// CSRF token
$router->get('/api/csrf-token', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });

// VnWords — chuyển số thành chữ (VD: "Một trăm triệu đồng")
$router->get('/api/helpers/vn-words', function() {
    $amount = (float)($_GET['amount'] ?? 0);
    $words = '';
    if ($amount > 0) {
        try { $words = \Accounting\Domain\ValueObject\VnWords::toWords($amount); }
        catch (\Exception $e) { $words = ''; }
    }
    JsonResponse::ok(['words' => $words]);
});

// === INTERCOMPANY (Nội bộ) ===
$router->get('/api/ic/entities', function() use ($c) { $c['IntercompanyController']->entities(); });
$router->get('/api/ic/match/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->match($entityId); });
$router->post('/api/ic/eliminate/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->eliminate($entityId); });
$router->get('/api/ic/consolidated', function() use ($c) { $c['IntercompanyController']->consolidated(); });

// === OPENING BALANCES ===
$router->get('/api/opening-balances', function() use ($c) { $c['OpeningBalanceController']->list(); });
$router->post('/api/opening-balances/set', function() use ($c) { $c['OpeningBalanceController']->set(); });
$router->post('/api/opening-balances/:accountCode/:period/verify', function($accountCode, $period) use ($c) { $c['OpeningBalanceController']->verify($accountCode, $period); });
$router->post('/api/opening-balances/convert', function() use ($c) { $c['OpeningBalanceController']->convert(); });
$router->get('/he-thong/so-du-dau-ky', function() use ($c) { $c['OpeningBalanceController']->view(); });

// === DEBT COLLECTION ===
$router->get('/api/debt-collection/queue', function() use ($c) { $c['DebtCollectionController']->queueList(); });
$router->get('/api/debt-collection/queue/:id', function($id) use ($c) { $c['DebtCollectionController']->queueDetail($id); });
$router->post('/api/debt-collection/queue/generate', function() use ($c) { $c['DebtCollectionController']->queueGenerate(); });
$router->put('/api/debt-collection/queue/:id/assign', function($id) use ($c) { $c['DebtCollectionController']->queueAssign($id); });
$router->put('/api/debt-collection/queue/:id/hold', function($id) use ($c) { $c['DebtCollectionController']->queueHold($id); });
$router->put('/api/debt-collection/queue/:id/release', function($id) use ($c) { $c['DebtCollectionController']->queueRelease($id); });
$router->put('/api/debt-collection/queue/:id/priority', function($id) use ($c) { $c['DebtCollectionController']->queuePriority($id); });
$router->get('/api/debt-collection/queue/:id/activities', function($id) use ($c) { $c['DebtCollectionController']->activityList($id); });
$router->post('/api/debt-collection/queue/:id/activities', function($id) use ($c) { $c['DebtCollectionController']->activityCreate($id); });
$router->get('/api/debt-collection/queue/:id/promises', function($id) use ($c) { $c['DebtCollectionController']->promiseList($id); });
$router->post('/api/debt-collection/queue/:id/promises', function($id) use ($c) { $c['DebtCollectionController']->promiseCreate($id); });
$router->post('/api/debt-collection/promises/:id/keep', function($id) use ($c) { $c['DebtCollectionController']->promiseKeep($id); });
$router->post('/api/debt-collection/promises/:id/break', function($id) use ($c) { $c['DebtCollectionController']->promiseBreak($id); });
$router->post('/api/debt-collection/queue/:id/propose-writeoff', function($id) use ($c) { $c['DebtCollectionController']->proposeWriteOff($id); });
$router->get('/api/debt-collection/approvals', function() use ($c) { $c['DebtCollectionController']->approvalList(); });
$router->put('/api/debt-collection/approvals/:id/approve', function($id) use ($c) { $c['DebtCollectionController']->approvalApprove($id); });
$router->put('/api/debt-collection/approvals/:id/reject', function($id) use ($c) { $c['DebtCollectionController']->approvalReject($id); });
$router->post('/api/debt-collection/settlements', function() use ($c) { $c['DebtCollectionController']->settlementCreate(); });
$router->post('/api/debt-collection/settlements/:id/pay', function($id) use ($c) { $c['DebtCollectionController']->settlementPay($id); });
$router->get('/api/debt-collection/stats', function() use ($c) { $c['DebtCollectionController']->stats(); });
$router->get('/api/debt-collection/stats/collector/:id', function($id) use ($c) { $c['DebtCollectionController']->collectorStats($id); });

// Debt Collection views
$router->get('/thu-hoi-cong-no', function() use ($c) { $c['DebtCollectionController']->viewDashboard(); });
$router->get('/thu-hoi-cong-no/hang-doi', function() use ($c) { $c['DebtCollectionController']->viewQueue(); });
$router->get('/thu-hoi-cong-no/phe-duyet', function() use ($c) { $c['DebtCollectionController']->viewApprovals(); });

// === SUB-LEDGER REPORTS ===
$router->get('/api/reports/sub-ledger', function() use ($c) { $c['SubLedgerController']->getReport(); });
$router->post('/api/reports/sub-ledger/export', function() use ($c) { $c['SubLedgerController']->exportReport(); });
$router->get('/api/reports/sub-ledger/parameters', function() use ($c) { $c['SubLedgerController']->getParameters(); });
$router->get('/api/reports/sub-ledger/supported', function() use ($c) { $c['SubLedgerController']->getSupportedReports(); });
$router->get('/so-chi-tiet', function() use ($c) { $c['SubLedgerController']->viewIndex(); });

// === EXPORT (PDF/Excel/CSV) Gap 10 ===
// Endpoint xuất file thống nhất cho mọi báo cáo — client gửi JSON body
// Body: { format: "csv"|"xls"|"pdf", title, headers, rows, options }
// Response: file download với Content-Disposition: attachment
// Yêu cầu quyền report.export (Auth::requirePermission)
$router->post('/api/export', function() use ($c) { $c['ExportController']->export(); });

// === MENU API (Navigation) ===
// Menu động — sidebar load dữ liệu từ API, render bằng JS
$router->get('/api/menu/sidebar', function() use ($c) { $c['MenuController']->getSidebar(); });
$router->get('/api/menu/search', function() use ($c) { $c['MenuController']->search(); });
$router->get('/api/menu/section/:section', function($section) use ($c) { $c['MenuController']->getSection($section); });
$router->get('/api/menu/favorites', function() use ($c) { $c['MenuController']->getFavorites(); });
$router->post('/api/menu/favorites', function() use ($c) { $c['MenuController']->addFavorite(); });
$router->delete('/api/menu/favorites/:id', function($id) use ($c) { $c['MenuController']->removeFavorite($id); });

// === JOURNAL ATTACHMENTS (Slice 4) ===
// Upload file đính kèm cho bút toán — hỗ trợ PDF, JPG, PNG, Excel
// Nghiệp vụ: TT99 Điều 16 — chứng từ gốc phải được đính kèm bút toán
// Rủi ro: File upload không validate → file độc hại. Giới hạn 10MB, white-list MIME
$router->post('/api/journal/attachments/upload', function() {
    Auth::requirePermission('journal', 'create');
    $txnId = $_POST['transaction_id'] ?? '';
    if (!$txnId) { JsonResponse::error('Thiếu transaction_id', 400); return; }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        JsonResponse::error('Lỗi upload file', 400); return;
    }
    $file = $_FILES['file'];
    $allowedMime = ['application/pdf','image/jpeg','image/png','image/gif',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel','application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime)) {
        JsonResponse::error('Định dạng file không được hỗ trợ. Chấp nhận: PDF, JPG, PNG, GIF, Excel, Word', 400);
        return;
    }
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        JsonResponse::error('File quá lớn (tối đa 10MB)', 400); return;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $storedName = uniqid('att_') . '.' . $ext;
    $uploadDir = __DIR__ . '/../../public/uploads/attachments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $dest = $uploadDir . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        JsonResponse::error('Không thể lưu file', 500); return;
    }
    $pdo = $GLOBALS['container']['pdo'];
    $stmt = $pdo->prepare("INSERT INTO journal_attachments (transaction_id, original_name, stored_name, mime_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$txnId, $file['name'], $storedName, $mime, $file['size'], $_SESSION['user']['username'] ?? 'system']);
    $id = $pdo->lastInsertId();
    JsonResponse::ok(['id' => $id, 'original_name' => $file['name'], 'stored_name' => $storedName, 'file_size' => $file['size']], 201);
});

// Download attachment
$router->get('/api/journal/attachments/:id/download', function($id) {
    $pdo = $GLOBALS['container']['pdo'];
    $stmt = $pdo->prepare("SELECT * FROM journal_attachments WHERE id = ?");
    $stmt->execute([$id]);
    $att = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$att) { JsonResponse::error('Không tìm thấy file', 404); return; }
    $path = __DIR__ . '/../../public/uploads/attachments/' . $att['stored_name'];
    if (!file_exists($path)) { JsonResponse::error('File không tồn tại trên server', 404); return; }
    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . $att['original_name'] . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

// List attachments for a transaction
$router->get('/api/journal/attachments/:transactionId', function($transactionId) {
    $pdo = $GLOBALS['container']['pdo'];
    $stmt = $pdo->prepare("SELECT id, transaction_id, original_name, mime_type, file_size, description, uploaded_by, created_at FROM journal_attachments WHERE transaction_id = ? ORDER BY created_at DESC");
    $stmt->execute([$transactionId]);
    JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
});

// Delete attachment
$router->delete('/api/journal/attachments/:id', function($id) {
    Auth::requirePermission('journal', 'create');
    $pdo = $GLOBALS['container']['pdo'];
    $stmt = $pdo->prepare("SELECT * FROM journal_attachments WHERE id = ?");
    $stmt->execute([$id]);
    $att = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$att) { JsonResponse::error('Không tìm thấy file', 404); return; }
    $path = __DIR__ . '/../../public/uploads/attachments/' . $att['stored_name'];
    if (file_exists($path)) unlink($path);
    $stmt = $pdo->prepare("DELETE FROM journal_attachments WHERE id = ?");
    $stmt->execute([$id]);
    JsonResponse::ok(['deleted' => true]);
});
