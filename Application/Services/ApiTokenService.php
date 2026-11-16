<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/Out/ApiTokenPort.php';

final class ApiTokenService
{
    public function __construct(private ApiTokenPort $port)
    {
    }

    public function issue(string $id, string $userId, string $name, int $days = 90): string
    {
        if ($days < 1 || $days > 365 || trim($name) === '') {
            throw new InvalidArgumentException('El nombre es obligatorio y la vigencia debe estar entre 1 y 365 días.');
        }
        $token = 'cat_' . bin2hex(random_bytes(32));
        $this->port->issue(
            $id,
            $userId,
            mb_substr(trim($name), 0, 100),
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + ($days * 86400))
        );
        return $token;
    }

    /** @return array{id: string, name: string, role: string}|null */
    public function authenticate(string $token): ?array
    {
        if (!preg_match('/^cat_[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        return $this->port->authenticate(hash('sha256', $token));
    }

    public function revoke(string $id): bool
    {
        return $this->port->revoke($id);
    }
}
