<?php
// --- Views/header.php ---
// Este archivo es un 'partial' o 'include'.
// Asume que BASE_URL es una constante global definida en index.php.
// También asume que la sesión ya está iniciada por index.php.

$base_url_header = defined('BASE_URL') ? BASE_URL : '';
$username_header = $_SESSION['username'] ?? 'Invitado';
$is_logged_in_header = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$site_name_or_logo_alt = $pageTitle ?? 'Markdown2Video'; // $pageTitle vendría del controlador que incluye este header

?>
<!-- Header con navbar -->
<header class="header-container"> <!-- Tu CSS podría tener estilos para esta clase también -->
    <nav class="navbar">
        <div class="navbar-left">
            <a href="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/<?php echo $is_logged_in_header ? 'dashboard' : 'auth/login'; ?>">
                <img src="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/Assets/imagen/logo_corto.png" alt="Logo <?php echo htmlspecialchars($site_name_or_logo_alt, ENT_QUOTES, 'UTF-8'); ?>" class="logo-img">
            </a>
        </div>
        <div class="navbar-right">
            <?php if ($is_logged_in_header): ?>
                <div class="user-profile">
                    <span>Hola, <?php echo htmlspecialchars($username_header, ENT_QUOTES, 'UTF-8'); ?></span>
                    <img src="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/Assets/imagen/usuario.png" alt="Perfil de <?php echo htmlspecialchars($username_header, ENT_QUOTES, 'UTF-8'); ?>" class="usuario-img">
                    <a href="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/auth/logout" class="logout-link" style="margin-left:10px; color:white; text-decoration:none;">Cerrar Sesión</a>
                </div>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/auth/login" class="login-link" style="color:white; text-decoration:none; margin-right:10px;">Iniciar Sesión</a>
                <a href="<?php echo htmlspecialchars($base_url_header, ENT_QUOTES, 'UTF-8'); ?>/auth/register" class="register-link" style="color:white; text-decoration:none;">Registrarse</a>
            <?php endif; ?>
        </div>
    </nav>
</header>