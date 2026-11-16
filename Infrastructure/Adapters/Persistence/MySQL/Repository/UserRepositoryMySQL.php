<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Ports/Out/SaveUserPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/UpdateUserPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/GetUserByIdPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/GetUserByEmailPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/GetAllUsersPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/DeleteUserPort.php';
require_once __DIR__ . '/../../../../../Application/Ports/Out/GetActiveUserCountPort.php';

require_once __DIR__ . '/../Mapper/UserPersistenceMapper.php';
require_once __DIR__ . '/../../../../../Domain/Models/UserModel.php';
require_once __DIR__ . '/../../../../../Domain/ValueObjects/UserId.php';
require_once __DIR__ . '/../../../../../Domain/ValueObjects/UserEmail.php';
require_once __DIR__ . '/../../../../../Application/Security/RolePermissions.php';

final class UserRepositoryMySQL implements
    SaveUserPort,
    UpdateUserPort,
    GetUserByIdPort,
    GetUserByEmailPort,
    GetAllUsersPort,
    DeleteUserPort,
    GetActiveUserCountPort
{
    private PDO $pdo;
    private UserPersistenceMapper $mapper;

    public function __construct(PDO $pdo, UserPersistenceMapper $mapper)
    {
        $this->pdo = $pdo;
        $this->mapper = $mapper;
    }

    public function save(UserModel $user): UserModel
    {
        $dto = $this->mapper->fromModelToDto($user);

        $sql = '
            INSERT INTO users (
                id,
                name,
                email,
                password,
                role,
                status,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :name,
                :email,
                :password,
                :role,
                :status,
                NOW(),
                NOW()
            )
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array(
            ':id' => $dto->id(),
            ':name' => $dto->name(),
            ':email' => $dto->email(),
            ':password' => $dto->password(),
            ':role' => $dto->role(),
            ':status' => $dto->status(),
        ));

        $savedUser = $this->getById(new UserId($dto->id()));

        if ($savedUser === null) {
            throw new RuntimeException('The user could not be recovered after save.');
        }

        return $savedUser;
    }

    public function update(UserModel $user): UserModel
    {
        $this->pdo->beginTransaction();
        try {
            $activeAdministratorIds = $this->lockActiveAdministratorIds();
            $currentlyActiveAdministrator = in_array($user->id()->value(), $activeAdministratorIds, true);
            $willRemainActiveAdministrator = $user->role() === 'ADMIN' && $user->status() === 'ACTIVE';
            RolePermissions::assertActiveAdministratorInvariant(
                $currentlyActiveAdministrator,
                $willRemainActiveAdministrator,
                count($activeAdministratorIds)
            );

            $currentStatement = $this->pdo->prepare(
                'SELECT email, password, role, status, authorization_version FROM users WHERE id = :id FOR UPDATE'
            );
            $currentStatement->execute(array(':id' => $user->id()->value()));
            $current = $currentStatement->fetch();
            if ($current === false) {
                throw new RuntimeException('The user could not be recovered before update.');
            }
            $securityChanged = (string) $current['email'] !== $user->email()->value()
                || (string) $current['password'] !== $user->password()->value()
                || (string) $current['role'] !== $user->role()
                || (string) $current['status'] !== $user->status();
            $authorizationVersion = (int) $current['authorization_version'] + ($securityChanged ? 1 : 0);

            $dto = $this->mapper->fromModelToDto($user);
            $sql = '
                UPDATE users
                   SET name = :name,
                       email = :email,
                       password = :password,
                       role = :role,
                       status = :status,
                       authorization_version = :authorization_version,
                       updated_at = NOW()
                 WHERE id = :id
            ';
            $statement = $this->pdo->prepare($sql);
            $statement->execute(array(
                ':id' => $dto->id(),
                ':name' => $dto->name(),
                ':email' => $dto->email(),
                ':password' => $dto->password(),
                ':role' => $dto->role(),
                ':status' => $dto->status(),
                ':authorization_version' => $authorizationVersion,
            ));

            $updatedUser = $this->getById(new UserId($dto->id()));
            if ($updatedUser === null) {
                throw new RuntimeException('The user could not be recovered after update.');
            }

            $this->pdo->commit();
            return $updatedUser;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getById(UserId $userId): ?UserModel
    {
        $sql = '
            SELECT
                id,
                name,
                email,
                password,
                role,
                status,
                authorization_version,
                created_at,
                updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array(
            ':id' => $userId->value(),
        ));

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapper->fromRowToModel($row);
    }

    public function getByEmail(UserEmail $email): ?UserModel
    {
        $sql = '
            SELECT
                id,
                name,
                email,
                password,
                role,
                status,
                authorization_version,
                created_at,
                updated_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array(
            ':email' => $email->value(),
        ));

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapper->fromRowToModel($row);
    }

    /**
     * @return UserModel[]
     */
    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $sql = '
            SELECT
                id,
                name,
                email,
                password,
                role,
                status,
                authorization_version,
                created_at,
                updated_at
            FROM users
            ORDER BY name ASC
            LIMIT :limit OFFSET :offset
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        return $this->mapper->fromRowsToModels($rows);
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function delete(UserId $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $activeAdministratorIds = $this->lockActiveAdministratorIds();
            $currentlyActiveAdministrator = in_array($userId->value(), $activeAdministratorIds, true);
            RolePermissions::assertActiveAdministratorInvariant(
                $currentlyActiveAdministrator,
                false,
                count($activeAdministratorIds)
            );

            $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(array(':id' => $userId->value()));
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function countActive(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'")->fetchColumn();
    }

    /** @return string[] Locks active administrator rows in a consistent order. */
    private function lockActiveAdministratorIds(): array
    {
        $statement = $this->pdo->query(
            "SELECT id FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE' ORDER BY id FOR UPDATE"
        );
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
