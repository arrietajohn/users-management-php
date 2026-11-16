<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

<div class="auth-box">
    <h1>Recuperar contraseña</h1>
    <p>Introduce el correo de tu cuenta. Si está activa, recibirás un enlace de un solo uso válido durante 30 minutos.</p>

    <?php if (!empty($message)): ?>
        <div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="?route=auth.forgot.send">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" autocomplete="email"
                value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autofocus
                <?= !empty($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (!empty($errors['email'])): ?>
                <div class="field-error" id="email-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Enviar enlace seguro</button>
    </form>

    <p class="auth-links"><a href="?route=auth.login">Volver al inicio de sesión</a></p>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
