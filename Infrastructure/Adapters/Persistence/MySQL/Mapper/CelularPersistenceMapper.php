<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Domain/Models/Celular.php';

final class CelularPersistenceMapper
{
    /** @return array<string, mixed> */
    public function toRow(Celular $celular): array
    {
        $data = $celular->toArray();
        unset($data['imei']);
        $row = array('id' => $data['id']);
        foreach ($data as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            $row[$this->column($field)] = $value;
        }
        return $row;
    }

    /** @param array<string, mixed> $row
     *  @param string[] $imeis
     */
    public function fromRow(array $row, array $imeis): Celular
    {
        return new Celular(
            (string) $row['id'],
            (string) $row['marca'],
            $imeis,
            (string) $row['pulgadas'],
            (string) $row['megapixeles'],
            (int) $row['ram'],
            (int) $row['almacenamiento_principal'],
            (int) $row['almacenamiento_secundario'],
            (string) $row['sistema_operativo'],
            (string) $row['operador'],
            (string) $row['tecnologia_banda'],
            (bool) $row['wifi'],
            (bool) $row['bluetooth'],
            (int) $row['camaras'],
            (string) $row['marca_cpu'],
            (string) $row['velocidad_cpu'],
            (bool) $row['nfc'],
            (bool) $row['huella'],
            (bool) $row['ir'],
            (bool) $row['resiste_agua'],
            (int) $row['cantidad_sim']
        );
    }

    private function column(string $field): string
    {
        $columns = array(
            'almacenamientoPrincipal' => 'almacenamiento_principal',
            'almacenamientoSecundario' => 'almacenamiento_secundario',
            'sistemaOperativo' => 'sistema_operativo',
            'tecnologiaBanda' => 'tecnologia_banda',
            'marcaCpu' => 'marca_cpu',
            'velocidadCpu' => 'velocidad_cpu',
            'resisteAgua' => 'resiste_agua',
            'cantidadSim' => 'cantidad_sim',
        );

        return $columns[$field] ?? $field;
    }
}
