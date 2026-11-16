<?php

declare(strict_types=1);

final class CelularQuery
{
    private const SORT_FIELDS = array(
        'marca', 'created_at', 'updated_at', 'ram', 'almacenamiento_principal', 'sistema_operativo', 'operador'
    );

    /** @param array<string, bool|null> $features */
    public function __construct(
        private string $search = '',
        private string $marca = '',
        private string $imei = '',
        private string $sistemaOperativo = '',
        private string $operador = '',
        private array $features = array(),
        private string $sort = 'created_at',
        private string $direction = 'DESC',
        private int $page = 1,
        private int $perPage = 20
    ) {
        if (!in_array($sort, self::SORT_FIELDS, true)) {
            $this->sort = 'created_at';
        }
        $this->direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
        foreach ($features as $name => $value) {
            if (!in_array($name, array('wifi', 'bluetooth', 'nfc', 'huella', 'ir', 'resiste_agua'), true)) {
                unset($this->features[$name]);
            }
        }
    }

    public function search(): string { return trim($this->search); }
    public function marca(): string { return trim($this->marca); }
    public function imei(): string { return trim($this->imei); }
    public function sistemaOperativo(): string { return trim($this->sistemaOperativo); }
    public function operador(): string { return trim($this->operador); }
    /** @return array<string, bool|null> */
    public function features(): array { return $this->features; }
    public function sort(): string { return $this->sort; }
    public function direction(): string { return $this->direction; }
    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
}
