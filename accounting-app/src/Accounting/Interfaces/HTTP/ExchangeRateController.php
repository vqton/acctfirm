<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\ExchangeRate;
use Accounting\Domain\Repository\ExchangeRateRepositoryInterface;

class ExchangeRateController
{
    use CrudControllerTrait;

    private ExchangeRateRepositoryInterface $repo;
    public function __construct(ExchangeRateRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'exr_'; }
    protected function codeField(): string { return 'currency_code'; }
    protected function requiredFields(): array { return ['currency_code', 'currency_name', 'rate', 'rate_date']; }

    protected function createEntity(array $data): object
    {
        return new ExchangeRate(
            $data['id'], $data['currency_code'], $data['currency_name'],
            (float)$data['rate'], $data['rate_date']
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['currency_code'])) $entity->setCurrencyCode($data['currency_code']);
        if (isset($data['currency_name'])) $entity->setCurrencyName($data['currency_name']);
        if (isset($data['rate'])) $entity->setRate((float)$data['rate']);
        if (isset($data['rate_date'])) $entity->setRateDate($data['rate_date']);
    }
}
