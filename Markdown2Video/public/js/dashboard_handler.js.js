document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZoneDashboard');
    const fileInput = document.getElementById('fileInputDashboard');
    // La variable window.BASE_APP_URL es creada por tu vista PHP
    const baseUrl = window.BASE_APP_URL || ''; 

    if (!dropZone || !fileInput) {
        console.warn("Elementos para 'Abrir Archivo' no encontrados en el DOM.");
        return;
    }

    // El clic en toda la zona activa el input de archivo
    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    // Cuando el usuario selecciona un archivo
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            handleFile(file);
        }
    });

    // --- Lógica de Drag & Drop ---
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
        const file = e.dataTransfer.files[0];
        if (file) {
            handleFile(file);
        }
    }, false);


    // --- Función central para procesar el archivo y redirigir ---
    function handleFile(file) {
        if (!file.name.endsWith('.md') && !file.name.endsWith('.markdown')) {
            alert('Por favor, selecciona un archivo Markdown (.md).');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            // Guardamos el contenido en sessionStorage para pasarlo a la siguiente página
            sessionStorage.setItem('markdown_content_to_load', content);
            // Redirigimos al editor
            window.location.href = baseUrl + '/markdown/create';
        };
        reader.readAsText(file);
    }
});