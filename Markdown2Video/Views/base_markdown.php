<?php
// --- Views/base_markdown.php ---

$base_url = $base_url ?? '';
$pageTitle = $pageTitle ?? 'Editor Markdown';

// Tokens pasados desde MarkdownController->create()
$csrf_token_generate_pdf = $csrf_token_generate_pdf ?? '';
$csrf_token_image_action = $csrf_token_image_action ?? ''; // ¡NUEVO! para el gestor de imágenes

// Definir variables globales de JavaScript ANTES de incluir el script externo
echo "<script>\n";
echo "  window.BASE_APP_URL = " . json_encode($base_url) . ";\n";
echo "  window.CSRF_TOKEN_PDF_GENERATE = " . json_encode($csrf_token_generate_pdf) . ";\n";
echo "  window.CSRF_TOKEN_IMAGE_ACTION = " . json_encode($csrf_token_image_action) . ";\n"; // ¡NUEVO!
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
  <!-- ¡NUEVO! Se añade Font Awesome para los iconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
  
  <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/header.css">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/base_markdown.css">
  <!-- ¡NUEVO! Enlace al nuevo archivo CSS para el modal -->
  <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/css/modal_and_gallery.css">
</head>
<body>
  <?php if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'header.php')) { include VIEWS_PATH . 'header.php'; } ?>

  <div class="container">
    <div class="editor-container">
      <div class="editor-header">
        <h2>Editor</h2>
        <!-- ¡NUEVO! Contenedor para alinear el botón y el selector -->
        <div class="editor-controls">
            <!-- ¡NUEVO! Botón para abrir el modal de imágenes -->
            <button id="openImageModalBtn" class="icon-btn" title="Gestionar imágenes"><i class="fa-solid fa-image"></i></button>
            <select id="mode-select" class="mode-selector">
                <option value="markdown" selected>Markdown Estándar</option>
                <option value="marp">Marp</option>
            </select>
        </div>
      </div>
      <div class="editor-body"><textarea id="editor" class="editor" placeholder="Escribe tu presentación aquí..."></textarea></div>
    </div>
    <div class="preview-container">
      <div class="preview-header"><h2>Vista Previa</h2></div>
      <div class="preview-body"><div id="ppt-preview" class="ppt-preview"><p>La vista previa se mostrará aquí...</p></div></div>
      <div class="button-container">
        <button class="generate-btn" data-action="generate-ppt">Generar PPT</button>
        <button class="generate-btn" id="generatePdfBtnHtml">Generar PDF (desde Preview)</button>
        <button class="generate-btn" data-action="generate-mp4">Generar Video MP4</button>
        <button class="generate-btn" data-action="generate-html">Generar HTML</button>
      </div>
    </div>
  </div>

  <!-- ¡NUEVO! HTML completo del Modal para gestionar imágenes (colocado antes de los scripts) -->
  <div id="imageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <button class="modal-close" id="closeImageModalBtn">×</button>
      <h2>Gestor de Imágenes</h2>
      
      <div class="modal-body">
        <div class="upload-section">
          <h3>Subir Nueva Imagen</h3>
          <form id="uploadImageForm">
            <div class="form-group">
              <label for="image_name">Nombre de Referencia:</label>
              <input type="text" id="image_name" name="image_name" required pattern="[a-zA-Z0-9_-]+" placeholder="mi_foto_1">
              <small>Solo letras, números, guiones y guión bajo.</small>
            </div>
            <div class="form-group">
              <label for="image_file">Seleccionar archivo (max 5MB):</label>
              <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required>
            </div>
            <button type="submit" class="submit-btn">Subir Imagen</button>
          </form>
          <div id="uploadStatus" class="status-message"></div>
        </div>

        <div class="gallery-section">
          <h3>Mis Imágenes</h3>
          <div id="imageGallery" class="image-gallery-grid">
            <!-- Las imágenes se cargarán aquí con JavaScript -->
            <p>Cargando imágenes...</p>
          </div>
        </div>
      </div>
    </div>
  </div>


  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/mode/markdown/markdown.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/addon/edit/continuelist.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.59.4/addon/display/placeholder.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  
  <script src="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/public/js/base_markdown.js"></script>
</body>
</html>