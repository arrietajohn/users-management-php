<?php

declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        if (!session_start()) {
            throw new RuntimeException('No se pudo iniciar la sesión.');
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);
        $lastRegenerated = (int) ($_SESSION['_last_regenerated'] ?? $now);
        if ($now - $lastActivity > 1800) {
            $_SESSION = array();
            session_regenerate_id(true);
            $lastRegenerated = $now;
        } elseif ($now - $lastRegenerated > 900) {
            session_regenerate_id(true);
            $lastRegenerated = $now;
        }
        $_SESSION['_last_activity'] = $now;
        $_SESSION['_last_regenerated'] = $lastRegenerated;
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }

    public static function isHttps(): bool
    {
        return strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}
