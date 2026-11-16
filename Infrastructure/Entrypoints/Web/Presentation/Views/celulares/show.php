<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>
<div class="page-heading">
    <div><h1><?= htmlspecialchars($celular->marca(), ENT_QUOTES, 'UTF-8') ?></h1><p class="muted">Ficha técnica del equipo.</p></div>
    <div class="heading-actions"><a class="btn btn-quiet" href="?route=celulares.index">Volver</a>
    <?php if (RolePermissions::allows((string) ($_SESSION['auth']['role'] ?? ''), 'celulares.update')): ?><a class="btn btn-primary" href="?route=celulares.edit&amp;id=<?= urlencode($celular->id()) ?>">Editar equipo</a><?php endif; ?></div>
</div>
<section class="card detail-card">
    <div class="device-summary"><span class="device-avatar" aria-hidden="true">▣</span><div><h2><?= htmlspecialchars($celular->marca(), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($celular->sistemaOperativo(), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($celular->operador(), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    <?php
    $groups = array(
        'Identificación' => array('IMEI' => implode(' · ', $celular->imei()), 'Cantidad de SIM' => (string) $celular->cantidadSim()),
        'Pantalla y cámaras' => array('Pantalla (pulgadas)' => $celular->pulgadas(), 'Megapíxeles' => $celular->megapixeles(), 'Cámaras' => (string) $celular->camaras()),
        'Memoria y sistema' => array('RAM (GB)' => (string) $celular->ram(), 'Almacenamiento principal (GB)' => (string) $celular->almacenamientoPrincipal(), 'Almacenamiento secundario (GB)' => (string) $celular->almacenamientoSecundario(), 'Sistema operativo' => $celular->sistemaOperativo()),
        'Conectividad' => array('Operador' => $celular->operador(), 'Tecnología de banda' => $celular->tecnologiaBanda(), 'Wi-Fi' => $celular->wifi() ? 'Sí' : 'No', 'Bluetooth' => $celular->bluetooth() ? 'Sí' : 'No', 'NFC' => $celular->nfc() ? 'Sí' : 'No'),
        'Procesador y características' => array('Marca CPU' => $celular->marcaCpu(), 'Velocidad CPU (GHz)' => $celular->velocidadCpu(), 'Lector de huella' => $celular->huella() ? 'Sí' : 'No', 'Puerto infrarrojo' => $celular->ir() ? 'Sí' : 'No', 'Resiste agua' => $celular->resisteAgua() ? 'Sí' : 'No'),
    );
    ?>
    <?php foreach ($groups as $title => $items): ?>
        <section class="detail-group"><h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3><dl class="detail-grid">
            <?php foreach ($items as $label => $value): ?><div><dt><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $label === 'IMEI' ? View::imeiList($celular->imei()) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endforeach; ?>
        </dl></section>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
