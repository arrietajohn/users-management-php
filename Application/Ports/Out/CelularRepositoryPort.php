<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Domain/Models/Celular.php';
require_once __DIR__ . '/../../Services/Dto/Queries/CelularQuery.php';

interface CelularRepositoryPort
{
    public function save(Celular $celular): Celular;
    public function update(Celular $celular): Celular;
    public function getById(string $id): ?Celular;
    public function delete(string $id): void;
    /** @return array{items: Celular[], total: int, page: int} */
    public function search(CelularQuery $query): array;
    /** @return array{total: int, byBrand: array<int, array{label: string, total: int}>, byOs: array<int, array{label: string, total: int}>, recent: Celular[]} */
    public function dashboard(): array;
}
