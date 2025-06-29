document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZoneDashboard');
    const fileInput = document.getElementById('fileInputDashboard');
    // La variable window.BASE_APP_URL es creada por tu vista PHP
    const baseUrl = window.BASE_APP_URL || ''; 

    // Inicializar manejadores para la tabla de archivos guardados
    initSavedFilesHandlers();

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

    // --- Función para inicializar manejadores de eventos para la tabla de archivos guardados ---
    function initSavedFilesHandlers() {
        // Manejador para botones de eliminación de archivos
        document.querySelectorAll('.action-icon-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const fileId = this.getAttribute('data-file-id');
                if (!fileId) {
                    console.error('No se encontró el ID del archivo');
                    return;
                }

                if (confirm('¿Estás seguro de que deseas eliminar este archivo? Esta acción no se puede deshacer.')) {
                    // Realizar la solicitud AJAX para eliminar el archivo
                    fetch(baseUrl + '/api/saved-files/delete/' + fileId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            file_id: fileId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Eliminar la fila de la tabla
                            const row = this.closest('tr');
                            row.parentNode.removeChild(row);
                            
                            // Verificar si la tabla está vacía y mostrar mensaje
                            const tbody = document.querySelector('.saved-files-table tbody');
                            if (tbody.children.length === 0) {
                                const emptyRow = document.createElement('tr');
                                emptyRow.innerHTML = '<td colspan="5" class="text-center">No tienes archivos guardados aún.</td>';
                                tbody.appendChild(emptyRow);
                            }
                        } else {
                            alert('Error al eliminar el archivo: ' + (data.message || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al procesar la solicitud. Por favor, inténtalo de nuevo.');
                    });
                }
            });
        });
    }
});