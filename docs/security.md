# Seguridad aplicada y límites conocidos

## Controles implementados

- **SQL injection:** PDO con prepared statements. Ordenamiento usa una lista blanca; límites y offsets son parámetros enteros.
- **Validación:** el formulario/API transforma entrada en comandos y el dominio vuelve a validar. IMEI es texto, longitud/dígito Luhn y cantidad SIM se verifica en dominio.
- **XSS:** vistas PHP escapan contenido dinámico con `htmlspecialchars`; JSON usa codificación segura. Los IDs de HTML no sustituyen autorización.
- **CSRF:** token aleatorio por sesión en todos los POST web, incluido logout. API requiere un Bearer token explícito y no autentica cookies de sesión.
- **Sesión:** cookies HttpOnly y SameSite=Lax; Secure cuando HTTPS; strict mode y solo cookies; regeneración en login y cada 15 minutos; expiración por inactividad a los 30 minutos. En cada petición web autenticada se consulta la cuenta actual; `authorization_version` monotónica cambia al actualizar email, rol, estado o contraseña, revocando aunque los valores vuelvan a su estado previo. El restablecimiento de contraseña también la incrementa. Si la persistencia falla, la petición se cierra con 503.
- **Autorización:** `RolePermissions` aplica permisos en el servidor tanto en rutas web como API. Alta/edición/eliminación de usuarios es solo `ADMIN`; el registro público no existe.
- **Contraseñas:** `password_hash` bcrypt y `password_verify`; contraseñas nuevas se limitan a 72 bytes para evitar el truncamiento de bcrypt. No se reponen ni envían en correo. Primer administrador se crea por CLI.
- **Recuperación:** secreto aleatorio de 256 bits; solo se guarda SHA-256; expiración 30 minutos, un solo uso y actualización de contraseña/token atómica. El enlace lleva el token en fragmento URL para que no viaje en la petición GET inicial; referrer policy impide propagarlo.
- **Rate limiting:** límites persistentes MySQL para login por IP y cuenta, solicitud de recuperación por IP y envío de restablecimiento por digest de token. Las claves se almacenan como HMAC con `APP_SECRET`.
- **Rate limiting API:** 300 solicitudes/minuto por `REMOTE_ADDR` y 120/minuto por identidad validada. Si MySQL falla al verificar límites o autenticación, se responde JSON 503 sin detalles internos. `php bin/cleanup-rate-limits.php` elimina hasta 1000 registros con ventana vencida hace más de 24 horas y sin bloqueo futuro; debe programarse externamente.
- **API:** token aleatorio `cat_…`, almacenamiento SHA-256, cuenta debe estar `ACTIVE`, vencimiento limitado a 365 días, revocación CLI y último uso. Tokens no se muestran de nuevo. Las solicitudes se limitan por IP (300/minuto) y por identidad autenticada (120/minuto); el límite persistente usa claves HMAC y responde 429 con `Retry-After`.
- **Último administrador:** la persistencia bloquea las filas de administradores activos dentro de una transacción antes de eliminar, desactivar o cambiar el rol, y rechaza la operación si dejaría cero administradores activos.
- **Errores/cabeceras:** mensajes internos se sustituyen por una referencia genérica en web/API; logs no incluyen el cuerpo de excepciones. Se envían `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Permissions-Policy`.
- **Secretos:** `.env` se ignora por Git; `.env.example` contiene marcadores. No se usa CORS.

## Límites y trabajo pendiente

- En esta auditoría no se ejecutaron migraciones ni la suite de integración MySQL: no hay cliente MySQL ni Docker disponible para levantar una base temporal aislada. Una revisión local anterior registró el servicio `MYSQL267` sin autenticación disponible; su estado actual no se verificó. No se ejecutaron escrituras en bases. Tampoco se ejecutaron pruebas SMTP ni de navegador.
- El correo de alta/cambio es notificación de mejor esfuerzo: la operación de usuario persiste aunque SMTP falle; se registra una línea genérica en log y no se envían contraseñas.
- La auditoría de actividad se escribe después de guardar/eliminar y falla de forma no bloqueante; no es una bitácora transaccional resistente a manipulación.
- El límite por IP usa `REMOTE_ADDR`; detrás de un proxy inverso configura `mod_remoteip` o equivalente para confiar solo en rangos de proxy controlados y reescribir encabezados entrantes. La aplicación no confía en `X-Forwarded-For`. Las cuotas son límites operativos iniciales y deben ajustarse con métricas del despliegue.
- No hay MFA, recuperación con notificación transaccional/outbox, política de contraseñas configurable, CSP estricta (hay scripts inline), ni detección de anomalías.
- No se configuró una URL pública ni se ejecutó despliegue. Revisa TLS, proxy, headers de HTTPS y backups en el entorno destino.
- No se aplicaron las migraciones 002/003. Revisa y respalda la base antes de `php bin/migrate.php`; correos duplicados o un esquema legado incompatible requieren remediación previa. Los SQL manuales de rollback están bajo `Migrations/Rollback/`; antes de reducir precisión decimal verifica que los datos quepan en los tipos originales.

La lista sirve como estado real del repositorio y no es una certificación de cumplimiento OWASP/WCAG.
