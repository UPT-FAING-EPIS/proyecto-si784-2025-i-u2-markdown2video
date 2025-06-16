<?php
// --- Views/download_video.php ---
// Variables pasadas por MarkdownController->showVideoDownloadPage():
// $base_url, $pageTitle, $downloadLink, $actual_filename, $videoPreviewUrl
$base_url = $base_url ?? '';
$pageTitle = $pageTitle ?? 'Descargar Video';
$downloadLink = $downloadLink ?? '#'; // Enlace para la descarga real
$actual_filename = $actual_filename ?? 'video.mp4'; // Nombre del archivo a mostrar
$videoPreviewUrl = $videoPreviewUrl ?? ''; // URL para previsualizar el video
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/header.css">
    <!-- CSS específico para esta página de descarga de video -->
    <style>
        body { font-family: Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; min-height: 100vh; margin: 0; background-color: #f4f7f9; }
        .page-container { width: 100%; } /* Para que el header ocupe todo el ancho */
        .download-wrapper { margin-top: 50px; } /* Espacio después del header */
        .download-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 800px; }
        .download-container h2 { margin-top: 0; color: #333; }
        .video-preview { margin: 20px 0; }
        .video-preview video { width: 100%; max-width: 600px; height: auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .download-btn { display: inline-block; background-color: #dc3545; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 1.1em; margin-top: 15px; margin-bottom:20px; transition: background-color 0.2s ease; }
        .download-btn:hover { background-color: #c82333; }
        .filename-display { font-weight: bold; margin-top:10px; display: block; color: #555; font-size: 0.95em; }
        .actions-links { margin-top: 25px; font-size: 0.9em; }
        .actions-links a { color: #007bff; text-decoration: none; margin: 0 10px; }
        .actions-links a:hover { text-decoration: underline; }
        .video-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .video-info p { margin: 5px 0; color: #666; }
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
                <h2>¡Tu Video MP4 está listo!</h2>
                
                <?php if (!empty($videoPreviewUrl)): ?>
                <div class="video-preview">
                    <video controls preload="metadata">
                        <source src="<?php echo htmlspecialchars($videoPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                        Tu navegador no soporta la reproducción de videos.
                    </video>
                </div>
                <?php endif; ?>
                
                <div class="video-info">
                    <p><strong>Archivo:</strong> <span class="filename-display"><?php echo htmlspecialchars($actual_filename, ENT_QUOTES, 'UTF-8'); ?></span></p>
                    <p><strong>Formato:</strong> MP4 (H.264)</p>
                    <p><strong>Calidad:</strong> HD (1280x720)</p>
                </div>
                
                <a href="<?php echo htmlspecialchars($downloadLink, ENT_QUOTES, 'UTF-8'); ?>" class="download-btn">📥 Descargar Video MP4</a>
                
                <div class="actions-links">
                    <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/markdown/marp-editor">Crear otro video</a>
                    <span>|</span>
                    <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/markdown/create">Editor Markdown</a>
                    <span>|</span>
                    <a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/dashboard">Volver al Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>