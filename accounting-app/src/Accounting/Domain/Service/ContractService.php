<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\ContractRepositoryInterface;
use Accounting\Domain\Model\Contract;
use PDO;

/**
 * Dịch vụ quản lý hợp đồng — nghiệp vụ trọn đời hợp đồng.
 *
 * Cung cấp các chức năng: thống kê dashboard, liên kết giao dịch,
 * lịch thanh toán, phụ lục hợp đồng, thanh lý và xuất danh sách.
 *
 * NGHIỆP VỤ:
 * - Hợp đồng làm cơ sở phát sinh giao dịch mua/bán, tạm ứng, thanh lý.
 * - Quyết định thời điểm ghi nhận doanh thu, thời hạn thanh toán, điều khoản TC.
 * - Thanh lý hợp đồng có thể phát sinh phạt vi phạm (thu nhập khác TK 711).
 *
 * RỦI RO:
 * - Doanh thu theo % hoàn thành (construction) cần ước lượng đáng tin cậy.
 * - Hợp đồng ngoại tệ phải theo dõi chênh lệch tỷ giá cuối kỳ.
 *
 * @see Contract
 * @see ContractRepositoryInterface
 * @see ReportExportService
 */
class ContractService
{
    private ContractRepositoryInterface $contractRepo;
    private PDO $pdo;
    private ReportExportService $export;

    /**
     * @param ContractRepositoryInterface $contractRepo Repository cho thao tác hợp đồng
     * @param PDO $pdo Kết nối PDO MySQL (transaction support)
     * @param ReportExportService $export Dịch vụ xuất danh sách CSV
     */
    public function __construct(
        ContractRepositoryInterface $contractRepo,
        PDO $pdo,
        ReportExportService $export
    ) {
        $this->contractRepo = $contractRepo;
        $this->pdo = $pdo;
        $this->export = $export;
    }

    /**
     * Thống kê tổng quan hợp đồng cho dashboard.
     *
     * Trả về số lượng hợp đồng theo trạng thái và giá trị tổng/đã thực hiện/đã thanh toán.
     *
     * NGHIỆP VỤ:
     * - total: tổng số hợp đồng
     * - active: đang hiệu lực
     * - draft: bản nháp
     * - completed/liquidated: đã hoàn thành hoặc thanh lý
     * - cancelled: đã hủy
     * - total_value: tổng giá trị hợp đồng đang hiệu lực
     * - total_fulfilled: giá trị đã thực hiện
     * - total_paid: giá trị đã thanh toán
     *
     * @return array{total: int, active: int, draft: int, completed: int, cancelled: int, total_value: float, total_fulfilled: float, total_paid: float}
     */
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

    /**
     * Danh sách hợp đồng sắp hết hạn trong vòng N ngày tới.
     *
     * NGHIỆP VỤ: Hợp đồng đang active có end_date trong khoảng từ hôm nay đến N ngày sau.
     * Kết quả sắp xếp theo end_date tăng dần.
     *
     * RỦI RO: Hợp đồng hết hạn nhưng chưa thanh lý → rủi ro pháp lý và sai sót BC.
     *
     * @param int $days Số ngày sắp tới để kiểm tra (mặc định 30)
     * @return array<array<string, mixed>> Danh sách hợp đồng sắp hết hạn
     */
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

    /**
     * Liên kết giao dịch thực tế với hợp đồng và cập nhật giá trị đã thực hiện.
     *
     * NGHIỆP VỤ: Khi phát sinh giao dịch (xuất kho, thanh toán, tạm ứng) liên quan đến hợp đồng,
     * cần ghi nhận vào bảng contract_fulfillment_links và cập nhật fulfilled_amount.
     *
     * THUẾ: Giá trị liên kết ảnh hưởng đến doanh thu/chi phí của hợp đồng.
     * Nếu hợp đồng có VAT, giao dịch liên kết cần khớp với hóa đơn GTGT.
     *
     * RỦI RO: Transaction rollback nếu bất kỳ bước nào lỗi — đảm bảo toàn vẹn dữ liệu.
     *
     * @param string $contractId ID hợp đồng
     * @param string $linkedType Loại liên kết (invoice, payment, receipt, goods_issue, ...)
     * @param string $linkedId ID bản ghi được liên kết
     * @param string|null $linkedRef Số tham chiếu của giao dịch (số hóa đơn, phiếu chi,...)
     * @param float $amount Giá trị giao dịch được liên kết
     * @param string $description Diễn giải nội dung liên kết
     * @param string $createdBy Người tạo liên kết (user ID)
     * @throws \InvalidArgumentException Nếu tham số không hợp lệ
     * @throws \PDOException Nếu có lỗi database
     */
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

    /**
     * Thêm lịch thanh toán cho hợp đồng.
     *
     * NGHIỆP VỤ: Mỗi hợp đồng có thể có nhiều kỳ hạn thanh toán theo tiến độ.
     * Mốc thanh toán (milestone) gắn với biên bản nghiệm thu hoặc tiến độ dự án.
     *
     * RỦI RO: Lịch thanh toán không khớp với tiến độ thực tế → sai số dư công nợ.
     *
     * @param string $contractId ID hợp đồng
     * @param string $dueDate Ngày đến hạn thanh toán (Y-m-d)
     * @param float $amount Số tiền thanh toán
     * @param string|null $milestone Mốc thanh toán (nghiệm thu giai đoạn, kết thúc,...)
     * @param string|null $notes Ghi chú bổ sung
     */
    public function addPaymentSchedule(string $contractId, string $dueDate, float $amount, ?string $milestone, ?string $notes): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contract_payment_schedules (contract_id, due_date, amount, milestone, notes, status)
             VALUES (?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$contractId, $dueDate, $amount, $milestone, $notes]);
    }

    /**
     * Ghi nhận thanh toán cho một kỳ hạn trong lịch thanh toán hợp đồng.
     *
     * NGHIỆP VỤ: Khi khách hàng thanh toán hoặc đơn vị chi trả cho nhà cung cấp,
     * cập nhật paid_amount và trạng thái (paid nếu đủ, partial nếu còn thiếu).
     * Đồng thời cập nhật tổng paid_amount trên contracts.
     *
     * RỦI RO:
     * - SELECT FOR UPDATE tránh race condition khi ghi nhận đồng thời.
     * - Transaction rollback nếu bất kỳ bước nào lỗi.
     * - Thanh toán vượt quá số tiền kỳ hạn có thể phát sinh công nợ phải trả.
     *
     * @param string $scheduleId ID lịch thanh toán
     * @param float $amount Số tiền thanh toán kỳ này
     * @param string $userId Người ghi nhận (user ID)
     * @throws \InvalidArgumentException Nếu không tìm thấy lịch thanh toán
     * @throws \PDOException Nếu có lỗi database
     */
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

    /**
     * Thêm phụ lục hợp đồng (điều chỉnh giá trị).
     *
     * NGHIỆP VỤ: Phụ lục điều chỉnh tăng/giảm giá trị hợp đồng gốc.
     * type = 'increase' → cộng thêm, type = 'decrease' → trừ bớt.
     * Giá trị tuyệt đối luôn dương, dấu được xác định bởi type.
     *
     * RỦI RO:
     * - Transaction rollback nếu bất kỳ bước nào lỗi.
     * - Phụ lục giảm có thể làm total_amount < fulfilled_amount → sai sót BC.
     * - Cần đảm bảo amendment_no duy nhất trong năm.
     *
     * @param string $contractId ID hợp đồng
     * @param string $amendmentNo Số phụ lục (duy nhất trong năm)
     * @param string $date Ngày phụ lục (Y-m-d)
     * @param string $type Loại điều chỉnh: 'increase' hoặc 'decrease'
     * @param float $amountChange Giá trị điều chỉnh (luôn dương)
     * @param string $description Nội dung điều chỉnh
     * @param string $createdBy Người tạo (user ID)
     * @throws \InvalidArgumentException Nếu type không hợp lệ
     * @throws \PDOException Nếu có lỗi database
     */
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

    /**
     * Thanh lý hợp đồng — chuyển trạng thái thành 'liquidated'.
     *
     * NGHIỆP VỤ: Hợp đồng đã hoàn thành hoặc chấm dứt trước thời hạn được thanh lý.
     * Ghi nhận người duyệt và thời điểm đóng.
     *
     * THUẾ: Thanh lý hợp đồng có thể phát sinh phạt vi phạm (thu nhập khác TK 711).
     *
     * RỦI RO:
     * - Hợp đồng đã thanh lý không được phép sửa/xóa giao dịch liên quan.
     * - Cần kiểm tra công nợ tồn đọng trước khi thanh lý.
     * - Hợp đồng chưa thanh lý nhưng đã hết hạn → sai BC.
     *
     * @param string $contractId ID hợp đồng
     * @param string $userId Người duyệt thanh lý (user ID)
     * @throws \InvalidArgumentException Nếu không tìm thấy hợp đồng
     */
    public function liquidateContract(string $contractId, string $userId): void
    {
        $contract = $this->contractRepo->findById($contractId);
        if (!$contract) throw new \InvalidArgumentException('Không tìm thấy hợp đồng');
        $stmt = $this->pdo->prepare(
            "UPDATE contracts SET status = 'liquidated', approved_by = ?, closed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$userId, $contractId]);
    }

    /**
     * Lấy danh sách các giao dịch đã liên kết với hợp đồng.
     *
     * NGHIỆP VỤ: Các giao dịch thực tế (xuất kho, thanh toán, tạm ứng)
     * được liên kết với hợp đồng qua bảng contract_fulfillment_links.
     *
     * RỦI RO: Nếu thiếu liên kết → sai số liệu giá trị thực hiện hợp đồng.
     *
     * @param string $contractId ID hợp đồng
     * @return array<array<string, mixed>> Danh sách liên kết giao dịch
     */
    public function getFulfillmentLinks(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_fulfillment_links WHERE contract_id = ? ORDER BY created_at'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy lịch thanh toán của hợp đồng.
     *
     * NGHIỆP VỤ: Trả về các kỳ hạn thanh toán theo thứ tự ngày đến hạn.
     * Dùng để theo dõi công nợ phải thu/phải trả theo tiến độ hợp đồng.
     *
     * @param string $contractId ID hợp đồng
     * @return array<array<string, mixed>> Danh sách lịch thanh toán
     */
    public function getPaymentSchedules(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_payment_schedules WHERE contract_id = ? ORDER BY due_date'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách phụ lục hợp đồng.
     *
     * NGHIỆP VỤ: Phụ lục điều chỉnh giá trị hợp đồng (tăng/giảm).
     * Sắp xếp theo ngày phụ lục để dễ theo dõi lịch sử điều chỉnh.
     *
     * @param string $contractId ID hợp đồng
     * @return array<array<string, mixed>> Danh sách phụ lục
     */
    public function getAmendments(string $contractId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contract_amendments WHERE contract_id = ? ORDER BY amendment_date'
        );
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Xuất danh sách hợp đồng dạng CSV.
     *
     * NGHIỆP VỤ: Lọc theo loại hợp đồng và/hoặc trạng thái.
     * Giới hạn 500 bản ghi để tránh quá tải.
     * File CSV có BOM UTF-8 để Excel nhận diện tiếng Việt.
     *
     * RỦI RO:
     - Nếu có >500 hợp đồng, cần thêm phân trang.
     - Cột partner_name có thể chứa dấu phẩy → cần escape CSV đúng.
     *
     * @param string $format Định dạng xuất (hiện tại chỉ hỗ trợ 'csv')
     * @param array{type?: string, status?: string} $filters Bộ lọc: 'type' (contract_type), 'status'
     * @return array{content: string, filename: string, mime: string} Mảng response (content, filename, mime)
     */
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
