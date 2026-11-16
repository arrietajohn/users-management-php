<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Domain/Models/UserModel.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserId.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserName.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserEmail.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserPassword.php';
require_once __DIR__ . '/../Domain/Enums/UserRoleEnum.php';
require_once __DIR__ . '/../Domain/Enums/UserStatusEnum.php';

DependencyInjection::boot();

$name = EnvLoader::get('APP_BOOTSTRAP_ADMIN_NAME');
$email = EnvLoader::get('APP_BOOTSTRAP_ADMIN_EMAIL');
$password = EnvLoader::get('APP_BOOTSTRAP_ADMIN_PASSWORD');

if ($name === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Define APP_BOOTSTRAP_ADMIN_NAME, APP_BOOTSTRAP_ADMIN_EMAIL y APP_BOOTSTRAP_ADMIN_PASSWORD en el entorno.\n");
    exit(2);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "La contraseña inicial debe tener al menos 12 caracteres.\n");
    exit(2);
}

if (strlen($password) > 72) {
    fwrite(STDERR, "La contraseña inicial no puede superar 72 bytes.\n");
    exit(2);
}

$repository = DependencyInjection::getUserRepository();
if ($repository->getByEmail(new UserEmail($email)) !== null) {
    fwrite(STDERR, "Ya existe una cuenta con ese correo; no se hicieron cambios.\n");
    exit(3);
}

$idBytes = random_bytes(16);
$idBytes[6] = chr((ord($idBytes[6]) & 0x0f) | 0x40);
$idBytes[8] = chr((ord($idBytes[8]) & 0x3f) | 0x80);
$id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($idBytes), 4));

$admin = new UserModel(
    new UserId($id),
    new UserName($name),
    new UserEmail($email),
    UserPassword::fromPlainText($password),
    UserRoleEnum::ADMIN,
    UserStatusEnum::ACTIVE
);
$repository->save($admin);

fwrite(STDOUT, "Administrador creado. Retira las variables APP_BOOTSTRAP_ADMIN_* del entorno.\n");
