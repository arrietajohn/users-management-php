<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/Out/CelularRepositoryPort.php';
require_once __DIR__ . '/Dto/Commands/CelularCommand.php';
require_once __DIR__ . '/Dto/Queries/CelularQuery.php';
require_once __DIR__ . '/Dto/Queries/CelularPage.php';
require_once __DIR__ . '/../../Domain/Models/Celular.php';

final class CelularService
{
    public function __construct(private CelularRepositoryPort $repository)
    {
    }

    public function create(string $id, CelularCommand $command): Celular
    {
        return $this->repository->save($this->toModel($id, $command));
    }

    public function update(string $id, CelularCommand $command): Celular
    {
        if ($this->repository->getById($id) === null) {
            throw new DomainException('Celular no encontrado.');
        }

        return $this->repository->update($this->toModel($id, $command));
    }

    public function get(string $id): Celular
    {
        $celular = $this->repository->getById($id);
        if ($celular === null) {
            throw new DomainException('Celular no encontrado.');
        }
        return $celular;
    }

    public function delete(string $id): void
    {
        if ($this->repository->getById($id) === null) {
            throw new DomainException('Celular no encontrado.');
        }
        $this->repository->delete($id);
    }

    public function search(CelularQuery $query): CelularPage
    {
        $result = $this->repository->search($query);
        return new CelularPage($result['items'], $result['total'], $result['page'], $query->perPage());
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return $this->repository->dashboard();
    }

    private function toModel(string $id, CelularCommand $command): Celular
    {
        $data = $command->attributes();
        $bool = static function (string $key) use ($data): bool {
            $value = $data[$key] ?? null;
            if (is_bool($value)) {
                return $value;
            }
            if ($value === '1' || $value === 1 || $value === 'true') {
                return true;
            }
            if ($value === '0' || $value === 0 || $value === 'false') {
                return false;
            }
            throw InvalidCelularException::because($key, 'selecciona sí o no.');
        };

        return new Celular(
            $id,
            (string) ($data['marca'] ?? ''),
            is_array($data['imei'] ?? null) ? $data['imei'] : array(),
            (string) ($data['pulgadas'] ?? ''),
            (string) ($data['megapixeles'] ?? ''),
            filter_var($data['ram'] ?? null, FILTER_VALIDATE_INT) !== false ? (int) $data['ram'] : 0,
            filter_var($data['almacenamientoPrincipal'] ?? null, FILTER_VALIDATE_INT) !== false ? (int) $data['almacenamientoPrincipal'] : 0,
            filter_var($data['almacenamientoSecundario'] ?? '0', FILTER_VALIDATE_INT) !== false ? (int) $data['almacenamientoSecundario'] : -1,
            (string) ($data['sistemaOperativo'] ?? ''),
            (string) ($data['operador'] ?? ''),
            (string) ($data['tecnologiaBanda'] ?? ''),
            $bool('wifi'),
            $bool('bluetooth'),
            filter_var($data['camaras'] ?? null, FILTER_VALIDATE_INT) !== false ? (int) $data['camaras'] : 0,
            (string) ($data['marcaCpu'] ?? ''),
            (string) ($data['velocidadCpu'] ?? ''),
            $bool('nfc'),
            $bool('huella'),
            $bool('ir'),
            $bool('resisteAgua'),
            filter_var($data['cantidadSim'] ?? null, FILTER_VALIDATE_INT) !== false ? (int) $data['cantidadSim'] : 0
        );
    }
}
