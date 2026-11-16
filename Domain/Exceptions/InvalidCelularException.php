<?php

declare(strict_types=1);

final class InvalidCelularException extends DomainException
{
    public static function because(string $field, string $reason): self
    {
        return new self('Celular inválido (' . $field . '): ' . $reason);
    }
}
