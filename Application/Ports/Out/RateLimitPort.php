<?php

declare(strict_types=1);

interface RateLimitPort
{
    public function consume(string $keyHash, int $maximum, int $windowSeconds): bool;
    public function clear(string $keyHash): void;
}
