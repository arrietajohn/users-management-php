# Guía de operación

## Requisitos y configuración

- PHP 8.3+ con `PDO`, `pdo_mysql`, `mbstring`, `session`, `openssl`, `filter` y `json`.
- MySQL 8.0.16+ con InnoDB. La CI declara MySQL 8.4.
- Apache con `mod_rewrite` y `AllowOverride All`, o Docker Compose.
- SMTP es opcional para iniciar la aplicación; sin configuración las notificaciones no se envían.

Copia `.env.example` a `.env` fuera del control de versiones. Define `APP_ENV`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `DB_CHARSET`, `APP_BASE_URL` y `APP_SECRET`. Genera el secreto con `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; no uses un valor de plantilla. SMTP usa `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_ADDRESS` y `SMTP_FROM_NAME`.

Usa una base previamente aprobada para el entorno. Antes de migrar, identifica su propietario, servidor, nombre y política de respaldo. Ejecuta `php bin/migrate.php` solo tras confirmar que el destino es el previsto. Con `APP_ENV=production`, el migrador exige `MIGRATIONS_ALLOW_PRODUCTION=1` como autorización explícita. Las migraciones son explícitas y no se ejecutan desde una petición web.

## Docker Compose

1. Completa las variables obligatorias de `.env`; cambia todos los marcadores.
2. Ejecuta `docker compose config --quiet` para validar la configuración sin imprimir los valores interpolados de secretos.
3. Ejecuta `docker compose up --build -d` y `docker compose ps`.
4. En el contenedor web ejecuta `docker compose exec web php bin/migrate.php` únicamente contra el volumen de desarrollo deseado.
5. Verifica `/public/index.php?route=auth.login` y el estado de salud de ambos servicios.
6. `docker compose down` conserva el volumen. No uses `-v` si necesitas conservar la base.

Este repositorio no contiene credenciales reales ni crea una base cloud. Docker no fue ejecutado en el host auditado; estos pasos son instrucciones, no una declaración de que el entorno está probado.

## Pruebas

```sh
php tests/run.php
```

La suite unitaria corre con dobles en memoria. La integración incluye MySQL, límites persistidos, tokens y una concurrencia coordinada con conexiones separadas. Solo debe ejecutarse en una base aislada y descartable:

```sh
php bin/migrate.php
RUN_DB_INTEGRATION=1 php tests/integration.php
```

En PowerShell define temporalmente `$env:RUN_DB_INTEGRATION = '1'`, ejecuta el script y retira la variable. La prueba puede dejar un administrador fixture cuando protege el último administrador; usa una base descartable.

## Usuarios, API y Postman

Crear el primer administrador requiere `APP_BOOTSTRAP_ADMIN_NAME`, `APP_BOOTSTRAP_ADMIN_EMAIL` y `APP_BOOTSTRAP_ADMIN_PASSWORD` en el entorno de proceso; el CLI exige al menos 12 caracteres. Retira esas variables al terminar.

Emite/revoca tokens con `php bin/create-api-token.php` y `php bin/revoke-api-token.php`. El secreto aparece una sola vez; almacénalo en un secret de Postman y no lo exportes. Importa `docs/postman_collection.json` y `docs/postman_environment.example.json`; agrega el token generado a la variable secreta local.

El API limita a 300 solicitudes por minuto por `REMOTE_ADDR` y 120 por minuto por identidad. Programa `php bin/cleanup-rate-limits.php` de forma periódica: cada ejecución elimina hasta 1000 buckets con ventana vencida hace más de 24 horas y sin bloqueo futuro.

Detrás de proxy, configura el webserver para sustituir `REMOTE_ADDR` solo cuando la conexión proviene de rangos de proxy confiables (por ejemplo, `RemoteIPTrustedProxy` de Apache). No configures el proxy para aceptar sin filtro el `X-Forwarded-For` enviado por clientes.

## Despliegue y rollback

No hay proveedor cloud, URL pública, dominio, cuenta ni servicio de cómputo configurado en este repositorio. Para desplegar se requieren cuenta/proyecto del estudiante, base MySQL administrada, URL/TLS, variables secretas y presupuesto aprobado. No subir `.env` ni copiar credenciales a imágenes, capturas o documentación.

Los rollbacks SQL manuales están bajo `Infrastructure/Adapters/Persistence/MySQL/Migrations/Rollback/`. Antes de rollback, despliega una versión que no requiera el cambio; para estrechar decimales comprueba que los datos no excedan la precisión antigua. Haz respaldo y ensaya sobre una copia desechable.
