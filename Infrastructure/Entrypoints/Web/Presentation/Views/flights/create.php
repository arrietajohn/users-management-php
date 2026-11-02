<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

    <h1>Registrar vuelo</h1>

<?php if (!empty($message)): ?>
    <div class="alert-error">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

    <form method="POST" action="?route=flights.store">

        <?php require __DIR__ . '/_form_fields.php'; ?>

        <button type="submit" class="btn btn-primary">Registrar vuelo</button>
        &nbsp;
        <a class="btn" href="?route=flights.index">Cancelar</a>
    </form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>