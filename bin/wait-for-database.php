<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../Common/DependencyInjection.php';

/** Keep connection diagnostics useful in CI without exposing configuration values. */
$safeMessage = static function (Throwable $exception): string {
    $message = $exception->getMessage();
    foreach (array('DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_SECRET') as $key) {
        $value = getenv($key);
        if (!is_string($value) || $value === '') {
            continue;
        }
        if ($key === 'DB_USERNAME') {
            $message = str_replace(array("'{$value}'", "\"{$value}\""), '[redacted]', $message);
        }
        if (strlen($value) >= 4) {
            $message = str_replace($value, '[redacted]', $message);
        }
    }

    return $message;
};

try {
    DependencyInjection::boot();
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf(
        "No se pudo inicializar la configuración de base de datos (%s): %s\n",
        get_class($exception),
        $safeMessage($exception)
    ));
    exit(1);
}

$timeoutSeconds = 90;
$deadline = microtime(true) + $timeoutSeconds;
$lastError = null;

do {
    try {
        DependencyInjection::getPdo()->query('SELECT 1');
        fwrite(STDOUT, "MySQL está disponible mediante PDO.\n");
        exit(0);
    } catch (PDOException $exception) {
        $lastError = $exception;
        usleep(1_000_000);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf(
            "Falló la comprobación PDO de disponibilidad (%s): %s\n",
            get_class($exception),
            $safeMessage($exception)
        ));
        exit(1);
    }
} while (microtime(true) < $deadline);

$sqlState = $lastError?->errorInfo[0] ?? $lastError?->getCode() ?? 'unknown';
$driverCode = $lastError?->errorInfo[1] ?? 'unknown';
fwrite(STDERR, sprintf(
    "MySQL no respondió a la conexión PDO en %d segundos (SQLSTATE %s, código de controlador %s): %s\n",
    $timeoutSeconds,
    (string) $sqlState,
    (string) $driverCode,
    $safeMessage($lastError)
));
exit(1);
