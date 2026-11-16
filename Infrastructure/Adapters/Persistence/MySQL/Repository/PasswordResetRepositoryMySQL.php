<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Ports/Out/PasswordResetPort.php';

final class PasswordResetRepositoryMySQL implements PasswordResetPort
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(string $userId, string $tokenHash, string $expiresAt): void
    {
        $this->pdo->beginTransaction();
        try {
            $invalidate = $this->pdo->prepare(
                'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND used_at IS NULL'
            );
            $invalidate->execute(array(':user_id' => $userId));

            $insert = $this->pdo->prepare(
                'INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (:hash, :user_id, :expires_at)'
            );
            $insert->execute(array(
                ':hash' => $tokenHash,
                ':user_id' => $userId,
                ':expires_at' => $expiresAt,
            ));
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function consumeAndReplacePassword(string $tokenHash, string $passwordHash): bool
    {
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare(
                'SELECT user_id FROM password_reset_tokens WHERE token_hash = :hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE'
            );
            $find->execute(array(':hash' => $tokenHash));
            $row = $find->fetch();
            if ($row === false) {
                $this->pdo->rollBack();
                return false;
            }

            $update = $this->pdo->prepare(
                'UPDATE users SET password = :password, authorization_version = authorization_version + 1, updated_at = UTC_TIMESTAMP() WHERE id = :user_id'
            );
            $update->execute(array(':password' => $passwordHash, ':user_id' => $row['user_id']));
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $consume = $this->pdo->prepare(
                'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = :hash AND used_at IS NULL'
            );
            $consume->execute(array(':hash' => $tokenHash));
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
