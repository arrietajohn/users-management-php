<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle ?? 'Gestión de celulares', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= (int) @filemtime(__DIR__ . '/../../../../../../public/assets/css/styles.css') ?>">
</head>

<body>
<div class="app-shell">
    <header class="topbar">
        <a class="mobile-brand" href="?route=home"><span class="brand-mark">M</span> Móvil Admin</a>
        <span class="topbar-caption">Inventario de celulares y cuentas de acceso</span>
        <?php if (isset($_SESSION['auth']['name'])): ?>
            <span class="topbar-user"><?= htmlspecialchars((string) $_SESSION['auth']['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </header>
    <div class="app-layout">
