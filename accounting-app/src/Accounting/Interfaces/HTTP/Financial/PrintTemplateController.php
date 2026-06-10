<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\PrintTemplateService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Mẫu in (Print Templates)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý mẫu in cho chứng từ kế toán
 *   - Template engine mini: {{var}}, {{#if}}, {{#each}}, {{{var}}} raw
 *   - Hỗ trợ nhiều resource type (ap_invoice, ar_invoice, sales_order)
 *
 * API endpoints:
 *   GET  /api/print-templates — Danh sách mẫu in
 *   GET  /api/print-templates/{id} — Chi tiết mẫu
 *   POST /api/print-templates — Tạo mẫu mới
 *   PUT  /api/print-templates/{id} — Cập nhật mẫu
 *   DELETE /api/print-templates/{id} — Xoá mẫu
 *   POST /api/print-templates/{id}/render — Render mẫu với dữ liệu
 *   POST /api/print-templates/{id}/set-default — Đặt làm mặc định
 *   POST /api/print-templates/{id}/duplicate — Nhân bản
 *   POST /api/print-templates/seed — Seed mẫu mặc định
 *
 * Rủi ro:
 *   - Template sai -> in sai chứng từ -> ảnh hưởng pháp lý
 *   - Lỗi render engine -> không in được
 *
 * Tích hợp:
 *   - PrintTemplateService xử lý render
 *   - Các resource controllers cung cấp data
 */
class PrintTemplateController
{
    private PrintTemplateService $service;

    public function __construct(PrintTemplateService $service) { $this->service = $service; }

    /**
     * Danh sách mẫu in
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('admin', 'read');
        $resourceType = $_GET['resource_type'] ?? null;
        JsonResponse::ok($this->service->getTemplates($resourceType));
    }

    /**
     * Chi tiết mẫu in
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('admin', 'read');
        $template = $this->service->getTemplate($id);
        if (!$template) { JsonResponse::error('Không tìm thấy mẫu in', 404); return; }
        JsonResponse::ok($template);
    }

    /**
     * Tạo mẫu in mới
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('admin', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name'], $data['resource_type'], $data['content'])) {
            JsonResponse::error('Vui lòng nhập tên, loại tài nguyên và nội dung', 400);
            return;
        }
        try {
            $result = $this->service->createTemplate($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Cập nhật mẫu in
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function update(string $id): void
    {
        Auth::requirePermission('admin', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Dữ liệu không hợp lệ', 400); return; }
        try {
            $result = $this->service->updateTemplate($id, $data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Xoá mẫu in
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function delete(string $id): void
    {
        Auth::requirePermission('admin', 'delete');
        Auth::checkCsrf();
        $this->service->deleteTemplate($id);
        JsonResponse::ok(['message' => 'Đã xoá mẫu in']);
    }

    /**
     * Render mẫu in với dữ liệu
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function render(string $id): void
    {
        Auth::requirePermission('admin', 'read');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $result = $this->service->renderTemplate($id, $data);
            JsonResponse::ok(['rendered' => $result]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Đặt mẫu in làm mặc định
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function setDefault(string $id): void
    {
        Auth::requirePermission('admin', 'update');
        Auth::checkCsrf();
        $this->service->setDefault($id);
        JsonResponse::ok(['message' => 'Đã đặt làm mặc định']);
    }

    /**
     * Nhân bản mẫu in
     *
     * @param string $id ID mẫu in
     * @return void
     */
    public function duplicate(string $id): void
    {
        Auth::requirePermission('admin', 'create');
        Auth::checkCsrf();
        try {
            $result = $this->service->duplicateTemplate($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Seed mẫu in mặc định
     *
     * @return void
     */
    public function seed(): void
    {
        Auth::requirePermission('admin', 'create');
        Auth::checkCsrf();
        $this->service->seedDefaults();
        JsonResponse::ok(['message' => 'Đã seed mẫu in mặc định'], 201);
    }
}
