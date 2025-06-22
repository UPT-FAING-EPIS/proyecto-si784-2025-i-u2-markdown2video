document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZoneDashboard');
    const fileInput = document.getElementById('fileInputDashboard');
    const baseUrl = typeof window.BASE_APP_URL !== 'undefined' ? window.BASE_APP_URL : '';

    if (!dropZone || !fileInput) {
        return;
    }

    dropZone.addEventListener('click', () => {
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
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
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
            sessionStorage.setItem('markdown_content_to_load', content);
            
            window.location.href = baseUrl + '/markdown/create';
        };

        reader.onerror = function() {
            alert('Ocurrió un error al leer el archivo.');
        };

        reader.readAsText(file);
    }
});