<?php

declare(strict_types=1);

require_once __DIR__ . '/../Exceptions/InvalidCelularException.php';
require_once __DIR__ . '/../ValueObjects/Imei.php';

/** Domain entity for one physical mobile device. */
final class Celular
{
    private string $id;
    private string $marca;
    /** @var string[] */
    private array $imei;
    private string $pulgadas;
    private string $megapixeles;
    private int $ram;
    private int $almacenamientoPrincipal;
    private int $almacenamientoSecundario;
    private string $sistemaOperativo;
    private string $operador;
    private string $tecnologiaBanda;
    private bool $wifi;
    private bool $bluetooth;
    private int $camaras;
    private string $marcaCpu;
    private string $velocidadCpu;
    private bool $nfc;
    private bool $huella;
    private bool $ir;
    private bool $resisteAgua;
    private int $cantidadSim;

    /** @param string[] $imei One identifier per SIM slot. */
    public function __construct(
        string $id,
        string $marca,
        array $imei,
        string $pulgadas,
        string $megapixeles,
        int $ram,
        int $almacenamientoPrincipal,
        int $almacenamientoSecundario,
        string $sistemaOperativo,
        string $operador,
        string $tecnologiaBanda,
        bool $wifi,
        bool $bluetooth,
        int $camaras,
        string $marcaCpu,
        string $velocidadCpu,
        bool $nfc,
        bool $huella,
        bool $ir,
        bool $resisteAgua,
        int $cantidadSim
    ) {
        $this->id = self::requiredText($id, 'id', 36);
        $this->marca = self::requiredText($marca, 'marca', 80);
        if (!in_array($cantidadSim, array(1, 2), true)) {
            throw InvalidCelularException::because('cantidadSim', 'solo se admite 1 o 2.');
        }
        if (count($imei) !== $cantidadSim) {
            throw InvalidCelularException::because('imei', 'debe incluir un IMEI por cada SIM.');
        }
        $normalizedImeis = array();
        foreach ($imei as $value) {
            if (!is_string($value)) {
                throw InvalidCelularException::because('imei', 'cada valor debe ser texto.');
            }
            $normalizedImeis[] = (new Imei($value))->value();
        }
        if (count(array_unique($normalizedImeis)) !== count($normalizedImeis)) {
            throw InvalidCelularException::because('imei', 'los IMEI del equipo deben ser distintos.');
        }

        $this->imei = array_values($normalizedImeis);
        $this->pulgadas = self::positiveDecimal($pulgadas, 'pulgadas', 100);
        $this->megapixeles = self::positiveDecimal($megapixeles, 'megapixeles', 100000);
        if ($ram < 1 || $ram > 65535) {
            throw InvalidCelularException::because('ram', 'debe ser un entero positivo representable.');
        }
        if ($almacenamientoPrincipal < 1 || $almacenamientoPrincipal > 65535) {
            throw InvalidCelularException::because('almacenamientoPrincipal', 'debe ser un entero positivo representable.');
        }
        if ($almacenamientoSecundario < 0 || $almacenamientoSecundario > 65535) {
            throw InvalidCelularException::because('almacenamientoSecundario', 'no puede ser negativo.');
        }
        if ($camaras < 1 || $camaras > 255) {
            throw InvalidCelularException::because('camaras', 'debe estar entre 1 y 255.');
        }
        $this->sistemaOperativo = self::requiredText($sistemaOperativo, 'sistemaOperativo', 80);
        $this->operador = self::requiredText($operador, 'operador', 80);
        $this->tecnologiaBanda = self::requiredText($tecnologiaBanda, 'tecnologiaBanda', 80);
        $this->marcaCpu = self::requiredText($marcaCpu, 'marcaCpu', 80);
        $this->velocidadCpu = self::positiveDecimal($velocidadCpu, 'velocidadCpu', 1000);
        $this->ram = $ram;
        $this->almacenamientoPrincipal = $almacenamientoPrincipal;
        $this->almacenamientoSecundario = $almacenamientoSecundario;
        $this->wifi = $wifi;
        $this->bluetooth = $bluetooth;
        $this->camaras = $camaras;
        $this->nfc = $nfc;
        $this->huella = $huella;
        $this->ir = $ir;
        $this->resisteAgua = $resisteAgua;
        $this->cantidadSim = $cantidadSim;
    }

    public function id(): string { return $this->id; }
    public function marca(): string { return $this->marca; }
    /** @return string[] */
    public function imei(): array { return $this->imei; }
    public function pulgadas(): string { return $this->pulgadas; }
    public function megapixeles(): string { return $this->megapixeles; }
    public function ram(): int { return $this->ram; }
    public function almacenamientoPrincipal(): int { return $this->almacenamientoPrincipal; }
    public function almacenamientoSecundario(): int { return $this->almacenamientoSecundario; }
    public function sistemaOperativo(): string { return $this->sistemaOperativo; }
    public function operador(): string { return $this->operador; }
    public function tecnologiaBanda(): string { return $this->tecnologiaBanda; }
    public function wifi(): bool { return $this->wifi; }
    public function bluetooth(): bool { return $this->bluetooth; }
    public function camaras(): int { return $this->camaras; }
    public function marcaCpu(): string { return $this->marcaCpu; }
    public function velocidadCpu(): string { return $this->velocidadCpu; }
    public function nfc(): bool { return $this->nfc; }
    public function huella(): bool { return $this->huella; }
    public function ir(): bool { return $this->ir; }
    public function resisteAgua(): bool { return $this->resisteAgua; }
    public function cantidadSim(): int { return $this->cantidadSim; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array(
            'id' => $this->id,
            'marca' => $this->marca,
            'imei' => $this->imei,
            'pulgadas' => $this->pulgadas,
            'megapixeles' => $this->megapixeles,
            'ram' => $this->ram,
            'almacenamientoPrincipal' => $this->almacenamientoPrincipal,
            'almacenamientoSecundario' => $this->almacenamientoSecundario,
            'sistemaOperativo' => $this->sistemaOperativo,
            'operador' => $this->operador,
            'tecnologiaBanda' => $this->tecnologiaBanda,
            'wifi' => $this->wifi,
            'bluetooth' => $this->bluetooth,
            'camaras' => $this->camaras,
            'marcaCpu' => $this->marcaCpu,
            'velocidadCpu' => $this->velocidadCpu,
            'nfc' => $this->nfc,
            'huella' => $this->huella,
            'ir' => $this->ir,
            'resisteAgua' => $this->resisteAgua,
            'cantidadSim' => $this->cantidadSim,
        );
    }

    private static function requiredText(string $value, string $field, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw InvalidCelularException::because($field, 'es obligatorio y admite máximo ' . $maxLength . ' caracteres.');
        }

        return $value;
    }

    private static function positiveDecimal(string $value, string $field, float $maximum): string
    {
        $value = trim($value);
        if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $value)) {
            throw InvalidCelularException::because($field, 'debe ser un decimal positivo con hasta dos posiciones decimales.');
        }
        $number = (float) $value;
        if ($number <= 0 || $number > $maximum) {
            throw InvalidCelularException::because($field, 'está fuera del rango permitido.');
        }

        return number_format($number, 2, '.', '');
    }
}
