<?php
namespace Accounting\Domain\Service;

//
// DỊCH VỤ XÁC ĐỊNH THUẾ SUẤT GTGT
//
// Xác định thuế suất VAT cho hàng hóa/dịch vụ dựa trên nhóm thuế (vat_groups).
// Tuân thủ Luật VAT 48/2024 + NQ 204/2025 (giảm 8% đến 31/12/2026).
//
// THUẬT TOÁN (theo tax spec Section 6.1):
//   1. Xác định VAT group từ item category/product type
//   2. Lấy rate mặc định từ group
//   3. Kiểm tra override rate
//   4. Áp dụng giảm 8% nếu đủ điều kiện
//   5. Kiểm tra xuất khẩu (0%), miễn thuế
//
class VatRateService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Xác định thuế suất cho một mặt hàng
    // Input: item array (có item_id, category_code, product_type) + transaction context
    // Output: ['rate' => float, 'group_code' => string, 'is_reduction' => bool, 'is_exempt' => bool]
    public function determineRate(array $item, array $context = []): array
    {
        $groupId = $this->resolveGroup($item);
        $group = $this->getGroup($groupId);

        if (!$group) {
            // Mặc định 10% nếu không tìm thấy nhóm
            return ['rate' => 10, 'group_code' => 'VAT10', 'is_reduction' => false, 'is_exempt' => false];
        }

        $rate = (float)$group['default_rate'];
        $isReduction = false;
        $isExempt = (bool)$group['is_exempt'];

        // Bước 4: Kiểm tra giảm 8% (NQ 204/2025)
        if (!$isExempt && $rate == 10 && (bool)$group['is_reduction_eligible']) {
            $now = date('Y-m-d');
            $endDate = $group['reduction_end_date'];
            if ($endDate && $now <= $endDate) {
                $reductionRate = $group['reduction_rate'];
                if ($reductionRate !== null) {
                    $rate = (float)$reductionRate;
                    $isReduction = true;
                }
            }
        }

        // Bước 5: Kiểm tra xuất khẩu
        if (!empty($context['is_export']) || (bool)$group['is_zero_rated']) {
            $rate = 0;
        }

        return [
            'rate' => $rate,
            'group_code' => $group['code'],
            'group_name' => $group['name'],
            'is_reduction' => $isReduction,
            'is_exempt' => $isExempt,
        ];
    }

    // Lấy danh sách nhóm thuế
    public function getGroups(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM vat_groups WHERE is_active = 1 ORDER BY sort_order");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Lấy thông tin một nhóm thuế
    public function getGroup(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vat_groups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Kiểm tra mặt hàng có được giảm thuế 8% không
    public function isEligibleForReduction(string $itemId): bool
    {
        $result = $this->determineRate(['item_id' => $itemId]);
        return $result['is_reduction'];
    }

    // Ánh xạ mặt hàng vào nhóm thuế
    public function assignItemToGroup(string $itemId, string $groupId): void
    {
        $this->pdo->prepare(
            "INSERT INTO vat_group_products (id, vat_group_id, item_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE vat_group_id = VALUES(vat_group_id)"
        )->execute([uniqid('vgp_'), $groupId, $itemId]);
    }

    // Xác định nhóm thuế cho một mặt hàng
    private function resolveGroup(array $item): string
    {
        // Ưu tiên: item_id → category_code → product_type → default
        if (!empty($item['item_id'])) {
            $stmt = $this->pdo->prepare(
                "SELECT vat_group_id FROM vat_group_products WHERE item_id = ? LIMIT 1"
            );
            $stmt->execute([$item['item_id']]);
            $id = $stmt->fetchColumn();
            if ($id) return $id;
        }

        if (!empty($item['category_code'])) {
            $stmt = $this->pdo->prepare(
                "SELECT vat_group_id FROM vat_group_products WHERE category_code = ? LIMIT 1"
            );
            $stmt->execute([$item['category_code']]);
            $id = $stmt->fetchColumn();
            if ($id) return $id;
        }

        if (!empty($item['product_type'])) {
            $stmt = $this->pdo->prepare(
                "SELECT vat_group_id FROM vat_group_products WHERE product_type = ? LIMIT 1"
            );
            $stmt->execute([$item['product_type']]);
            $id = $stmt->fetchColumn();
            if ($id) return $id;
        }

        // Mặc định: nhóm 10%
        return 'vg_10';
    }
}
