<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Repository/RateLimitRepositoryMySQL.php';

DependencyInjection::boot();
$repository = new RateLimitRepositoryMySQL(DependencyInjection::getPdo());
$deleted = $repository->pruneExpired();
fwrite(STDOUT, 'Rate-limit buckets eliminados: ' . $deleted . PHP_EOL);
