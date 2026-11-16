<?php

declare(strict_types=1);

require_once __DIR__ . '/../Application/Security/RolePermissions.php';
require_once __DIR__ . '/../Infrastructure/Entrypoints/Web/Presentation/Security.php';
require_once __DIR__ . '/../Application/Ports/Out/RateLimitPort.php';
require_once __DIR__ . '/../Application/Services/RateLimitService.php';
require_once __DIR__ . '/../Application/Ports/Out/ApiTokenPort.php';
require_once __DIR__ . '/../Application/Services/ApiTokenService.php';
require_once __DIR__ . '/../Application/Services/CreateUserService.php';
require_once __DIR__ . '/../Application/Services/UpdateUserService.php';
require_once __DIR__ . '/../Application/Services/DeleteUserService.php';
require_once __DIR__ . '/../Application/Services/LoginService.php';
require_once __DIR__ . '/../Application/Services/EmailNotificationService.php';
require_once __DIR__ . '/../Application/Services/GetAllUsersService.php';
require_once __DIR__ . '/../Application/Services/Dto/Commands/CreateUserCommand.php';
require_once __DIR__ . '/../Application/Services/Dto/Commands/UpdateUserCommand.php';
require_once __DIR__ . '/../Application/Services/Dto/Commands/DeleteUserCommand.php';
require_once __DIR__ . '/../Application/Services/Dto/Commands/LoginCommand.php';
require_once __DIR__ . '/../Application/Services/Dto/Queries/GetAllUsersQuery.php';
require_once __DIR__ . '/../Application/Ports/Out/SaveUserPort.php';
require_once __DIR__ . '/../Application/Ports/Out/GetUserByEmailPort.php';
require_once __DIR__ . '/../Application/Ports/Out/UpdateUserPort.php';
require_once __DIR__ . '/../Application/Ports/Out/GetUserByIdPort.php';
require_once __DIR__ . '/../Application/Ports/Out/DeleteUserPort.php';
require_once __DIR__ . '/../Application/Ports/Out/GetAllUsersPort.php';
require_once __DIR__ . '/../Application/Ports/Out/EmailSenderPort.php';
require_once __DIR__ . '/../Domain/Models/Celular.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Mapper/CelularPersistenceMapper.php';
require_once __DIR__ . '/../Application/Services/CelularService.php';
require_once __DIR__ . '/../Infrastructure/Entrypoints/Web/Controllers/Mapper/CelularWebMapper.php';
require_once __DIR__ . '/../Common/ClassLoader.php';
ClassLoader::register();

final class InMemoryRateLimitPort implements RateLimitPort
{
    /** @var array<string, int> */
    private array $attempts = array();

    public function consume(string $keyHash, int $maximum, int $windowSeconds): bool
    {
        $this->attempts[$keyHash] = ($this->attempts[$keyHash] ?? 0) + 1;
        return $this->attempts[$keyHash] <= $maximum;
    }

    public function clear(string $keyHash): void
    {
        unset($this->attempts[$keyHash]);
    }
}

final class InMemoryCelularRepository implements CelularRepositoryPort
{
    /** @var array<string, Celular> */
    private array $records = array();

    public function save(Celular $celular): Celular { $this->records[$celular->id()] = $celular; return $celular; }
    public function update(Celular $celular): Celular { $this->records[$celular->id()] = $celular; return $celular; }
    public function getById(string $id): ?Celular { return $this->records[$id] ?? null; }
    public function delete(string $id): void { unset($this->records[$id]); }
    public function search(CelularQuery $query): array
    {
        $items = array_values($this->records);
        return array('items' => $items, 'total' => count($items), 'page' => 1);
    }
    public function dashboard(): array
    {
        return array('total' => count($this->records), 'byBrand' => array(), 'byOs' => array(), 'features' => array(), 'recent' => array_values($this->records));
    }
}

final class InMemoryApiTokenPort implements ApiTokenPort
{
    public ?string $storedHash = null;
    public int $authenticateCalls = 0;
    private bool $revoked = false;
    public function issue(string $id, string $userId, string $name, string $tokenHash, string $expiresAt): void { $this->storedHash = $tokenHash; $this->revoked = false; }
    public function revoke(string $id): bool { if ($id !== 'token-id') return false; $this->revoked = true; return true; }
    public function authenticate(string $tokenHash): ?array
    {
        $this->authenticateCalls++;
        return !$this->revoked && $tokenHash === $this->storedHash ? array('id' => 'u1', 'name' => 'Admin', 'role' => 'ADMIN') : null;
    }
}

final class InMemoryUserRepository implements SaveUserPort, GetUserByEmailPort, UpdateUserPort, GetUserByIdPort, DeleteUserPort, GetAllUsersPort
{
    /** @var array<string, UserModel> */
    private array $records = array();
    public function save(UserModel $user): UserModel { $this->records[$user->id()->value()] = $user; return $user; }
    public function getByEmail(UserEmail $email): ?UserModel
    {
        foreach ($this->records as $user) if ($user->email()->equals($email)) return $user;
        return null;
    }
    public function update(UserModel $user): UserModel
    {
        $current = $this->records[$user->id()->value()] ?? null;
        $admins = array_filter($this->records, static fn (UserModel $record): bool => $record->role() === 'ADMIN' && $record->status() === UserStatusEnum::ACTIVE);
        RolePermissions::assertActiveAdministratorInvariant(
            $current !== null && $current->role() === 'ADMIN' && $current->status() === UserStatusEnum::ACTIVE,
            $user->role() === 'ADMIN' && $user->status() === UserStatusEnum::ACTIVE,
            count($admins)
        );
        $this->records[$user->id()->value()] = $user;
        return $user;
    }
    public function getById(UserId $userId): ?UserModel { return $this->records[$userId->value()] ?? null; }
    public function delete(UserId $userId): void
    {
        $current = $this->records[$userId->value()] ?? null;
        $admins = array_filter($this->records, static fn (UserModel $record): bool => $record->role() === 'ADMIN' && $record->status() === UserStatusEnum::ACTIVE);
        RolePermissions::assertActiveAdministratorInvariant(
            $current !== null && $current->role() === 'ADMIN' && $current->status() === UserStatusEnum::ACTIVE,
            false,
            count($admins)
        );
        unset($this->records[$userId->value()]);
    }
    public function getAll(int $limit, int $offset): array { return array_slice(array_values($this->records), $offset, $limit); }
    public function countAll(): int { return count($this->records); }
}

final class InMemoryEmailSender implements EmailSenderPort
{
    /** @var string[] */
    public array $bodies = array();
    public function send(EmailDestinationModel $destination): void { $this->bodies[] = $destination->getBody(); }
}

$tests = 0;
$failures = array();
$assert = static function (bool $condition, string $name) use (&$tests, &$failures): void {
    $tests++;
    if (!$condition) {
        $failures[] = $name;
    }
};

$assert(RolePermissions::allows('ADMIN', 'users.write'), 'admin manages users');
$assert(RolePermissions::allows('SUPERVISOR', 'celulares.delete'), 'supervisor manages celulares');
$assert(RolePermissions::allows('OPERADOR', 'celulares.update'), 'operator updates celulares');
$assert(!RolePermissions::allows('OPERADOR', 'celulares.delete'), 'operator cannot delete celulares');
$assert(!RolePermissions::allows('CONSULTA', 'celulares.update'), 'read-only role cannot update');
$assert(RolePermissions::allows('MEMBER', 'celulares.create'), 'legacy member maps to operator');
$assert(!RolePermissions::allows('REVIEWER', 'celulares.update'), 'legacy reviewer maps to read-only');
$assert(!RolePermissions::allows('UNKNOWN', 'users.write'), 'unknown role is denied');
$assert(strlen(UserPassword::fromPlainText(str_repeat('a', 72))->value()) > 0, 'bcrypt accepts passwords through 72 bytes');
$longPasswordRejected = false;
try {
    UserPassword::fromPlainText(str_repeat('a', 73));
} catch (InvalidUserPasswordException) {
    $longPasswordRejected = true;
}
$assert($longPasswordRejected, 'passwords over bcrypt 72-byte limit are rejected');

session_save_path(sys_get_temp_dir());
Security::startSession();
$token = Security::csrfToken();
$assert(strlen($token) === 64, 'csrf token has sufficient entropy');
$assert(Security::verifyCsrf($token), 'csrf accepts matching token');
$assert(!Security::verifyCsrf(str_repeat('0', 64)), 'csrf rejects a different token');
$assert(!Security::verifyCsrf(null), 'csrf rejects missing token');

$limiterPort = new InMemoryRateLimitPort();
$limiter = new RateLimitService($limiterPort, str_repeat('s', 40));
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $assert($limiter->allow('login:account:test@example.com'), 'rate limiter permits attempt ' . $attempt);
}
$assert(!$limiter->allow('login:account:test@example.com'), 'rate limiter rejects attempt above limit');
$assert($limiter->allow('login:account:other@example.com'), 'rate limiter scopes separate accounts');
$assert($limiter->allow('api:test', 2, 60) && $limiter->allow('api:test', 2, 60), 'API rate policy permits requests within its configured quota');
$assert(!$limiter->allow('api:test', 2, 60), 'API rate policy rejects requests above its configured quota');
$limiter->clear('login:account:test@example.com');
$assert($limiter->allow('login:account:test@example.com'), 'successful authentication can reset its rate bucket');

$apiTokenPort = new InMemoryApiTokenPort();
$apiTokenService = new ApiTokenService($apiTokenPort);
$apiToken = $apiTokenService->issue('token-id', 'u1', 'test integration');
$assert((bool) preg_match('/^cat_[a-f0-9]{64}$/D', $apiToken), 'API token has a high-entropy format');
$assert($apiTokenPort->storedHash === hash('sha256', $apiToken), 'only a token hash is persisted');
$assert($apiTokenService->authenticate('invalid') === null && $apiTokenPort->authenticateCalls === 0, 'malformed API token is rejected before storage lookup');
$assert(($apiTokenService->authenticate($apiToken)['id'] ?? null) === 'u1', 'valid API token resolves an active identity');
$assert($apiTokenService->revoke('token-id'), 'API token can be revoked');
$assert($apiTokenService->authenticate($apiToken) === null, 'revoked API token can no longer authenticate');

$sessionUser = new UserModel(
    new UserId('session-user'), new UserName('Session User'), new UserEmail('session@example.test'),
    UserPassword::fromPlainText('session-password-123'), 'OPERADOR', UserStatusEnum::ACTIVE
);
$sessionFingerprint = RolePermissions::sessionFingerprint($sessionUser);
$assert(RolePermissions::sessionIsCurrent($sessionUser, $sessionFingerprint), 'active unchanged account keeps its session');
$changedRoleUser = new UserModel(
    $sessionUser->id(), $sessionUser->name(), $sessionUser->email(), $sessionUser->password(), 'CONSULTA', UserStatusEnum::ACTIVE
);
$assert(!RolePermissions::sessionIsCurrent($changedRoleUser, $sessionFingerprint), 'role change invalidates an existing session');
$disabledSessionUser = new UserModel(
    $sessionUser->id(), $sessionUser->name(), $sessionUser->email(), $sessionUser->password(), 'OPERADOR', UserStatusEnum::INACTIVE
);
$assert(!RolePermissions::sessionIsCurrent($disabledSessionUser, $sessionFingerprint), 'deactivated account invalidates an existing session');
$assert(!RolePermissions::sessionIsCurrent($sessionUser, ''), 'missing session fingerprint is revoked');
$assert(!RolePermissions::sessionIsCurrent(null, $sessionFingerprint), 'deleted account revokes its existing session');
$reactivatedSessionUser = new UserModel(
    $sessionUser->id(), $sessionUser->name(), $sessionUser->email(), $sessionUser->password(), 'OPERADOR', UserStatusEnum::ACTIVE,
    $sessionUser->authorizationVersion() + 1
);
$assert(!RolePermissions::sessionIsCurrent($reactivatedSessionUser, $sessionFingerprint), 'monotonic authorization version revokes session after account state is restored');
$changedPasswordUser = new UserModel(
    $sessionUser->id(), $sessionUser->name(), $sessionUser->email(), UserPassword::fromPlainText('new-session-password-123'), 'OPERADOR', UserStatusEnum::ACTIVE
);
$assert(!RolePermissions::sessionIsCurrent($changedPasswordUser, $sessionFingerprint), 'password reset revokes existing sessions');
$assert(!array_key_exists('password', $sessionUser->toArray()), 'domain user serialization omits password hash');
$assert(!array_key_exists('password', UserApplicationMapper::fromModelToArray($sessionUser)), 'application user mapping omits password hash');

$adminRepository = new InMemoryUserRepository();
$admin = new UserModel(
    new UserId('only-admin'), new UserName('Only Admin'), new UserEmail('admin@example.test'),
    UserPassword::fromPlainText('admin-password-123'), 'ADMIN', UserStatusEnum::ACTIVE
);
$adminRepository->save($admin);
$adminNotification = new EmailNotificationService(new InMemoryEmailSender());
$lastAdminDeleteRejected = false;
try {
    (new DeleteUserService($adminRepository, $adminRepository))->execute(new DeleteUserCommand('only-admin'));
} catch (DomainException) {
    $lastAdminDeleteRejected = true;
}
$assert($lastAdminDeleteRejected && $adminRepository->getById($admin->id()) !== null, 'last active administrator cannot be deleted');
$adminUpdater = new UpdateUserService($adminRepository, $adminRepository, $adminRepository, $adminNotification);
$lastAdminDeactivationRejected = false;
try {
    $adminUpdater->execute(new UpdateUserCommand('only-admin', 'Only Admin', 'admin@example.test', '', 'ADMIN', UserStatusEnum::INACTIVE));
} catch (DomainException) {
    $lastAdminDeactivationRejected = true;
}
$assert($lastAdminDeactivationRejected, 'last active administrator cannot be deactivated');
$lastAdminDemotionRejected = false;
try {
    $adminUpdater->execute(new UpdateUserCommand('only-admin', 'Only Admin', 'admin@example.test', '', 'CONSULTA', UserStatusEnum::ACTIVE));
} catch (DomainException) {
    $lastAdminDemotionRejected = true;
}
$assert($lastAdminDemotionRejected, 'last active administrator cannot be demoted');
$adminRepository->save(new UserModel(
    new UserId('second-admin'), new UserName('Second Admin'), new UserEmail('admin2@example.test'),
    UserPassword::fromPlainText('admin-password-456'), 'ADMIN', UserStatusEnum::ACTIVE
));
$adminUpdater->execute(new UpdateUserCommand('only-admin', 'Only Admin', 'admin@example.test', '', 'CONSULTA', UserStatusEnum::ACTIVE));
$assert($adminRepository->getById($admin->id())->role() === 'CONSULTA', 'administrator can be demoted when another active administrator remains');

$userRepository = new InMemoryUserRepository();
$emailSender = new InMemoryEmailSender();
$notifications = new EmailNotificationService($emailSender);
$createUserService = new CreateUserService($userRepository, $userRepository, $notifications);
$userCommand = new CreateUserCommand('user-1', 'Test Operator', 'operator@example.test', 'test-password-123', 'OPERADOR');
$createdUser = $createUserService->execute($userCommand);
$assert($createdUser->status() === UserStatusEnum::PENDING, 'new user requires activation');
$assert($createdUser->password()->verifyPlain('test-password-123'), 'created user password is hashed and verifies');
$assert(!str_contains($emailSender->bodies[0], 'test-password-123'), 'account email never contains a plain password');
$duplicateRejected = false;
try {
    $createUserService->execute(new CreateUserCommand('user-2', 'Other Operator', 'operator@example.test', 'other-password-123', 'OPERADOR'));
} catch (UserAlreadyExistsException) {
    $duplicateRejected = true;
}
$assert($duplicateRejected, 'duplicate email is rejected by the user use case');

$updateUserService = new UpdateUserService($userRepository, $userRepository, $userRepository, $notifications);
$updatedUser = $updateUserService->execute(new UpdateUserCommand('user-1', 'Updated Operator', 'operator@example.test', '', 'CONSULTA', UserStatusEnum::ACTIVE));
$assert($updatedUser->name()->value() === 'Updated Operator' && $updatedUser->role() === 'CONSULTA', 'user update changes profile and role');
$assert($updatedUser->password()->verifyPlain('test-password-123'), 'blank update password preserves current password hash');

$loginService = new LoginService($userRepository);
$loggedUser = $loginService->execute(new LoginCommand('operator@example.test', 'test-password-123'));
$assert($loggedUser->id()->value() === 'user-1', 'active user can log in');
$badLoginRejected = false;
try {
    $loginService->execute(new LoginCommand('operator@example.test', 'wrong-password'));
} catch (InvalidCredentialsException) {
    $badLoginRejected = true;
}
$assert($badLoginRejected, 'invalid password cannot authenticate');

$userPage = (new GetAllUsersService($userRepository))->execute(new GetAllUsersQuery(1, 1));
$assert($userPage->total() === 1 && count($userPage->users()) === 1, 'user query returns a bounded page');
(new DeleteUserService($userRepository, $userRepository))->execute(new DeleteUserCommand('user-1'));
$assert($userRepository->countAll() === 0, 'delete use case removes existing user');

$makeImei = static function (string $first14): string {
    $sum = 0;
    for ($index = 0; $index < 14; $index++) {
        $digit = (int) $first14[$index];
        if ($index % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return $first14 . ((10 - ($sum % 10)) % 10);
};
$validImei2 = $makeImei('35693803564380');
$celular = new Celular(
    'device-1', 'Acme', array('490154203237518'), '6.70', '50.00', 8, 256, 0,
    'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, true, 1
);
$businessAttributes = $celular->toArray();
unset($businessAttributes['id']);
$assert(count($businessAttributes) === 20, 'Celular exposes the exact 20 required business attributes');
$persistenceMapper = new CelularPersistenceMapper();
$celularRow = $persistenceMapper->toRow($celular);
$expectedCelularColumns = array(
    'id', 'marca', 'pulgadas', 'megapixeles', 'ram', 'almacenamiento_principal',
    'almacenamiento_secundario', 'sistema_operativo', 'operador', 'tecnologia_banda',
    'wifi', 'bluetooth', 'camaras', 'marca_cpu', 'velocidad_cpu', 'nfc', 'huella', 'ir',
    'resiste_agua', 'cantidad_sim',
);
$assert(array_keys($celularRow) === $expectedCelularColumns, 'persistence mapper emits every SQL column for the 20 attributes and ID');
$assert($celularRow['resiste_agua'] === true && !array_key_exists('resisteAgua', $celularRow), 'water resistance maps to the SQL snake_case column');
$assert($persistenceMapper->fromRow($celularRow, $celular->imei())->toArray() === $celular->toArray(), 'persistence mapper round-trips all 20 attributes and types');
$assert($celular->imei() === array('490154203237518'), 'single-SIM phone stores one IMEI as text');
$dualSim = new Celular(
    'device-2', 'Acme', array('490154203237518', $validImei2), '6.70', '50', 8, 256, 0,
    'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, true, 2
);
$assert(count($dualSim->imei()) === 2, 'dual-SIM phone stores two IMEIs');
$assert($celular->pulgadas() === '6.70', 'decimal values normalize without floating precision');
$maxDecimalCelular = new Celular(
    'device-max', 'Acme', array('490154203237518'), '100.00', '100000.00', 8, 256, 0,
    'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '1000.00', true, true, false, true, 1
);
$assert($maxDecimalCelular->megapixeles() === '100000.00' && $maxDecimalCelular->velocidadCpu() === '1000.00', 'domain accepts documented decimal maxima representable by expanded MySQL precision');

$invalidImeiRejected = false;
try {
    new Celular(
        'device-3', 'Acme', array('123456789012345'), '6.70', '50', 8, 256, 0,
        'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, true, 1
    );
} catch (InvalidCelularException) {
    $invalidImeiRejected = true;
}
$assert($invalidImeiRejected, 'IMEI check digit validation rejects invalid values');

$simMismatchRejected = false;
try {
    new Celular(
        'device-4', 'Acme', array('490154203237518'), '6.70', '50', 8, 256, 0,
        'Android', 'Libre', '5G', true, true, 3, 'Qualcomm', '3.20', true, true, false, true, 2
    );
} catch (InvalidCelularException) {
    $simMismatchRejected = true;
}
$assert($simMismatchRejected, 'IMEI count must agree with SIM count');

$formMapper = new CelularWebMapper();
$trueQuery = $formMapper->fromInputToQuery(array('wifi' => true, 'nfc' => 'TRUE'));
$falseQuery = $formMapper->fromInputToQuery(array('wifi' => false, 'nfc' => 'false'));
$assert($trueQuery->features()['wifi'] === true && $trueQuery->features()['nfc'] === true, 'API feature filters accept JSON and textual true values');
$assert($falseQuery->features()['wifi'] === false && $falseQuery->features()['nfc'] === false, 'API feature filters accept JSON and textual false values');
$formCommand = $formMapper->fromInputToCommand(array(
    'marca' => ' Acme ', 'imei' => array('490154203237518', ''), 'pulgadas' => '6.7', 'megapixeles' => '50',
    'ram' => '8', 'almacenamientoPrincipal' => '256', 'almacenamientoSecundario' => '0',
    'sistemaOperativo' => 'Android', 'operador' => 'Libre', 'tecnologiaBanda' => '5G',
    'wifi' => '1', 'bluetooth' => '0', 'camaras' => '3', 'marcaCpu' => 'Qualcomm',
    'velocidadCpu' => '3.2', 'nfc' => '1', 'huella' => '1', 'ir' => '0', 'resisteAgua' => '1', 'cantidadSim' => '1',
));
$serviceRepository = new InMemoryCelularRepository();
$celularService = new CelularService($serviceRepository);
$created = $celularService->create('service-device', $formCommand);
$assert($created->marca() === 'Acme', 'web mapper trims submitted values');
$assert($created->wifi() && !$created->bluetooth(), 'web mapper handles boolean fields explicitly');
$assert(count($created->imei()) === 1, 'web mapper ignores a blank optional second IMEI');
$assert($celularService->get('service-device')->id() === 'service-device', 'application service loads created entity');
$jsonCommand = $formMapper->fromInputToCommand(array(
    'marca' => 'API phone', 'imei' => array('490154203237518'), 'pulgadas' => '6.7', 'megapixeles' => '50',
    'ram' => 8, 'almacenamientoPrincipal' => 256, 'almacenamientoSecundario' => 0, 'sistemaOperativo' => 'Android',
    'operador' => 'Libre', 'tecnologiaBanda' => '5G', 'wifi' => true, 'bluetooth' => false, 'camaras' => 3,
    'marcaCpu' => 'Qualcomm', 'velocidadCpu' => '3.2', 'nfc' => false, 'huella' => true, 'ir' => false,
    'resisteAgua' => true, 'cantidadSim' => 1,
));
$jsonCelular = $celularService->create('api-device', $jsonCommand);
$assert($jsonCelular->wifi() && !$jsonCelular->bluetooth() && !$jsonCelular->nfc(), 'API JSON booleans map without string coercion bugs');
$celularService->delete('api-device');
$updated = $celularService->update('service-device', $formCommand);
$assert($updated->almacenamientoPrincipal() === 256, 'application service updates existing entity');
$celularService->delete('service-device');
$assert($serviceRepository->getById('service-device') === null, 'application service deletes existing entity');

foreach (array(
    'CelularController', 'CelularRepositoryMySQL', 'CelularPersistenceMapper', 'DashboardService',
    'ActivityLogRepositoryMySQL', 'PasswordResetRepositoryMySQL', 'RateLimitRepositoryMySQL',
    'ApiTokenRepositoryMySQL', 'ApiRouter', 'UserResponsePage', 'MigrationRunner'
) as $className) {
    if ($className === 'MigrationRunner') {
        require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Config/MigrationRunner.php';
    } else {
        ClassLoader::loadClass($className);
    }
    $assert(class_exists($className), 'autoloads ' . $className);
}

session_destroy();

if ($failures !== array()) {
    fwrite(STDERR, 'FAILED ' . count($failures) . ' of ' . $tests . " assertions:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'OK ' . $tests . " assertions\n");
