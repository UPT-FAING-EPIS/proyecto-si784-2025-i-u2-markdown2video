document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadButton = document.getElementById('uploadMdButton');
    const editorInstance = document.querySelector('.CodeMirror').CodeMirror; // Obtenemos la instancia de CodeMirror

    if (!dropZone || !fileInput || !uploadButton || !editorInstance) {
        console.warn("Faltan elementos para la funcionalidad de 'drag and drop'.");
        return;
    }

    uploadButton.addEventListener('click', (e) => {
        e.preventDefault();
        fileInput.click(); 
    });

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            handleFile(file);
        }
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            handleFile(files[0]);
        }
    }, false);

    function handleFile(file) {
        if (!file.type.match('markdown') && !file.name.endsWith('.md')) {
            alert('Por favor, sube un archivo con extensión .md o .markdown');
            return;
        }

        const reader = new FileReader();
        
        reader.onload = function(e) {
            const content = e.target.result;
            editorInstance.setValue(content);
        };

        reader.onerror = function() {
            alert('Ocurrió un error al leer el archivo.');
        };

        reader.readAsText(file);
    }
});