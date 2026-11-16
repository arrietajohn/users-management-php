<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Services/Dto/Queries/GetAllUsersQuery.php';
require_once __DIR__ . '/../../../Domain/Models/UserModel.php';
require_once __DIR__ . '/../../Services/Dto/Queries/UserPage.php';

interface GetAllUsersUseCase
{
    /** Returns a bounded page of users. */
    public function execute(GetAllUsersQuery $query): UserPage;
}

