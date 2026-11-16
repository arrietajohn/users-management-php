<?php require __DIR__ . '/layouts/header.php'; ?>
<?php require __DIR__ . '/layouts/menu.php'; ?>
<div class="page-heading">
    <div><h1>Resumen</h1><p class="muted">Estado actual del inventario y de las cuentas de usuario.</p></div>
</div>
<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<section class="metric-grid" aria-label="Indicadores principales">
    <article class="card metric-card"><span class="metric-icon blue">▣</span><div><p>Celulares registrados</p><strong><?= number_format((int) ($dashboard['total'] ?? 0)) ?></strong></div><a href="?route=celulares.index">Ver catálogo</a></article>
    <article class="card metric-card"><span class="metric-icon violet">♙</span><div><p>Usuarios activos</p><strong><?= number_format((int) ($dashboard['activeUsers'] ?? 0)) ?></strong></div><?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'users.read')): ?><a href="?route=users.index">Ver usuarios</a><?php else: ?><span class="metric-note">Cuentas habilitadas</span><?php endif; ?></article>
    <article class="card metric-card"><span class="metric-icon green">⌁</span><div><p>Equipos con NFC</p><strong><?= number_format((int) (($dashboard['features']['nfc'] ?? 0))) ?></strong></div><span class="metric-note">De <?= number_format((int) ($dashboard['total'] ?? 0)) ?> equipos</span></article>
    <article class="card metric-card"><span class="metric-icon amber">◉</span><div><p>Resistentes al agua</p><strong><?= number_format((int) (($dashboard['features']['resiste_agua'] ?? 0))) ?></strong></div><span class="metric-note">Dato del catálogo</span></article>
</section>

<div class="dashboard-grid">
    <section class="card chart-card"><div class="section-header"><div><h2>Equipos por marca</h2><p class="muted">Distribución de registros actuales</p></div></div>
        <?php if (empty($dashboard['byBrand'])): ?><div class="compact-empty">Los indicadores aparecerán al registrar celulares.</div><?php else: ?>
            <?php $maxBrand = max(array_column($dashboard['byBrand'], 'total')); foreach ($dashboard['byBrand'] as $item): ?>
                <div class="bar-row"><div><span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($item['total']) ?></strong></div><div class="bar-track"><span style="width: <?= (int) round(($item['total'] / max(1, $maxBrand)) * 100) ?>%"></span></div></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <section class="card chart-card"><div class="section-header"><div><h2>Sistema operativo</h2><p class="muted">Distribución de registros actuales</p></div></div>
        <?php if (empty($dashboard['byOs'])): ?><div class="compact-empty">Aún no hay datos para comparar.</div><?php else: ?>
            <?php $maxOs = max(array_column($dashboard['byOs'], 'total')); foreach ($dashboard['byOs'] as $item): ?>
                <div class="bar-row"><div><span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($item['total']) ?></strong></div><div class="bar-track purple"><span style="width: <?= (int) round(($item['total'] / max(1, $maxOs)) * 100) ?>%"></span></div></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<section class="card recent-card"><div class="section-header"><div><h2>Registros recientes</h2><p class="muted">Últimos celulares incorporados al catálogo</p></div><a href="?route=celulares.index">Ver todos</a></div>
    <?php if (empty($dashboard['recent'])): ?><div class="empty-state compact"><span class="empty-icon" aria-hidden="true">▣</span><h3>Aún no hay celulares registrados</h3><p>Cuando se registren equipos, aparecerán aquí.</p><?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.create')): ?><a class="btn btn-primary" href="?route=celulares.create">Registrar celular</a><?php endif; ?></div>
    <?php else: ?><div class="recent-list"><?php foreach ($dashboard['recent'] as $item): ?><a href="?route=celulares.show&amp;id=<?= urlencode($item->id()) ?>"><span class="device-avatar small" aria-hidden="true">▣</span><span><strong><?= htmlspecialchars($item->marca(), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($item->sistemaOperativo(), ENT_QUOTES, 'UTF-8') ?> <?= View::imeiList($item->imei()) ?></small></span></a><?php endforeach; ?></div><?php endif; ?>
</section>

<?php if (!empty($dashboard['activity'])): ?>
    <?php $activityLabels = array('user.created' => 'Usuario creado', 'user.updated' => 'Usuario actualizado', 'user.deleted' => 'Usuario eliminado', 'celular.created' => 'Celular registrado', 'celular.updated' => 'Celular actualizado', 'celular.deleted' => 'Celular eliminado', 'auth.login' => 'Inicio de sesión', 'auth.logout' => 'Cierre de sesión', 'auth.password_reset' => 'Contraseña restablecida'); ?>
    <section class="card recent-card activity-card"><div class="section-header"><div><h2>Actividad reciente</h2><p class="muted">Operaciones registradas por el sistema</p></div></div>
        <ol class="activity-list">
            <?php foreach ($dashboard['activity'] as $entry): ?>
                <li><span class="activity-dot" aria-hidden="true"></span><div><strong><?= htmlspecialchars($activityLabels[$entry['action']] ?? 'Operación registrada', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($entry['actor_name'] ?? 'Sistema', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($entry['subject_type'], ENT_QUOTES, 'UTF-8') ?></small></div><time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($entry['created_at'])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d/m H:i', strtotime($entry['created_at'])), ENT_QUOTES, 'UTF-8') ?></time></li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/layouts/footer.php'; ?>
