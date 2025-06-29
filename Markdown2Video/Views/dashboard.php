<?php
// --- Views/dashboard.php ---
// Asegurarse de que las variables esperadas existan con valores por defecto
$base_url = $base_url ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';
$markdownTemplates = $markdownTemplates ?? [];
$marpTemplates = $marpTemplates ?? [];
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
        /* Contenedor principal del dashboard */
        .dashboard-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Grid para las acciones de inicio */
        .quick-start-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        /* Estilo común para las tarjetas de acción */
        .start-section {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            text-align: center;
        }
        .start-section h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .btn-historical { /* Tu botón original */
            background-color: #6b56f0;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-historical:hover {
            background-color: #5e46e7;
            transform: translateY(-2px);
        }

        /* Estilos para la tarjeta de arrastrar y soltar */
        .drop-zone-dashboard {
            border: 2px dashed #d1d5db;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .drop-zone-dashboard:hover,
        .drop-zone-dashboard.drag-over {
            border-color: #6b56f0;
            background-color: #f7f5ff;
        }
        .drop-zone-dashboard p {
            color: #6c757d;
            font-size: 0.9em;
            margin: 0 0 15px 0;
        }
        .drop-zone-dashboard i {
            font-size: 2.5em;
            color: #a5b4fc;
            margin-top: 10px;
        }

        /* Sección de plantillas */
        .templates-section {
            margin-top: 40px;
        }
        .templates-section h3 {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 20px;
        }
        .templates-container {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 15px;
        }
        .templates-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 25px;
        }
        .template-card {
            flex: 0 0 280px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            background-color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .template-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-bottom: 1px solid #e9ecef;
        }
        .template-card-content { padding: 15px; }
        .template-card h4 { margin: 0 0 8px 0; font-size: 1.05em; }
        .template-card p { font-size: 0.9em; color: #6c757d; line-height: 1.5; }
    </style>
</head>
<body>

    <?php if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'header.php')) { include VIEWS_PATH . 'header.php'; } ?>

    <div class="dashboard-container">
        
        <!-- Contenedor para las acciones de inicio rápido -->
        <div class="quick-start-grid">
            <!-- Tarjeta para Crear desde Cero -->
            <div class="start-section">
                <h2>Empieza</h2>
                <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/markdown/create">
                    <button class="btn-historical">Creando desde Cero +</button>
                </a>
            </div>

            <!-- Tarjeta para Subir/Arrastrar Archivo -->
            <div id="dropZoneDashboard" class="start-section drop-zone-dashboard">
                <h2>Abrir Archivo</h2>
                <p>Arrastra un archivo <code>.md</code> o haz clic para seleccionarlo.</p>
                <i class="fa-solid fa-file-arrow-up"></i>
                <input type="file" id="fileInputDashboard" accept=".md,.markdown,text/markdown" style="display: none;">
            </div>
        </div>

        <!-- SECCIÓN DE PLANTILLAS MARKDOWN -->
        <div class="templates-section">
            <h3>...o empieza desde una Plantilla Markdown</h3>
            <div class="templates-container">
                <div class="templates-row">
                    <?php if (!empty($markdownTemplates)): ?>
                        <?php foreach ($markdownTemplates as $template): ?>
                            <a href="<?php echo htmlspecialchars($base_url . '/markdown/create-from-template/' . $template['id_template'], ENT_QUOTES, 'UTF-8'); ?>" class="template-card">
                                <img src="<?php echo htmlspecialchars($base_url . '/public/assets/imagen/' . $template['preview_image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="template-card-content">
                                    <h4><?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p><?php echo htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No hay plantillas Markdown disponibles en este momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN DE PLANTILLAS MARP -->
        <div class="templates-section">
            <h3>...o empieza desde una Plantilla Marp</h3>
            <div class="templates-container">
                <div class="templates-row">
                    <?php if (!empty($marpTemplates)): ?>
                        <?php foreach ($marpTemplates as $template): ?>
                            <a href="<?php echo htmlspecialchars($base_url . '/markdown/marp-editor?template_id=' . $template['id_template'], ENT_QUOTES, 'UTF-8'); ?>" class="template-card">
                                <img src="<?php echo htmlspecialchars($base_url . '/public/assets/imagen/' . $template['preview_image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="template-card-content">
                                    <h4><?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p><?php echo htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No hay plantillas Marp disponibles en este momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN DE HISTORIAL (opcional) -->
        <!-- ... tu sección de historial ... -->

    </div>

    <!-- Cargar Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Cargar los scripts necesarios -->
    <script>
        // Pasamos la variable BASE_URL de PHP a JavaScript
        window.BASE_APP_URL = <?php echo json_encode($base_url); ?>;
    </script>
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/js/dashboard_handler.js"></script>

</body>
</html>