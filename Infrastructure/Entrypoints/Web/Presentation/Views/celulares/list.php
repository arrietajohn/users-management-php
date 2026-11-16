<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

<div class="page-heading">
    <div><h1>Celulares</h1><p class="muted">Consulta y administra los equipos registrados.</p></div>
    <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.create')): ?>
        <a class="btn btn-primary" href="?route=celulares.create">Registrar celular</a>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<section class="card filter-card" aria-label="Filtros de celulares">
    <form method="GET" class="filter-form">
        <input type="hidden" name="route" value="celulares.index">
        <label class="search-field"><span>Buscar</span><input type="search" name="q" placeholder="Marca, sistema o IMEI" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <label><span>Marca</span><input name="marca" value="<?= htmlspecialchars((string) ($filters['marca'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Cualquier marca"></label>
        <label><span>Sistema operativo</span><input name="sistema_operativo" value="<?= htmlspecialchars((string) ($filters['sistema_operativo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Cualquiera"></label>
        <label><span>Operador</span><input name="operador" value="<?= htmlspecialchars((string) ($filters['operador'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Cualquiera"></label>
        <label><span>Ordenar por</span><select name="sort">
            <?php foreach (array('created_at' => 'Más recientes', 'marca' => 'Marca', 'ram' => 'RAM', 'almacenamiento_principal' => 'Almacenamiento', 'sistema_operativo' => 'Sistema operativo', 'operador' => 'Operador') as $key => $label): ?>
                <option value="<?= $key ?>" <?= (($filters['sort'] ?? 'created_at') === $key) ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select></label>
        <label><span>Dirección</span><select name="direction"><option value="DESC" <?= (($filters['direction'] ?? 'DESC') === 'DESC') ? 'selected' : '' ?>>Descendente</option><option value="ASC" <?= (($filters['direction'] ?? '') === 'ASC') ? 'selected' : '' ?>>Ascendente</option></select></label>
        <label><span>Wi-Fi</span><select name="wifi"><option value="">Cualquiera</option><option value="1" <?= (($filters['wifi'] ?? '') === '1') ? 'selected' : '' ?>>Sí</option><option value="0" <?= (($filters['wifi'] ?? '') === '0') ? 'selected' : '' ?>>No</option></select></label>
        <label><span>NFC</span><select name="nfc"><option value="">Cualquiera</option><option value="1" <?= (($filters['nfc'] ?? '') === '1') ? 'selected' : '' ?>>Sí</option><option value="0" <?= (($filters['nfc'] ?? '') === '0') ? 'selected' : '' ?>>No</option></select></label>
        <label><span>Resiste agua</span><select name="resiste_agua"><option value="">Cualquiera</option><option value="1" <?= (($filters['resiste_agua'] ?? '') === '1') ? 'selected' : '' ?>>Sí</option><option value="0" <?= (($filters['resiste_agua'] ?? '') === '0') ? 'selected' : '' ?>>No</option></select></label>
        <div class="filter-actions"><button type="submit" class="btn btn-primary">Aplicar filtros</button><a class="btn btn-quiet" href="?route=celulares.index">Limpiar</a></div>
    </form>
</section>

<section class="card table-card">
    <div class="section-header"><div><h2>Equipos</h2><p class="muted"><?= number_format($page->total()) ?> resultados</p></div><span class="result-count">Página <?= $page->page() ?> de <?= $page->pages() ?></span></div>
    <?php if ($page->items() === array()): ?>
        <div class="empty-state"><span class="empty-icon" aria-hidden="true">▣</span><h3>No hay celulares para mostrar</h3><p>Prueba quitando filtros o registra el primer equipo del inventario.</p>
            <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.create')): ?><a class="btn btn-primary" href="?route=celulares.create">Registrar celular</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Equipo</th><th>IMEI</th><th>Memoria</th><th>Conectividad</th><th>Operador</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody>
            <?php foreach ($page->items() as $celular): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($celular->marca(), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($celular->sistemaOperativo(), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($celular->pulgadas(), ENT_QUOTES, 'UTF-8') ?>&Prime;</small></td>
                    <td><?= View::imeiList($celular->imei()) ?><small><?= $celular->cantidadSim() === 2 ? 'Dos SIM' : 'Una SIM' ?></small></td>
                    <td><?= $celular->ram() ?> GB RAM<small><?= $celular->almacenamientoPrincipal() ?> GB almacenamiento</small></td>
                    <td><span class="status-pill <?= $celular->wifi() ? 'is-on' : 'is-off' ?>"><?= $celular->wifi() ? 'Wi-Fi' : 'Sin Wi-Fi' ?></span> <span class="status-pill <?= $celular->nfc() ? 'is-on' : 'is-off' ?>"><?= $celular->nfc() ? 'NFC' : 'Sin NFC' ?></span></td>
                    <td><?= htmlspecialchars($celular->operador(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="row-actions"><a class="icon-action" aria-label="Ver <?= htmlspecialchars($celular->marca(), ENT_QUOTES, 'UTF-8') ?>" href="?route=celulares.show&amp;id=<?= urlencode($celular->id()) ?>">Ver</a>
                        <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.update')): ?><a class="icon-action" href="?route=celulares.edit&amp;id=<?= urlencode($celular->id()) ?>">Editar</a><?php endif; ?>
                        <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.delete')): ?><form method="POST" action="?route=celulares.delete" onsubmit="return confirm('¿Eliminar este celular? Esta acción no se puede deshacer.');"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= htmlspecialchars($celular->id(), ENT_QUOTES, 'UTF-8') ?>"><button class="icon-action danger-link" type="submit">Eliminar</button></form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($page->pages() > 1): ?>
            <?php $queryParams = $_GET; unset($queryParams['route']); ?>
            <nav class="pagination" aria-label="Paginación">
                <?php if ($page->page() > 1): ?><?php $queryParams['page'] = $page->page() - 1; ?><a class="btn btn-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge(array('route' => 'celulares.index'), $queryParams)), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
                <span><?= $page->page() ?> / <?= $page->pages() ?></span>
                <?php if ($page->page() < $page->pages()): ?><?php $queryParams['page'] = $page->page() + 1; ?><a class="btn btn-quiet" href="?<?= htmlspecialchars(http_build_query(array_merge(array('route' => 'celulares.index'), $queryParams)), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
