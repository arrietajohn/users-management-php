<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
DependencyInjection::boot();
$id = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($id === '') {
    fwrite(STDERR, "Uso: php bin/revoke-api-token.php <id-del-token>\n");
    exit(2);
}

if (!DependencyInjection::getApiTokenService()->revoke($id)) {
    fwrite(STDERR, "El token no existe o ya estaba revocado.\n");
    exit(3);
}
fwrite(STDOUT, "Token revocado.\n");
