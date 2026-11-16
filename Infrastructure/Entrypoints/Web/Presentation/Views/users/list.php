<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>
<?php
$canWrite = RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'users.write');
$statusLabels = array('ACTIVE' => array('Activo', 'is-ok'), 'INACTIVE' => array('Inactivo', 'is-off'), 'PENDING' => array('Pendiente', ''), 'BLOCKED' => array('Bloqueado', 'is-danger'));
?>

<div class="page-heading">
    <div><h1>Usuarios</h1><p class="muted">Cuentas, roles y estado de acceso.</p></div>
    <?php if ($canWrite): ?><a class="btn btn-primary" href="?route=users.create">Registrar usuario</a><?php endif; ?>
</div>

<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<section class="card table-card">
    <div class="section-header"><div><h2>Cuentas</h2><p class="muted"><?= number_format($page->total()) ?> usuarios</p></div><span class="result-count">Página <?= $page->page() ?> de <?= max(1, $page->pages()) ?></span></div>
    <?php if ($page->total() === 0): ?>
        <div class="empty-state"><span class="empty-icon" aria-hidden="true">♙</span><h3>Todavía no hay usuarios</h3><p>Registra la primera cuenta para dar acceso al inventario.</p>
            <?php if ($canWrite): ?><a class="btn btn-primary" href="?route=users.create">Registrar usuario</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table users-table">
            <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th>ID</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
                <?php foreach ($page->users() as $user): ?>
                    <?php [$statusText, $statusClass] = $statusLabels[$user->getStatus()] ?? array($user->getStatus(), ''); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars($user->getRole(), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><code><?= htmlspecialchars($user->getId(), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td class="row-actions">
                            <a class="icon-action" aria-label="Ver <?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?>" href="?route=users.show&amp;id=<?= urlencode($user->getId()) ?>">Ver</a>
                            <?php if ($canWrite): ?>
                                <a class="icon-action" aria-label="Editar <?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?>" href="?route=users.edit&amp;id=<?= urlencode($user->getId()) ?>">Editar</a>
                                <form method="POST" action="?route=users.delete" onsubmit="return confirmDelete(this);"
                                    data-name="<?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($user->getId(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="icon-action danger-link">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if ($page->pages() > 1): ?>
            <nav class="pagination" aria-label="Paginación de usuarios">
                <?php if ($page->page() > 1): ?><a class="btn btn-quiet" href="?route=users.index&amp;page=<?= $page->page() - 1 ?>">Anterior</a><?php endif; ?>
                <span><?= $page->page() ?> / <?= $page->pages() ?></span>
                <?php if ($page->page() < $page->pages()): ?><a class="btn btn-quiet" href="?route=users.index&amp;page=<?= $page->page() + 1 ?>">Siguiente</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
