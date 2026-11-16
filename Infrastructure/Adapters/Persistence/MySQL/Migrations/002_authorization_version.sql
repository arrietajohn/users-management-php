-- Monotonic revocation epoch for existing and future account sessions.
SET @has_authorization_version = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'authorization_version'
);
SET @add_authorization_version = IF(
    @has_authorization_version = 0,
    'ALTER TABLE users ADD COLUMN authorization_version BIGINT UNSIGNED NOT NULL DEFAULT 1',
    'SET @migration_noop = 1'
);
PREPARE add_authorization_version FROM @add_authorization_version;
EXECUTE add_authorization_version;
DEALLOCATE PREPARE add_authorization_version;
