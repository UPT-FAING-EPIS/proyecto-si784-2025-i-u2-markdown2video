// public/js/base_marp.js
document.addEventListener('DOMContentLoaded', function() {
    const editorTextareaMarp = document.getElementById('editor-marp');
    const previewDivMarp = document.getElementById('ppt-preview');
    const modeSelectMarp = document.getElementById('mode-select-marp-page');

    let marpDebounceTimer;

    if (!editorTextareaMarp) {
        console.error("Textarea #editor-marp no encontrado. Editor Marp no se inicializará.");
        return; 
    }

    const marpCodeMirrorEditor = CodeMirror.fromTextArea(editorTextareaMarp, {
        mode: 'markdown',
        theme: 'dracula',
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        placeholder: editorTextareaMarp.getAttribute('placeholder') || "Escribe tu presentación Marp aquí...",
        extraKeys: { "Enter": "newlineAndIndentContinueMarkdownList" }
    });

    function refreshMarpEditorLayout() {
        marpCodeMirrorEditor.setSize('100%', '100%');
        marpCodeMirrorEditor.refresh();
    }
    setTimeout(refreshMarpEditorLayout, 50);

    async function updateMarpPreview() {
        if (!previewDivMarp || !marpCodeMirrorEditor) return;
        const markdownText = marpCodeMirrorEditor.getValue();
        previewDivMarp.innerHTML = '<p>Generando vista previa Marp...</p>';

        try {
            const renderEndpoint = '/markdown/render-marp-preview';
            const requestBody = `markdown=${encodeURIComponent(markdownText)}`;
            const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };

            const response = await fetch(renderEndpoint, { method: 'POST', headers: headers, body: requestBody });

            if (!response.ok) {
                let errorDetail = await response.text();
                try {
                    const errorJson = JSON.parse(errorDetail);
                    errorDetail = errorJson.details || errorJson.error || errorDetail;
                } catch (e) { /* No era JSON */ }
                throw new Error(`Error del servidor: ${response.status} - ${errorDetail}`);
            }

            const htmlResult = await response.text();

            if (typeof DOMPurify !== 'undefined') {
                const cleanHtml = DOMPurify.sanitize(htmlResult, { 
                    USE_PROFILES: { html: true },
                    // Configuraciones específicas de Marp pueden agregarse aquí si es necesario
                });
                previewDivMarp.innerHTML = cleanHtml;
            } else {
                console.warn("DOMPurify no está cargado. El HTML se insertará sin saneamiento.");
                previewDivMarp.innerHTML = htmlResult;
            }

        } catch (error) {
            console.error("Error al generar vista previa Marp:", error);
            previewDivMarp.innerHTML = '';
            const errorParagraph = document.createElement('p');
            errorParagraph.style.color = 'red';
            errorParagraph.textContent = `Error al cargar la previsualización Marp: ${error.message}`;
            previewDivMarp.appendChild(errorParagraph);
        }
    }

    marpCodeMirrorEditor.on('change', () => {
        clearTimeout(marpDebounceTimer);
        marpDebounceTimer = setTimeout(updateMarpPreview, 700);
    });

    if (modeSelectMarp) {
        modeSelectMarp.addEventListener('change', function () {
            const selectedMode = this.value;
            if (selectedMode === 'markdown') {
                window.location.href = '/markdown/create';
            } else if (selectedMode === 'marp') {
                console.log("Modo Marp ya seleccionado.");
            }
        });
    }

    // Event listeners para los botones de generación
    const generateButtons = document.querySelectorAll('.generate-btn');
    generateButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const format = this.getAttribute('data-format');
            
            if (format === 'mp4') {
                await generateMp4Video();
            } else {
                console.log(`Funcionalidad para ${format} no implementada aún.`);
            }
        });
    });

    async function generateMp4Video() {
        const markdownContent = marpCodeMirrorEditor.getValue();
        
        console.log('[MARP-UI] Iniciando generación de video');
        console.log(`[MARP-UI] Longitud del contenido Markdown: ${markdownContent.length} caracteres`);
        
        if (!markdownContent.trim()) {
            console.error('[MARP-UI-ERROR] Contenido Markdown vacío');
            alert('Por favor, escribe contenido en el editor antes de generar el video.');
            return;
        }
        
        // Mostrar indicador de carga
        const mp4Button = document.querySelector('[data-format="mp4"]');
        const originalText = mp4Button.textContent;
        mp4Button.textContent = 'Generando Video...';
        mp4Button.disabled = true;

        try {
            console.log('[MARP-UI] Enviando contenido al servidor para conversión');
            const response = await fetch('/markdown/generate-mp4-video', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `markdown=${encodeURIComponent(markdownContent)}`
            });
            
            console.log('[MARP-UI] Respuesta del servidor recibida');
            const result = await response.json();
            
            if (result.success) {
                console.log('[MARP-UI] Video generado exitosamente');
                showVideoPreview(result.videoUrl);
                
                // Redirigir a la página de descarga después de un momento
                setTimeout(() => {
                    window.location.href = result.downloadPageUrl;
                }, 2000);
            } else {
                console.error('[MARP-UI-ERROR] Error en la generación:', result.error);
                alert('Error al generar el video: ' + (result.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('[MARP-UI-ERROR] Error en la solicitud:', error);
            alert('Error al generar el video. Por favor, inténtalo de nuevo.');
        } finally {
            console.log('[MARP-UI] Finalizando proceso de generación');
            // Restaurar el botón
            mp4Button.textContent = originalText;
            mp4Button.disabled = false;
        }
    }

    function showVideoPreview(videoUrl) {
        // Crear un elemento de video para mostrar la preview
        const previewContainer = document.getElementById('ppt-preview');
        
        const videoElement = document.createElement('video');
        videoElement.src = videoUrl;
        videoElement.controls = true;
        videoElement.style.width = '100%';
        videoElement.style.maxWidth = '600px';
        videoElement.style.height = 'auto';
        
        const successMessage = document.createElement('p');
        successMessage.textContent = '¡Video generado exitosamente!';
        successMessage.style.color = '#28a745';
        successMessage.style.fontWeight = 'bold';
        successMessage.style.textAlign = 'center';
        
        previewContainer.innerHTML = '';
        previewContainer.appendChild(successMessage);
        previewContainer.appendChild(videoElement);
    }

    setTimeout(updateMarpPreview, 100);
});
