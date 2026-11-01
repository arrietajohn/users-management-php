<?php
/** @var FlightResponse $flight */
/** @var array<string, string> $values */
?>

<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

    <h1>Editar vuelo</h1>

<?php if (!empty($message)): ?>
    <div class="alert-error">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

    <form method="POST" action="?route=flights.update">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($values['id'] ?? $flight->getId()), ENT_QUOTES, 'UTF-8') ?>">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        &nbsp;
        <a class="btn" href="?route=flights.index">Cancelar</a>
    </form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>