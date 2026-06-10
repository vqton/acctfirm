<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * TRAIT: CRUD chuẩn cho Master Data Controllers
 *
 * Cung cấp 5 method CRUD mặc định: list, get, create, update, delete.
 * Các controller sử dụng trait này cần implement repo(), createEntity(), updateEntity().
 *
 * API endpoints (mặc định):
 *   GET    /api/{resource}       — Danh sách
 *   GET    /api/{resource}/{id}  — Chi tiết
 *   POST   /api/{resource}       — Tạo mới
 *   PUT    /api/{resource}/{id}  — Cập nhật
 *   DELETE /api/{resource}/{id}  — Xóa
 *
 * Rủi ro:
 *   - Trùng mã code -> 409 Conflict
 *   - Thiếu trường bắt buộc -> 400 Bad Request
 *   - Không tìm thấy bản ghi -> 404 Not Found
 */
trait CrudControllerTrait
{
    /**
     * Lấy repository instance — controller phải implement
     *
     * @return object RepositoryInterface
     */
    abstract protected function repo();

    /**
     * Danh sách trường bắt buộc khi tạo mới
     *
     * @return array Danh sách tên trường
     */
    protected function requiredFields(): array { return ['code', 'name']; }

    /**
     * Tiền tố cho ID tự sinh (uniqid)
     *
     * @return string Tiền tố
     */
    protected function idPrefix(): string { return 'ent_'; }

    /**
     * Tên trường code để kiểm tra trùng lặp
     *
     * @return string Tên trường
     */
    protected function codeField(): string { return 'code'; }

    /**
     * Tạo entity từ dữ liệu đầu vào — controller phải implement
     *
     * @param array $data Dữ liệu đầu vào
     * @return object Entity instance
     */
    abstract protected function createEntity(array $data): object;

    /**
     * Cập nhật entity từ dữ liệu đầu vào — controller phải implement
     *
     * @param object $entity Entity cần cập nhật
     * @param array $data Dữ liệu cập nhật
     * @return void
     */
    abstract protected function updateEntity(object $entity, array $data): void;

    /**
     * Danh sách tất cả bản ghi
     *
     * @return void
     */
    public function list(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->repo()->findAll()));
    }

    /**
     * Chi tiết một bản ghi theo ID
     *
     * @param string $id ID bản ghi
     * @return void
     */
    public function get(string $id): void
    {
        $entity = $this->repo()->findById($id);
        if (!$entity) { JsonResponse::error('Không tìm thấy bản ghi', 404); return; }
        JsonResponse::ok($entity->toArray());
    }

    /**
     * Tạo mới bản ghi — kiểm tra CSRF, validate trường bắt buộc, kiểm tra trùng mã
     *
     * @return void
     */
    public function create(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        foreach ($this->requiredFields() as $f) {
            if (!isset($data[$f])) {
                JsonResponse::error('Vui lòng nhập các trường bắt buộc: ' . implode(', ', $this->requiredFields()), 400);
                return;
            }
        }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode') && $this->repo()->findByCode($data[$cf])) {
            JsonResponse::error('Mã đã tồn tại trong hệ thống', 409); return;
        }
        if (!isset($data['id'])) $data['id'] = uniqid($this->idPrefix());
        $entity = $this->createEntity($data);
        $this->repo()->save($entity);
        JsonResponse::ok($entity->toArray(), 201);
    }

    /**
     * Cập nhật bản ghi — kiểm tra CSRF, validate dữ liệu, kiểm tra trùng mã
     *
     * @param string $id ID bản ghi
     * @return void
     */
    public function update(string $id): void
    {
        Auth::checkCsrf();
        $entity = $this->repo()->findById($id);
        if (!$entity) { JsonResponse::error('Không tìm thấy bản ghi', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.', 400); return; }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode')) {
            $existing = $this->repo()->findByCode($data[$cf]);
            if ($existing && $existing->getId() !== $id) {
                JsonResponse::error('Mã đã tồn tại trong hệ thống', 409); return;
            }
        }
        $this->updateEntity($entity, $data);
        $this->repo()->save($entity);
        JsonResponse::ok($entity->toArray());
    }

    /**
     * Xóa bản ghi — kiểm tra CSRF, kiểm tra tồn tại
     *
     * @param string $id ID bản ghi
     * @return void
     */
    public function delete(string $id): void
    {
        Auth::checkCsrf();
        if (!$this->repo()->findById($id)) {
            JsonResponse::error('Không tìm thấy bản ghi', 404); return;
        }
        $this->repo()->delete($id);
        JsonResponse::ok(['message' => 'Đã xóa thành công']);
    }
}
