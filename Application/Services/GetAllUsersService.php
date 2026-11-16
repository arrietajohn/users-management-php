<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/In/GetAllUsersUseCase.php';
require_once __DIR__ . '/../Ports/Out/GetAllUsersPort.php';
require_once __DIR__ . '/Dto/Queries/UserPage.php';

final class GetAllUsersService implements GetAllUsersUseCase
{
    private GetAllUsersPort $getAllUsersPort;

    public function __construct(GetAllUsersPort $getAllUsersPort)
    {
        $this->getAllUsersPort = $getAllUsersPort;
    }

    /** Returns a bounded page of users. */
    public function execute(GetAllUsersQuery $query): UserPage
    {
        $total = $this->getAllUsersPort->countAll();
        $lastPage = max(1, (int) ceil($total / $query->perPage()));
        $page = min($query->page(), $lastPage);
        $users = $this->getAllUsersPort->getAll($query->perPage(), ($page - 1) * $query->perPage());
        return new UserPage($users, $total, $page, $query->perPage());
    }
}
