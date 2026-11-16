<?php $authUser = $_SESSION['auth'] ?? null; $currentRoute = (string) ($_GET['route'] ?? 'home'); ?>
<aside class="sidebar" aria-label="Navegación principal">
    <a class="brand" href="?route=home"><span class="brand-mark">M</span><span>Móvil Admin<small>Inventario de celulares</small></span></a>
    <p class="nav-label">Menú</p>
    <nav class="side-nav">
        <?php if ($authUser && RolePermissions::allows((string) ($authUser['role'] ?? ''), 'dashboard.read')): ?>
            <a class="<?= $currentRoute === 'home' ? 'active' : '' ?>" href="?route=home"><span aria-hidden="true">▦</span> Resumen</a>
        <?php endif; ?>
        <?php if ($authUser && RolePermissions::allows((string) ($authUser['role'] ?? ''), 'celulares.read')): ?>
            <a class="<?= str_starts_with($currentRoute, 'celulares.') ? 'active' : '' ?>" href="?route=celulares.index"><span aria-hidden="true">▣</span> Celulares</a>
        <?php endif; ?>
        <?php if ($authUser && RolePermissions::allows((string) ($authUser['role'] ?? ''), 'users.read')): ?>
            <a class="<?= str_starts_with($currentRoute, 'users.') ? 'active' : '' ?>" href="?route=users.index"><span aria-hidden="true">♙</span> Usuarios</a>
        <?php endif; ?>
        <?php if (!$authUser): ?>
            <a class="<?= str_starts_with($currentRoute, 'auth.login') ? 'active' : '' ?>" href="?route=auth.login">Iniciar sesión</a>
            <a href="?route=auth.forgot">Recuperar contraseña</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <?php if ($authUser): ?>
            <div class="profile-card"><span class="avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) $authUser['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><span><strong><?= htmlspecialchars((string) $authUser['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(RolePermissions::normalizeRole((string) ($authUser['role'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small></span></div>
            <form method="POST" action="?route=auth.logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="nav-logout" type="submit">↪ <span>Cerrar sesión</span></button>
            </form>
        <?php endif; ?>
    </div>
</aside>
<main class="page-main">
    <div class="page-context"><span>Móvil Admin</span><span><?= htmlspecialchars($pageTitle ?? 'Panel', ENT_QUOTES, 'UTF-8') ?></span></div>
