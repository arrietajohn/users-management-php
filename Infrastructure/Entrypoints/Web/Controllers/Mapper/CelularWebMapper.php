<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Services/Dto/Commands/CelularCommand.php';
require_once __DIR__ . '/../../../../../Application/Services/Dto/Queries/CelularQuery.php';

final class CelularWebMapper
{
    /** @param array<string, mixed> $input */
    public function fromInputToCommand(array $input): CelularCommand
    {
        $attributes = array();
        foreach (array(
            'marca', 'pulgadas', 'megapixeles', 'ram', 'almacenamientoPrincipal',
            'almacenamientoSecundario', 'sistemaOperativo', 'operador', 'tecnologiaBanda',
            'camaras', 'marcaCpu', 'velocidadCpu', 'cantidadSim'
        ) as $field) {
            $attributes[$field] = isset($input[$field]) && is_scalar($input[$field])
                ? trim((string) $input[$field])
                : '';
        }

        $rawImeis = $input['imei'] ?? array();
        $attributes['imei'] = is_array($rawImeis)
            ? array_values(array_map(static fn ($value): string => is_scalar($value) ? trim((string) $value) : '', $rawImeis))
            : array(is_scalar($rawImeis) ? trim((string) $rawImeis) : '');
        $attributes['imei'] = array_values(array_filter($attributes['imei'], static fn (string $value): bool => $value !== ''));

        foreach (array('wifi', 'bluetooth', 'nfc', 'huella', 'ir', 'resisteAgua') as $field) {
            $rawValue = $input[$field] ?? null;
            if (is_bool($rawValue)) {
                $attributes[$field] = $rawValue;
            } elseif ($rawValue === '1' || $rawValue === 1) {
                $attributes[$field] = true;
            } elseif ($rawValue === '0' || $rawValue === 0) {
                $attributes[$field] = false;
            } else {
                $attributes[$field] = null;
            }
        }

        return new CelularCommand($attributes);
    }

    /** @param array<string, mixed> $input */
    public function fromInputToQuery(array $input): CelularQuery
    {
        $features = array();
        foreach (array('wifi', 'bluetooth', 'nfc', 'huella', 'ir', 'resiste_agua') as $field) {
            $rawValue = $input[$field] ?? null;
            if (is_bool($rawValue)) {
                $features[$field] = $rawValue;
                continue;
            }
            $value = is_scalar($rawValue) ? strtolower(trim((string) $rawValue)) : '';
            $features[$field] = in_array($value, array('1', 'true'), true)
                ? true
                : (in_array($value, array('0', 'false'), true) ? false : null);
        }
        $string = static fn (string $key): string => isset($input[$key]) && is_scalar($input[$key])
            ? trim((string) $input[$key])
            : '';
        $int = static fn (string $key, int $default): int => isset($input[$key]) && filter_var($input[$key], FILTER_VALIDATE_INT) !== false
            ? (int) $input[$key]
            : $default;

        return new CelularQuery(
            $string('q'),
            $string('marca'),
            $string('imei'),
            $string('sistema_operativo'),
            $string('operador'),
            $features,
            $string('sort') !== '' ? $string('sort') : 'created_at',
            $string('direction') !== '' ? $string('direction') : 'DESC',
            $int('page', 1),
            $int('per_page', 20)
        );
    }
}
