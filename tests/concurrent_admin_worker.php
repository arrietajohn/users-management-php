<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Common/Uuid.php';
require_once __DIR__ . '/../Domain/Models/UserModel.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserId.php';
require_once __DIR__ . '/../Domain/Enums/UserStatusEnum.php';
require_once __DIR__ . '/../Application/Security/RolePermissions.php';

DependencyInjection::boot();
$pdo = DependencyInjection::getPdo();
$repository = DependencyInjection::getUserRepository();
$mode = $argv[1] ?? '';
$userId = $argv[2] ?? '';

if ($mode === 'hold-and-demote') {
    $readyFile = $argv[3] ?? '';
    $releaseFile = $argv[4] ?? '';
    $pdo->beginTransaction();
    try {
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE' ORDER BY id FOR UPDATE")
            ->fetchAll(PDO::FETCH_COLUMN);
        RolePermissions::assertActiveAdministratorInvariant(true, false, count($admins));
        $update = $pdo->prepare(
            "UPDATE users SET role = 'CONSULTA', authorization_version = authorization_version + 1 WHERE id = :id"
        );
        $update->execute(array(':id' => $userId));
        file_put_contents($readyFile, 'ready');
        $deadline = microtime(true) + 15;
        while (!is_file($releaseFile) && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (!is_file($releaseFile)) {
            throw new RuntimeException('Timed out waiting for the parent test process.');
        }
        $pdo->commit();
        fwrite(STDOUT, "committed\n");
        exit(0);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, get_class($exception) . PHP_EOL);
        exit(2);
    }
}

if ($mode === 'demote-through-repository') {
    $attemptingFile = $argv[3] ?? '';
    try {
        $user = $repository->getById(new UserId($userId));
        if ($user === null) {
            throw new RuntimeException('Concurrent test user was not found.');
        }
        file_put_contents($attemptingFile, 'attempting');
        $repository->update(new UserModel(
            $user->id(), $user->name(), $user->email(), $user->password(), 'CONSULTA', $user->status()
        ));
        fwrite(STDOUT, "updated\n");
        exit(0);
    } catch (DomainException) {
        fwrite(STDOUT, "last-admin-denied\n");
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, get_class($exception) . PHP_EOL);
        exit(2);
    }
}

fwrite(STDERR, "Unknown worker mode.\n");
exit(2);
