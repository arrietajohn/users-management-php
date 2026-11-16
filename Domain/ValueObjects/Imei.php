<?php

declare(strict_types=1);

require_once __DIR__ . '/../Exceptions/InvalidCelularException.php';

final class Imei
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (!preg_match('/^[0-9]{15}$/D', $value) || !self::hasValidCheckDigit($value)) {
            throw InvalidCelularException::because('imei', 'debe contener 15 dígitos y un dígito de control válido.');
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function hasValidCheckDigit(string $imei): bool
    {
        $sum = 0;
        for ($index = 0; $index < 15; $index++) {
            $digit = (int) $imei[$index];
            if ($index % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
