<?php

declare(strict_types=1);

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = array()): void
    {
        $file = __DIR__ . '/Views/' . $template . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException('Vista no encontrada: ' . $template);
        }

        extract($data, EXTR_SKIP);

        require $file;
    }

    /**
     * Muestra un IMEI con su estructura real: TAC (8), serie (6) y dígito de control (1).
     */
    public static function imei(string $imei): string
    {
        $safe = htmlspecialchars($imei, ENT_QUOTES, 'UTF-8');

        if (preg_match('/^(\d{8})(\d{6})(\d)$/', $imei, $parts) !== 1) {
            return '<span class="imei">' . $safe . '</span>';
        }

        return '<span class="imei" aria-label="IMEI ' . $safe . '">'
            . '<span class="imei-tac" title="Código de tipo (TAC)">' . $parts[1] . '</span>'
            . '<span class="imei-serial" title="Número de serie">' . $parts[2] . '</span>'
            . '<span class="imei-check" title="Dígito de control">' . $parts[3] . '</span>'
            . '</span>';
    }

    /**
     * @param list<string> $imeis
     */
    public static function imeiList(array $imeis): string
    {
        return '<span class="imei-list">' . implode('', array_map(array(self::class, 'imei'), $imeis)) . '</span>';
    }

    public static function redirect(string $route): void
    {
        header('Location: ?route=' . urlencode($route));
        exit;
    }
}