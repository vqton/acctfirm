<?php
namespace Accounting\Domain\Service;

//
// DỊCH VỤ THUẾ NHÀ THẦU NƯỚC NGOÀI (FCT): Tính toán và khấu trừ thuế
// Tuân thủ Thông tư 103/2014/TT-BTC về thuế nhà thầu nước ngoài
//
// Nghiệp vụ: Khi DN thanh toán cho nhà thầu nước ngoài không có cơ sở thường trú tại VN,
// DN có nghĩa vụ khấu trừ thuế GTGT và TNDN trước khi thanh toán.
//
// Tỷ lệ khấu trừ (TT 103/2014):
//   services:                   VAT 5% + CIT 5%
//   services_with_goods:        VAT 3% + CIT 2%
//   trading:                    VAT 1% + CIT 1%
//   leasing:                    VAT 5% + CIT 5%
//   other:                      VAT 2% + CIT 2%
//
// Bút toán: Dr 642/635/241 (giá trị trước thuế) / Cr 331 (sau thuế) + Cr 33312 (VAT) + Cr 3338 (CIT)
//
// RỦI RO: Sai tỷ lệ khấu trừ → bị truy thu thuế + phạt. Cần kiểm tra Điều ước quốc tế (DTA)
// để xác định có được giảm thuế suất hay không.
//

class FctService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Bảng tỷ lệ khấu trừ theo loại dịch vụ (TT 103/2014)
    private static array $WITHHOLDING_RATES = [
        'services'              => ['vat_rate' => 5,  'cit_rate' => 5],
        'services_with_goods'   => ['vat_rate' => 3,  'cit_rate' => 2],
        'trading'               => ['vat_rate' => 1,  'cit_rate' => 1],
        'leasing'               => ['vat_rate' => 5,  'cit_rate' => 5],
        'other'                 => ['vat_rate' => 2,  'cit_rate' => 2],
    ];

    private const SERVICE_TYPE_LABELS = [
        'services'              => 'Dịch vụ + Cho thuê máy móc',
        'services_with_goods'   => 'Dịch vụ kèm hàng hóa',
        'trading'               => 'Phân phối, cung ứng hàng hóa',
        'leasing'               => 'Cho thuê máy móc thiết bị',
        'other'                 => 'Kinh doanh khác',
    ];

    // Tính toán khấu trừ cho hợp đồng nhà thầu
    public function calculateWithholding(string $serviceType, float $contractValue): array
    {
        if (!isset(self::$WITHHOLDING_RATES[$serviceType])) {
            throw new \InvalidArgumentException("Loại dịch vụ không hợp lệ: {$serviceType}. Chấp nhận: " . implode(', ', array_keys(self::$WITHHOLDING_RATES)));
        }
        if ($contractValue <= 0) {
            throw new \InvalidArgumentException('Giá trị hợp đồng phải lớn hơn 0');
        }

        $rates = self::$WITHHOLDING_RATES[$serviceType];
        $vatRate = $rates['vat_rate'];
        $citRate = $rates['cit_rate'];

        // VAT withholding = contractValue × vatRate / (1 + vatRate)
        // Tức là tính ngược từ giá đã gồm VAT
        $vatWithholding = round($contractValue * $vatRate / (100 + $vatRate), 0);
        $citWithholding = round($contractValue * $citRate / 100, 0);
        $netPayment = $contractValue - $vatWithholding - $citWithholding;
        $vatBeforeTax = $contractValue - $vatWithholding;

        return [
            'service_type' => $serviceType,
            'service_type_label' => self::SERVICE_TYPE_LABELS[$serviceType] ?? $serviceType,
            'contract_value' => $contractValue,
            'vat_rate' => $vatRate,
            'cit_rate' => $citRate,
            'vat_withholding' => $vatWithholding,
            'cit_withholding' => $citWithholding,
            'net_payment' => $netPayment,
            'vat_before_tax' => $vatBeforeTax,
        ];
    }

    // Ghi nhận khấu trừ thuế nhà thầu
    public function recordWithholding(string $contractNo, string $contractorName, string $contractorCountry,
        string $serviceType, float $contractValue, string $currency = 'VND', float $exchangeRate = 1,
        string $notes = '', string $createdBy = 'system'): array
    {
        $calc = $this->calculateWithholding($serviceType, $contractValue);

        $id = uniqid('fct_');

        $this->pdo->prepare(
            "INSERT INTO fct_contracts (id, contract_no, contractor_name, contractor_country, service_type,
             contract_value, vat_rate, cit_rate, vat_withholding, cit_withholding, net_payment,
             currency, exchange_rate, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)"
        )->execute([
            $id, $contractNo, $contractorName, $contractorCountry, $serviceType,
            $calc['contract_value'], $calc['vat_rate'], $calc['cit_rate'],
            $calc['vat_withholding'], $calc['cit_withholding'], $calc['net_payment'],
            $currency, $exchangeRate, $notes, $createdBy
        ]);

        return $this->getContract($id);
    }

    // Lấy một hợp đồng
    public function getContract(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM fct_contracts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['contract_value'] = (float)$row['contract_value'];
        $row['vat_withholding'] = (float)$row['vat_withholding'];
        $row['cit_withholding'] = (float)$row['cit_withholding'];
        $row['net_payment'] = (float)$row['net_payment'];
        $row['vat_rate'] = (float)$row['vat_rate'];
        $row['cit_rate'] = (float)$row['cit_rate'];
        $row['exchange_rate'] = (float)$row['exchange_rate'];
        return $row;
    }

    // Danh sách hợp đồng
    public function getContracts(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, contract_no, contractor_name, contractor_country, service_type,
             contract_value, vat_withholding, cit_withholding, net_payment, status,
             currency, created_by, created_at
             FROM fct_contracts ORDER BY created_at DESC LIMIT 100"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($r) => [
            'id' => $r['id'],
            'contract_no' => $r['contract_no'],
            'contractor_name' => $r['contractor_name'],
            'contractor_country' => $r['contractor_country'],
            'service_type' => $r['service_type'],
            'contract_value' => (float)$r['contract_value'],
            'vat_withholding' => (float)$r['vat_withholding'],
            'cit_withholding' => (float)$r['cit_withholding'],
            'net_payment' => (float)$r['net_payment'],
            'status' => $r['status'],
            'currency' => $r['currency'],
            'created_by' => $r['created_by'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    // Chuẩn bị tờ khai FCT theo kỳ
    public function prepareDeclaration(string $period, string $createdBy): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // Kiểm tra tờ khai đã tồn tại cho kỳ này chưa
        $existing = $this->pdo->prepare("SELECT id, status FROM fct_declarations WHERE period = ?");
        $existing->execute([$period]);
        $existingRow = $existing->fetch(\PDO::FETCH_ASSOC);
        if ($existingRow && $existingRow['status'] === 'finalised') {
            throw new \RuntimeException('Tờ khai FCT kỳ ' . $period . ' đã được khóa. Không thể chuẩn bị lại.');
        }

        // Tổng hợp từ các hợp đồng trong kỳ
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(contract_value), 0) as total_value,
                    COALESCE(SUM(vat_withholding), 0) as total_vat,
                    COALESCE(SUM(cit_withholding), 0) as total_cit
             FROM fct_contracts
             WHERE created_at BETWEEN ? AND ? AND status = 'posted'"
        );
        $stmt->execute([$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        $id = $existingRow ? $existingRow['id'] : uniqid('fctd_');
        if ($existingRow) {
            $this->pdo->prepare(
                "UPDATE fct_declarations SET
                 total_contract_value = ?, total_vat_withholding = ?,
                 total_cit_withholding = ?, contract_count = ?
                 WHERE id = ?"
            )->execute([
                (float)$data['total_value'], (float)$data['total_vat'],
                (float)$data['total_cit'], (int)$data['cnt'],
                $id
            ]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO fct_declarations (id, period, total_contract_value, total_vat_withholding,
                 total_cit_withholding, contract_count, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)"
            )->execute([
                $id, $period,
                (float)$data['total_value'], (float)$data['total_vat'],
                (float)$data['total_cit'], (int)$data['cnt'],
                $createdBy
            ]);
        }

        return $this->getDeclaration($id);
    }

    public function getDeclaration(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM fct_declarations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['total_contract_value'] = (float)$row['total_contract_value'];
        $row['total_vat_withholding'] = (float)$row['total_vat_withholding'];
        $row['total_cit_withholding'] = (float)$row['total_cit_withholding'];
        return $row;
    }

    public function getDeclarations(): array
    {
        $rows = $this->pdo->query(
            "SELECT * FROM fct_declarations ORDER BY period DESC LIMIT 50"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => $r['id'],
            'period' => $r['period'],
            'status' => $r['status'],
            'total_contract_value' => (float)$r['total_contract_value'],
            'total_vat_withholding' => (float)$r['total_vat_withholding'],
            'total_cit_withholding' => (float)$r['total_cit_withholding'],
            'contract_count' => (int)$r['contract_count'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    public function finalise(string $id): array
    {
        // Load declaration first to get period info
        $decl = $this->getDeclaration($id);
        if (!$decl) throw new \RuntimeException('Không tìm thấy tờ khai FCT.');

        // Kiểm tra kỳ kế toán đang mở
        $period = $decl['period'] ?? '';
        if ($period && !PeriodService::isPeriodOpen($period . '-15', $this->pdo)) {
            throw new \RuntimeException("Kỳ kế toán {$period} đã đóng. Không thể khóa tờ khai.");
        }

        $stmt = $this->pdo->prepare(
            "UPDATE fct_declarations SET status = 'finalised' WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Không thể khóa tờ khai. Tờ khai đã được khóa trước đó.');
        }
        return $this->getDeclaration($id);
    }

    // Hủy hợp đồng
    public function cancelContract(string $id): array
    {
        $stmt = $this->pdo->prepare("UPDATE fct_contracts SET status = 'cancelled' WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            $contract = $this->getContract($id);
            if (!$contract) {
                throw new \RuntimeException('Không tìm thấy hợp đồng nhà thầu.');
            }
            throw new \RuntimeException('Không thể hủy hợp đồng. Hợp đồng không còn ở trạng thái nháp.');
        }
        return $this->getContract($id);
    }
}
