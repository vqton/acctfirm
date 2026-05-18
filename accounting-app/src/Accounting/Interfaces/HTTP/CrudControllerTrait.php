<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

trait CrudControllerTrait
{
    abstract protected function repo();

    protected function requiredFields(): array { return ['code', 'name']; }
    protected function idPrefix(): string { return 'ent_'; }
    protected function codeField(): string { return 'code'; }

    abstract protected function createEntity(array $data): object;
    abstract protected function updateEntity(object $entity, array $data): void;

    public function list(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->repo()->findAll()));
    }

    public function get(string $id): void
    {
        $entity = $this->repo()->findById($id);
        if (!$entity) { JsonResponse::error('Not found', 404); return; }
        JsonResponse::ok($entity->toArray());
    }

    public function create(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        foreach ($this->requiredFields() as $f) {
            if (!isset($data[$f])) {
                JsonResponse::error(implode(', ', $this->requiredFields()) . ' required', 400);
                return;
            }
        }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode') && $this->repo()->findByCode($data[$cf])) {
            JsonResponse::error('Code already exists', 409); return;
        }
        if (!isset($data['id'])) $data['id'] = uniqid($this->idPrefix());
        $entity = $this->createEntity($data);
        $this->repo()->save($entity);
        JsonResponse::ok($entity->toArray(), 201);
    }

    public function update(string $id): void
    {
        Auth::checkCsrf();
        $entity = $this->repo()->findById($id);
        if (!$entity) { JsonResponse::error('Not found', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Invalid data', 400); return; }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode')) {
            $existing = $this->repo()->findByCode($data[$cf]);
            if ($existing && $existing->getId() !== $id) {
                JsonResponse::error('Code already exists', 409); return;
            }
        }
        $this->updateEntity($entity, $data);
        $this->repo()->save($entity);
        JsonResponse::ok($entity->toArray());
    }

    public function delete(string $id): void
    {
        Auth::checkCsrf();
        if (!$this->repo()->findById($id)) {
            JsonResponse::error('Not found', 404); return;
        }
        $this->repo()->delete($id);
        JsonResponse::ok(['message' => 'Deleted']);
    }
}
