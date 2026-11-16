<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Config/MigrationRunner.php';

try {
    DependencyInjection::boot();
    $appEnvironment = strtolower(EnvLoader::get('APP_ENV', 'development'));
    if ($appEnvironment === 'production' && EnvLoader::get('MIGRATIONS_ALLOW_PRODUCTION') !== '1') {
        throw new RuntimeException(
            'Las migraciones de producción requieren MIGRATIONS_ALLOW_PRODUCTION=1 tras verificar el destino y el respaldo.'
        );
    }

    $runner = new MigrationRunner(DependencyInjection::getPdo());
    $applied = $runner->migrate(__DIR__ . '/../Infrastructure/Adapters/Persistence/MySQL/Migrations');
    fwrite(STDOUT, $applied === array()
        ? "La base ya está actualizada.\n"
        : 'Migraciones aplicadas: ' . implode(', ', $applied) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migración detenida: ' . get_class($exception) . ". Verifica precondiciones y copia de seguridad.\n");
    if (getenv('CI') === 'true') {
        $diagnostic = $exception->getMessage();
        foreach (array('DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_SECRET') as $key) {
            $value = getenv($key);
            if (!is_string($value) || $value === '') {
                continue;
            }
            if ($key === 'DB_USERNAME') {
                $diagnostic = str_replace(array("'{$value}'", "\"{$value}\""), '[redacted]', $diagnostic);
            }
            if (strlen($value) >= 4) {
                $diagnostic = str_replace($value, '[redacted]', $diagnostic);
            }
        }

        $sqlState = $exception instanceof PDOException
            ? ($exception->errorInfo[0] ?? $exception->getCode())
            : $exception->getCode();
        $driverCode = $exception instanceof PDOException
            ? ($exception->errorInfo[1] ?? 'unknown')
            : 'n/a';
        fwrite(STDERR, sprintf(
            "Diagnóstico CI: SQLSTATE %s, código de controlador %s, %s en %s:%d\n",
            (string) $sqlState,
            (string) $driverCode,
            $diagnostic,
            basename($exception->getFile()),
            $exception->getLine()
        ));
    }
    exit(1);
}
