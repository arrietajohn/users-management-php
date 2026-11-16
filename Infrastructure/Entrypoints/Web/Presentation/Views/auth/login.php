<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

<div class="auth-box">
    <h1>Iniciar sesión</h1>
    <p>Accede con tu cuenta para consultar y administrar el inventario.</p>

    <?php if (!empty($message)): ?>
        <div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="?route=auth.authenticate">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" autocomplete="username"
                value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autofocus
                <?= !empty($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (!empty($errors['email'])): ?>
                <div class="field-error" id="email-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="current-password"
                <?= !empty($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
            <?php if (!empty($errors['password'])): ?>
                <div class="field-error" id="password-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>

    <p class="auth-links"><a href="?route=auth.forgot">¿Olvidaste tu contraseña?</a></p>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
