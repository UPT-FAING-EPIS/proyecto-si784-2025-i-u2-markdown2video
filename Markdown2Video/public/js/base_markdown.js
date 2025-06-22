document.addEventListener('DOMContentLoaded', function () {
    // =========================================================================
    // === SELECTORES Y VARIABLES GLOBALES =====================================
    // =========================================================================
    const editorTextarea = document.getElementById('editor');
    const previewDiv = document.getElementById('ppt-preview');
    const modeSelect = document.getElementById('mode-select');
    const generatePdfBtnHtml = document.getElementById('generatePdfBtnHtml');
    
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
    // --- FIN DE LA NUEVA LÓGICA ---


    function refreshEditor() { if (editorInstance) { editorInstance.setSize('100%', '100%'); editorInstance.refresh(); } }
    setTimeout(refreshEditor, 100);

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
});