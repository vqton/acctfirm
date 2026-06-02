<?php
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

// === API: Report Builder (Gap 7) ===
$router->get('/api/report-builder/tables', function () use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->tables();
});

$router->get('/api/report-builder', function () use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->list();
});

$router->post('/api/report-builder', function () use ($c) {
    Auth::requirePermission('report', 'create');
    Auth::checkCsrf();
    $c['ReportBuilderController']->save();
});

$router->get('/api/report-builder/{id}', function ($id) use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->get($id);
});

$router->get('/api/report-builder/{id}/run', function ($id) use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->run($id);
});

$router->post('/api/report-builder/adhoc', function () use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->runAdhoc();
});

$router->delete('/api/report-builder/{id}', function ($id) use ($c) {
    Auth::requirePermission('report', 'delete');
    Auth::checkCsrf();
    $c['ReportBuilderController']->delete($id);
});

$router->get('/api/report-builder/{id}/export', function ($id) use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->export($id);
});

$router->post('/api/report-builder/adhoc/export', function () use ($c) {
    Auth::requirePermission('report', 'read');
    $c['ReportBuilderController']->exportAdhoc();
});
