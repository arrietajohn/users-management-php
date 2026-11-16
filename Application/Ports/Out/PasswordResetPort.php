<?php

declare(strict_types=1);

interface PasswordResetPort
{
    public function issue(string $userId, string $tokenHash, string $expiresAt): void;

    /** Atomically consumes a valid token and replaces the stored password hash. */
    public function consumeAndReplacePassword(string $tokenHash, string $passwordHash): bool;
}
