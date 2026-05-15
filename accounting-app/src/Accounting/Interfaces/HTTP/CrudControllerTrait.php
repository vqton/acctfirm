<?php
namespace Accounting\Interfaces\HTTP;

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
        echo json_encode(array_map(fn($x) => $x->toArray(), $this->repo()->findAll()));
    }

    public function get(string $id): void
    {
        $entity = $this->repo()->findById($id);
        if (!$entity) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($entity->toArray());
    }

    public function create(): void
    {
        \Accounting\Infrastructure\Helpers::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        foreach ($this->requiredFields() as $f) {
            if (!isset($data[$f])) {
                http_response_code(400);
                echo json_encode(['error' => implode(', ', $this->requiredFields()) . ' required']);
                return;
            }
        }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode') && $this->repo()->findByCode($data[$cf])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        if (!isset($data['id'])) $data['id'] = uniqid($this->idPrefix());
        $entity = $this->createEntity($data);
        $this->repo()->save($entity);
        http_response_code(201);
        echo json_encode($entity->toArray());
    }

    public function update(string $id): void
    {
        \Accounting\Infrastructure\Helpers::checkCsrf();
        $entity = $this->repo()->findById($id);
        if (!$entity) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }
        $cf = $this->codeField();
        if (isset($data[$cf]) && method_exists($this->repo(), 'findByCode')) {
            $existing = $this->repo()->findByCode($data[$cf]);
            if ($existing && $existing->getId() !== $id) {
                http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
            }
        }
        $this->updateEntity($entity, $data);
        $this->repo()->save($entity);
        echo json_encode($entity->toArray());
    }

    public function delete(string $id): void
    {
        \Accounting\Infrastructure\Helpers::checkCsrf();
        if (!$this->repo()->findById($id)) {
            http_response_code(404); echo json_encode(['error' => 'Not found']); return;
        }
        $this->repo()->delete($id);
        echo json_encode(['message' => 'Deleted']);
    }
}
