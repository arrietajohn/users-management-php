-- Target: MySQL 8.0.16+. Apply once to an existing CRUD Usuarios database.
-- Back up the database and review the legacy role conversion before applying.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Creates the documented baseline on a fresh database; existing users rows are preserved.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy installations may have modeled role as an ENUM; widen it before role conversion.
ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL;

-- Ensure an existing users table also has unique emails, unless one already exists.
SET @has_unique_user_email = (
    SELECT COUNT(*) FROM information_schema.statistics s
    WHERE s.table_schema = DATABASE() AND s.table_name = 'users'
      AND s.column_name = 'email' AND s.non_unique = 0
      AND (SELECT COUNT(*) FROM information_schema.statistics parts
           WHERE parts.table_schema = s.table_schema AND parts.table_name = s.table_name
             AND parts.index_name = s.index_name) = 1
);
SET @add_user_email_index = IF(
    @has_unique_user_email = 0,
    'CREATE UNIQUE INDEX uq_users_email ON users (email)',
    'SET @migration_noop = 1'
);
PREPARE add_user_email_index FROM @add_user_email_index;
EXECUTE add_user_email_index;
DEALLOCATE PREPARE add_user_email_index;

CREATE TABLE IF NOT EXISTS celulares (
    id CHAR(36) NOT NULL PRIMARY KEY,
    marca VARCHAR(80) NOT NULL,
    pulgadas DECIMAL(5,2) NOT NULL,
    megapixeles DECIMAL(7,2) NOT NULL,
    ram SMALLINT UNSIGNED NOT NULL,
    almacenamiento_principal SMALLINT UNSIGNED NOT NULL,
    almacenamiento_secundario SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sistema_operativo VARCHAR(80) NOT NULL,
    operador VARCHAR(80) NOT NULL,
    tecnologia_banda VARCHAR(80) NOT NULL,
    wifi BOOLEAN NOT NULL,
    bluetooth BOOLEAN NOT NULL,
    camaras TINYINT UNSIGNED NOT NULL,
    marca_cpu VARCHAR(80) NOT NULL,
    velocidad_cpu DECIMAL(5,2) NOT NULL,
    nfc BOOLEAN NOT NULL,
    huella BOOLEAN NOT NULL,
    ir BOOLEAN NOT NULL,
    resiste_agua BOOLEAN NOT NULL,
    cantidad_sim TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_celulares_marca (marca),
    INDEX idx_celulares_os (sistema_operativo),
    INDEX idx_celulares_operador (operador),
    INDEX idx_celulares_created (created_at),
    CONSTRAINT chk_celulares_pulgadas CHECK (pulgadas > 0 AND pulgadas <= 100),
    CONSTRAINT chk_celulares_megapixeles CHECK (megapixeles > 0),
    CONSTRAINT chk_celulares_ram CHECK (ram > 0),
    CONSTRAINT chk_celulares_camaras CHECK (camaras > 0),
    CONSTRAINT chk_celulares_velocidad CHECK (velocidad_cpu > 0),
    CONSTRAINT chk_celulares_sim CHECK (cantidad_sim IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per physical IMEI; dual-SIM devices point two unique IMEIs to one celular.
CREATE TABLE IF NOT EXISTS celular_imeis (
    imei VARCHAR(15) NOT NULL PRIMARY KEY,
    celular_id CHAR(36) NOT NULL,
    sim_slot TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_celular_sim_slot (celular_id, sim_slot),
    INDEX idx_celular_imeis_celular (celular_id),
    CONSTRAINT fk_celular_imeis_celular FOREIGN KEY (celular_id)
        REFERENCES celulares (id) ON DELETE CASCADE,
    CONSTRAINT chk_celular_imei_digits CHECK (imei REGEXP '^[0-9]{15}$'),
    CONSTRAINT chk_celular_sim_slot CHECK (sim_slot IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_expiry (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    scope_key CHAR(64) NOT NULL PRIMARY KEY,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_auth_rate_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_tokens (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_tokens_user (user_id),
    INDEX idx_api_tokens_expiry (expires_at),
    CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_user_id VARCHAR(36) NULL,
    action VARCHAR(80) NOT NULL,
    subject_type VARCHAR(40) NOT NULL,
    subject_id VARCHAR(64) NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_created (created_at),
    INDEX idx_activity_actor (actor_user_id),
    CONSTRAINT fk_activity_actor FOREIGN KEY (actor_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve existing accounts while aligning their authorization semantics.
UPDATE users SET role = 'OPERADOR' WHERE role = 'MEMBER';
UPDATE users SET role = 'CONSULTA' WHERE role = 'REVIEWER';
