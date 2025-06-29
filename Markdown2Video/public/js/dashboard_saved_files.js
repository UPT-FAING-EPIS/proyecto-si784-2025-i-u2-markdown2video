// public/js/dashboard_saved_files.js

document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en la página del dashboard
    const savedFilesTableBody = document.getElementById('savedFilesTableBody');
    if (!savedFilesTableBody) return;

    // Cargar los archivos guardados del usuario
    loadSavedFiles();

    /**
     * Carga los archivos guardados del usuario desde el servidor
     */
    function loadSavedFiles() {
        fetch('/savedfile/get-user-files')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error al cargar los archivos guardados');
                }
                return response.json();
            })
            .then(data => {
                // Ocultar la fila de carga
                const loadingRow = document.getElementById('loadingRow');
                if (loadingRow) loadingRow.style.display = 'none';

                if (data.success && data.files && data.files.length > 0) {
                    // Mostrar los archivos guardados
                    renderSavedFiles(data.files);
                } else {
                    // Mostrar mensaje de que no hay archivos guardados
                    const noFilesRow = document.getElementById('noFilesRow');
                    if (noFilesRow) noFilesRow.style.display = 'table-row';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Mostrar mensaje de error
                const loadingRow = document.getElementById('loadingRow');
                if (loadingRow) {
                    loadingRow.innerHTML = `<td colspan="5" style="text-align: center; color: #dc3545;">Error al cargar los archivos: ${error.message}</td>`;
                }
            });
    }

    /**
     * Renderiza los archivos guardados en la tabla
     * @param {Array} files - Lista de archivos guardados
     */
    function renderSavedFiles(files) {
        // Limpiar la tabla (excepto las filas de carga y sin archivos)
        const loadingRow = document.getElementById('loadingRow');
        const noFilesRow = document.getElementById('noFilesRow');
        
        // Guardar estas filas para restaurarlas después
        const tempRows = [];
        if (loadingRow) tempRows.push(loadingRow);
        if (noFilesRow) tempRows.push(noFilesRow);
        
        // Limpiar la tabla
        savedFilesTableBody.innerHTML = '';
        
        // Restaurar las filas temporales
        tempRows.forEach(row => savedFilesTableBody.appendChild(row));

        // Agregar cada archivo a la tabla
        files.forEach(file => {
            const row = document.createElement('tr');
            row.dataset.fileId = file.id;
            
            // Formatear la fecha
            const date = new Date(file.modified_at);
            const formattedDate = date.toLocaleDateString();
            
            // Determinar si hay plantilla y vista previa
            const hasTemplate = file.template_id ? true : false;
            const hasPreview = file.preview_image_id ? true : false;
            
            // Crear el contenido de la fila
            row.innerHTML = `
                <td>${file.title}</td>
                <td><span class="badge badge-${file.file_type}">${file.file_type.charAt(0).toUpperCase() + file.file_type.slice(1)}</span></td>
                <td>${formattedDate}</td>
                <td>${file.is_public == 1 ? '<i class="fa-solid fa-check" style="color: #28a745;"></i>' : '<i class="fa-solid fa-xmark" style="color: #dc3545;"></i>'}</td>
                <td>${hasTemplate ? '<i class="fa-solid fa-check" style="color: #28a745;"></i>' : '<i class="fa-solid fa-xmark" style="color: #dc3545;"></i>'}</td>
                <td>${hasPreview ? '<i class="fa-solid fa-check" style="color: #28a745;"></i>' : '<i class="fa-solid fa-xmark" style="color: #dc3545;"></i>'}</td>
                <td>
                    <a href="/markdown/${file.file_type === 'marp' ? 'marp-editor' : 'create'}?file_id=${file.id}" class="action-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                    <a href="#" class="action-icon-delete" title="Eliminar" data-file-id="${file.id}"><i class="fa-solid fa-trash"></i></a>
                </td>
            `;
            
            // Agregar la fila a la tabla
            savedFilesTableBody.appendChild(row);
        });

        // Agregar eventos a los botones de eliminar
        document.querySelectorAll('.action-icon-delete').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const fileId = this.dataset.fileId;
                if (confirm('¿Estás seguro de que deseas eliminar este archivo?')) {
                    deleteFile(fileId);
                }
            });
        });
    }

    /**
     * Elimina un archivo guardado
     * @param {number} fileId - ID del archivo a eliminar
     */
    function deleteFile(fileId) {
        const formData = new URLSearchParams();
        formData.append('id', fileId);

        fetch('/savedfile/delete-file', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al eliminar el archivo');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Eliminar la fila de la tabla
                const row = document.querySelector(`tr[data-file-id="${fileId}"]`);
                if (row) row.remove();
                
                // Si no quedan archivos, mostrar el mensaje de que no hay archivos
                const fileRows = savedFilesTableBody.querySelectorAll('tr:not(#loadingRow):not(#noFilesRow)');
                if (fileRows.length === 0) {
                    const noFilesRow = document.getElementById('noFilesRow');
                    if (noFilesRow) noFilesRow.style.display = 'table-row';
                }
            } else {
                alert(`Error: ${data.message || 'No se pudo eliminar el archivo'}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Error: ${error.message}`);
        });
    }
});