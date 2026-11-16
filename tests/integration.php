<?php

declare(strict_types=1);

if (getenv('RUN_DB_INTEGRATION') !== '1') {
    fwrite(STDERR, "Define RUN_DB_INTEGRATION=1 solo para una base de datos descartable.\n");
    exit(2);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Common/Uuid.php';
require_once __DIR__ . '/../Domain/Models/Celular.php';
require_once __DIR__ . '/../Domain/Models/UserModel.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserId.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserName.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserEmail.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserPassword.php';
require_once __DIR__ . '/../Domain/Enums/UserRoleEnum.php';
require_once __DIR__ . '/../Domain/Enums/UserStatusEnum.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Mapper/CelularPersistenceMapper.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/CelularRepositoryMySQL.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/PasswordResetRepositoryMySQL.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/RateLimitRepositoryMySQL.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/ApiTokenRepositoryMySQL.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/ActivityLogRepositoryMySQL.php';

DependencyInjection::boot();
$pdo = DependencyInjection::getPdo();
$users = DependencyInjection::getUserRepository();
$celulares = new CelularRepositoryMySQL($pdo, new CelularPersistenceMapper());
$reset = new PasswordResetRepositoryMySQL($pdo);
$limits = new RateLimitRepositoryMySQL($pdo);
$tokens = new ApiTokenRepositoryMySQL($pdo);
$activity = new ActivityLogRepositoryMySQL($pdo);
$userId = Uuid::v4();
$email = 'integration+' . bin2hex(random_bytes(8)) . '@example.test';
$celularId = Uuid::v4();
$imei = '490154203237518';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Integration assertion failed: ' . $message);
    }
};

$userSaved = false;
$celularSaved = false;
$raceAdminSaved = false;
$limitKey = hash('sha256', random_bytes(32));
$expiredLimitKey = hash('sha256', random_bytes(32));
$activeLimitKey = hash('sha256', random_bytes(32));
try {
    $user = new UserModel(
        new UserId($userId),
        new UserName('Integration Admin'),
        new UserEmail($email),
        UserPassword::fromPlainText('integration-password-123'),
        UserRoleEnum::ADMIN,
        UserStatusEnum::ACTIVE
    );
    $users->save($user);
    $userSaved = true;
    $activeAdminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'")->fetchColumn();
    if ($activeAdminCount === 1) {
        $adminUpdateRejected = static function (string $role, string $status) use ($users, $userId, $user): bool {
            try {
                $users->update(new UserModel(
                    new UserId($userId), $user->name(), $user->email(), $user->password(), $role, $status
                ));
                return false;
            } catch (DomainException) {
                return true;
            }
        };
        $assert($adminUpdateRejected('ADMIN', UserStatusEnum::INACTIVE), 'last active administrator cannot be deactivated transactionally');
        $assert($adminUpdateRejected('CONSULTA', UserStatusEnum::ACTIVE), 'last active administrator cannot be demoted transactionally');
        $lastAdminDeleteRejected = false;
        try {
            $users->delete(new UserId($userId));
        } catch (DomainException) {
            $lastAdminDeleteRejected = true;
        }
        $assert($lastAdminDeleteRejected, 'last active administrator cannot be deleted transactionally');
    }
    $assert($users->getByEmail(new UserEmail($email)) !== null, 'user insert and lookup');
    $assert($users->countActive() > 0, 'active user count');
    $assert(count($users->getAll(1, 0)) <= 1 && $users->countAll() > 0, 'bounded user pagination');

    if ($activeAdminCount === 1 && function_exists('proc_open')) {
        $raceAdminId = Uuid::v4();
        $raceAdminEmail = 'integration-race+' . bin2hex(random_bytes(6)) . '@example.test';
        $raceAdmin = new UserModel(
            new UserId($raceAdminId), new UserName('Concurrent Admin'), new UserEmail($raceAdminEmail),
            UserPassword::fromPlainText('concurrent-password-123'), UserRoleEnum::ADMIN, UserStatusEnum::ACTIVE
        );
        $users->save($raceAdmin);
        $raceAdminSaved = true;

        $syncPrefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'admin-race-' . bin2hex(random_bytes(8));
        $readyFile = $syncPrefix . '.ready';
        $attemptingFile = $syncPrefix . '.attempting';
        $releaseFile = $syncPrefix . '.release';
        $workerScript = __DIR__ . '/concurrent_admin_worker.php';
        $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $firstWorker = proc_open(array(PHP_BINARY, $workerScript, 'hold-and-demote', $userId, $readyFile, $releaseFile), $descriptors, $firstPipes);
        if (!is_resource($firstWorker)) {
            throw new RuntimeException('Could not start the first concurrent administrator worker.');
        }
        fclose($firstPipes[0]);
        $readyDeadline = microtime(true) + 10;
        while (!is_file($readyFile) && microtime(true) < $readyDeadline) {
            usleep(10000);
        }
        if (!is_file($readyFile)) {
            file_put_contents($releaseFile, 'release');
            throw new RuntimeException('The first concurrent administrator worker did not acquire its lock.');
        }

        $secondWorker = proc_open(array(PHP_BINARY, $workerScript, 'demote-through-repository', $raceAdminId, $attemptingFile), $descriptors, $secondPipes);
        if (!is_resource($secondWorker)) {
            file_put_contents($releaseFile, 'release');
            throw new RuntimeException('Could not start the second concurrent administrator worker.');
        }
        fclose($secondPipes[0]);
        $attemptDeadline = microtime(true) + 10;
        while (!is_file($attemptingFile) && microtime(true) < $attemptDeadline) {
            usleep(10000);
        }
        usleep(100000);
        $secondWasWaiting = is_file($attemptingFile) && proc_get_status($secondWorker)['running'];
        file_put_contents($releaseFile, 'release');

        $firstOutput = trim((string) stream_get_contents($firstPipes[1]));
        $firstError = trim((string) stream_get_contents($firstPipes[2]));
        fclose($firstPipes[1]);
        fclose($firstPipes[2]);
        $firstExit = proc_close($firstWorker);
        $secondOutput = trim((string) stream_get_contents($secondPipes[1]));
        $secondError = trim((string) stream_get_contents($secondPipes[2]));
        fclose($secondPipes[1]);
        fclose($secondPipes[2]);
        $secondExit = proc_close($secondWorker);
        @unlink($readyFile);
        @unlink($attemptingFile);
        @unlink($releaseFile);

        $assert($firstExit === 0 && $firstOutput === 'committed', 'first MySQL connection commits while holding the administrator lock');
        $assert($secondWasWaiting && $secondExit === 0 && $secondOutput === 'last-admin-denied', 'second MySQL connection waits, then rejects demotion of the last remaining administrator');
        if ($firstError !== '' || $secondError !== '') {
            throw new RuntimeException('Concurrent worker stderr: ' . $firstError . ' ' . $secondError);
        }

        $users->update(new UserModel(
            $user->id(), $user->name(), $user->email(), $user->password(), UserRoleEnum::ADMIN, UserStatusEnum::ACTIVE
        ));
        $users->delete(new UserId($raceAdminId));
        $raceAdminSaved = false;
    }

    $celular = new Celular(
        $celularId, 'Integration Device', array($imei), '6.70', '50.00', 8, 256, 0,
        'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, true, 1
    );
    $celulares->save($celular);
    $celularSaved = true;
    $loaded = $celulares->getById($celularId);
    $assert($loaded !== null && $loaded->toArray() === $celular->toArray(), 'cellphone insert and hydration preserve all 20 attributes and types');
    $prefix = '35693803564380';
    $sum = 0;
    for ($index = 0; $index < 14; $index++) {
        $digit = (int) $prefix[$index];
        if ($index % 2 === 1) { $digit *= 2; if ($digit > 9) $digit -= 9; }
        $sum += $digit;
    }
    $secondImei = $prefix . ((10 - ($sum % 10)) % 10);
    $dualSim = new Celular(
        $celularId, 'Integration Device', array($imei, $secondImei), '6.70', '50.00', 8, 256, 0,
        'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, false, 2
    );
    $celulares->update($dualSim);
    $loadedAfterUpdate = $celulares->getById($celularId);
    $assert($loadedAfterUpdate !== null && $loadedAfterUpdate->toArray() === $dualSim->toArray(), 'update persists and reloads all attributes, including resisteAgua=false');
    $assert(count($loadedAfterUpdate->imei()) === 2, 'transactional dual-SIM update');
    $duplicateRejected = false;
    try {
        $celulares->save(new Celular(
            Uuid::v4(), 'Duplicate Device', array($imei), '6.10', '12.00', 4, 64, 0,
            'Android', 'Libre', '4G', true, true, 2, 'Qualcomm', '2.00', false, false, false, false, 1
        ));
    } catch (PDOException $exception) {
        $duplicateRejected = (string) $exception->getCode() === '23000';
    }
    $assert($duplicateRejected && $celulares->getById($celularId) !== null, 'unique IMEI rejects duplicates without damaging the original');
    $result = $celulares->search(new CelularQuery(search: 'Integration Device', page: 1, perPage: 10));
    $assert($result['total'] === 1 && $result['items'][0]->id() === $celularId, 'cellphone search and pagination');

    $resetToken = bin2hex(random_bytes(32));
    $reset->issue($userId, hash('sha256', $resetToken), gmdate('Y-m-d H:i:s', time() + 600));
    $originalPasswordHash = $user->password()->value();
    $newPasswordHash = UserPassword::fromPlainText('new-integration-password-456')->value();
    $assert($reset->consumeAndReplacePassword(hash('sha256', $resetToken), $newPasswordHash), 'reset token atomically updates password');
    $assert(!$reset->consumeAndReplacePassword(hash('sha256', $resetToken), $newPasswordHash), 'reset token is one time');
    $afterReset = $users->getByEmail(new UserEmail($email));
    $assert($afterReset->password()->verifyPlain('new-integration-password-456'), 'reset password hash verifies');
    $restored = $users->update(new UserModel(
        $afterReset->id(), $afterReset->name(), $afterReset->email(), UserPassword::fromHash($originalPasswordHash),
        $afterReset->role(), $afterReset->status()
    ));
    $assert($restored->authorizationVersion() > $afterReset->authorizationVersion(), 'restoring prior security values still advances the authorization version');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $assert($limits->consume($limitKey, 5, 900), 'rate limit permits attempt ' . $attempt);
    }
    $assert(!$limits->consume($limitKey, 5, 900), 'rate limit blocks attempt above threshold');
    $limits->clear($limitKey);
    $limitFixtures = $pdo->prepare(
        'INSERT INTO auth_rate_limits (scope_key, attempts, window_started_at, blocked_until) VALUES ' .
        '(:expired, 1, UTC_TIMESTAMP() - INTERVAL 2 DAY, NULL), ' .
        '(:active, 120, UTC_TIMESTAMP() - INTERVAL 2 DAY, UTC_TIMESTAMP() + INTERVAL 1 HOUR)'
    );
    $limitFixtures->execute(array(':expired' => $expiredLimitKey, ':active' => $activeLimitKey));
    $limits->pruneExpired(1000);
    $limitState = $pdo->prepare('SELECT COUNT(*) FROM auth_rate_limits WHERE scope_key = :key');
    $limitState->execute(array(':key' => $expiredLimitKey));
    $expiredDeleted = (int) $limitState->fetchColumn() === 0;
    $limitState->execute(array(':key' => $activeLimitKey));
    $blockedRetained = (int) $limitState->fetchColumn() === 1;
    $assert($expiredDeleted && $blockedRetained, 'rate cleanup deletes stale buckets and preserves a currently blocked quota');

    $rawToken = 'cat_' . bin2hex(random_bytes(32));
    $tokenId = Uuid::v4();
    $tokens->issue($tokenId, $userId, 'integration', hash('sha256', $rawToken), gmdate('Y-m-d H:i:s', time() + 600));
    $assert(($tokens->authenticate(hash('sha256', $rawToken))['id'] ?? null) === $userId, 'API token authenticates active account');
    $assert($tokens->revoke($tokenId), 'API token revocation');
    $assert($tokens->authenticate(hash('sha256', $rawToken)) === null, 'revoked API token is rejected');

    $activity->record($userId, 'integration.checked', 'user', $userId);
    $recent = $activity->recent(1);
    $assert(($recent[0]['action'] ?? null) === 'integration.checked', 'activity log write and query');

    $celulares->delete($celularId);
    $celularSaved = false;
    $assert($celulares->getById($celularId) === null, 'cellphone delete');
    fwrite(STDOUT, 'OK ' . $assertions . " MySQL integration assertions\n");
} finally {
    if ($celularSaved) {
        $celulares->delete($celularId);
    }
    if ($raceAdminSaved) {
        $currentFixture = $users->getById(new UserId($userId));
        if ($currentFixture !== null && ($currentFixture->role() !== UserRoleEnum::ADMIN || $currentFixture->status() !== UserStatusEnum::ACTIVE)) {
            $users->update(new UserModel(
                $currentFixture->id(), $currentFixture->name(), $currentFixture->email(), $currentFixture->password(),
                UserRoleEnum::ADMIN, UserStatusEnum::ACTIVE
            ));
        }
        $users->delete(new UserId($raceAdminId));
    }
    if ($userSaved) {
        $remainingAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'")->fetchColumn();
        if ($remainingAdmins > 1) {
            $users->delete(new UserId($userId));
        } else {
            fwrite(STDOUT, "Preserving the sole active administrator fixture in the disposable database.\n");
        }
    }
    $limits->clear($limitKey);
    $pdo->prepare('DELETE FROM auth_rate_limits WHERE scope_key IN (:expired, :active)')->execute(array(
        ':expired' => $expiredLimitKey, ':active' => $activeLimitKey,
    ));
}
