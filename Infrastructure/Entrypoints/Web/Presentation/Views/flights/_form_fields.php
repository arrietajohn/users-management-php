<?php
// Campos compartidos por los formularios de crear y editar vuelo.
// Variables esperadas: $values, $errors, $isEdit y (al editar) $statusOptions.

/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var bool $isEdit */
/** @var string[] $statusOptions */

$dateValue = static function (string $value): string {
    return $value === '' ? '' : substr(str_replace(' ', 'T', $value), 0, 16);
};

$textFields = array(
    'flight_number' => 'Número de vuelo (ejemplo: AV8523)',
    'airline' => 'Aerolínea',
    'travel_agency' => 'Agencia de viajes',
    'customer' => 'Cliente',
    'seat' => 'Puesto (ejemplo: 12A)',
    'aircraft' => 'Avión',
    'pilot' => 'Piloto',
    'departure_airport' => 'Aeropuerto de salida',
    'arrival_airport' => 'Aeropuerto de llegada',
);

$dateFields = array(
    'purchase_date' => 'Fecha de compra',
    'departure_date' => 'Fecha de salida',
    'arrival_date' => 'Fecha de llegada',
);
?>

<?php foreach ($textFields as $field => $label): ?>
    <div class="form-group">
        <label for="<?= $field ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label><br>
        <input type="text" id="<?= $field ?>" name="<?= $field ?>"
               value="<?= htmlspecialchars((string) ($values[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors[$field])): ?>
            <div class="field-error"><?= htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php foreach ($dateFields as $field => $label): ?>
    <div class="form-group">
        <label for="<?= $field ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label><br>
        <input type="datetime-local" id="<?= $field ?>" name="<?= $field ?>"
               value="<?= htmlspecialchars($dateValue((string) ($values[$field] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors[$field])): ?>
            <div class="field-error"><?= htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

    <div class="form-group">
        <label for="price">Valor</label><br>
        <input type="number" step="0.01" min="0" id="price" name="price"
               value="<?= htmlspecialchars((string) ($values['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors['price'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

<?php if (!empty($isEdit)): ?>
    <div class="form-group">
        <label for="status">Estado</label><br>
        <select id="status" name="status">
            <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($values['status'] ?? '') === $opt) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['status'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['status'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>