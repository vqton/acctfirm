<?php
/**
 * Giao diện hệ thống ghi nhật ký kiểm toán (Audit Trail).
 *
 * Nghiệp vụ: Mọi thay đổi dữ liệu quan trọng trong hệ thống kế toán
 * đều phải được ghi lại đầy đủ để phục vụ kiểm toán và truy xuất nguồn gốc.
 * Audit log là bất biến — không được sửa hoặc xóa sau khi ghi.
 *
 * Yêu cầu kiểm toán:
 *   - Mọi bút toán kế toán (journal entry) phải có audit trail
 *   - Mọi thay đổi số dư tài khoản phải có audit trail
 *   - Mọi thao tác xóa/sửa chứng từ phải có audit trail
 *   - Audit trail phải bao gồm: thời gian, người thực hiện, hành động,
 *     giá trị cũ, giá trị mới, địa chỉ IP
 */
namespace Accounting\Domain\Contract;

interface AuditLoggerInterface
{
    /**
     * Ghi một nhật ký kiểm toán.
     *
     * @param string $action     Mã hành động (định danh duy nhất, ví dụ: 'journal.post', 'cash.receipt', 'inventory.issue')
     * @param string $resourceType Loại tài nguyên bị thay đổi (ví dụ: 'transaction', 'account', 'item')
     * @param string|null $resourceId Định danh của tài nguyên (ví dụ: mã giao dịch, mã tài khoản)
     * @param array|null  $oldValues   Giá trị trước khi thay đổi (null nếu là hành động tạo mới)
     * @param array|null  $newValues   Giá trị sau khi thay đổi (luôn bao gồm thông tin nhận dạng)
     * @param string|null $actorId     Mã định danh của người thực hiện hành động
     * @param string|null $actorEmail  Email của người thực hiện hành động
     * @return void
     */
    public function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorId = null,
        ?string $actorEmail = null
    ): void;
}
