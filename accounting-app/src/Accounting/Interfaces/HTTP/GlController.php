<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\GlService;
use Accounting\Infrastructure\Helpers;

class GlController
{
    private GlService $gl;

    public function __construct(GlService $gl) { $this->gl = $gl; }

    public function ledger(): void
    {
        $account = $_GET['account'] ?? '111';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        try {
            Helpers::jsonOk($this->gl->getGeneralLedger($account, $from, $to));
        } catch (\InvalidArgumentException $e) {
            Helpers::jsonError($e->getMessage(), 404);
        }
    }

    public function accounts(): void
    {
        Helpers::jsonOk($this->gl->getAccounts());
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../public/views/so_cai.php';
    }
}
