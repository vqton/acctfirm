<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use PDO;

class ReportBuilderService
{
    private PDO $pdo;
    private ReportExportService $export;

    private array $allowedTables = [
        'transactions' => ['id','transaction_date','description','reference','voucher_type','source_module','currency','status','created_by'],
        'ledger_entries' => ['id','transaction_id','account_id','amount','is_debit','note','currency','project_id'],
        'accounts' => ['id','code','name','type','parent_id','normal_balance','account_class','balance','is_control','detail_by'],
        'items' => ['id','code','name','item_type','unit','purchase_price','sale_price','stock_qty'],
        'customers' => ['id','code','name','tax_code','phone','email'],
        'suppliers' => ['id','code','name','tax_code','phone','email'],
        'sales_orders' => ['id','reference','customer_id','order_date','total_amount','status','created_by'],
        'contracts' => ['id','code','name','contract_type','party_id','total_amount','status','fulfilled_amount'],
        'projects' => ['id','code','name','customer_id','budget','actual_cost','status'],
        'production_orders' => ['id','reference','product_id','qty','completed_qty','total_cost','unit_cost','status'],
    ];

    public function __construct(PDO $pdo, ReportExportService $export)
    {
        $this->pdo = $pdo;
        $this->export = $export;
    }

    public function getAvailableTables(): array
    {
        $result = [];
        foreach ($this->allowedTables as $table => $fields) {
            $result[] = ['table' => $table, 'fields' => $fields];
        }
        return $result;
    }

    public function saveReport(array $data): string
    {
        $id = uniqid('rpt_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_definitions (id,name,type,source_table,fields,filters,sort_config,chart_type,chart_config,group_by,is_public,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            $id, $data['name'], $data['type'] ?? 'list', $data['source_table'],
            json_encode($data['fields']), json_encode($data['filters'] ?? null),
            json_encode($data['sort_config'] ?? null), $data['chart_type'] ?? null,
            json_encode($data['chart_config'] ?? null), $data['group_by'] ?? null,
            $data['is_public'] ?? 0, $data['created_by'] ?? null
        ]);
        return $id;
    }

    public function getSavedReports(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM report_definitions WHERE created_by = ? OR is_public = 1 ORDER BY updated_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportDefinition(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM report_definitions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['fields'] = json_decode($row['fields'], true);
        $row['filters'] = json_decode($row['filters'], true);
        $row['sort_config'] = json_decode($row['sort_config'], true);
        $row['chart_config'] = json_decode($row['chart_config'], true);
        return $row;
    }

    public function deleteReport(string $id): void
    {
        $this->pdo->prepare('DELETE FROM report_definitions WHERE id = ?')->execute([$id]);
    }

    public function executeReport(array $reportDef): array
    {
        $table = $reportDef['source_table'];
        if (!isset($this->allowedTables[$table])) throw new \InvalidArgumentException("Table '$table' not allowed");

        $allowedFields = $this->allowedTables[$table];
        $fields = $reportDef['fields'] ?? ['*'];
        $selectFields = [];
        foreach ($fields as $f) {
            if ($f === '*' || in_array($f, $allowedFields)) $selectFields[] = $f;
        }
        if (empty($selectFields)) $selectFields = [$allowedFields[0]];

        $sql = "SELECT " . implode(',', $selectFields) . " FROM $table WHERE 1=1";
        $params = [];

        $filters = $reportDef['filters'] ?? [];
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $col = $filter['field'] ?? '';
                $op = $filter['operator'] ?? '=';
                $val = $filter['value'] ?? '';
                if (!in_array($col, $allowedFields)) continue;
                if ($op === '=') { $sql .= " AND $col = ?"; $params[] = $val; }
                elseif ($op === '>') { $sql .= " AND $col > ?"; $params[] = $val; }
                elseif ($op === '<') { $sql .= " AND $col < ?"; $params[] = $val; }
                elseif ($op === '>=') { $sql .= " AND $col >= ?"; $params[] = $val; }
                elseif ($op === '<=') { $sql .= " AND $col <= ?"; $params[] = $val; }
                elseif ($op === 'LIKE') { $sql .= " AND $col LIKE ?"; $params[] = "%$val%"; }
                elseif ($op === 'IN') {
                    $vals = explode(',', (string)$val);
                    $placeholders = implode(',', array_fill(0, count($vals), '?'));
                    $sql .= " AND $col IN ($placeholders)";
                    $params = array_merge($params, $vals);
                }
            }
        }

        $groupBy = $reportDef['group_by'] ?? null;
        if ($groupBy && in_array($groupBy, $allowedFields)) {
            $sql .= " GROUP BY $groupBy";
        }

        $sortConfig = $reportDef['sort_config'] ?? null;
        if ($sortConfig && !empty($sortConfig)) {
            $sortField = $sortConfig['field'] ?? null;
            $sortDir = strtoupper($sortConfig['direction'] ?? 'ASC');
            if ($sortField && in_array($sortField, $allowedFields) && in_array($sortDir, ['ASC', 'DESC'])) {
                $sql .= " ORDER BY $sortField $sortDir";
            }
        }

        $sql .= " LIMIT 1000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function executeAndExport(array $reportDef, string $format): array
    {
        $data = $this->executeReport($reportDef);
        if (empty($data)) return ['content' => '', 'mime' => 'text/csv', 'filename' => 'report_empty.csv'];

        $headers = array_keys($data[0]);
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
        }
        return $this->export->exportCsv($headers, $rows, 'bao_cao_' . date('Ymd') . '.csv');
    }
}
