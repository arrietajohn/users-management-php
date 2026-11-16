<?php

declare(strict_types=1);

require_once __DIR__ . '/Mapper/CelularWebMapper.php';
require_once __DIR__ . '/../../../Adapters/Persistence/MySQL/Repository/CelularRepositoryMySQL.php';
require_once __DIR__ . '/../../../../Application/Services/CelularService.php';

final class CelularController
{
    public function __construct(private CelularService $service, private CelularWebMapper $mapper)
    {
    }

    public function index(array $query): CelularPage
    {
        return $this->service->search($this->mapper->fromInputToQuery($query));
    }

    public function show(string $id): Celular
    {
        return $this->service->get($id);
    }

    public function store(array $input): Celular
    {
        return $this->service->create(Uuid::v4(), $this->mapper->fromInputToCommand($input));
    }

    public function update(string $id, array $input): Celular
    {
        return $this->service->update($id, $this->mapper->fromInputToCommand($input));
    }

    public function delete(string $id): void
    {
        $this->service->delete($id);
    }

    public function dashboard(): array
    {
        return $this->service->dashboard();
    }
}
