<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\FsService;
use Accounting\Infrastructure\JsonResponse;

class FsController
{
    private FsService $fs;

    public function __construct(FsService $fs) { $this->fs = $fs; }

    public function bc01(): void
    {
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC01($period);
        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $this->fs->validateBC01($data),
            'total_assets' => $this->findValue($data, '280'),
            'total_equity' => $this->findValue($data, '440'),
        ]);
    }

    public function bc02(): void
    {
        $period = $_GET['period'] ?? date('Y');
        $data = $this->fs->generateBC02($period);
        JsonResponse::ok([
            'items' => $data,
            'period' => $period,
            'errors' => $this->fs->validateBC02($data),
            'net_profit' => $this->findValue($data, '60'),
        ]);
    }

    public function viewBC01(): void
    {
        require __DIR__ . '/../../../../public/views/fs_bc01.php';
    }

    public function viewBC02(): void
    {
        require __DIR__ . '/../../../../public/views/fs_bc02.php';
    }

    private function findValue(array $items, string $maSo): float
    {
        foreach ($items as $r) {
            if ($r['ma_so'] === $maSo) return $r['value'];
        }
        return 0;
    }
}
