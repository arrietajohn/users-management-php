<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../layouts/menu.php'; ?>

<div class="page-heading"><div><h1>Editar usuario</h1><p class="muted">Actualiza la cuenta, el rol o el estado.</p></div><a class="btn btn-quiet" href="?route=users.index">Volver a usuarios</a></div>

<?php if (!empty($message)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<form method="POST" action="?route=users.update" class="card form-section user-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($old['id'] ?? $user->getId(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label for="name"><span>Nombre <b>*</b></span>
        <input type="text" id="name" name="name" maxlength="120" required
            value="<?= htmlspecialchars($old['name'] ?? $user->getName(), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors['name'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?></label>
    </div>

    <div class="form-group">
        <label for="email"><span>Correo electrónico <b>*</b></span>
        <input type="email" id="email" name="email" maxlength="254" required autocomplete="email"
            value="<?= htmlspecialchars($old['email'] ?? $user->getEmail(), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors['email'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?></label>
    </div>

    <div class="form-group">
        <label for="password"><span>Contraseña</span><small>Déjala en blanco para conservar la actual.</small>
        <input type="password" id="password" name="password" value="" autocomplete="new-password">
        <?php if (!empty($errors['password'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?></label>
    </div>

    <div class="form-group">
        <label for="role"><span>Rol <b>*</b></span>
        <select id="role" name="role" required>
            <?php foreach ($roleOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= (($old['role'] ?? $user->getRole()) === $opt) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['role'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['role'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?></label>
    </div>

    <div class="form-group">
        <label for="status"><span>Estado <b>*</b></span>
        <select id="status" name="status" required>
            <?php $statusLabels = array('ACTIVE' => 'Activo', 'INACTIVE' => 'Inactivo', 'PENDING' => 'Pendiente', 'BLOCKED' => 'Bloqueado'); ?>
            <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= (($old['status'] ?? $user->getStatus()) === $opt) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($statusLabels[$opt] ?? $opt, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['status'])): ?>
            <div class="field-error"><?= htmlspecialchars($errors['status'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?></label>
    </div>

    <div class="form-submit"><a class="btn btn-quiet" href="?route=users.index">Cancelar</a><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
