<?php
$base_url = $base_url ?? ''; 
$csrf_token = $csrf_token ?? ''; 
$error_message = $error_message ?? null; 
$success_message = $success_message ?? null; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/login.css">
    <title>Iniciar Sesión</title>
    <style>
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <?php if ($error_message): ?>
                <p class="message error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="message success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form class="login-form" action="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/auth/processlogin" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="input-group">
                    <h2>INICIAR SESIÓN</h2>
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" placeholder="tu@correo.com" required autofocus>
                </div>
                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Contraseña" required>
                </div>
                <button type="submit">Ingresar Ahora</button>

                <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/auth/register">¿No tienes una cuenta? Regístrate</a>
            </form>
        </div>
        <img class="login-image" src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/Assets/imagen/logo.png" alt="Imagen de Login">
    </div>
</body>
</html>