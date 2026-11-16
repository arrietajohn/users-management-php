# Modelo relacional

Las migraciones `001` a `003` crean el esquema base, agregan una versión monotónica de autorización y amplían la precisión decimal de Celular. `bin/migrate.php` aplica los archivos SQL en orden y registra cada versión; la aplicación web nunca migra por sí sola. Los scripts manuales de rollback están bajo `Migrations/Rollback/` y requieren desplegar primero código que ya no dependa del cambio.

## Relaciones principales

```mermaid
erDiagram
    USERS ||--o{ API_TOKENS : owns
    USERS ||--o{ PASSWORD_RESET_TOKENS : requests
    USERS ||--o{ ACTIVITY_LOGS : performs
    CELULARES ||--|{ CELULAR_IMEIS : identifies
    USERS {
        string id PK
        string email UK
        string role
        string status
        uint authorization_version
    }
    CELULARES {
        string id PK
        decimal megapixeles
        decimal velocidad_cpu
    }
    CELULAR_IMEIS {
        string imei PK
        string celular_id FK
        uint sim_slot
    }
```

### `users`

Se conserva la tabla de usuarios del repositorio. El esquema documenta `id VARCHAR(36)`, `name VARCHAR(120)`, `email VARCHAR(254)`, `password VARCHAR(255)`, `role VARCHAR(20)`, `status VARCHAR(20)`, `authorization_version BIGINT UNSIGNED` y marcas de tiempo. `email` tiene índice único. Al migrar una base previa se preservan las filas, se amplía `role` a texto para permitir los nuevos roles y se convierten `MEMBER` a `OPERADOR` y `REVIEWER` a `CONSULTA`. La versión de autorización avanza ante cambios de email, rol, estado o contraseña.

Antes de migrar una base antigua, comprueba que `users` tenga las columnas que espera el repositorio y resuelve correos duplicados. La creación del índice único se detiene ante duplicados; la migración no elimina cuentas.

### `celulares` y `celular_imeis`

`celulares` almacena los 19 atributos escalares del ejercicio, más el ID y timestamps. El atributo de negocio `imei` se representa mediante filas en `celular_imeis`: `imei` es `VARCHAR(15)` y clave primaria, `celular_id` referencia el equipo y `sim_slot` identifica la posición. Un celular de una SIM tiene un IMEI; uno dual tiene dos IMEI distintos vinculados al mismo ID. No se serializan como CSV/JSON ni se convierten a número.

La unicidad global se aplica al identificador IMEI de cada ranura, porque el IMEI identifica un módem/slot individual; no se fuerza que cada equipo solo tenga un IMEI. El modelo valida el dígito Luhn y que el número de valores coincida con `cantidadSim`.

| Atributo | Columna/representación | Tipo y unidad |
|---|---|---|
| `marca` | `marca` | `VARCHAR(80)` |
| `imei` | `celular_imeis.imei` (relación) | texto de 15 dígitos; 1 o 2 valores |
| `pulgadas` | `pulgadas` | `DECIMAL(5,2)` pulgadas |
| `megapixeles` | `megapixeles` | `DECIMAL(8,2)` MP; máximo de dominio 100000.00 |
| `ram` | `ram` | `SMALLINT UNSIGNED` GB |
| `almacenamientoPrincipal` | `almacenamiento_principal` | `SMALLINT UNSIGNED` GB |
| `almacenamientoSecundario` | `almacenamiento_secundario` | `SMALLINT UNSIGNED` GB; cero significa no disponible |
| `sistemaOperativo` | `sistema_operativo` | `VARCHAR(80)` |
| `operador` | `operador` | `VARCHAR(80)` |
| `tecnologiaBanda` | `tecnologia_banda` | `VARCHAR(80)` |
| `wifi` | `wifi` | `BOOLEAN` |
| `bluetooth` | `bluetooth` | `BOOLEAN` |
| `camaras` | `camaras` | `TINYINT UNSIGNED`, cantidad |
| `marcaCpu` | `marca_cpu` | `VARCHAR(80)` |
| `velocidadCpu` | `velocidad_cpu` | `DECIMAL(6,2)` GHz; máximo de dominio 1000.00 |
| `nfc` | `nfc` | `BOOLEAN` |
| `huella` | `huella` | `BOOLEAN` |
| `ir` | `ir` | `BOOLEAN` |
| `resisteAgua` | `resiste_agua` | `BOOLEAN` |
| `cantidadSim` | `cantidad_sim` | `TINYINT UNSIGNED`, 1 o 2 |

`ON DELETE CASCADE` elimina IMEI al eliminar el equipo. Los índices de marca, sistema operativo, operador y fecha soportan filtros, dashboard y orden más comunes.

### Seguridad y operación

- `password_reset_tokens`: digest SHA-256, usuario, expiración, fecha de uso. El token en claro solo se envía al usuario.
- `auth_rate_limits`: contador por clave HMAC de IP/cuenta y ventana temporal.
- `api_tokens`: digest del token aleatorio, usuario, etiqueta, vencimiento, revocación y último uso.
- `activity_logs`: usuario actor opcional, acción, recurso, ID y detalles JSON controlados (sin contraseñas ni tokens).
- `schema_migrations`: versión y fecha de aplicación.

La tabla `celular_imeis` y las actualizaciones del equipo se escriben en transacción. La auditoría web se registra después de la operación y es de mejor esfuerzo; si el registro falla, el cambio de negocio no se revierte. Para requisitos regulatorios se debe convertir a una escritura transaccional/outbox antes de producción.
