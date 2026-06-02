<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\Bc09Config;
use Accounting\Domain\Model\Bc09Data;
use Accounting\Domain\Repository\Bc09RepositoryInterface;
use PDO;

class PDOBc09Repository implements Bc09RepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getConfig(): array
    {
        $rows = $this->pdo->query(
            'SELECT * FROM bc09_config ORDER BY sort_order, id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($r) => $this->hydrateConfig($r), $rows);
    }

    public function getSection(string $sectionCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bc09_config WHERE section_code = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$sectionCode]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrateConfig($r), $rows);
    }

    public function getConfigByIndicator(string $indicatorCode): ?Bc09Config
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bc09_config WHERE indicator_code = ?');
        $stmt->execute([$indicatorCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateConfig($row) : null;
    }

    public function getData(int $periodId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bc09_data WHERE period_id = ? ORDER BY id');
        $stmt->execute([$periodId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrateData($r), $rows);
    }

    public function saveData(int $periodId, string $sectionCode, string $indicatorCode, float $yearStart, float $yearEnd, ?string $noteText, bool $isManual, ?int $createdBy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bc09_data (period_id, section_code, indicator_code, year_start, year_end, note_text, is_manual, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                year_start = VALUES(year_start),
                year_end = VALUES(year_end),
                note_text = VALUES(note_text),
                is_manual = VALUES(is_manual),
                updated_at = NOW()'
        );
        $stmt->execute([$periodId, $sectionCode, $indicatorCode, $yearStart, $yearEnd, $noteText, $isManual ? 1 : 0, $createdBy]);
    }

    public function deleteDataForPeriod(int $periodId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bc09_data WHERE period_id = ?');
        $stmt->execute([$periodId]);
    }

    public function getPriorPeriodData(int $periodId, string $indicatorCode): ?float
    {
        // Lấy year_end của kỳ trước qua bảng accounting_periods
        $stmt = $this->pdo->prepare(
            "SELECT bd.year_end FROM bc09_data bd
             JOIN accounting_periods ap ON ap.id = bd.period_id
             WHERE (SELECT end_date FROM accounting_periods WHERE id = ?) > ap.end_date
               AND bd.indicator_code = ?
             ORDER BY ap.end_date DESC LIMIT 1"
        );
        $stmt->execute([$periodId, $indicatorCode]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : null;
    }

    private function hydrateConfig(array $r): Bc09Config
    {
        return new Bc09Config(
            (int)$r['id'],
            $r['section_code'],
            $r['indicator_code'],
            $r['indicator_name'],
            $r['formula_expression'] ?? null,
            $r['account_codes'] ?? null,
            (bool)($r['is_auto_calc'] ?? true),
            (bool)($r['is_required'] ?? true),
            $r['parent_code'] ?? null,
            (int)($r['sort_order'] ?? 0)
        );
    }

    private function hydrateData(array $r): Bc09Data
    {
        return new Bc09Data(
            (int)$r['id'],
            (int)$r['period_id'],
            $r['section_code'],
            $r['indicator_code'],
            (float)($r['year_start'] ?? 0),
            (float)($r['year_end'] ?? 0),
            $r['note_text'] ?? null,
            (bool)($r['is_manual'] ?? false),
            $r['created_by'] !== null ? (int)$r['created_by'] : null
        );
    }
}
