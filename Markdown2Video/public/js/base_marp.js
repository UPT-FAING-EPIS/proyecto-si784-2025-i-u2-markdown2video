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

    // --- Lógica para botones de generación ---
    const generateButtons = document.querySelectorAll('.generate-btn');

    generateButtons.forEach(button => {
        button.addEventListener('click', async function(event) {
            event.preventDefault();
            const format = this.dataset.format;
            const markdownContent = marpCodeMirrorEditor.getValue();

            if (!markdownContent.trim()) {
                alert('El editor de Markdown está vacío.');
                return;
            }

            if (format === 'mp4') {
                handleVideoGeneration(this, markdownContent);
            } else {
                handleFileGeneration(this, format, markdownContent);
            }
        });
    });

    async function handleFileGeneration(button, format, markdownContent) {
        const originalButtonText = button.textContent;
        button.disabled = true;
        button.textContent = `Generando ${format.toUpperCase()}...`;

        try {
            const response = await fetch('/markdown/generate-marp-file', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    markdown: markdownContent,
                    format: format
                })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || `Error del servidor: ${response.status}`);
            }

            if (result.success && result.downloadUrl) {
                window.location.href = result.downloadUrl;
            } else {
                throw new Error(result.message || 'Respuesta inválida del servidor.');
            }
        } catch (error) {
            console.error(`Error al generar ${format.toUpperCase()}:`, error);
            alert(`Error al generar ${format.toUpperCase()}: ${error.message}`);
        } finally {
            button.disabled = false;
            button.textContent = originalButtonText;
        }
    }

    async function handleVideoGeneration(button, markdownContent) {
        const originalButtonText = button.textContent;
        button.disabled = true;
        button.textContent = 'Generando Video...';

        try {
            const response = await fetch('/markdown/generate-marp-video', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ markdown: markdownContent })
            });

            const result = await response.json();

            if (!response.ok) {
                const errorMsg = result.message || `Error del servidor (${response.status})`;
                const errorDetails = result.details ? `\n\nDetalles:\n${result.details}` : '';
                throw new Error(`${errorMsg}${errorDetails}`);
            }

            if (result.success && result.downloadUrl) {
                window.location.href = result.downloadUrl;
            } else {
                throw new Error(result.message || 'Respuesta inválida del servidor.');
            }
        } catch (error) {
            console.error('Error al generar MP4:', error);
            alert(`Error al generar MP4: ${error.message}`);
        } finally {
            button.disabled = false;
            button.textContent = originalButtonText;
        }
    }
});
