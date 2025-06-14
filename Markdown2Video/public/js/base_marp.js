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

    setTimeout(updateMarpPreview, 100);

    // --- INICIO: Lógica para Generar Archivos ---

    const generateButtons = document.querySelectorAll('.generate-btn');

    async function generateFile(format) {
        const markdownText = marpCodeMirrorEditor.getValue();
        if (!markdownText.trim()) {
            alert("El editor está vacío. Escribe algo antes de generar un archivo.");
            return;
        }

        // Opcional: Mostrar un indicador de carga
        const button = document.querySelector(`.generate-btn[data-format="${format}"]`);
        const originalText = button.textContent;
        button.textContent = 'Generando...';
        button.disabled = true;

        try {
            const response = await fetch('/markdown/generate-file', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    markdown: markdownText,
                    format: format
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Error en el servidor');
            }

            const result = await response.json();

            if (result.success && result.download_url) {
                // Crear un enlace temporal para iniciar la descarga
                const link = document.createElement('a');
                link.href = result.download_url;
                link.download = result.filename || `presentacion.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                throw new Error(result.message || 'No se pudo generar el archivo.');
            }

        } catch (error) {
            console.error(`Error al generar ${format}:`, error);
            alert(`Hubo un error al generar el archivo ${format}: ${error.message}`);
        } finally {
            // Restaurar el botón
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    generateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const format = this.getAttribute('data-format');
            if (format) {
                generateFile(format);
            }
        });
    });

    // --- FIN: Lógica para Generar Archivos ---
});
