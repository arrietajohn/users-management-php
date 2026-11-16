<?php

declare(strict_types=1);

final class GetAllUsersQuery
{
    private int $page;
    private int $perPage;

    public function __construct(int $page = 1, int $perPage = 20)
    {
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
    }

    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
}
