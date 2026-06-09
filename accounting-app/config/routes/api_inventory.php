<?php

use Accounting\Infrastructure\JsonResponse;

// === CCDC ALLOCATIONS ===
$router->post('/api/inventory/ccdc-allocations/run', function() use ($c) { $c['CcdcAllocationController']->run(); });
$router->get('/api/inventory/ccdc-allocations/history', function() use ($c) { $c['CcdcAllocationController']->history(); });

// === RECEIPT (Nhập kho) ===
// Backward compat: single-item quick receipt
$router->post('/api/inventory/receive', function() use ($c) { $c['ReceiptController']->receive(); });
$router->get('/api/inventory/receipts', function() use ($c) { $c['ReceiptController']->list(); });
$router->get('/api/inventory/receive/items', function() use ($c) { $c['ReceiptController']->items(); });
// Mẫu 01-VT: multi-line batch PNK với header fields
$router->post('/api/goods-receipt/draft', function() use ($c) { $c['GoodsReceiptController']->createDraft(); });
$router->post('/api/goods-receipt/{id}/post', function($id) use ($c) { $c['GoodsReceiptController']->postReceipt($id); });
$router->post('/api/goods-receipt/{id}/cancel', function($id) use ($c) { $c['GoodsReceiptController']->cancelReceipt($id); });
$router->get('/api/goods-receipt/{id}/print', function($id) use ($c) { $c['GoodsReceiptController']->getPrintData($id); });
$router->get('/goods-receipt/{id}/print-view', function($id) use ($c) { $c['GoodsReceiptController']->viewPrint($id); });
$router->get('/api/goods-receipt/{id}', function($id) use ($c) { $c['GoodsReceiptController']->getDetail($id); });
$router->get('/api/goods-receipt/list', function() use ($c) { $c['GoodsReceiptController']->list(); });

// === ISSUE (Xuất kho) ===
// Backward compat: single-item quick issue
$router->post('/api/inventory/issue', function() use ($c) { $c['IssueController']->issue(); });
$router->get('/api/inventory/issues', function() use ($c) { $c['IssueController']->list(); });
$router->get('/api/inventory/issue/items', function() use ($c) { $c['IssueController']->items(); });
// Mẫu 02-VT: multi-line batch PXK với header fields
$router->post('/api/inventory/issues/draft', function() use ($c) { $c['IssueController']->createDraft(); });
$router->post('/api/inventory/issues/{id}/post', function($id) use ($c) { $c['IssueController']->postDraft($id); });
$router->post('/api/inventory/issues/{id}/cancel', function($id) use ($c) { $c['IssueController']->cancelDraft($id); });
$router->get('/api/inventory/issues/{id}', function($id) use ($c) { $c['IssueController']->getDetail($id); });
$router->get('/api/inventory/issues/list', function() use ($c) { $c['IssueController']->listIssues(); });

// === CUSTOMER RETURN ===
$router->post('/api/inventory/customer-return', function() use ($c) { $c['CustomerReturnController']->return(); });
$router->get('/api/inventory/customer-returns', function() use ($c) { $c['CustomerReturnController']->list(); });
$router->get('/api/inventory/customer-return/items', function() use ($c) { $c['CustomerReturnController']->items(); });

// === CONSIGNMENT (Hàng gửi bán) ===
$router->get('/api/consignments', function() use ($c) { $c['ConsignmentController']->list(); });
$router->post('/api/consignments', function() use ($c) { $c['ConsignmentController']->consign(); });
$router->post('/api/consignments/sell', function() use ($c) { $c['ConsignmentController']->sell(); });
$router->post('/api/consignments/return', function() use ($c) { $c['ConsignmentController']->returnConsignment(); });

// === PHYSICAL COUNT ===
$router->get('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->sessions(); });
$router->get('/api/physical-count/lines/:id', function($id) use ($c) { $c['PhysicalCountController']->lines($id); });
$router->post('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->createSession(); });
$router->post('/api/physical-count/adjust', function() use ($c) { $c['PhysicalCountController']->adjust(); });

// === IMPAIRMENT ===
$router->get('/api/impairments', function() use ($c) { $c['ImpairmentController']->list(); });
$router->post('/api/impairments', function() use ($c) { $c['ImpairmentController']->record(); });
$router->post('/api/impairments/reverse', function() use ($c) { $c['ImpairmentController']->reverse(); });

// === PROMOTIONAL ===
$router->post('/api/promotional/issue', function() use ($c) { $c['PromotionalController']->issue(); });

// === SUPPLIER RETURN ===
$router->post('/api/inventory/supplier-return', function() use ($c) { $c['ReturnToSupplierController']->return(); });
$router->get('/api/inventory/supplier-returns', function() use ($c) { $c['ReturnToSupplierController']->list(); });
$router->get('/api/inventory/supplier-return/items', function() use ($c) { $c['ReturnToSupplierController']->items(); });

// === WRITE-OFF ===
$router->post('/api/inventory/write-off', function() use ($c) { $c['WriteOffController']->writeOff(); });
$router->get('/api/inventory/write-offs', function() use ($c) { $c['WriteOffController']->list(); });

// === INVENTORY REPORTS ===
$router->get('/api/inventory/aging', function() use ($c) { $c['InventoryReportController']->aging(); });
$router->get('/api/inventory/turnover', function() use ($c) { $c['InventoryReportController']->turnover(); });
$router->get('/api/inventory/valuation', function() use ($c) { $c['InventoryReportController']->valuation(); });

// === PERIODIC INVENTORY ===
$router->get('/api/periodic', function() use ($c) { $c['PeriodicController']->list(); });
$router->post('/api/periodic/close', function() use ($c) { $c['PeriodicController']->close(); });

// === INVENTORY TRANSIT ===
$router->get('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->list(); });
$router->post('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->record(); });
$router->post('/api/inventory-transit/receive', function() use ($c) { $c['InventoryTransitController']->receive(); });

// === TRANSFERS ===
$router->get('/api/transfers', function() use ($c) { $c['TransferController']->list(); });
$router->post('/api/transfers', function() use ($c) { $c['TransferController']->transfer(); });
$router->get('/api/transfers/items', function() use ($c) { $c['TransferController']->items(); });
$router->get('/api/transfers/warehouses', function() use ($c) { $c['TransferController']->warehouses(); });
