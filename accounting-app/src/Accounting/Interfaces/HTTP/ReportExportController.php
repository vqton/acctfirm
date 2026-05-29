<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ReportExportService;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\FsService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class ReportExportController
{
    private ReportExportService $export;
    private GlService $gl;
    private FsService $fs;

    public function __construct(ReportExportService $export, GlService $gl, FsService $fs)
    {
        $this->export = $export;
        $this->gl = $gl;
        $this->fs = $fs;
    }

    public function exportCsvLedger(): void
    {
        Auth::requirePermission('report', 'export');
        $account = $_GET['account'] ?? '111';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        try {
            $data = $this->gl->getGeneralLedger($account, $from, $to);
            $headers = ['Ngày', 'Tham chiếu', 'Diễn giải', 'Nợ', 'Có', 'Số dư'];
            $rows = [];
            foreach ($data['entries'] as $e) {
                $rows[] = [$e['date'], $e['reference'], $e['description'],
                    $e['debit'] ?: '', $e['credit'] ?: '', $e['running_balance']];
            }
            $result = $this->export->exportCsv($headers, $rows, "so_cai_{$account}.csv");
            header("Content-Type: {$result['mime']}");
            header("Content-Disposition: attachment; filename=\"{$result['filename']}\"");
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function exportHtmlLedger(): void
    {
        Auth::requirePermission('report', 'export');
        $account = $_GET['account'] ?? '111';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        try {
            $data = $this->gl->getGeneralLedger($account, $from, $to);
            $headers = ['Ngày', 'Tham chiếu', 'Diễn giải', 'Nợ', 'Có', 'Số dư'];
            $rows = [];
            foreach ($data['entries'] as $e) {
                $rows[] = [$e['date'], $e['reference'], $e['description'],
                    number_format($e['debit']), number_format($e['credit']), number_format($e['running_balance'])];
            }
            $title = "Sổ cái TK {$data['account_code']} - {$data['account_name']}";
            $summary = [
                'Số dư đầu kỳ' => number_format($data['opening_balance']),
                'Số dư cuối kỳ' => number_format($data['closing_balance']),
            ];
            $result = $this->export->exportHtml($title, $headers, $rows, $summary);
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function exportCsvTrialBalance(): void
    {
        Auth::requirePermission('report', 'export');
        $period = $_GET['period'] ?? date('Y-m');
        try {
            $tbService = new \Accounting\Domain\Service\TrialBalanceService(\App::pdo());
            $tb = $tbService->getTrialBalance($period);
            $headers = ['TK', 'Tên TK', 'SD ĐK Nợ', 'SD ĐK Có', 'PS Nợ', 'PS Có', 'SD CK Nợ', 'SD CK Có'];
            $rows = [];
            foreach ($tb['accounts'] as $a) {
                $rows[] = [$a['code'], $a['name'],
                    $a['opening_debit'] ?: '', $a['opening_credit'] ?: '',
                    $a['debit'] ?: '', $a['credit'] ?: '',
                    $a['closing_debit'] ?: '', $a['closing_credit'] ?: ''];
            }
            $result = $this->export->exportCsv($headers, $rows, "trial_balance_{$period}.csv");
            header("Content-Type: {$result['mime']}");
            header("Content-Disposition: attachment; filename=\"{$result['filename']}\"");
            echo $result['content'];
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}
