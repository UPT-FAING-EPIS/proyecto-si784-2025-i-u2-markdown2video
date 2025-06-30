document.addEventListener('DOMContentLoaded', function () {
    // =========================================================================
    // === SELECTORES Y VARIABLES GLOBALES =====================================
    // =========================================================================
    const editorTextarea = document.getElementById('editor');
    const previewDiv = document.getElementById('ppt-preview');
    const modeSelect = document.getElementById('mode-select');
    const generatePdfBtnHtml = document.getElementById('generatePdfBtnHtml');
    const saveMarkdownBtn = document.getElementById('saveMarkdownBtn');
    
    // Selectores del modal principal de imágenes
    const openModalBtn = document.getElementById('openImageModalBtn');
    const closeModalBtn = document.getElementById('closeImageModalBtn');
    const imageModal = document.getElementById('imageModal');
    const uploadForm = document.getElementById('uploadImageForm');
    const imageGallery = document.getElementById('imageGallery');
    const uploadStatusDiv = document.getElementById('uploadStatus');

    // Selectores para el modal de copiado
    const copySyntaxModal = document.getElementById('copySyntaxModal');
    const syntaxToCopyInput = document.getElementById('syntaxToCopy');
    const copySyntaxBtn = document.getElementById('copySyntaxBtn');
    const closeCopyModalBtn = document.getElementById('closeCopyModalBtn');
    const copyStatusMessage = document.getElementById('copyStatusMessage');

    // Variables globales
    const baseUrlJs = typeof window.BASE_APP_URL !== 'undefined' ? window.BASE_APP_URL : '';
    const csrfTokenPdfGenerate = typeof window.CSRF_TOKEN_PDF_GENERATE !== 'undefined' ? window.CSRF_TOKEN_PDF_GENERATE : '';
    const csrfTokenImageAction = typeof window.CSRF_TOKEN_IMAGE_ACTION !== 'undefined' ? window.CSRF_TOKEN_IMAGE_ACTION : '';
    
    // Variables para el manejo de archivos
    let currentFileId = null;
    let isPublic = false;
    const publicToggle = document.getElementById('publicToggle');

    // =========================================================================
    // === INICIALIZACIÓN DE CODEMIRROR Y MARKED.JS ============================
    // =========================================================================
    if (!editorTextarea) { console.error("JS ERROR: Textarea #editor no encontrado."); return; }
    let editorInstance = null;
    try {
        editorInstance = CodeMirror.fromTextArea(editorTextarea, {
            lineNumbers: true, mode: "markdown", theme: "dracula", lineWrapping: true,
            matchBrackets: true, placeholder: editorTextarea.getAttribute('placeholder') || "Escribe...",
            extraKeys: { "Enter": "newlineAndIndentContinueMarkdownList" }
        });
    } catch (e) { console.error("JS ERROR: CodeMirror init falló:", e); return; }

    // --- ¡AQUÍ ESTÁ LA NUEVA LÓGICA AÑADIDA! ---
    // Carga el contenido desde sessionStorage si existe (después de arrastrar un archivo en el dashboard)
    const contentToLoad = sessionStorage.getItem('markdown_content_to_load');
    if (contentToLoad && editorInstance) {
        editorInstance.setValue(contentToLoad);
        // Limpiamos el sessionStorage para que no se vuelva a cargar si se recarga la página
        sessionStorage.removeItem('markdown_content_to_load');
    }
    
    // Verificar si estamos editando un archivo existente y si el usuario es el propietario
    const pathSegments = window.location.pathname.split('/');
    const lastSegment = pathSegments[pathSegments.length - 1];
    
    // Verificar si el último segmento de la URL es un número (ID del archivo)
    if (!isNaN(lastSegment) && lastSegment.trim() !== '') {
        const fileId = parseInt(lastSegment);
        
        // Hacer una petición para obtener la información del archivo
        fetch(`${baseUrlJs}/api/saved-files/info/${fileId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                // Verificar si el usuario es el propietario
                if (data.data.is_owner === false) {
                    // Si el archivo no es público, deshabilitar la edición
                    if (!data.data.is_public) {
                        // Deshabilitar la edición si el usuario no es el propietario y el archivo no es público
                        if (editorInstance) {
                            editorInstance.setOption('readOnly', true);
                        }
                        
                        // Cambiar el estilo para indicar que está en modo de solo lectura
                        document.querySelector('.editor-container').classList.add('read-only-mode');
                        
                        // Deshabilitar los botones de generación
                        const generateButtons = document.querySelectorAll('.generate-btn');
                        generateButtons.forEach(button => {
                            button.disabled = true;
                            button.title = 'No tienes permiso para modificar este archivo privado';
                        });
                    }
                    // Si el archivo es público, permitir la edición a cualquier usuario autenticado
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar información del archivo:', error);
        });
    }
    // --- FIN DE LA NUEVA LÓGICA ---


    function refreshEditor() { if (editorInstance) { editorInstance.setSize('100%', '100%'); editorInstance.refresh(); } }

// Función para crear un modal personalizado para el título
function createTitleModal(isNewFile = true, currentTitle = '') {
    return new Promise((resolve, reject) => {
      // Crear elementos del modal
      const modalOverlay = document.createElement('div');
      modalOverlay.className = 'modal-overlay';
      
      const modalContent = document.createElement('div');
      modalContent.className = 'modal-content';
      
      // Header del modal
      const modalHeader = document.createElement('div');
      modalHeader.className = 'modal-header';
      const modalTitle = document.createElement('h3');
      modalTitle.textContent = isNewFile ? 'Guardar nuevo archivo' : 'Actualizar archivo';
      modalHeader.appendChild(modalTitle);
      
      // Body del modal
      const modalBody = document.createElement('div');
      modalBody.className = 'modal-body';
      
      const formGroup = document.createElement('div');
      formGroup.className = 'form-group';
      
      const titleLabel = document.createElement('label');
      titleLabel.setAttribute('for', 'file-title-input');
      titleLabel.textContent = 'Título del archivo:';
      
      const titleInput = document.createElement('input');
      titleInput.type = 'text';
      titleInput.id = 'file-title-input';
      titleInput.className = 'form-control';
      titleInput.value = currentTitle;
      titleInput.placeholder = 'Ingresa un título para tu archivo';
      titleInput.required = true;
      
      formGroup.appendChild(titleLabel);
      formGroup.appendChild(titleInput);
      modalBody.appendChild(formGroup);
      
      // Footer del modal
      const modalFooter = document.createElement('div');
      modalFooter.className = 'modal-footer';
      
      const cancelButton = document.createElement('button');
      cancelButton.className = 'btn btn-secondary';
      cancelButton.textContent = 'Cancelar';
      cancelButton.onclick = () => {
        document.body.removeChild(modalOverlay);
        reject('Operación cancelada');
      };
      
      const saveButton = document.createElement('button');
      saveButton.className = 'btn btn-primary';
      saveButton.textContent = 'Guardar';
      saveButton.onclick = () => {
        const title = titleInput.value.trim();
        if (!title) {
          titleInput.style.borderColor = 'red';
          return;
        }
        document.body.removeChild(modalOverlay);
        resolve(title);
      };
      
      // Si es un archivo existente, agregar opción para mantener el título actual
      if (!isNewFile) {
        const keepTitleButton = document.createElement('button');
        keepTitleButton.className = 'btn btn-secondary';
        keepTitleButton.textContent = 'Mantener título actual';
        keepTitleButton.onclick = () => {
          document.body.removeChild(modalOverlay);
          resolve('KEEP_EXISTING_TITLE');
        };
        modalFooter.appendChild(keepTitleButton);
      }
      
      modalFooter.appendChild(cancelButton);
      modalFooter.appendChild(saveButton);
      
      // Ensamblar el modal
      modalContent.appendChild(modalHeader);
      modalContent.appendChild(modalBody);
      modalContent.appendChild(modalFooter);
      modalOverlay.appendChild(modalContent);
      
      // Agregar el modal al DOM
      document.body.appendChild(modalOverlay);
      
      // Enfocar el input
      titleInput.focus();
      
      // Permitir enviar con Enter
      titleInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          saveButton.click();
        }
      });
    });
  }

// Función para guardar el archivo Markdown
async function saveMarkdownFile() {
    const markdownContent = editorInstance.getValue();
    
    // Validar que el contenido no esté vacío
    if (!markdownContent.trim()) {
        alert('El contenido no puede estar vacío');
        return;
    }
    
    try {
        // Obtener el título mediante el modal personalizado
        let title;
        if (!currentFileId) {
            // Nuevo archivo
            title = await createTitleModal(true);
        } else {
            // Archivo existente
            title = await createTitleModal(false);
            
            // Si el usuario eligió mantener el título actual, no enviamos el título
            if (title === 'KEEP_EXISTING_TITLE') {
                // No incluimos el título en formData para mantener el actual
                title = null;
            }
        }
        
        // Preparar los datos para enviar
        const formData = new FormData();
        formData.append('content', markdownContent);
        if (title !== null) {
            formData.append('title', title);
        }
        
        if (currentFileId) formData.append('fileId', currentFileId);
        
        // Obtener el estado del toggle de público/privado
        isPublic = publicToggle.checked;
        formData.append('isPublic', isPublic ? '1' : '0'); // Enviar como '1' o '0' para asegurar la conversión correcta en PHP
        
        // Mostrar indicador de carga
        saveMarkdownBtn.textContent = 'Guardando...';
        saveMarkdownBtn.disabled = true;
        
        // Enviar la solicitud al servidor
        const response = await fetch(`${baseUrlJs}/markdown/save-markdown`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Actualizar el ID del archivo si es nuevo
            if (!currentFileId && result.fileId) {
                currentFileId = result.fileId;
                // Actualizar la URL sin recargar la página
                window.history.replaceState({}, document.title, `${baseUrlJs}/markdown/editor/${currentFileId}`);
            }
            // Mostrar mensaje de éxito
            const successMessage = document.createElement('div');
            successMessage.style.position = 'fixed';
            successMessage.style.top = '20px';
            successMessage.style.left = '50%';
            successMessage.style.transform = 'translateX(-50%)';
            successMessage.style.backgroundColor = '#4CAF50';
            successMessage.style.color = 'white';
            successMessage.style.padding = '10px 20px';
            successMessage.style.borderRadius = '4px';
            successMessage.style.zIndex = '1000';
            successMessage.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
            successMessage.textContent = 'Archivo guardado correctamente';
            document.body.appendChild(successMessage);
            
            // Eliminar el mensaje después de 3 segundos
            setTimeout(() => {
                document.body.removeChild(successMessage);
            }, 3000);
        } else {
            alert(`Error al guardar: ${result.error || 'Error desconocido'}`);
        }
    } catch (error) {
        if (error !== 'Operación cancelada') {
            console.error('Error al guardar el archivo:', error);
            alert('Error al guardar el archivo. Revisa la consola para más detalles.');
        }
    } finally {
        // Restaurar el botón solo si no fue una operación cancelada
        if (saveMarkdownBtn.disabled) {
            saveMarkdownBtn.textContent = 'Guardar';
            saveMarkdownBtn.disabled = false;
        }
    }
}
    setTimeout(refreshEditor, 100);
    
    // Agregar evento al botón de guardar
    if (saveMarkdownBtn) {
        saveMarkdownBtn.addEventListener('click', saveMarkdownFile);
    }

    if (typeof marked !== 'undefined') {
        const renderer = {
            // Sobrescribimos la función de imagen
            image(href, title, text) {
                // --- CORRECCIÓN CLAVE ---
                // Primero, nos aseguramos de que 'href' sea una cadena de texto (string).
                // Si marked.js nos pasa un objeto, extraemos la URL de la propiedad .href
                const url = typeof href === 'string' ? href : (href.href || '');

                // Ahora que 'url' es un string seguro, podemos usar .startsWith()
                if (url.startsWith('img:')) {
                    const imageName = url.substring(4);
                    const imageUrl = `${baseUrlJs}/image/serve/${encodeURIComponent(imageName)}`;
                    // Devolvemos la etiqueta <img> completa para nuestras imágenes locales.
                    return `<img src="${imageUrl}" alt="${text}" ${title ? `title="${title}"` : ''}>`;
                }

                // Para cualquier otro caso (imágenes de internet, etc.),
                // devolvemos 'false' para que marked.js use su renderizador por defecto.
                return false;
            }
        };
        
        marked.use({ renderer });
    }

    function updateMarkdownPreview() {
        if (!previewDiv) return;
        if (typeof marked !== 'undefined' && editorInstance) {
            try {
                previewDiv.innerHTML = marked.parse(editorInstance.getValue(), { breaks: true });
                if (typeof renderMermaidDiagrams === 'function') {
                    renderMermaidDiagrams(previewDiv);
                }
                if (typeof makeImagesInteractive === 'function') {
                    makeImagesInteractive(previewDiv);
                }
            } catch (e) {
                console.error("JS Error en la actualización de la vista previa:", e);
                previewDiv.innerHTML = "<p style='color:red;'>Error al generar la vista previa.</p>";
            }
        } else if (typeof marked === 'undefined') {
            previewDiv.innerHTML = "<p style='color:orange;'>Marked.js no cargado.</p>";
        }
    }

    if (editorInstance) {
        editorInstance.on("change", updateMarkdownPreview);
        editorInstance.on("paste", function() {
            setTimeout(updateMarkdownPreview, 50); 
        });
        setTimeout(updateMarkdownPreview, 150);
    }
    
    // =========================================================================
    // === LÓGICA DE INTERFAZ ==================================================
    // =========================================================================
    if (modeSelect) {
        modeSelect.addEventListener("change", function () {
            if (this.value === "marp") { window.location.href = baseUrlJs + '/markdown/marp-editor'; }
        });
    }

    function showStatusMessage(message, isSuccess) {
        if (!uploadStatusDiv) return;
        uploadStatusDiv.textContent = message;
        uploadStatusDiv.className = `status-message ${isSuccess ? 'success' : 'error'}`;
        uploadStatusDiv.style.display = 'block';
        setTimeout(() => { uploadStatusDiv.style.display = 'none'; }, 5000);
    }

    async function fetchAndDisplayImages() {
        if (!imageGallery) return;
        imageGallery.innerHTML = '<div class="gallery-spinner"></div>';
        try {
            const response = await fetch(baseUrlJs + '/markdown/get-user-images');
            if (!response.ok) throw new Error('No se pudo cargar la galería. (Error: ' + response.status + ')');
            const images = await response.json();
            imageGallery.innerHTML = '';
            if (images.length === 0) {
                imageGallery.innerHTML = '<p>No has subido ninguna imagen todavía.</p>';
                return;
            }
            images.forEach(img => {
                const item = document.createElement('div');
                item.className = 'gallery-item';
                item.innerHTML = `
                    <img src="${baseUrlJs}/image/serve/${encodeURIComponent(img.image_name)}" alt="${img.image_name}" loading="lazy">
                    <div class="gallery-item-name">${img.image_name}</div>
                    <div class="gallery-item-actions">
                        <button class="copy" title="Copiar sintaxis" data-name="${img.image_name}"><i class="fa-solid fa-copy"></i></button>
                        <button class="delete" title="Eliminar" data-id="${img.id_image}"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                `;
                imageGallery.appendChild(item);
            });
        } catch (error) { imageGallery.innerHTML = `<p style="color: #842029;">${error.message}</p>`; }
    }

    // Listeners para el modal principal
    if (openModalBtn && imageModal) {
        openModalBtn.addEventListener('click', () => {
            imageModal.style.display = 'flex';
            fetchAndDisplayImages();
        });
    }
    if (closeModalBtn && imageModal) {
        closeModalBtn.addEventListener('click', () => { imageModal.style.display = 'none'; });
    }

    // Listener para el formulario de subida
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(uploadForm);
            formData.append('csrf_token', csrfTokenImageAction);
            const submitBtn = uploadForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true; submitBtn.textContent = 'Subiendo...';
            try {
                const response = await fetch(baseUrlJs + '/markdown/upload-image', { method: 'POST', body: formData });
                const result = await response.json();
                if (response.ok && result.success) {
                    showStatusMessage(result.message, true);
                    uploadForm.reset();
                    fetchAndDisplayImages();
                } else { throw new Error(result.error || 'Ocurrió un error desconocido.'); }
            } catch (error) {
                showStatusMessage(`Error: ${error.message}`, false);
            } finally {
                submitBtn.disabled = false; submitBtn.textContent = 'Subir Imagen';
            }
        });
    }
    
    // Listener para acciones en la galería (copiar y borrar)
    if (imageGallery) {
        imageGallery.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (!button) return;

            if (button.classList.contains('copy')) {
                if (!copySyntaxModal || !syntaxToCopyInput) return;
                const imageName = button.dataset.name;
                const syntax = `![texto descriptivo](img:${imageName})`;
                syntaxToCopyInput.value = syntax;
                copyStatusMessage.textContent = '';
                copySyntaxModal.style.display = 'flex';
                syntaxToCopyInput.select();
                syntaxToCopyInput.setSelectionRange(0, 99999);
            }

            if (button.classList.contains('delete')) {
                const imageIdToDelete = button.dataset.id; 
                if (confirm('¿Estás seguro de que quieres eliminar esta imagen?')) {
                    try {
                        const response = await fetch(baseUrlJs + '/markdown/delete-image', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_image: imageIdToDelete, csrf_token: csrfTokenImageAction })
                        });
                        const result = await response.json();
                        if (response.ok && result.success) {
                            fetchAndDisplayImages();
                        } else { throw new Error(result.error || 'No se pudo eliminar.'); }
                    } catch (error) { alert(`Error: ${error.message}`); }
                }
            }
        });
    }

    // Listeners para el modal de copiado
    if (copySyntaxBtn && syntaxToCopyInput) {
        copySyntaxBtn.addEventListener('click', () => {
            syntaxToCopyInput.select();
            syntaxToCopyInput.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                copyStatusMessage.textContent = '¡Copiado!';
            } catch (err) {
                copyStatusMessage.textContent = 'Error al copiar.';
            }
        });
    }
    if (closeCopyModalBtn && copySyntaxModal) {
        closeCopyModalBtn.addEventListener('click', () => {
            copySyntaxModal.style.display = 'none';
        });
    }

    // =========================================================================
    // === LÓGICA DE GENERACIÓN DE PDF =========================================
    // =========================================================================
    if (generatePdfBtnHtml && previewDiv) {
        generatePdfBtnHtml.addEventListener('click', async function () {
            const htmlContentForPdf = previewDiv.innerHTML;
            if (!htmlContentForPdf.trim() || htmlContentForPdf.includes("La vista previa se mostrará aquí...")) {
                alert("La vista previa está vacía."); return;
            }
            const originalButtonText = this.textContent;
            this.textContent = 'Generando PDF...'; this.disabled = true;
            try {
                const endpoint = baseUrlJs + '/markdown/generate-pdf-from-html';
                const bodyParams = new URLSearchParams();
                bodyParams.append('html_content', htmlContentForPdf);
                if (csrfTokenPdfGenerate) { bodyParams.append('csrf_token_generate_pdf', csrfTokenPdfGenerate); }
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: bodyParams.toString()
                });
                if (!response.ok) {
                    let errorMsg = `Error del servidor: ${response.status}`;
                    try { const errorData = await response.json(); errorMsg = errorData.error || errorMsg; }
                    catch (e) { /* No hacer nada si no es JSON */ }
                    throw new Error(errorMsg);
                }
                const data = await response.json();
                if (data.success && data.downloadPageUrl) {
                    window.open(data.downloadPageUrl, '_blank');
                } else { throw new Error(data.error || "Respuesta inesperada del servidor."); }
            } catch (error) {
                console.error("JS ERROR en func. generar PDF (catch):", error);
                alert(`Ocurrió un error: ${error.message}`);
            } finally {
                this.textContent = originalButtonText; this.disabled = false;
            }
        });
    }

    // =========================================================================
    // === ¡NUEVO! LÓGICA DE GENERACIÓN DE HTML ================================
    // =========================================================================
    const generateHtmlBtn = document.getElementById('generateHtmlBtn');

    if (generateHtmlBtn && previewDiv) {
        generateHtmlBtn.addEventListener('click', async function() {
            const originalButtonText = this.textContent;
            this.textContent = 'Generando...';
            this.disabled = true;

            try {
                const previewContent = previewDiv.innerHTML;
                
                if (!previewContent.trim() || previewContent.includes("La vista previa se mostrará aquí...")) {
                    alert("La vista previa está vacía. Escribe algo primero.");
                    return; 
                }
                let cssStyles = '';
                try {
                    const cssResponse = await fetch(`${baseUrlJs}/public/css/preview_styles.css`);
                    if (cssResponse.ok) {
                        cssStyles = await cssResponse.text();
                    } else {
                        console.warn('No se pudo cargar preview_styles.css para la exportación HTML.');
                    }
                } catch (e) {
                    console.error('Error cargando CSS para exportación HTML:', e);
                }

                const fullHtml = `
                    <!DOCTYPE html>
                    <html lang="es">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Documento Generado</title>
                        <style>
                            /* Estilos básicos para el documento exportado */
                            body {
                                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                                max-width: 900px;
                                margin: 40px auto;
                                padding: 0 20px;
                            }
                            /* Incrustamos los estilos de la vista previa */
                            ${cssStyles}
                        </style>
                    </head>
                    <body>
                        <div class="ppt-preview">
                            ${previewContent}
                        </div>
                    </body>
                    </html>
                `;

                const blob = new Blob([fullHtml], { type: 'text/html' });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = 'presentacion.html'; 
                document.body.appendChild(a);
                a.click(); 
                
                // Limpieza
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

            } catch (error) {
                console.error("Error al generar el archivo HTML:", error);
                alert("Ocurrió un error al intentar generar el archivo HTML.");
            } finally {
                this.textContent = originalButtonText;
                this.disabled = false;
            }
        });
    }
});