<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\ContractRepositoryInterface;
use Accounting\Domain\Model\Contract;
use PDO;

class ContractService
{
    private ContractRepositoryInterface $contractRepo;
    private PDO $pdo;
    private ReportExportService $export;

    public function __construct(
        ContractRepositoryInterface $contractRepo,
        PDO $pdo,
        ReportExportService $export
    ) {
        $this->contractRepo = $contractRepo;
        $this->pdo = $pdo;
        $this->export = $export;
    }

    public function getDashboardStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status='completed' OR status='liquidated' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                COALESCE(SUM(CASE WHEN status='active' THEN total_amount ELSE 0 END), 0) as total_value,
                COALESCE(SUM(CASE WHEN status='active' THEN fulfilled_amount ELSE 0 END), 0) as total_fulfilled,
                COALESCE(SUM(CASE WHEN status='active' THEN paid_amount ELSE 0 END), 0) as total_paid
            FROM contracts
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getExpiringContracts(int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM contracts 
            WHERE status = 'active' 
            AND end_date IS NOT NULL 
            AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY end_date
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function linkTransaction(string $contractId, string $linkedType, string $linkedId, ?string $linkedRef, float $amount, string $description, string $createdBy): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO contract_fulfillment_links (contract_id, linked_type, linked_id, linked_reference, amount, description, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$contractId, $linkedType, $linkedId, $linkedRef, $amount, $description, $createdBy]);

            $stmt = $this->pdo->prepare('UPDATE contracts SET fulfilled_amount = fulfilled_amount + ? WHERE id = ?');
            $stmt->execute([$amount, $contractId]);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addPaymentSchedule(string $contractId, string $dueDate, float $amount, ?string $milestone, ?string $notes): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contract_payment_schedules (contract_id, due_date, amount, milestone, notes, status)
             VALUES (?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$contractId, $dueDate, $amount, $milestone, $notes]);
    }

    public function recordPaymentSchedule(string $scheduleId, float $amount, string $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM contract_payment_schedules WHERE id = ? FOR UPDATE');
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$schedule) throw new \InvalidArgumentException('Không tìm thấy lịch thanh toán');

            $newPaid = $schedule['paid_amount'] + $amount;
            $newStatus = $newPaid >= $schedule['amount'] - 0.01 ? 'paid' : 'partial';

            $stmt = $this->pdo->prepare(
                'UPDATE contract_payment_schedules SET paid_amount = ?, status = ? WHERE id = ?'
            );
            $stmt->execute([$newPaid, $newStatus, $scheduleId]);

            $stmt = $this->pdo->prepare('UPDATE contracts SET paid_amount = paid_amount + ? WHERE id = ?');
            $stmt->execute([$amount, $schedule['contract_id']]);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addAmendment(string $contractId, string $amendmentNo, string $date, string $type, float $amountChange, string $description, string $createdBy): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO contract_amendments (contract_id, amendment_no, amendment_date, type, amount_change, description, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "active", ?, NOW())'
            );
            $stmt->execute([$contractId, $amendmentNo, $date, $type, $amountChange, $description, $createdBy]);

            $sign = $type === 'increase' ? '+' : '-';
            $stmt = $this->pdo->prepare("UPDATE contracts SET total_amount = total_amount $sign ? WHERE id = ?");
            $stmt->execute([abs($amountChange), $contractId]);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function liquidateContract(string $contractId, string $userId): void
    {
        $contract = $this->contractRepo->findById($contractId);
        if (!$contract) throw new \InvalidArgumentException('Không tìm thấy hợp đồng');
        $stmt = $this->pdo->prepare(
            "UPDATE contracts SET status = 'liquidated', approved_by = ?, closed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$userId, $contractId]);
    }

    public function getFulfillmentLinks(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_fulfillment_links WHERE contract_id = ? ORDER BY created_at'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentSchedules(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_payment_schedules WHERE contract_id = ? ORDER BY due_date'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAmendments(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_amendments WHERE contract_id = ? ORDER BY amendment_date'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportContractList(string $format, array $filters): array
    {
        $sql = 'SELECT * FROM contracts WHERE 1=1';
        $params = [];
        if (!empty($filters['type'])) { $sql .= ' AND contract_type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['status'])) { $sql .= ' AND status = ?'; $params[] = $filters['status']; }
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['Số HĐ', 'Loại', 'Đối tác', 'Ngày ký', 'Giá trị', 'Đã thực hiện', 'Đã thanh toán', 'Trạng thái', 'Ngày kết thúc'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['reference'] ?? $r['code'], $r['contract_type'], $r['partner_name'],
                $r['signed_date'], $r['total_amount'], $r['fulfilled_amount'],
                $r['paid_amount'], $r['status'], $r['end_date'],
            ];
        }
        return $this->export->exportCsv($headers, $data, 'hop_dong_' . date('Ymd') . '.csv');
    }
}
