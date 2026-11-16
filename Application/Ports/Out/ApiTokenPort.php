<?php

declare(strict_types=1);

interface ApiTokenPort
{
    public function issue(string $id, string $userId, string $name, string $tokenHash, string $expiresAt): void;
    public function revoke(string $id): bool;

    /** @return array{id: string, name: string, role: string}|null */
    public function authenticate(string $tokenHash): ?array;
}
