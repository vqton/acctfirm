<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\FsService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

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

    public function tt99(): void
    {
        Auth::requirePermission('report', 'read');
        $period = $_GET['period'] ?? date('Y');
        $bc01 = $this->fs->generateBC01($period);
        $bc02 = $this->fs->generateBC02($period);
        $errors = array_merge($this->fs->validateBC01($bc01), $this->fs->validateBC02($bc02));
        $prior = $this->fs->getPriorPeriodValues('BC01', $period);
        $priorIncome = $this->fs->getPriorPeriodValues('BC02', $period);
        JsonResponse::ok([
            'period' => $period,
            'items' => array_merge(
                array_map(fn($r) => ['ma_so' => 'BC01_'.$r['ma_so'], 'name_vi' => $r['name_vi'], 'value' => $r['value'], 'prior' => $prior[$r['ma_so']] ?? 0], $bc01),
                array_map(fn($r) => ['ma_so' => 'BC02_'.$r['ma_so'], 'name_vi' => $r['name_vi'], 'value' => $r['value'], 'prior' => $priorIncome[$r['ma_so']] ?? 0], $bc02)
            ),
            'errors' => $errors,
            'total_assets' => $this->findValue($bc01, '280'),
            'total_equity' => $this->findValue($bc01, '440'),
            'net_profit' => $this->findValue($bc02, '60'),
        ]);
    }

    public function viewBC01(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc01.php';
    }

    public function viewBC02(): void
    {
        require __DIR__ . '/../../../../../public/views/fs_bc02.php';
    }

    private function findValue(array $items, string $maSo): float
    {
        foreach ($items as $r) {
            if ($r['ma_so'] === $maSo) return $r['value'];
        }
        return 0;
    }
}
