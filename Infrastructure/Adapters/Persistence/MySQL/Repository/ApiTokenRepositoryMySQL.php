<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Ports/Out/ApiTokenPort.php';

final class ApiTokenRepositoryMySQL implements ApiTokenPort
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(string $id, string $userId, string $name, string $tokenHash, string $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO api_tokens (id, user_id, name, token_hash, expires_at) VALUES (:id, :user, :name, :hash, :expires)'
        );
        $statement->execute(array(
            ':id' => $id, ':user' => $userId, ':name' => $name, ':hash' => $tokenHash, ':expires' => $expiresAt,
        ));
    }

    public function authenticate(string $tokenHash): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT t.id AS token_id, u.id, u.name, u.role FROM api_tokens t ' .
                'INNER JOIN users u ON u.id = t.user_id ' .
                'WHERE t.token_hash = :hash AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP() AND u.status = :active LIMIT 1 FOR UPDATE'
            );
            $statement->execute(array(':hash' => $tokenHash, ':active' => 'ACTIVE'));
            $row = $statement->fetch();
            if ($row === false) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = :id');
            $update->execute(array(':id' => $row['token_id']));
            $this->pdo->commit();
            return array('id' => (string) $row['id'], 'name' => (string) $row['name'], 'role' => (string) $row['role']);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function revoke(string $id): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = :id AND revoked_at IS NULL'
        );
        $statement->execute(array(':id' => $id));
        return $statement->rowCount() === 1;
    }
}
