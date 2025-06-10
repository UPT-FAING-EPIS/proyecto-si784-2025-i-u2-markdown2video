<?php
// --- Views/base_marp.php ---
$base_url = $base_url ?? ''; // Pasada por MarkdownController->showMarpEditor()
$pageTitle = $pageTitle ?? 'Editor Marp';
// $csrf_token_marp_editor = $csrf_token_marp_editor ?? ''; // Pasada por el controlador

// Definir variable global JS para BASE_URL
echo "<script>\n";
echo "  window.BASE_APP_URL = " . json_encode($base_url) . ";\n";
// echo "  window.CSRF_TOKEN_MARP_EDITOR = " . json_encode($csrf_token_marp_editor) . ";\n"; // Si lo necesitas
echo "</script>\n";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/theme/dracula.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/header.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/base_marp.css"> <!-- CSS específico para Marp -->
</head>
<body class="marp-editor-page">
    <?php
        if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'header.php')) {
            include VIEWS_PATH . 'header.php';
        }
    ?>
    <div class="marp-editor-page-container">
        <div class="editor-container">
            <div class="editor-header">
                <h2>Editor (Marp)</h2>
                <select id="mode-select-marp-page" class="mode-selector"> <!-- ID DIFERENTE -->
                    <option value="marp" selected>Marp</option>
                    <option value="markdown">Markdown Estándar</option>
                </select>
            </div>
            <div class="editor-body">
                <textarea id="editor-marp" class="editor" placeholder="Escribe tu presentación Marp aquí..."></textarea> <!-- ID DIFERENTE -->
            </div>
        </div>
        <div class="preview-container">
            <div class="preview-header"><h2>Vista Previa Marp</h2></div>
            <div class="preview-body"><div id="ppt-preview"><p>Escribe en el editor para ver la vista previa...</p></div></div>
            <div class="button-container">
                <button class="generate-btn" data-format="ppt">Generar PPT</button>
                <button class="generate-btn" data-format="pdf">Generar PDFXDD</button>
                <button class="generate-btn" data-format="mp4">Generar Video MP4</button>
                <button class="generate-btn" data-format="html">Generar HTML</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/mode/markdown/markdown.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/addon/edit/continuelist.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/addon/display/placeholder.js"></script>
    <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/js/base_marp.js"></script>
</body>
</html>