<?php

declare(strict_types=1);

final class CelularCommand
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes)
    {
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
