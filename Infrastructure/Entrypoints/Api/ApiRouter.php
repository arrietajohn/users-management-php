<?php

declare(strict_types=1);

final class ApiRouter
{
    public static function dispatch(string $apiPrefix): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim(substr($path, strlen($apiPrefix)), '/');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));
        if (count($segments) < 1 || count($segments) > 2 || !in_array($segments[0], array('users', 'celulares'), true)) {
            self::respond(404, self::error('not_found', 'El recurso solicitado no existe.'));
        }

        try {
            $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $rateLimits = DependencyInjection::getRateLimitService();
            if (!$rateLimits->allow('api:ip:' . $remoteAddress, 300, 60)) {
                header('Retry-After: 60');
                self::respond(429, self::error('rate_limited', 'Se excedió el límite de solicitudes. Inténtalo de nuevo en un minuto.'));
            }

            $token = self::bearerToken();
            $identity = $token === null ? null : DependencyInjection::getApiTokenService()->authenticate($token);
        } catch (Throwable $exception) {
            error_log('API authentication infrastructure failed: ' . get_class($exception));
            self::respond(503, self::error('service_unavailable', 'El servicio no está disponible temporalmente.'));
        }
        if ($identity === null) {
            self::respond(401, self::error('unauthorized', 'Se requiere un token Bearer válido y vigente.'));
        }
        try {
            if (!$rateLimits->allow('api:user:' . $identity['id'], 120, 60)) {
                header('Retry-After: 60');
                self::respond(429, self::error('rate_limited', 'Se excedió el límite de solicitudes. Inténtalo de nuevo en un minuto.'));
            }
        } catch (Throwable $exception) {
            error_log('API identity rate limit failed: ' . get_class($exception));
            self::respond(503, self::error('service_unavailable', 'El servicio no está disponible temporalmente.'));
        }

        $resource = $segments[0];
        $id = $segments[1] ?? null;
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $permission = self::requiredPermission($resource, $method, $id !== null);
        if ($permission === null) {
            header('Allow: GET, POST, PUT, DELETE');
            self::respond(405, self::error('method_not_allowed', 'Método HTTP no permitido.'));
        }
        if (!RolePermissions::allows($identity['role'], $permission)) {
            self::respond(403, self::error('forbidden', 'El rol del token no tiene permiso para esta operación.'));
        }

        try {
            if ($resource === 'celulares') {
                self::dispatchCelulares($method, $id, $apiPrefix, $identity);
            } else {
                self::dispatchUsers($method, $id, $apiPrefix, $identity);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof DomainException && str_contains($exception->getMessage(), 'administrador activo')) {
                self::respond(409, self::error('last_active_admin', $exception->getMessage()));
            }
            if ($exception instanceof UserAlreadyExistsException || ($exception instanceof PDOException && (string) $exception->getCode() === '23000')) {
                self::respond(409, self::error('conflict', 'El registro entra en conflicto con un dato existente.'));
            }
            if ($exception instanceof DomainException) {
                $notFound = $exception instanceof UserNotFoundException || str_contains($exception->getMessage(), 'no encontrado');
                self::respond($notFound ? 404 : 422, self::error($notFound ? 'not_found' : 'validation_error', $exception->getMessage()));
            }
            if ($exception instanceof InvalidArgumentException) {
                self::respond(422, self::error('validation_error', 'Los datos enviados no son válidos.'));
            }

            error_log('API request failed: ' . get_class($exception));
            self::respond(500, self::error('internal_error', 'No se pudo completar la solicitud.'));
        }
    }

    private static function dispatchCelulares(string $method, ?string $id, string $apiPrefix, array $identity): void
    {
        $controller = DependencyInjection::getCelularController();
        if ($method === 'GET' && $id === null) {
            $page = $controller->index($_GET);
            self::respond(200, array(
                'data' => array_map(static fn (Celular $item): array => $item->toArray(), $page->items()),
                'meta' => array('total' => $page->total(), 'page' => $page->page(), 'per_page' => $page->perPage(), 'pages' => $page->pages()),
            ));
        }
        if ($method === 'GET' && $id !== null) {
            self::respond(200, array('data' => $controller->show($id)->toArray()));
        }
        if ($method === 'POST' && $id === null) {
            $celular = $controller->store(self::jsonBody());
            self::recordActivity($identity['id'], 'celular.created', 'celular', $celular->id());
            header('Location: ' . $apiPrefix . '/celulares/' . rawurlencode($celular->id()));
            self::respond(201, array('data' => $celular->toArray()));
        }
        if ($method === 'PUT' && $id !== null) {
            $celular = $controller->update($id, self::jsonBody());
            self::recordActivity($identity['id'], 'celular.updated', 'celular', $celular->id());
            self::respond(200, array('data' => $celular->toArray()));
        }
        if ($method === 'DELETE' && $id !== null) {
            $controller->delete($id);
            self::recordActivity($identity['id'], 'celular.deleted', 'celular', $id);
            self::respond(204, null);
        }
        header('Allow: GET, POST, PUT, DELETE');
        self::respond(405, self::error('method_not_allowed', 'Método HTTP no permitido para este recurso.'));
    }

    private static function dispatchUsers(string $method, ?string $id, string $apiPrefix, array $identity): void
    {
        $controller = DependencyInjection::getUserController();
        if ($method === 'GET' && $id === null) {
            $page = self::intQuery('page', 1);
            $perPage = min(100, self::intQuery('per_page', 20));
            $result = $controller->index($page, $perPage);
            self::respond(200, array(
                'data' => array_map(static fn (UserResponse $item): array => $item->toArray(), $result->users()),
                'meta' => array('total' => $result->total(), 'page' => $result->page(), 'per_page' => $result->perPage(), 'pages' => $result->pages()),
            ));
        }
        if ($method === 'GET' && $id !== null) {
            self::respond(200, array('data' => $controller->show($id)->toArray()));
        }
        if ($method === 'POST' && $id === null) {
            $body = self::jsonBody();
            $request = new CreateUserWebRequest(
                Uuid::v4(), self::stringValue($body, 'name'), self::stringValue($body, 'email'),
                self::stringValue($body, 'password'), self::stringValue($body, 'role')
            );
            $user = $controller->store($request);
            self::recordActivity($identity['id'], 'user.created', 'user', $user->getId());
            header('Location: ' . $apiPrefix . '/users/' . rawurlencode($user->getId()));
            self::respond(201, array('data' => $user->toArray()));
        }
        if ($method === 'PUT' && $id !== null) {
            $body = self::jsonBody();
            $request = new UpdateUserWebRequest(
                $id, self::stringValue($body, 'name'), self::stringValue($body, 'email'),
                self::stringValue($body, 'password'), self::stringValue($body, 'role'), self::stringValue($body, 'status')
            );
            $user = $controller->update($request);
            self::recordActivity($identity['id'], 'user.updated', 'user', $user->getId());
            self::respond(200, array('data' => $user->toArray()));
        }
        if ($method === 'DELETE' && $id !== null) {
            $controller->delete($id);
            self::recordActivity($identity['id'], 'user.deleted', 'user', $id);
            self::respond(204, null);
        }
        header('Allow: GET, POST, PUT, DELETE');
        self::respond(405, self::error('method_not_allowed', 'Método HTTP no permitido para este recurso.'));
    }

    private static function requiredPermission(string $resource, string $method, bool $hasId): ?string
    {
        if ($resource === 'celulares') {
            if ($method === 'GET') return 'celulares.read';
            if ($method === 'POST' && !$hasId) return 'celulares.create';
            if ($method === 'PUT' && $hasId) return 'celulares.update';
            if ($method === 'DELETE' && $hasId) return 'celulares.delete';
        } else {
            if ($method === 'GET') return 'users.read';
            if ($method === 'POST' && !$hasId) return 'users.write';
            if (in_array($method, array('PUT', 'DELETE'), true) && $hasId) return 'users.write';
        }
        return null;
    }

    private static function bearerToken(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = is_array($headers) ? (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '') : '';
        }
        return preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) ? trim($matches[1]) : null;
    }

    /** @return array<string, mixed> */
    private static function jsonBody(): array
    {
        $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType !== 'application/json') {
            self::respond(415, self::error('unsupported_media_type', 'Envía el cuerpo como application/json.'));
        }
        $raw = file_get_contents('php://input');
        try {
            $data = json_decode($raw === false ? '' : $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::respond(400, self::error('invalid_json', 'El cuerpo JSON no es válido.'));
        }
        if (!is_array($data) || array_is_list($data)) {
            self::respond(400, self::error('invalid_json', 'Se esperaba un objeto JSON.'));
        }
        return $data;
    }

    /** @param array<string, mixed> $body */
    private static function stringValue(array $body, string $key): string
    {
        return isset($body[$key]) && is_scalar($body[$key]) ? trim((string) $body[$key]) : '';
    }

    private static function intQuery(string $key, int $default): int
    {
        return isset($_GET[$key]) && filter_var($_GET[$key], FILTER_VALIDATE_INT) !== false
            ? max(1, (int) $_GET[$key])
            : $default;
    }

    private static function recordActivity(string $actorId, string $action, string $type, string $subjectId): void
    {
        try {
            DependencyInjection::getActivityLogService()->record($actorId, $action, $type, $subjectId);
        } catch (Throwable $exception) {
            error_log('API activity logging failed: ' . get_class($exception));
        }
    }

    /** @return array{error: array{code: string, message: string}} */
    private static function error(string $code, string $message): array
    {
        return array('error' => array('code' => $code, 'message' => $message));
    }

    private static function respond(int $status, ?array $body): never
    {
        http_response_code($status);
        if ($body !== null) {
            echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }
}
