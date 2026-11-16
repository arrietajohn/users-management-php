<?php

declare(strict_types=1);

require_once __DIR__ . '/../Infrastructure/Entrypoints/Web/Presentation/Security.php';
require_once __DIR__ . '/../Application/Security/RolePermissions.php';

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$requestPath = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$publicBase = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
$applicationBase = $publicBase === '' ? '' : rtrim(dirname($publicBase), '/');
$applicationBase = $applicationBase === '.' ? '' : $applicationBase;
$apiPrefix = ($applicationBase === '' ? '' : $applicationBase) . '/api/v1';
$isApiRequest = $requestPath === $apiPrefix || str_starts_with($requestPath, $apiPrefix . '/');

// Guardia de seguridad: bloquear acceso directo a archivos/carpetas 
// El .htaccess redirige internamente cualquier URL que no sea public/ hacia aquí.
// Detectamos ese caso comparando la URL solicitada con la ruta pública esperada.

(function () use ($requestPath, $publicBase, $isApiRequest): void {
    // Si la URL pedida no comienza con /…/public/, fue un acceso directo indebido.
    if (!$isApiRequest && $requestPath !== $publicBase && !str_starts_with($requestPath, $publicBase . '/')) {
        Security::startSession();
        $dest = isset($_SESSION['auth']['id']) ? 'home' : 'auth.login';
        header('Location: ' . $publicBase . '/index.php?route=' . $dest);
        exit;
    }
})();

require_once __DIR__ . '/../Common/ClassLoader.php';
require_once __DIR__ . '/../Common/DependencyInjection.php';
require_once __DIR__ . '/../Infrastructure/Entrypoints/Web/Presentation/View.php';
require_once __DIR__ . '/../Infrastructure/Entrypoints/Web/Presentation/Flash.php';

DependencyInjection::boot();
if ($isApiRequest) {
    ClassLoader::loadClass('ApiRouter');
    ApiRouter::dispatch($apiPrefix);
}
Flash::start();
Security::startSession();

// ──────────────────────────────────────────────────────────────
// Auth helpers
// ──────────────────────────────────────────────────────────────

function isLoggedIn(): bool
{
    return isset($_SESSION['auth']['id']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        Flash::setMessage('Debes iniciar sesión para acceder a esta sección.');
        View::redirect('auth.login');
    }
}

function getLoggedUser(): array
{
    return is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : array();
}

function revalidateAuthenticatedSession(): void
{
    if (!isLoggedIn()) {
        return;
    }

    try {
        $session = getLoggedUser();
        $userId = $session['id'] ?? null;
        $currentUser = is_string($userId) && trim($userId) !== ''
            ? DependencyInjection::getUserRepository()->getById(new UserId($userId))
            : null;
    } catch (Throwable $exception) {
        error_log('Session revalidation failed: ' . get_class($exception));
        http_response_code(503);
        echo 'No se pudo validar la sesión en este momento.';
        exit;
    }

    $storedFingerprint = (string) ($session['authorization_fingerprint'] ?? '');
    if (!RolePermissions::sessionIsCurrent($currentUser, $storedFingerprint)) {
        $_SESSION = array();
        session_regenerate_id(true);
        Flash::setMessage('La cuenta o sus permisos cambiaron. Inicia sesión nuevamente.');
        View::redirect('auth.login');
    }

    $_SESSION['auth'] = array(
        'id' => $currentUser->id()->value(),
        'name' => $currentUser->name()->value(),
        'email' => $currentUser->email()->value(),
        'role' => $currentUser->role(),
        'authorization_fingerprint' => RolePermissions::sessionFingerprint($currentUser),
    );
}

revalidateAuthenticatedSession();

// ──────────────────────────────────────────────────────────────
// Routing
// ──────────────────────────────────────────────────────────────

$route = isset($_GET['route']) ? trim((string) $_GET['route']) : 'home';
$routes = WebRoutes::routes();

if (!isset($routes[$route])) {
    http_response_code(404);
    View::render('home', buildHomeViewData('Ruta no encontrada.'));
    exit;
}

$definition = $routes[$route];
$httpMethod = strtoupper((string) $_SERVER['REQUEST_METHOD']);

if ($httpMethod !== $definition['method']) {
    http_response_code(405);
    View::render('home', buildHomeViewData('Método HTTP no permitido.'));
    exit;
}

// Protect all user management routes behind authentication.
$publicActions = array('login', 'authenticate', 'forgot', 'forgot.send', 'reset', 'reset.submit');
if (!in_array($definition['action'], $publicActions, true) && !isLoggedIn()) {
    Flash::setMessage('Debes iniciar sesión para acceder a esta sección.');
    View::redirect('auth.login');
}

$permissionByAction = array(
    'create' => 'users.write',
    'store' => 'users.write',
    'index' => 'users.read',
    'show' => 'users.read',
    'edit' => 'users.write',
    'update' => 'users.write',
    'delete' => 'users.write',
    'home' => 'dashboard.read',
    'celulares.index' => 'celulares.read',
    'celulares.create' => 'celulares.create',
    'celulares.store' => 'celulares.create',
    'celulares.show' => 'celulares.read',
    'celulares.edit' => 'celulares.update',
    'celulares.update' => 'celulares.update',
    'celulares.delete' => 'celulares.delete',
);
$permission = $permissionByAction[$definition['action']] ?? null;
if ($permission !== null && !RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), $permission)) {
    http_response_code(403);
    echo 'No tienes permiso para realizar esta operación.';
    exit;
}

if ($httpMethod === 'POST' && !Security::verifyCsrf(
    isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
)) {
    http_response_code(419);
    echo 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    exit;
}

try {
    switch ($definition['action']) {

        case 'home':
            $role = (string) ($_SESSION['auth']['role'] ?? '');
            $dashboard = DependencyInjection::getDashboardService()->execute(
                RolePermissions::allows($role, 'activity.read')
            );
            View::render('home', buildHomeViewData('', $dashboard));
            break;

        case 'create':
            View::render('users/create', buildCreateUserViewData());
            break;

        case 'store':
            $controller = DependencyInjection::getUserController();
            $form = getCreateUserFormData();
            $form['id'] = generateUuid4();
            $errors = validateCreateUserForm($form);

            if (!empty($errors)) {
                $old = $form;
                unset($old['password']);
                Flash::setOld($old);
                Flash::setErrors($errors);
                Flash::setMessage('Corrige los errores del formulario.');
                View::redirect('users.create');
            }

            $request = new CreateUserWebRequest(
                $form['id'],
                $form['name'],
                $form['email'],
                $form['password'],
                $form['role']
            );

            $controller->store($request);
            recordActivity('user.created', 'user', $form['id']);

            Flash::setSuccess('Usuario registrado correctamente.');
            View::redirect('users.index');
            break;

        case 'index':
            $controller = DependencyInjection::getUserController();
            $requestedPage = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) !== false
                ? (int) $_GET['page']
                : 1;
            $users = $controller->index($requestedPage);
            View::render('users/list', buildListUsersViewData($users));
            break;

        case 'show':
            $controller = DependencyInjection::getUserController();
            $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
            $user = $controller->show($id);
            View::render('users/show', array(
                'pageTitle' => 'Detalle de usuario',
                'user' => $user,
                'message' => Flash::message(),
            ));
            break;

        case 'edit':
            $controller = DependencyInjection::getUserController();
            $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
            $user = $controller->show($id);
            View::render('users/edit', buildEditUserViewData($user));
            break;

        case 'update':
            $controller = DependencyInjection::getUserController();
            $form = getUpdateUserFormData();
            $errors = validateUpdateUserForm($form);

            if (!empty($errors)) {
                $old = $form;
                unset($old['password']);
                Flash::setOld($old);
                Flash::setErrors($errors);
                Flash::setMessage('Corrige los errores del formulario.');
                header('Location: ?route=users.edit&id=' . urlencode($form['id']));
                exit;
            }

            $request = new UpdateUserWebRequest(
                $form['id'],
                $form['name'],
                $form['email'],
                $form['password'],
                $form['role'],
                $form['status']
            );

            $controller->update($request);
            recordActivity('user.updated', 'user', $form['id']);

            Flash::setSuccess('Usuario actualizado correctamente.');
            View::redirect('users.index');
            break;

        case 'delete':
            $controller = DependencyInjection::getUserController();
            $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
            $controller->delete($id);
            recordActivity('user.deleted', 'user', $id);
            Flash::setSuccess('Usuario eliminado correctamente.');
            View::redirect('users.index');
            break;

        case 'celulares.index':
            $page = DependencyInjection::getCelularController()->index($_GET);
            View::render('celulares/list', array(
                'pageTitle' => 'Catálogo de celulares',
                'page' => $page,
                'filters' => getCelularFilterData(),
                'message' => Flash::message(),
                'success' => Flash::success(),
            ));
            break;

        case 'celulares.create':
            View::render('celulares/form', buildCelularFormViewData(null));
            break;

        case 'celulares.store':
            try {
                $saved = DependencyInjection::getCelularController()->store($_POST);
                recordActivity('celular.created', 'celular', $saved->id());
                Flash::setSuccess('Celular registrado correctamente.');
                View::redirect('celulares.index');
            } catch (Throwable $exception) {
                Flash::setOld(getCelularFormData());
                Flash::setMessage(celularFormError($exception));
                View::redirect('celulares.create');
            }
            break;

        case 'celulares.show':
            $celular = DependencyInjection::getCelularController()->show(
                isset($_GET['id']) ? trim((string) $_GET['id']) : ''
            );
            View::render('celulares/show', array(
                'pageTitle' => 'Detalle del celular',
                'celular' => $celular,
            ));
            break;

        case 'celulares.edit':
            $celular = DependencyInjection::getCelularController()->show(
                isset($_GET['id']) ? trim((string) $_GET['id']) : ''
            );
            View::render('celulares/form', buildCelularFormViewData($celular));
            break;

        case 'celulares.update':
            $id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
            try {
                $saved = DependencyInjection::getCelularController()->update($id, $_POST);
                recordActivity('celular.updated', 'celular', $saved->id());
                Flash::setSuccess('Celular actualizado correctamente.');
                View::redirect('celulares.index');
            } catch (Throwable $exception) {
                Flash::setOld(getCelularFormData());
                Flash::setMessage(celularFormError($exception));
                header('Location: ?route=celulares.edit&id=' . urlencode($id));
                exit;
            }
            break;

        case 'celulares.delete':
            $deletedId = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
            DependencyInjection::getCelularController()->delete($deletedId);
            recordActivity('celular.deleted', 'celular', $deletedId);
            Flash::setSuccess('Celular eliminado correctamente.');
            View::redirect('celulares.index');
            break;

        case 'login':
            if (isLoggedIn()) {
                View::redirect('home');
            }
            View::render('auth/login', array(
                'pageTitle' => 'Iniciar sesión',
                'message' => Flash::message(),
                'errors' => Flash::errors(),
                'old' => Flash::old(),
            ));
            break;

        case 'authenticate':
            if (!allowAuthAttempt('login:ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
                http_response_code(429);
                Flash::setMessage('Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.');
                View::redirect('auth.login');
            }

            $email = trim(strtolower((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $authErrors = array();

            if ($email === '') {
                $authErrors['email'] = 'El correo es obligatorio.';
            }
            if ($password === '') {
                $authErrors['password'] = 'La contraseña es obligatoria.';
            }

            if (!empty($authErrors)) {
                Flash::setErrors($authErrors);
                Flash::setOld(array('email' => $email));
                View::redirect('auth.login');
            }

            if (!allowAuthAttempt('login:account:' . $email)) {
                http_response_code(429);
                Flash::setMessage('Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.');
                View::redirect('auth.login');
            }

            $loginUseCase = DependencyInjection::getLoginUseCase();
            $command = new LoginCommand($email, $password);
            $user = $loginUseCase->execute($command);
            $rateLimit = DependencyInjection::getRateLimitService();
            $rateLimit->clear('login:ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $rateLimit->clear('login:account:' . $email);

            $_SESSION['auth'] = array(
                'id' => $user->id()->value(),
                'name' => $user->name()->value(),
                'email' => $user->email()->value(),
                'role' => $user->role(),
                'authorization_fingerprint' => RolePermissions::sessionFingerprint($user),
            );
            recordActivity('auth.login', 'user', $user->id()->value());
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);

            Flash::setSuccess('Bienvenido/a, ' . $user->name()->value() . '.');
            View::redirect('home');
            break;

        case 'logout':
            recordActivity('auth.logout', 'user', (string) ($_SESSION['auth']['id'] ?? ''));
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $cookie = session_get_cookie_params();
                setcookie(session_name(), '', array(
                    'expires' => time() - 42000,
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => 'Lax',
                ));
            }
            session_destroy();
            header('Location: ?route=auth.login');
            exit;


        case 'forgot':
            View::render('auth/forgot-password', array(
                'pageTitle' => 'Recuperar contraseña',
                'message' => Flash::message(),
                'success' => Flash::success(),
                'errors' => Flash::errors(),
                'old' => Flash::old(),
            ));
            break;

        case 'forgot.send':
            $forgotEmail = trim(strtolower((string) ($_POST['email'] ?? '')));

            if (!allowAuthAttempt('recovery:ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
                http_response_code(429);
                Flash::setSuccess('Si el correo está registrado y la cuenta está activa, recibirás instrucciones.');
                View::redirect('auth.forgot');
            }

            if ($forgotEmail === '' || !filter_var($forgotEmail, FILTER_VALIDATE_EMAIL)) {
                Flash::setErrors(array('email' => 'Introduce un correo electrónico válido.'));
                Flash::setOld(array('email' => $forgotEmail));
                View::redirect('auth.forgot');
            }

            $resetService = DependencyInjection::getPasswordResetService();
            $resetToken = $resetService->request($forgotEmail);

            // Always show generic success to avoid user enumeration.
            if ($resetToken !== null) {
                $baseUrl = rtrim(EnvLoader::get('APP_BASE_URL'), '/');
                if (!preg_match('#^https?://[^/]+$#i', $baseUrl)) {
                    throw new RuntimeException('APP_BASE_URL debe ser una URL absoluta HTTP(S) sin ruta.');
                }

                $foundUser = DependencyInjection::getUserRepository()->getByEmail(new UserEmail($forgotEmail));
                if ($foundUser !== null) {
                    sendPasswordRecoveryEmail(
                        $foundUser->email()->value(),
                        $foundUser->name()->value(),
                    $baseUrl . '/index.php?route=auth.reset#token=' . urlencode($resetToken)
                    );
                }
            }

            Flash::setSuccess(
                'Si el correo está registrado y la cuenta está activa, ' .
                'recibirás un enlace seguro para crear una contraseña nueva.'
            );
            View::redirect('auth.forgot');
            break;

        case 'reset':
            View::render('auth/reset-password', array(
                'pageTitle' => 'Crear nueva contraseña',
                'token' => isset($_GET['token']) ? (string) $_GET['token'] : '',
                'message' => Flash::message(),
                'errors' => Flash::errors(),
            ));
            break;

        case 'reset.submit':
            $token = (string) ($_POST['token'] ?? '');
            if (!allowAuthAttempt('reset:token:' . hash('sha256', $token))) {
                http_response_code(429);
                Flash::setMessage('Demasiados intentos. Solicita un enlace nuevo más tarde.');
                View::redirect('auth.forgot');
            }

            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            $resetErrors = array();
            if (strlen($password) < 8) {
                $resetErrors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif (strlen($password) > 72) {
                $resetErrors['password'] = 'La contraseña no puede superar 72 bytes.';
            }
            if (!hash_equals($password, $confirmation)) {
                $resetErrors['password_confirmation'] = 'Las contraseñas no coinciden.';
            }
            if (!empty($resetErrors)) {
                Flash::setErrors($resetErrors);
                View::render('auth/reset-password', array(
                    'pageTitle' => 'Crear nueva contraseña',
                    'token' => $token,
                    'message' => '',
                    'errors' => $resetErrors,
                ));
                break;
            }

            if (!DependencyInjection::getPasswordResetService()->complete($token, $password)) {
                Flash::setMessage('El enlace no es válido, expiró o ya fue utilizado. Solicita uno nuevo.');
                View::redirect('auth.forgot');
            }

            recordActivity('auth.password_reset', 'user', null);

            Flash::setSuccess('La contraseña se actualizó. Ya puedes iniciar sesión.');
            View::redirect('auth.login');
            break;

        default:
            throw new RuntimeException('Acción no soportada.');
    }
} catch (Throwable $exception) {
    if ($exception instanceof UserAlreadyExistsException) {
        $msg = 'Ya existe una cuenta con ese correo.';
    } elseif ($exception instanceof UserNotFoundException) {
        $msg = 'No se encontró el usuario solicitado.';
    } elseif ($exception instanceof DomainException) {
        $msg = 'Los datos enviados no son válidos. Revisa el formulario.';
    } else {
        $incidentId = bin2hex(random_bytes(6));
        error_log('Unhandled request error [' . $incidentId . ']: ' . get_class($exception));
        $msg = 'No se pudo completar la solicitud. Referencia: ' . $incidentId;
    }
    Flash::setMessage($msg);

    switch ($route) {
        case 'users.store':
            $old = getCreateUserFormData();
            unset($old['password']);
            Flash::setOld($old);
            View::redirect('users.create');
            break;

        case 'users.update':
            $updateId = trim((string) ($_POST['id'] ?? ''));
            $old = getUpdateUserFormData();
            unset($old['password']);
            Flash::setOld($old);
            header('Location: ?route=users.edit&id=' . urlencode($updateId));
            exit;

        case 'auth.authenticate':
            Flash::setOld(array('email' => trim(strtolower((string) ($_POST['email'] ?? '')))));
            View::redirect('auth.login');
            break;

        case 'auth.forgot.send':
            Flash::setOld(array('email' => trim((string) ($_POST['email'] ?? ''))));
            View::redirect('auth.forgot');
            break;

        case 'auth.reset.submit':
            Flash::setMessage('No se pudo restablecer la contraseña. Solicita un enlace nuevo.');
            View::redirect('auth.forgot');
            break;

        case 'users.show':
        case 'users.edit':
            View::redirect('users.index');
            break;

        case 'celulares.show':
        case 'celulares.edit':
            Flash::setMessage($msg);
            View::redirect('celulares.index');
            break;

        case 'celulares.delete':
            Flash::setMessage($msg);
            View::redirect('celulares.index');
            break;

        case 'users.delete':
            Flash::setMessage($msg);
            View::redirect('users.index');
            break;

        default:
            View::render('home', buildHomeViewData($msg));
            break;
    }
}


function sendPasswordRecoveryEmail(string $email, string $name, string $resetUrl): void
{
    $templateFile = __DIR__ . '/../Infrastructure/Entrypoints/Web/Presentation/Views/emails/forgot-password.php';

    ob_start();
    extract(array('email' => $email, 'name' => $name, 'resetUrl' => $resetUrl), EXTR_SKIP);
    require $templateFile;
    $htmlBody = (string) ob_get_clean();

    ClassLoader::loadClass('EmailDestinationModel');

    $destination = new EmailDestinationModel(
        $email,
        $name,
        'Recuperación de contraseña',
        $htmlBody
    );

    DependencyInjection::getEmailSenderService()->send($destination);
}

function allowAuthAttempt(string $scope): bool
{
    return DependencyInjection::getRateLimitService()->allow($scope);
}

/** @param array<string, scalar|null> $details */
function recordActivity(string $action, string $subjectType, ?string $subjectId, array $details = array()): void
{
    try {
        $actorId = isset($_SESSION['auth']['id']) ? (string) $_SESSION['auth']['id'] : null;
        DependencyInjection::getActivityLogService()->record($actorId, $action, $subjectType, $subjectId, $details);
    } catch (Throwable $exception) {
        error_log('Activity logging failed: ' . get_class($exception));
    }
}


/**
 * @return array<string, mixed>
 */
function buildListUsersViewData(UserResponsePage $users): array
{
    return array(
        'pageTitle' => 'Lista de usuarios',
        'page' => $users,
        'message' => Flash::message(),
        'success' => Flash::success(),
    );
}

/**
 * @return array<string, mixed>
 */
function buildHomeViewData(string $message = '', array $dashboard = array()): array
{
    return array(
        'pageTitle' => 'Menú principal',
        'message' => $message,
        'success' => Flash::success(),
        'dashboard' => $dashboard,
    );
}

function buildCelularFormViewData(?Celular $celular): array
{
    return array(
        'pageTitle' => $celular === null ? 'Registrar celular' : 'Editar celular',
        'celular' => $celular,
        'old' => Flash::old(),
        'message' => Flash::message(),
    );
}

/** @return array<string, mixed> */
function getCelularFormData(): array
{
    $fields = array(
        'marca', 'pulgadas', 'megapixeles', 'ram', 'almacenamientoPrincipal',
        'almacenamientoSecundario', 'sistemaOperativo', 'operador', 'tecnologiaBanda',
        'wifi', 'bluetooth', 'camaras', 'marcaCpu', 'velocidadCpu', 'nfc', 'huella',
        'ir', 'resisteAgua', 'cantidadSim'
    );
    $data = array();
    foreach ($fields as $field) {
        $data[$field] = isset($_POST[$field]) && is_scalar($_POST[$field])
            ? trim((string) $_POST[$field])
            : '';
    }
    $data['imei'] = isset($_POST['imei']) && is_array($_POST['imei'])
        ? array_values(array_map(static fn ($value): string => is_scalar($value) ? trim((string) $value) : '', $_POST['imei']))
        : array();
    return $data;
}

/** @return array<string, string> */
function getCelularFilterData(): array
{
    $keys = array('q', 'marca', 'imei', 'sistema_operativo', 'operador', 'sort', 'direction', 'page', 'per_page', 'wifi', 'bluetooth', 'nfc', 'huella', 'ir', 'resiste_agua');
    $safe = array();
    foreach ($keys as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
            $safe[$key] = trim((string) $_GET[$key]);
        }
    }
    return $safe;
}

function celularFormError(Throwable $exception): string
{
    if ($exception instanceof InvalidCelularException) {
        return $exception->getMessage();
    }
    if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
        return 'Uno de los IMEI ya está asociado a otro celular.';
    }
    error_log('Celular operation failed: ' . get_class($exception));
    return 'No se pudo guardar el celular. Revisa los datos e inténtalo nuevamente.';
}

/**
 * @return array<string, mixed>
 */
function buildCreateUserViewData(): array
{
    return array(
        'pageTitle' => 'Registrar usuario',
        'roleOptions' => UserRoleEnum::values(),
        'message' => Flash::message(),
        'success' => Flash::success(),
        'errors' => Flash::errors(),
        'old' => Flash::old(),
    );
}

/**
 * @return array<string, mixed>
 */
function buildEditUserViewData(UserResponse $user): array
{
    return array(
        'pageTitle' => 'Editar usuario',
        'user' => $user,
        'roleOptions' => UserRoleEnum::values(),
        'statusOptions' => UserStatusEnum::values(),
        'message' => Flash::message(),
        'errors' => Flash::errors(),
        'old' => Flash::old(),
    );
}


function generateUuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


/**
 * @return array<string, string>
 */
function getCreateUserFormData(): array
{
    return array(
        'name' => isset($_POST['name']) ? trim((string) $_POST['name']) : '',
        'email' => isset($_POST['email']) ? trim((string) $_POST['email']) : '',
        'password' => isset($_POST['password']) ? trim((string) $_POST['password']) : '',
        'role' => isset($_POST['role']) ? trim((string) $_POST['role']) : '',
    );
}

/**
 * @return array<string, string>
 */
function getUpdateUserFormData(): array
{
    return array(
        'id' => isset($_POST['id']) ? trim((string) $_POST['id']) : '',
        'name' => isset($_POST['name']) ? trim((string) $_POST['name']) : '',
        'email' => isset($_POST['email']) ? trim((string) $_POST['email']) : '',
        'password' => isset($_POST['password']) ? (string) $_POST['password'] : '',
        'role' => isset($_POST['role']) ? trim((string) $_POST['role']) : '',
        'status' => isset($_POST['status']) ? trim((string) $_POST['status']) : '',
    );
}


/**
 * @param array<string, string> $form
 * @return array<string, string>
 */
function validateCreateUserForm(array $form): array
{
    $errors = array();

    if ($form['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }

    if ($form['email'] === '') {
        $errors['email'] = 'El correo es obligatorio.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El correo no tiene un formato válido.';
    }

    if ($form['password'] === '') {
        $errors['password'] = 'La contraseña es obligatoria.';
    } elseif (strlen($form['password']) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (strlen($form['password']) > 72) {
        $errors['password'] = 'La contraseña no puede superar 72 bytes.';
    }

    if ($form['role'] === '') {
        $errors['role'] = 'El rol es obligatorio.';
    }

    return $errors;
}

/**
 * @param array<string, string> $form
 * @return array<string, string>
 */
function validateUpdateUserForm(array $form): array
{
    $errors = array();

    if ($form['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }

    if ($form['email'] === '') {
        $errors['email'] = 'El correo es obligatorio.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El correo no tiene un formato válido.';
    }

    // Password is optional on update; validate only if provided.
    if ($form['password'] !== '' && strlen($form['password']) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres si deseas cambiarla.';
    } elseif (strlen($form['password']) > 72) {
        $errors['password'] = 'La contraseña no puede superar 72 bytes.';
    }

    if ($form['role'] === '') {
        $errors['role'] = 'El rol es obligatorio.';
    }

    if ($form['status'] === '') {
        $errors['status'] = 'El estado es obligatorio.';
    }

    return $errors;
}
