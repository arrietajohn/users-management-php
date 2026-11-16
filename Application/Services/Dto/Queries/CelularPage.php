<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../Domain/Models/Celular.php';

final class CelularPage
{
    /** @param Celular[] $items */
    public function __construct(
        private array $items,
        private int $total,
        private int $page,
        private int $perPage
    ) {
    }

    /** @return Celular[] */
    public function items(): array { return $this->items; }
    public function total(): int { return $this->total; }
    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
    public function pages(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
}
