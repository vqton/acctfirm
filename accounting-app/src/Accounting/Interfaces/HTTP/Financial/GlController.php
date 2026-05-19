<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\GlService;
use Accounting\Infrastructure\JsonResponse;

class GlController
{
    private GlService $gl;

    public function __construct(GlService $gl) { $this->gl = $gl; }

    public function ledger(): void
    {
        $account = $_GET['account'] ?? '111';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $mode = $_GET['mode'] ?? 'detail';
        try {
            $data = $mode === 'monthly'
                ? $this->gl->getMonthlyLedger($account, $from, $to)
                : $this->gl->getGeneralLedger($account, $from, $to);
            JsonResponse::ok($data);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function accounts(): void
    {
        JsonResponse::ok($this->gl->getAccounts());
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/so_cai.php';
    }
}
