<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Ports/Out/CelularRepositoryPort.php';
require_once __DIR__ . '/../Mapper/CelularPersistenceMapper.php';

final class CelularRepositoryMySQL implements CelularRepositoryPort
{
    public function __construct(private PDO $pdo, private CelularPersistenceMapper $mapper)
    {
    }

    public function save(Celular $celular): Celular
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->mapper->toRow($celular);
            $columns = array_keys($row);
            $quotedColumns = implode(', ', $columns);
            $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns));
            $statement = $this->pdo->prepare('INSERT INTO celulares (' . $quotedColumns . ') VALUES (' . $placeholders . ')');
            $this->bindAndExecute($statement, $row);
            $this->writeImeis($celular);
            $this->pdo->commit();
            return $celular;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function update(Celular $celular): Celular
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->mapper->toRow($celular);
            unset($row['id']);
            $assignments = array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($row));
            $statement = $this->pdo->prepare(
                'UPDATE celulares SET ' . implode(', ', $assignments) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $this->bindAndExecute($statement, array_merge($row, array('id' => $celular->id())));
            $deleteImeis = $this->pdo->prepare('DELETE FROM celular_imeis WHERE celular_id = :id');
            $deleteImeis->execute(array(':id' => $celular->id()));
            $this->writeImeis($celular);
            $this->pdo->commit();
            return $celular;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getById(string $id): ?Celular
    {
        $statement = $this->pdo->prepare('SELECT * FROM celulares WHERE id = :id LIMIT 1');
        try {
            $statement->execute(array(':id' => $id));
            $row = $statement->fetch();
        } finally {
            $statement->closeCursor();
        }
        if ($row === false) {
            return null;
        }
        return $this->hydrateRows(array($row))[0];
    }

    public function delete(string $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM celulares WHERE id = :id');
        $statement->execute(array(':id' => $id));
    }

    public function search(CelularQuery $query): array
    {
        [$where, $parameters] = $this->conditions($query);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM celulares c' . $where);
        try {
            $count->execute($parameters);
            $total = (int) $count->fetchColumn();
        } finally {
            $count->closeCursor();
        }
        $lastPage = max(1, (int) ceil($total / $query->perPage()));
        $currentPage = min($query->page(), $lastPage);

        $sortFields = array(
            'marca' => 'c.marca', 'created_at' => 'c.created_at', 'updated_at' => 'c.updated_at',
            'ram' => 'c.ram', 'almacenamiento_principal' => 'c.almacenamiento_principal',
            'sistema_operativo' => 'c.sistema_operativo', 'operador' => 'c.operador',
        );
        $orderBy = $sortFields[$query->sort()] ?? 'c.created_at';
        $sql = 'SELECT c.* FROM celulares c' . $where . ' ORDER BY ' . $orderBy . ' ' . $query->direction()
            . ' LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $query->perPage(), PDO::PARAM_INT);
        $statement->bindValue(':offset', ($currentPage - 1) * $query->perPage(), PDO::PARAM_INT);
        try {
            $statement->execute();
            $rows = $statement->fetchAll();
        } finally {
            $statement->closeCursor();
        }

        return array('items' => $this->hydrateRows($rows), 'total' => $total, 'page' => $currentPage);
    }

    public function dashboard(): array
    {
        $totalStatement = $this->pdo->query('SELECT COUNT(*) FROM celulares');
        try {
            $total = (int) $totalStatement->fetchColumn();
        } finally {
            $totalStatement->closeCursor();
        }
        $byBrand = $this->groupedCounts('marca');
        $byOs = $this->groupedCounts('sistema_operativo');
        $featuresStatement = $this->pdo->query(
            'SELECT COALESCE(SUM(wifi), 0) AS wifi, COALESCE(SUM(bluetooth), 0) AS bluetooth, ' .
            'COALESCE(SUM(nfc), 0) AS nfc, COALESCE(SUM(resiste_agua), 0) AS resiste_agua FROM celulares'
        );
        try {
            $features = $featuresStatement->fetch();
        } finally {
            $featuresStatement->closeCursor();
        }
        $recentStatement = $this->pdo->query('SELECT * FROM celulares ORDER BY created_at DESC LIMIT 5');
        try {
            $recentRows = $recentStatement->fetchAll();
        } finally {
            $recentStatement->closeCursor();
        }

        return array(
            'total' => $total,
            'byBrand' => $byBrand,
            'byOs' => $byOs,
            'features' => array_map('intval', $features ?: array()),
            'recent' => $this->hydrateRows($recentRows),
        );
    }

    /** @param array<string, mixed> $values */
    private function bindAndExecute(PDOStatement $statement, array $values): void
    {
        foreach ($values as $name => $value) {
            $type = is_bool($value)
                ? PDO::PARAM_BOOL
                : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->execute();
    }

    private function writeImeis(Celular $celular): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO celular_imeis (imei, celular_id, sim_slot) VALUES (:imei, :id, :slot)'
        );
        foreach ($celular->imei() as $index => $imei) {
            $this->bindAndExecute($statement, array(
                'imei' => $imei,
                'id' => $celular->id(),
                'slot' => $index + 1,
            ));
        }
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return Celular[]
     */
    private function hydrateRows(array $rows): array
    {
        if ($rows === array()) {
            return array();
        }
        $ids = array_column($rows, 'id');
        $placeholders = array();
        $params = array();
        foreach ($ids as $index => $id) {
            $key = ':id' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $imeiQuery = $this->pdo->prepare(
            'SELECT celular_id, imei, sim_slot FROM celular_imeis WHERE celular_id IN (' . implode(', ', $placeholders) . ') ORDER BY sim_slot'
        );
        try {
            $imeiQuery->execute($params);
            $imeiRows = $imeiQuery->fetchAll();
        } finally {
            $imeiQuery->closeCursor();
        }
        $imeisById = array();
        foreach ($imeiRows as $imeiRow) {
            $imeisById[$imeiRow['celular_id']][] = (string) $imeiRow['imei'];
        }

        $models = array();
        foreach ($rows as $row) {
            $models[] = $this->mapper->fromRow($row, $imeisById[$row['id']] ?? array());
        }
        return $models;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function conditions(CelularQuery $query): array
    {
        $parts = array();
        $params = array();
        if ($query->search() !== '') {
            $parts[] = '(c.marca LIKE :search_marca OR c.sistema_operativo LIKE :search_os OR EXISTS (' .
                'SELECT 1 FROM celular_imeis i WHERE i.celular_id = c.id AND i.imei LIKE :search_imei))';
            $params[':search_marca'] = '%' . $query->search() . '%';
            $params[':search_os'] = '%' . $query->search() . '%';
            $params[':search_imei'] = '%' . $query->search() . '%';
        }
        foreach (array(
            'marca' => array('c.marca', $query->marca()),
            'sistema_operativo' => array('c.sistema_operativo', $query->sistemaOperativo()),
            'operador' => array('c.operador', $query->operador()),
        ) as $key => [$column, $value]) {
            if ($value !== '') {
                $parts[] = $column . ' LIKE :' . $key;
                $params[':' . $key] = '%' . $value . '%';
            }
        }
        if ($query->imei() !== '') {
            $parts[] = 'EXISTS (SELECT 1 FROM celular_imeis fi WHERE fi.celular_id = c.id AND fi.imei LIKE :imei)';
            $params[':imei'] = '%' . $query->imei() . '%';
        }
        $featureColumns = array(
            'wifi' => 'c.wifi', 'bluetooth' => 'c.bluetooth', 'nfc' => 'c.nfc',
            'huella' => 'c.huella', 'ir' => 'c.ir', 'resiste_agua' => 'c.resiste_agua',
        );
        foreach ($query->features() as $name => $value) {
            if ($value !== null && isset($featureColumns[$name])) {
                $parts[] = $featureColumns[$name] . ' = :' . $name;
                $params[':' . $name] = (int) $value;
            }
        }

        return array($parts === array() ? '' : ' WHERE ' . implode(' AND ', $parts), $params);
    }

    /** @return array<int, array{label: string, total: int}> */
    private function groupedCounts(string $column): array
    {
        $allowed = array('marca', 'sistema_operativo');
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException('Columna de agrupación no permitida.');
        }
        $statement = $this->pdo->query(
            'SELECT ' . $column . ' AS label, COUNT(*) AS total FROM celulares GROUP BY ' . $column . ' ORDER BY total DESC, label ASC LIMIT 6'
        );
        try {
            $rows = $statement->fetchAll();
        } finally {
            $statement->closeCursor();
        }
        return array_map(static fn (array $row): array => array(
            'label' => (string) $row['label'],
            'total' => (int) $row['total'],
        ), $rows);
    }
}
