<?php
// --- Views/markdown/download_pdf.php ---
// Variables pasadas por MarkdownController->showPdfDownloadPage():
// $base_url, $pageTitle, $downloadLink, $actual_filename
$base_url = $base_url ?? '';
$pageTitle = $pageTitle ?? 'Descargar PDF';
$downloadLink = $downloadLink ?? '#'; // Enlace para la descarga real
$actual_filename = $actual_filename ?? 'documento.pdf'; // Nombre del archivo a mostrar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/header.css">
    <!-- Puedes añadir un CSS específico para esta página de descarga -->
    <style>
        body { font-family: Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; min-height: 100vh; margin: 0; background-color: #f4f7f9; }
        .page-container { width: 100%; } /* Para que el header ocupe todo el ancho */
        .download-wrapper { margin-top: 50px; } /* Espacio después del header */
        .download-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .download-container h2 { margin-top: 0; color: #333; }
        .download-btn { display: inline-block; background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 1.1em; margin-top: 15px; margin-bottom:20px; transition: background-color 0.2s ease; }
        .download-btn:hover { background-color: #218838; }
        .filename-display { font-weight: bold; margin-top:10px; display: block; color: #555; font-size: 0.95em; }
        .actions-links { margin-top: 25px; font-size: 0.9em; }
        .actions-links a { color: #007bff; text-decoration: none; margin: 0 10px; }
        .actions-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="page-container">
        <?php 
            // $pageTitle se pasa al header desde el controlador
            if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'header.php')) { 
                include VIEWS_PATH . 'header.php'; 
            } 
        ?>
        <div class="download-wrapper">
            <div class="download-container">
                <h2>¡Tu archivo está listo para descargar!</h2>
                <p>Archivo: <span class="filename-display"><?php echo htmlspecialchars($actual_filename, ENT_QUOTES, 'UTF-8'); ?></span></p>
                
                <?php if (isset($extraDownloadInfo)): ?>
                <p class="download-info"><?php echo htmlspecialchars($extraDownloadInfo, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                
                <a href="<?php echo htmlspecialchars($downloadLink, ENT_QUOTES, 'UTF-8'); ?>" class="download-btn">Descargar Ahora</a>

                <div class="actions-links">
                    <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/markdown/create">Crear otra presentación</a>
                    <span>|</span>
                    <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/dashboard">Volver al Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>