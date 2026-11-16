<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Restablecer contraseña</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f6f8;padding:24px;color:#182230">
    <main style="max-width:560px;margin:24px auto;background:white;padding:32px;border:1px solid #dce3eb;border-radius:12px">
        <h1 style="font-size:22px">Hola, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Recibimos una solicitud para restablecer la contraseña de <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>.</p>
        <p><a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>">Crear una nueva contraseña</a></p>
        <p>El enlace vence en 30 minutos y solo se puede usar una vez. Si no solicitaste el cambio, ignora este correo.</p>
    </main>
</body>
</html>
