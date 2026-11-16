<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>
<?php $statusLabels = array('ACTIVE' => 'Activo', 'INACTIVE' => 'Inactivo', 'PENDING' => 'Pendiente', 'BLOCKED' => 'Bloqueado'); ?>

<div class="page-heading">
    <div><h1><?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?></h1><p class="muted">Detalle de la cuenta.</p></div>
    <div class="heading-actions"><a class="btn btn-quiet" href="?route=users.index">Volver al listado</a>
    <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'users.write')): ?><a class="btn btn-primary" href="?route=users.edit&amp;id=<?= urlencode($user->getId()) ?>">Editar usuario</a><?php endif; ?></div>
</div>

<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<section class="card detail-card">
    <div class="device-summary"><span class="avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($user->getName(), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><div><h2><?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    <section class="detail-group"><h3>Acceso</h3><dl class="detail-grid">
        <div><dt>Rol</dt><dd><?= htmlspecialchars($user->getRole(), ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Estado</dt><dd><?= htmlspecialchars($statusLabels[$user->getStatus()] ?? $user->getStatus(), ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>ID</dt><dd><code><?= htmlspecialchars($user->getId(), ENT_QUOTES, 'UTF-8') ?></code></dd></div>
    </dl></section>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
