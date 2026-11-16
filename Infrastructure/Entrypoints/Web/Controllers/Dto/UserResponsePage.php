<?php

declare(strict_types=1);

final class UserResponsePage
{
    /** @param UserResponse[] $users */
    public function __construct(
        private array $users,
        private int $total,
        private int $page,
        private int $perPage
    ) {
    }

    /** @return UserResponse[] */
    public function users(): array { return $this->users; }
    public function total(): int { return $this->total; }
    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
    public function pages(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
}
