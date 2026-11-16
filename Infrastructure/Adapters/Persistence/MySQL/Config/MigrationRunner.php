<?php

declare(strict_types=1);

final class MigrationRunner
{
    public function __construct(private PDO $pdo)
    {
    }

    public function migrate(string $directory): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: array();
        sort($files, SORT_STRING);
        $applied = array();
        $hasVersion = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
        $markVersion = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            try {
                $hasVersion->execute(array(':version' => $version));
                $alreadyApplied = $hasVersion->fetchColumn() !== false;
            } finally {
                $hasVersion->closeCursor();
            }
            if ($alreadyApplied) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('No se pudo leer una migración.');
            }

            foreach ($this->splitStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }

            try {
                $markVersion->execute(array(':version' => $version));
            } finally {
                $markVersion->closeCursor();
            }
            $applied[] = $version;
        }

        return $applied;
    }

    /** @return string[] */
    private function splitStatements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*(?:--|#).*$/m', '', $sql) ?? $sql;
        $parts = explode(';', $withoutComments);

        return array_values(array_filter(array_map('trim', $parts), static function (string $part): bool {
            return $part !== '';
        }));
    }
}
