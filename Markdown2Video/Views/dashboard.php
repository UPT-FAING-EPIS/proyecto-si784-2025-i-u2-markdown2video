<?php
// --- Views/dashboard.php ---
// Asegurarse de que las variables esperadas existan con valores por defecto
$base_url = $base_url ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';
$templates = $templates ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- CSS del Header y Dashboard -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/header.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/dashboard.css">
    
    <!-- Estilos para las plantillas (puedes moverlos a dashboard.css si prefieres) -->
    <style>
        .start-section {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .start-options {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 15px;
        }
        .templates-section {
            margin-top: 40px;
        }
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .template-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .template-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-bottom: 1px solid #e9ecef;
        }
        .template-card-content {
            padding: 20px;
            flex-grow: 1;
        }
        .template-card h4 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
        }
        .template-card p {
            font-size: 0.9em;
            color: #6c757d;
            margin: 0;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <?php if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'header.php')) { include VIEWS_PATH . 'header.php'; } ?>

    <!-- Contenedor principal para las acciones -->
    <div class="main-actions-container">

        <!-- Columna Izquierda: Crear desde Cero -->
        <div class="start-section">
            <h2>Empieza</h2>
            <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/markdown/create">
                <button class="btn-historical">Creando desde Cero +</button>
            </a>
        </div>

        <!-- Columna Derecha: Plantillas -->
        <div class="templates-section">
            <h3>...o empieza desde una Plantilla Markdown</h3>
            <div class="templates-container">
                <div class="templates-row">
                    <?php if (!empty($templates)): ?>
                        <?php foreach ($templates as $template): ?>
                            <a href="<?php echo htmlspecialchars($base_url . '/markdown/create-from-template/' . $template['id_template'], ENT_QUOTES, 'UTF-8'); ?>" class="template-card">
                                <img src="<?php echo htmlspecialchars($base_url . '/public/assets/imagen/' . $template['preview_image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="template-card-content">
                                    <h4><?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p><?php echo htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No hay plantillas disponibles en este momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div> <!-- Fin de main-actions-container -->
        
        <!-- SECCIÓN DE HISTORIAL (opcional) -->
        <!-- <div class="dashboard-content">
            <div class="historical">
                <h3>Historial</h3>
                <div class="historical-content">
                     <p>El historial de tus presentaciones aparecerá aquí...</p>
                </div>
            </div>
        </div> -->
    </div>

</body>
</html>