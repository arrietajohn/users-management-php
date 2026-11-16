<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>
<div class="auth-box">
    <h1>Crear nueva contraseña</h1>
    <p>Usa al menos 8 caracteres. El enlace deja de funcionar al guardarla.</p>
    <?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="POST" action="?route=auth.reset.submit">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="password">Nueva contraseña</label>
            <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <?php if (!empty($errors['password'])): ?><div class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Repite la contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <?php if (!empty($errors['password_confirmation'])): ?><div class="field-error"><?= htmlspecialchars($errors['password_confirmation'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
    </form>
</div>
<script>
    (function () {
        const tokenField = document.querySelector('input[name="token"]');
        const fragmentToken = new URLSearchParams(window.location.hash.slice(1)).get('token');
        if (tokenField && fragmentToken && /^[a-f0-9]{64}$/.test(fragmentToken)) {
            tokenField.value = fragmentToken;
        }
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }());
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
