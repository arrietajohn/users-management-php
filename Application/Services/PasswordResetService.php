<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/Out/PasswordResetPort.php';
require_once __DIR__ . '/../Ports/Out/GetUserByEmailPort.php';
require_once __DIR__ . '/../../Domain/ValueObjects/UserEmail.php';
require_once __DIR__ . '/../../Domain/ValueObjects/UserPassword.php';
require_once __DIR__ . '/../../Domain/Enums/UserStatusEnum.php';

final class PasswordResetService
{
    private PasswordResetPort $resetPort;
    private GetUserByEmailPort $userLookup;

    public function __construct(PasswordResetPort $resetPort, GetUserByEmailPort $userLookup)
    {
        $this->resetPort = $resetPort;
        $this->userLookup = $userLookup;
    }

    /** Returns a raw, one-time token only when an active matching account exists. */
    public function request(string $email): ?string
    {
        $user = $this->userLookup->getByEmail(new UserEmail($email));
        if ($user === null || $user->status() !== UserStatusEnum::ACTIVE) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->resetPort->issue(
            $user->id()->value(),
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + 1800)
        );

        return $token;
    }

    public function complete(string $token, string $newPassword): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        $password = UserPassword::fromPlainText($newPassword);

        return $this->resetPort->consumeAndReplacePassword(
            hash('sha256', $token),
            $password->value()
        );
    }
}
