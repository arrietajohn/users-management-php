<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Common/Uuid.php';
require_once __DIR__ . '/../Domain/ValueObjects/UserEmail.php';
require_once __DIR__ . '/../Domain/Enums/UserStatusEnum.php';

DependencyInjection::boot();
$email = isset($argv[1]) ? trim((string) $argv[1]) : '';
$label = isset($argv[2]) ? trim((string) $argv[2]) : '';
$days = isset($argv[3]) && filter_var($argv[3], FILTER_VALIDATE_INT) !== false ? (int) $argv[3] : 90;
if ($email === '' || $label === '') {
    fwrite(STDERR, "Uso: php bin/create-api-token.php usuario@empresa.com \"integracion\" [dias]\n");
    exit(2);
}

$user = DependencyInjection::getUserRepository()->getByEmail(new UserEmail($email));
if ($user === null || $user->status() !== UserStatusEnum::ACTIVE) {
    fwrite(STDERR, "La cuenta no existe o no está activa.\n");
    exit(3);
}

$tokenId = Uuid::v4();
$token = DependencyInjection::getApiTokenService()->issue($tokenId, $user->id()->value(), $label, $days);
fwrite(STDOUT, "ID para revocación: " . $tokenId . "\nCopia este token ahora; no se vuelve a mostrar:\n" . $token . "\n");
