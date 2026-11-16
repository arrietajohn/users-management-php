# Móvil Admin

Plataforma web para administrar usuarios y un catálogo técnico de celulares. Este proyecto evoluciona el CRUD PHP original del ejercicio universitario 11 — Celular, conservando su arquitectura hexagonal, objetos de dominio, casos de uso, puertos y adaptadores.

## Estado y alcance

- CRUD de usuarios con páginas de hasta 20 registros y administración restringida al rol `ADMIN`.
- CRUD de celulares con los 20 atributos del ejercicio, filtros, búsqueda, ordenamiento y paginación de hasta 100 registros.
- Dashboard con métricas calculadas desde MySQL, distribución por marca y sistema operativo, equipos recientes y actividad visible para administración/supervisión.
- API REST `/api/v1` con tokens Bearer hasheados, vigencia, permisos por rol y respuestas JSON uniformes.
- Roles `ADMIN`, `SUPERVISOR`, `OPERADOR` y `CONSULTA`. Los roles legados `MEMBER` y `REVIEWER` se interpretan como `OPERADOR` y `CONSULTA`.
- Protección CSRF en formularios, cookies HttpOnly/SameSite, sesiones estrictas con expiración y regeneración, versión monotónica para revocar sesiones tras cambios de seguridad, límites persistentes para autenticación/API, contraseñas bcrypt de máximo 72 bytes y restablecimiento por token temporal de un solo uso.
- Diseño adaptable con HTML, CSS y JavaScript sin framework frontend.

## Arquitectura

- `Domain`: entidades, Value Objects y reglas de negocio sin dependencias de HTTP o PDO.
- `Application`: servicios, Commands, Queries y puertos.
- `Infrastructure`: controladores web/API, correo y adaptadores MySQL.
- `Common`: carga de clases, configuración y composición manual.
- `public/index.php`: front controller web/API.

Las lecturas usan Queries y las escrituras Commands; web y API llaman los mismos casos de uso. Ver [decisiones de arquitectura](docs/architecture.md) y [modelo de datos](docs/data-model.md).

## Requisitos

- PHP 8.3 o posterior.
- MySQL 8.0.16 o posterior (la migración utiliza restricciones `CHECK`).
- Extensiones PHP: `PDO`, `pdo_mysql`, `session`, `openssl`, `mbstring`, `filter`, `json`.
- Apache con `mod_rewrite` y `AllowOverride All`, o Docker Compose.
- SMTP configurado para notificaciones. PHPMailer 6.9.1 está incluido en `Infrastructure/Adapters/Email/PHPMailer`.

## Configuración local (Laragon/Apache)

1. Copia `.env.example` a `.env` y ajusta `APP_ENV`, la conexión MySQL, `APP_BASE_URL`, `APP_SECRET` y SMTP. Genera un secreto propio de al menos 32 caracteres para `APP_SECRET`; no uses el valor de ejemplo.
2. Crea/selecciona la base de datos indicada por `DB_DATABASE` y confirma que el usuario de MySQL puede crear tablas e índices.
3. Revisa los datos existentes y respalda la base antes de migrar. La migración convierte roles legados, crea tablas y añade unicidad de email; fallará si ya hay emails duplicados.
4. Desde la raíz, ejecuta `php bin/migrate.php`. No se ejecuta automáticamente al abrir la aplicación. En `APP_ENV=production`, habilita explícitamente `MIGRATIONS_ALLOW_PRODUCTION=1` solo después de verificar destino y respaldo. Esto aplica también las migraciones `002_authorization_version.sql` y `003_celular_decimal_precision.sql`.
5. Crea el primer administrador de forma interactiva por variables de entorno y CLI:

   ```powershell
   $env:APP_BOOTSTRAP_ADMIN_NAME = 'Administración'
   $env:APP_BOOTSTRAP_ADMIN_EMAIL = 'admin@example.com'
   $env:APP_BOOTSTRAP_ADMIN_PASSWORD = 'una-frase-secreta-larga'
   php bin/create-admin.php
   Remove-Item Env:APP_BOOTSTRAP_ADMIN_NAME, Env:APP_BOOTSTRAP_ADMIN_EMAIL, Env:APP_BOOTSTRAP_ADMIN_PASSWORD
   ```

6. Configura Apache para servir la raíz del repositorio con el `.htaccess` existente. El front controller es `public/index.php`.

El alta pública no permite elegir rol ni crear cuentas. Solo administración crea usuarios; los usuarios nuevos quedan `PENDING` hasta que un administrador los active.

## Docker

```sh
cp .env.example .env
# Cambia APP_SECRET y DB_ROOT_PASSWORD; define credenciales SMTP si usarás correo.
docker compose up --build -d
docker compose exec web php bin/migrate.php
```

La aplicación queda en `http://localhost:8080`. La base usa volumen persistente `mysql_data`. Las migraciones se ejecutan de forma explícita y no borran datos automáticamente. Detener los servicios: `docker compose down` (no uses `-v` si quieres conservar la base).

Los límites del API son 300 solicitudes por IP y minuto y 120 por identidad y minuto. El cliente IP se toma de `REMOTE_ADDR`; detrás de un proxy, configura el webserver para confiar solo en proxies controlados y no aceptes `X-Forwarded-For` sin validación. Limpia periódicamente cuotas viejas con `php bin/cleanup-rate-limits.php`.

## Celular: reglas y datos

Los atributos de negocio, con sus nombres requeridos, son `marca`, `imei`, `pulgadas`, `megapixeles`, `ram`, `almacenamientoPrincipal`, `almacenamientoSecundario`, `sistemaOperativo`, `operador`, `tecnologiaBanda`, `wifi`, `bluetooth`, `camaras`, `marcaCpu`, `velocidadCpu`, `nfc`, `huella`, `ir`, `resisteAgua` y `cantidadSim`. `id` es adicional. No se crea una relación celular-usuario porque el ejercicio no la exige.

`imei` es una colección de uno o dos IMEI, uno por ranura SIM, y se guarda en `celular_imeis`. Cada IMEI debe tener 15 dígitos y pasar Luhn; es único globalmente porque identifica una radio/ranura concreta. Un equipo dual SIM mantiene ambos identificadores relacionados con el mismo celular. `cantidadSim` debe coincidir con la cantidad de IMEI. `pulgadas`, `megapixeles` y `velocidadCpu` usan decimales de dos posiciones; memorias se expresan en GB. El almacenamiento secundario acepta cero cuando no existe.

## Roles

| Rol | Usuarios | Celulares | Dashboard | Actividad |
|---|---|---|---|---|
| `ADMIN` | Crear, consultar, editar, eliminar y asignar roles | CRUD completo | Sí | Sí |
| `SUPERVISOR` | Consultar | CRUD completo | Sí | Sí |
| `OPERADOR` | Sin acceso | Crear, consultar y editar | Sí | No |
| `CONSULTA` | Sin acceso | Solo consulta | Sí | No |

La autorización se comprueba en el servidor tanto para web como para API.

## API

Todos los endpoints requieren `Authorization: Bearer <token>` de una cuenta `ACTIVE`. Emite un token de 90 días (o vigencia de 1 a 365 días) y guárdalo de forma segura; solo se almacena su hash SHA-256:

```sh
php bin/create-api-token.php admin@example.com "integracion-reportes" 90
php bin/revoke-api-token.php <id-del-token>
```

El token completo se muestra una sola vez. No lo incluyas en logs, repositorios ni URLs. CORS está desactivado; configura un proxy/API gateway antes de habilitarlo para un origen concreto.

Base: `/api/v1`

| Método | Endpoint | Permiso |
|---|---|---|
| GET | `/celulares` | `celulares.read` |
| GET | `/celulares/{id}` | `celulares.read` |
| POST | `/celulares` | `celulares.create` |
| PUT | `/celulares/{id}` | `celulares.update` |
| DELETE | `/celulares/{id}` | `celulares.delete` |
| GET | `/users?page=1&per_page=20` | `users.read` |
| GET | `/users/{id}` | `users.read` |
| POST | `/users` | `users.write` |
| PUT | `/users/{id}` | `users.write` |
| DELETE | `/users/{id}` | `users.write` |

Listados responden `{ "data": [], "meta": { "total": 0, "page": 1, "per_page": 20, "pages": 1 } }`. Errores responden `{ "error": { "code": "...", "message": "..." } }`. Para crear/editar envía JSON; las propiedades de Celular usan los 20 nombres camelCase anteriores y `imei` es un arreglo. Revisa [OpenAPI](docs/openapi.yaml) y la colección [Postman](docs/postman_collection.json).

## Pruebas y verificación

```sh
php tests/run.php
```

La suite unitaria usa doubles en memoria para servicios, repositorios, tokens y límites; no sustituye pruebas MySQL/HTTP. La suite de integración incluye persistencia, limpieza de cuotas y una prueba concurrente con dos procesos/conexiones, pero solo debe ejecutarse en una base desechable (`RUN_DB_INTEGRATION=1 php tests/integration.php`). Para validar sintaxis en Linux: `find . -name '*.php' -not -path './Infrastructure/Adapters/Email/PHPMailer/*' -print0 | xargs -0 -n1 php -l`. El workflow declara lint, pruebas, migraciones, MySQL y Docker; consulta la ejecución del commit para conocer sus resultados reales.

La operación segura y rollback están en [guía de operaciones](docs/operations.md); los controles y límites comprobados están en [seguridad](docs/security.md). No hay despliegue público configurado; no se inventa una URL.
