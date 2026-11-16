<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>
<?php
$isEdit = $celular !== null;
$defaults = $isEdit ? $celular->toArray() : array();
$formData = array_merge($defaults, is_array($old ?? null) ? $old : array());
$imeis = is_array($formData['imei'] ?? null) ? array_values($formData['imei']) : array();
$imeis[] = '';
$booleanField = static function (string $field, string $label) use ($formData): void {
    $value = $formData[$field] ?? null;
    echo '<label><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><select name="' . $field . '" required>';
    echo '<option value="">Seleccionar</option>';
    foreach (array('1' => 'Sí', '0' => 'No') as $key => $text) {
        $selected = ($value === true || $value === 1 || $value === '1') ? $key === '1' : (($value === false || $value === 0 || $value === '0') && $key === '0');
        echo '<option value="' . $key . '"' . ($selected ? ' selected' : '') . '>' . $text . '</option>';
    }
    echo '</select></label>';
};
$fieldValue = static fn (string $field, mixed $fallback = ''): string => htmlspecialchars(
    (string) ($formData[$field] ?? $fallback), ENT_QUOTES, 'UTF-8'
);
?>
<div class="page-heading">
    <div><h1><?= $isEdit ? 'Editar celular' : 'Registrar celular' ?></h1><p class="muted">Completa las características técnicas del equipo.</p></div>
    <a class="btn btn-quiet" href="?route=celulares.index">Volver al catálogo</a>
</div>
<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<form method="POST" action="?route=celulares.<?= $isEdit ? 'update' : 'store' ?>" class="device-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= htmlspecialchars($celular->id(), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>

    <section class="card form-section"><div class="form-section-title"><div><h2>Identificación</h2><p>Datos comerciales e identificadores del equipo.</p></div></div>
        <div class="form-grid">
            <label><span>Marca <b>*</b></span><input name="marca" maxlength="80" required value="<?= $fieldValue('marca') ?>" autocomplete="organization"></label>
            <label><span>Cantidad de SIM <b>*</b></span><select name="cantidadSim" id="cantidadSim" required><option value="1" <?= ((string) ($formData['cantidadSim'] ?? '1') === '1') ? 'selected' : '' ?>>Una SIM</option><option value="2" <?= ((string) ($formData['cantidadSim'] ?? '') === '2') ? 'selected' : '' ?>>Dos SIM</option></select></label>
            <label><span>IMEI de SIM 1 <b>*</b></span><input name="imei[]" inputmode="numeric" pattern="[0-9]{15}" minlength="15" maxlength="15" required value="<?= htmlspecialchars((string) ($imeis[0] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="imei-help"><small id="imei-help">15 dígitos. El último es el dígito de control y se verifica al guardar.</small></label>
            <label id="imei-second-wrap"><span>IMEI de SIM 2 <b>*</b></span><input id="imei-second" name="imei[]" inputmode="numeric" pattern="[0-9]{15}" minlength="15" maxlength="15" value="<?= htmlspecialchars((string) ($imeis[1] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        </div>
    </section>

    <section class="card form-section"><div class="form-section-title"><div><h2>Pantalla y cámaras</h2><p>Dimensiones de pantalla y capacidad fotográfica.</p></div></div>
        <div class="form-grid">
            <label><span>Pulgadas <b>*</b></span><input type="number" name="pulgadas" min="0.01" max="100" step="0.01" required value="<?= $fieldValue('pulgadas') ?>"></label>
            <label><span>Megapíxeles <b>*</b></span><input type="number" name="megapixeles" min="0.01" max="99999" step="0.01" required value="<?= $fieldValue('megapixeles') ?>"></label>
            <label><span>Cámaras <b>*</b></span><input type="number" name="camaras" min="1" max="255" step="1" required value="<?= $fieldValue('camaras') ?>"></label>
        </div>
    </section>

    <section class="card form-section"><div class="form-section-title"><div><h2>Memoria y sistema</h2><p>Capacidades expresadas en GB.</p></div></div>
        <div class="form-grid">
            <label><span>RAM (GB) <b>*</b></span><input type="number" name="ram" min="1" max="65535" step="1" required value="<?= $fieldValue('ram') ?>"></label>
            <label><span>Almacenamiento principal (GB) <b>*</b></span><input type="number" name="almacenamientoPrincipal" min="1" max="65535" step="1" required value="<?= $fieldValue('almacenamientoPrincipal') ?>"></label>
            <label><span>Almacenamiento secundario (GB)</span><input type="number" name="almacenamientoSecundario" min="0" max="65535" step="1" value="<?= $fieldValue('almacenamientoSecundario', '0') ?>"><small>Usa 0 si no tiene almacenamiento expandible.</small></label>
            <label><span>Sistema operativo <b>*</b></span><input name="sistemaOperativo" maxlength="80" required value="<?= $fieldValue('sistemaOperativo') ?>" placeholder="Android, iOS…"></label>
        </div>
    </section>

    <section class="card form-section"><div class="form-section-title"><div><h2>Conectividad</h2><p>Redes inalámbricas y operador.</p></div></div>
        <div class="form-grid">
            <label><span>Operador <b>*</b></span><input name="operador" maxlength="80" required value="<?= $fieldValue('operador') ?>"></label>
            <label><span>Tecnología de banda <b>*</b></span><input name="tecnologiaBanda" maxlength="80" required value="<?= $fieldValue('tecnologiaBanda') ?>" placeholder="2G / 3G / 4G / 5G"></label>
            <?php $booleanField('wifi', 'Wi-Fi'); $booleanField('bluetooth', 'Bluetooth'); $booleanField('nfc', 'NFC'); ?>
        </div>
    </section>

    <section class="card form-section"><div class="form-section-title"><div><h2>Procesador y características</h2><p>Componentes y protección física.</p></div></div>
        <div class="form-grid">
            <label><span>Marca del CPU <b>*</b></span><input name="marcaCpu" maxlength="80" required value="<?= $fieldValue('marcaCpu') ?>"></label>
            <label><span>Velocidad CPU (GHz) <b>*</b></span><input type="number" name="velocidadCpu" min="0.01" max="1000" step="0.01" required value="<?= $fieldValue('velocidadCpu') ?>"></label>
            <?php $booleanField('huella', 'Lector de huella'); $booleanField('ir', 'Puerto infrarrojo'); $booleanField('resisteAgua', 'Resistencia al agua'); ?>
        </div>
    </section>

    <div class="form-submit"><a class="btn btn-quiet" href="?route=celulares.index">Cancelar</a><button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar celular' ?></button></div>
</form>
<script>
    (function () {
        const simCount = document.getElementById('cantidadSim');
        const secondField = document.getElementById('imei-second');
        const secondWrap = document.getElementById('imei-second-wrap');
        function syncSimFields() {
            const dual = simCount.value === '2';
            secondWrap.hidden = !dual;
            secondField.required = dual;
            if (!dual) secondField.value = '';
        }
        simCount.addEventListener('change', syncSimFields);
        syncSimFields();
    }());
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
