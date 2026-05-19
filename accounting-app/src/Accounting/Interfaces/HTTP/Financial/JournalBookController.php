<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\JournalBookService;
use Accounting\Infrastructure\JsonResponse;

class JournalBookController
{
    private JournalBookService $service;

    public function __construct(JournalBookService $service)
    {
        $this->service = $service;
    }

    public function journal(): void
    {
        $from = $_GET['from'] ?? date('Y-01-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        try {
            JsonResponse::ok($this->service->getGeneralJournal($from, $to));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/so_nhat_ky_chung.php';
    }
}
