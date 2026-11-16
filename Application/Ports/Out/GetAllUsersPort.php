<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Domain/Models/UserModel.php';

interface GetAllUsersPort
{
    /**
     * @return UserModel[]
     */
    public function getAll(int $limit, int $offset): array;
    public function countAll(): int;
}
