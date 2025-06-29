// public/js/save_file.js

document.addEventListener('DOMContentLoaded', function() {
    // Obtener referencia al botón de guardar
    const saveFileBtn = document.getElementById('saveFileBtn');
    
    if (!saveFileBtn) {
        console.error('Botón de guardar no encontrado en la página');
        return;
    }
    
    // Determinar qué tipo de editor está presente en la página
    const isMarkdownEditor = document.getElementById('editor') !== null;
    const isMarpEditor = document.getElementById('editor-marp') !== null;
    
    // Agregar evento de clic al botón de guardar
    saveFileBtn.addEventListener('click', function() {
        // Mostrar un modal para que el usuario ingrese el título y la visibilidad del archivo
        showSaveFileModal();
    });
    
    // Función para mostrar el modal de guardado
    function showSaveFileModal() {
        // Crear el modal dinámicamente
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';
        modalOverlay.id = 'saveFileModal';
        modalOverlay.style.display = 'flex';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        modalContent.style.width = '400px';
        
        // Contenido del modal
        modalContent.innerHTML = `
            <button class="modal-close" id="closeSaveModalBtn">×</button>
            <h2>Guardar Archivo</h2>
            
            <div class="modal-body">
                <form id="saveFileForm">
                    <div class="form-group">
                        <label for="file_title">Título:</label>
                        <input type="text" id="file_title" name="file_title" required placeholder="Ingrese un título para su archivo">
                    </div>
                    <div class="form-group">
                        <label for="file_public">Visibilidad:</label>
                        <select id="file_public" name="file_public">
                            <option value="1">Público</option>
                            <option value="0">Privado</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Guardar</button>
                        <button type="button" class="cancel-btn" id="cancelSaveBtn">Cancelar</button>
                    </div>
                </form>
                <div id="saveStatus" class="status-message"></div>
            </div>
        `;
        
        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);
        
        // Agregar eventos a los botones del modal
        document.getElementById('closeSaveModalBtn').addEventListener('click', closeSaveModal);
        document.getElementById('cancelSaveBtn').addEventListener('click', closeSaveModal);
        document.getElementById('saveFileForm').addEventListener('submit', saveFile);
    }
    
    // Función para cerrar el modal
    function closeSaveModal() {
        const modal = document.getElementById('saveFileModal');
        if (modal) {
            document.body.removeChild(modal);
        }
    }
    
    // Función para guardar el archivo
    function saveFile(event) {
        event.preventDefault();
        
        // Obtener los valores del formulario
        const fileTitle = document.getElementById('file_title').value;
        const filePublic = document.getElementById('file_public').value;
        
        // Obtener el contenido del editor según el tipo
        let fileContent = '';
        let fileType = '';
        
        if (isMarkdownEditor && window.editorInstance) {
            fileContent = window.editorInstance.getValue();
            fileType = 'markdown';
        } else if (isMarpEditor && window.marpCodeMirrorEditor) {
            fileContent = window.marpCodeMirrorEditor.getValue();
            fileType = 'marp';
        } else {
            // Intentar obtener el contenido directamente del textarea
            const editorElement = document.getElementById('editor') || document.getElementById('editor-marp');
            if (editorElement) {
                fileContent = editorElement.value;
                fileType = isMarkdownEditor ? 'markdown' : 'marp';
            } else {
                showSaveStatus('Error: No se pudo obtener el contenido del editor', true);
                return;
            }
        }
        
        // Validar que haya contenido para guardar
        if (!fileContent.trim()) {
            showSaveStatus('Error: El editor está vacío', true);
            return;
        }
        
        // Preparar los datos para enviar al servidor
        const formData = new URLSearchParams();
        formData.append('title', fileTitle);
        formData.append('content', fileContent);
        formData.append('file_type', fileType);
        formData.append('is_public', filePublic);
        
        // Agregar template_id y preview_image_id si están disponibles
        if (window.templateId) {
            formData.append('template_id', window.templateId);
        }
        
        if (window.previewImageId) {
            formData.append('preview_image_id', window.previewImageId);
        }
        
        // Mostrar mensaje de guardando
        showSaveStatus('Guardando archivo...', false);
        
        // Enviar los datos al servidor
        fetch('/savedfile/save-file', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al guardar el archivo');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSaveStatus('Archivo guardado correctamente', false);
                setTimeout(() => {
                    closeSaveModal();
                    // Opcional: redirigir al dashboard o mostrar un mensaje de éxito
                    // window.location.href = '/dashboard';
                }, 1500);
            } else {
                showSaveStatus(`Error: ${data.message || 'No se pudo guardar el archivo'}`, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showSaveStatus(`Error: ${error.message}`, true);
        });
    }
    
    // Función para mostrar mensajes de estado
    function showSaveStatus(message, isError) {
        const statusElement = document.getElementById('saveStatus');
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.className = 'status-message' + (isError ? ' error' : ' success');
        }
    }
});