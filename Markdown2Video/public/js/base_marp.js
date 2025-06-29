// public/js/base_marp.js
document.addEventListener("DOMContentLoaded", function () {
  const editorTextareaMarp = document.getElementById("editor-marp");
  const previewDivMarp = document.getElementById("ppt-preview");
  const modeSelectMarp = document.getElementById("mode-select-marp-page");
  const saveMarpBtn = document.getElementById("save-marp-btn");
  const publicToggle = document.getElementById("public-toggle");

  let marpDebounceTimer;
  // Variable para almacenar el ID del archivo si estamos editando uno existente
  let currentFileId = null;
  // Variable para almacenar si el archivo es público o privado
  let isPublic = false;
  
  // Extraer el ID del archivo de la URL si existe
  const urlParams = new URLSearchParams(window.location.search);
  const pathSegments = window.location.pathname.split('/');
  const lastSegment = pathSegments[pathSegments.length - 1];
  
  // Verificar si el último segmento de la URL es un número (ID del archivo)
  if (!isNaN(lastSegment) && lastSegment.trim() !== '') {
    currentFileId = parseInt(lastSegment);
    
    // Si estamos editando un archivo existente, cargar su estado público/privado
    if (currentFileId && publicToggle) {
      // Hacer una petición para obtener la información del archivo
      fetch(`/api/saved-files/info/${currentFileId}`, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data) {
            // Actualizar el estado del toggle
            isPublic = data.data.is_public;
            publicToggle.checked = isPublic;
          }
        })
        .catch(error => {
          console.error('Error al cargar información del archivo:', error);
        });
    }
  }

  if (!editorTextareaMarp) {
    console.error(
      "Textarea #editor-marp no encontrado. Editor Marp no se inicializará."
    );
    return;
  }

  const marpCodeMirrorEditor = CodeMirror.fromTextArea(editorTextareaMarp, {
    mode: "markdown",
    theme: "dracula",
    lineNumbers: true,
    lineWrapping: true,
    matchBrackets: true,
    placeholder:
      editorTextareaMarp.getAttribute("placeholder") ||
      "Escribe tu presentación Marp aquí...",
    extraKeys: { Enter: "newlineAndIndentContinueMarkdownList" },
  });

  // Carga el contenido desde sessionStorage si existe (después de arrastrar un archivo o editar uno existente)
  const contentToLoad = sessionStorage.getItem('markdown_content_to_load');
  if (contentToLoad && marpCodeMirrorEditor) {
    marpCodeMirrorEditor.setValue(contentToLoad);
    // Limpiamos el sessionStorage para que no se vuelva a cargar si se recarga la página
    sessionStorage.removeItem('markdown_content_to_load');
  }

  function refreshMarpEditorLayout() {
    marpCodeMirrorEditor.setSize("100%", "100%");
    marpCodeMirrorEditor.refresh();
  }
  setTimeout(refreshMarpEditorLayout, 50);

  async function updateMarpPreview() {
    if (!previewDivMarp || !marpCodeMirrorEditor) return;
    const markdownText = marpCodeMirrorEditor.getValue();
    previewDivMarp.innerHTML = "<p>Generando vista previa Marp...</p>";

    try {
      const renderEndpoint = "/markdown/render-marp-preview";
      const requestBody = `markdown=${encodeURIComponent(markdownText)}`;
      const headers = { "Content-Type": "application/x-www-form-urlencoded" };

      const response = await fetch(renderEndpoint, {
        method: "POST",
        headers: headers,
        body: requestBody,
      });

      if (!response.ok) {
        let errorDetail = await response.text();
        try {
          const errorJson = JSON.parse(errorDetail);
          errorDetail = errorJson.details || errorJson.error || errorDetail;
        } catch (e) {
          /* No era JSON */
        }
        throw new Error(
          `Error del servidor: ${response.status} - ${errorDetail}`
        );
      }

      const htmlResult = await response.text();

      if (typeof DOMPurify !== "undefined") {
        const cleanHtml = DOMPurify.sanitize(htmlResult, {
          USE_PROFILES: { html: true },
          // Configuraciones específicas de Marp pueden agregarse aquí si es necesario
        });
        previewDivMarp.innerHTML = cleanHtml;
      } else {
        console.warn(
          "DOMPurify no está cargado. El HTML se insertará sin saneamiento."
        );
        previewDivMarp.innerHTML = htmlResult;
      }
    } catch (error) {
      console.error("Error al generar vista previa Marp:", error);
      previewDivMarp.innerHTML = "";
      const errorParagraph = document.createElement("p");
      errorParagraph.style.color = "red";
      errorParagraph.textContent = `Error al cargar la previsualización Marp: ${error.message}`;
      previewDivMarp.appendChild(errorParagraph);
    }
  }

  marpCodeMirrorEditor.on("change", () => {
    clearTimeout(marpDebounceTimer);
    marpDebounceTimer = setTimeout(updateMarpPreview, 700);
  });

  if (modeSelectMarp) {
    modeSelectMarp.addEventListener("change", function () {
      const selectedMode = this.value;
      if (selectedMode === "markdown") {
        window.location.href = "/markdown/create";
      } else if (selectedMode === "marp") {
        console.log("Modo Marp ya seleccionado.");
      }
    });
  }

  // Función para crear un modal personalizado para el título
  function createTitleModal(isNewFile = true, currentTitle = '') {
    return new Promise((resolve, reject) => {
      // Crear elementos del modal
      const modalOverlay = document.createElement('div');
      modalOverlay.className = 'modal-overlay';
      
      const modalContent = document.createElement('div');
      modalContent.className = 'modal-content';
      
      // Header del modal
      const modalHeader = document.createElement('div');
      modalHeader.className = 'modal-header';
      const modalTitle = document.createElement('h3');
      modalTitle.textContent = isNewFile ? 'Guardar nuevo archivo' : 'Actualizar archivo';
      modalHeader.appendChild(modalTitle);
      
      // Body del modal
      const modalBody = document.createElement('div');
      modalBody.className = 'modal-body';
      
      const formGroup = document.createElement('div');
      formGroup.className = 'form-group';
      
      const titleLabel = document.createElement('label');
      titleLabel.setAttribute('for', 'file-title-input');
      titleLabel.textContent = 'Título del archivo:';
      
      const titleInput = document.createElement('input');
      titleInput.type = 'text';
      titleInput.id = 'file-title-input';
      titleInput.className = 'form-control';
      titleInput.value = currentTitle;
      titleInput.placeholder = 'Ingresa un título para tu archivo';
      titleInput.required = true;
      
      formGroup.appendChild(titleLabel);
      formGroup.appendChild(titleInput);
      modalBody.appendChild(formGroup);
      
      // Footer del modal
      const modalFooter = document.createElement('div');
      modalFooter.className = 'modal-footer';
      
      const cancelButton = document.createElement('button');
      cancelButton.className = 'btn btn-secondary';
      cancelButton.textContent = 'Cancelar';
      cancelButton.onclick = () => {
        document.body.removeChild(modalOverlay);
        reject('Operación cancelada');
      };
      
      const saveButton = document.createElement('button');
      saveButton.className = 'btn btn-primary';
      saveButton.textContent = 'Guardar';
      saveButton.onclick = () => {
        const title = titleInput.value.trim();
        if (!title) {
          titleInput.style.borderColor = 'red';
          return;
        }
        document.body.removeChild(modalOverlay);
        resolve(title);
      };
      
      // Si es un archivo existente, agregar opción para mantener el título actual
      if (!isNewFile) {
        const keepTitleButton = document.createElement('button');
        keepTitleButton.className = 'btn btn-secondary';
        keepTitleButton.textContent = 'Mantener título actual';
        keepTitleButton.onclick = () => {
          document.body.removeChild(modalOverlay);
          resolve('KEEP_EXISTING_TITLE');
        };
        modalFooter.appendChild(keepTitleButton);
      }
      
      modalFooter.appendChild(cancelButton);
      modalFooter.appendChild(saveButton);
      
      // Ensamblar el modal
      modalContent.appendChild(modalHeader);
      modalContent.appendChild(modalBody);
      modalContent.appendChild(modalFooter);
      modalOverlay.appendChild(modalContent);
      
      // Agregar el modal al DOM
      document.body.appendChild(modalOverlay);
      
      // Enfocar el input
      titleInput.focus();
      
      // Permitir enviar con Enter
      titleInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          saveButton.click();
        }
      });
    });
  }
  
  // Función para guardar el archivo Marp
  async function saveMarpFile() {
    const markdownContent = marpCodeMirrorEditor.getValue();
    
    // Validar que el contenido no esté vacío
    if (!markdownContent.trim()) {
      alert('El contenido no puede estar vacío');
      return;
    }
    
    try {
      // Obtener el título mediante el modal personalizado
      let title;
      if (!currentFileId) {
        // Nuevo archivo
        title = await createTitleModal(true);
      } else {
        // Archivo existente
        title = await createTitleModal(false);
      }
      
      // Preparar los datos para enviar
      const formData = new FormData();
      formData.append('content', markdownContent);
      formData.append('title', title);
      
      if (currentFileId) formData.append('fileId', currentFileId);
      
      // Obtener el estado del toggle de público/privado
      isPublic = publicToggle.checked;
      formData.append('isPublic', isPublic ? '1' : '0'); // Enviar como '1' o '0' para asegurar la conversión correcta en PHP
      
      // Mostrar indicador de carga
      saveMarpBtn.textContent = 'Guardando...';
      saveMarpBtn.disabled = true;
      
      // Enviar la solicitud al servidor
      const response = await fetch('/markdown/save-marp', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Actualizar el ID del archivo si es nuevo
        if (!currentFileId && result.fileId) {
          currentFileId = result.fileId;
          // Actualizar la URL sin recargar la página
          window.history.replaceState({}, document.title, `/markdown/marp-editor/${currentFileId}`);
        }
        // Mostrar mensaje de éxito
        const successMessage = document.createElement('div');
        successMessage.style.position = 'fixed';
        successMessage.style.top = '20px';
        successMessage.style.left = '50%';
        successMessage.style.transform = 'translateX(-50%)';
        successMessage.style.backgroundColor = '#4CAF50';
        successMessage.style.color = 'white';
        successMessage.style.padding = '10px 20px';
        successMessage.style.borderRadius = '4px';
        successMessage.style.zIndex = '1000';
        successMessage.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        successMessage.textContent = 'Archivo guardado correctamente';
        document.body.appendChild(successMessage);
        
        // Eliminar el mensaje después de 3 segundos
        setTimeout(() => {
          document.body.removeChild(successMessage);
        }, 3000);
      } else {
        alert(`Error al guardar: ${result.error || 'Error desconocido'}`);
      }
    } catch (error) {
      if (error !== 'Operación cancelada') {
        console.error('Error al guardar el archivo:', error);
        alert('Error al guardar el archivo. Revisa la consola para más detalles.');
      }
    } finally {
      // Restaurar el botón solo si no fue una operación cancelada
      if (saveMarpBtn.disabled) {
        saveMarpBtn.textContent = 'Guardar';
        saveMarpBtn.disabled = false;
      }
    }
  }
  
  // Agregar evento al botón de guardar
  if (saveMarpBtn) {
    saveMarpBtn.addEventListener('click', saveMarpFile);
  }

  // Event listeners para los botones de generación
  const generateButtons = document.querySelectorAll(".generate-btn");
  generateButtons.forEach((button) => {
    button.addEventListener("click", async function () {
      const format = this.getAttribute("data-format");

      if (format === "mp4") {
        await generateMp4Video();
      } else if (format === "pdf") {
        await generatePdf();
      } else if (format === "html") {
        await generateHtml();
      } else if (format === "jpg") {
        await generateJpg();
      } else {
        console.log(`Funcionalidad para ${format} no implementada aún.`);
      }
    });
  });

  async function generateMp4Video() {
    console.log("[MARP-UI] Iniciando generación de video MP4");
    const markdownContent = marpCodeMirrorEditor.getValue();
    console.log(
      `[MARP-UI] Longitud del contenido Markdown: ${markdownContent.length} caracteres`
    );

    if (!markdownContent.trim()) {
      console.error("[MARP-UI-ERROR] Contenido Markdown vacío");
      alert(
        "Por favor, escribe contenido en el editor antes de generar el video."
      );
      return;
    }

    console.log("[MARP-UI] Mostrando indicador de carga");
    const mp4Button = document.querySelector('[data-format="mp4"]');
    const originalText = mp4Button.textContent;
    mp4Button.textContent = "Generando Video...";
    mp4Button.disabled = true;

    try {
      console.log("[MARP-UI] Enviando contenido al servidor");
      const response = await fetch("/markdown/generate-mp4-video", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `markdown=${encodeURIComponent(markdownContent)}`,
      });

      // Lee la respuesta como TEXTO primero (no como JSON)
      const rawResponse = await response.text();
      console.log("[MARP-UI] Respuesta cruda:", rawResponse);

      // Intenta parsear manualmente el JSON
      let result;
      try {
        result = JSON.parse(rawResponse);
      } catch (jsonError) {
        console.error(
          "[MARP-UI-ERROR] El servidor no devolvió JSON válido:",
          jsonError
        );
        throw new Error(`Respuesta inválida del servidor: ${rawResponse}`);
      }

      // Procesa el resultado como antes...
      if (result.success) {
        console.log("[MARP-UI] Video generado exitosamente");
        showVideoPreview(result.videoUrl);
        setTimeout(() => {
          window.location.href = result.downloadPageUrl;
        }, 2000);
      } else {
        console.error("[MARP-UI-ERROR] Error en la generación:", result.error);
        alert(
          "Error al generar el video: " + (result.error || "Error desconocido")
        );
      }
    } catch (error) {
      console.error("[MARP-UI-ERROR] Error completo:", error);
      alert("Error al generar el video. Revisa la consola para más detalles.");
    } finally {
      console.log("[MARP-UI] Finalizando proceso de generación");
      mp4Button.textContent = originalText;
      mp4Button.disabled = false;
    }
  }

  function showVideoPreview(videoUrl) {
    // Crear un elemento de video para mostrar la preview
    const previewContainer = document.getElementById("ppt-preview");

    const videoElement = document.createElement("video");
    videoElement.src = videoUrl;
    videoElement.controls = true;
    videoElement.style.width = "100%";
    videoElement.style.maxWidth = "600px";
    videoElement.style.height = "auto";

    const successMessage = document.createElement("p");
    successMessage.textContent = "¡Video generado exitosamente!";
    successMessage.style.color = "#28a745";
    successMessage.style.fontWeight = "bold";
    successMessage.style.textAlign = "center";

    previewContainer.innerHTML = "";
    previewContainer.appendChild(successMessage);
    previewContainer.appendChild(videoElement);
  }

  async function generatePdf() {
    console.log("[MARP-UI] Iniciando generación de PDF");
    const markdownContent = marpCodeMirrorEditor.getValue();
    console.log(
      `[MARP-UI] Longitud del contenido Markdown: ${markdownContent.length} caracteres`
    );

    if (!markdownContent.trim()) {
      console.error("[MARP-UI-ERROR] Contenido Markdown vacío");
      alert(
        "Por favor, escribe contenido en el editor antes de generar el PDF."
      );
      return;
    }

    console.log("[MARP-UI] Mostrando indicador de carga");
    const pdfButton = document.querySelector('[data-format="pdf"]');
    const originalText = pdfButton.textContent;
    pdfButton.textContent = "Generando PDF...";
    pdfButton.disabled = true;

    try {
      console.log("[MARP-UI] Enviando contenido al servidor");
      const response = await fetch("/markdown/generate-pdf-from-markdown", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `markdown=${encodeURIComponent(markdownContent)}`,
      });

      const rawResponse = await response.text();
      console.log("[MARP-UI] Respuesta cruda:", rawResponse);

      let result;
      try {
        result = JSON.parse(rawResponse);
      } catch (jsonError) {
        console.error(
          "[MARP-UI-ERROR] El servidor no devolvió JSON válido:",
          jsonError
        );
        throw new Error(`Respuesta inválida del servidor: ${rawResponse}`);
      }

      if (result.success) {
        console.log("[MARP-UI] PDF generado exitosamente");
        window.open(result.downloadPageUrl, "_blank");
      } else {
        console.error("[MARP-UI-ERROR] Error en la generación:", result.error);
        alert(
          "Error al generar el PDF: " + (result.error || "Error desconocido")
        );
      }
    } catch (error) {
      console.error("[MARP-UI-ERROR] Error completo:", error);
      alert("Error al generar el PDF. Revisa la consola para más detalles.");
    } finally {
      console.log("[MARP-UI] Finalizando proceso de generación");
      pdfButton.textContent = originalText;
      pdfButton.disabled = false;
    }
  }
  async function generateHtml() {
    console.log("[MARP-UI] Iniciando generación de HTML");
    const markdownContent = marpCodeMirrorEditor.getValue();
    console.log(
      `[MARP-UI] Longitud del contenido Markdown: ${markdownContent.length} caracteres`
    );

    if (!markdownContent.trim()) {
      console.error("[MARP-UI-ERROR] Contenido Markdown vacío");
      alert(
        "Por favor, escribe contenido en el editor antes de generar el HTML."
      );
      return;
    }

    console.log("[MARP-UI] Mostrando indicador de carga");
    const htmlButton = document.querySelector('[data-format="html"]');
    const originalText = htmlButton.textContent;
    htmlButton.textContent = "Generando HTML...";
    htmlButton.disabled = true;

    try {
      console.log("[MARP-UI] Enviando contenido al servidor");
      const response = await fetch("/markdown/generate-html-from-markdown", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `markdown=${encodeURIComponent(markdownContent)}`,
      });

      const rawResponse = await response.text();
      console.log("[MARP-UI] Respuesta cruda:", rawResponse);

      let result;
      try {
        result = JSON.parse(rawResponse);
      } catch (jsonError) {
        console.error(
          "[MARP-UI-ERROR] El servidor no devolvió JSON válido:",
          jsonError
        );
        throw new Error(`Respuesta inválida del servidor: ${rawResponse}`);
      }

      if (result.success) {
        console.log("[MARP-UI] HTML generado exitosamente");
        window.open(result.downloadPageUrl, "_blank");
      } else {
        console.error("[MARP-UI-ERROR] Error en la generación:", result.error);
        alert(
          "Error al generar el HTML: " + (result.error || "Error desconocido")
        );
      }
    } catch (error) {
      console.error("[MARP-UI-ERROR] Error completo:", error);
      alert("Error al generar el HTML. Revisa la consola para más detalles.");
    } finally {
      console.log("[MARP-UI] Finalizando proceso de generación");
      htmlButton.textContent = originalText;
      htmlButton.disabled = false;
    }
  }
  
  /**
   * Genera imágenes PNG a partir del contenido Markdown
   */
  async function generateJpg() {
    console.log("[MARP-UI] Iniciando generación de PNG");
    const markdownContent = marpCodeMirrorEditor.getValue();
    console.log(
      `[MARP-UI] Longitud del contenido Markdown: ${markdownContent.length} caracteres`
    );

    if (!markdownContent.trim()) {
      console.error("[MARP-UI-ERROR] Contenido Markdown vacío");
      alert(
        "Por favor, escribe contenido en el editor antes de generar las imágenes PNG."
      );
      return;
    }

    console.log("[MARP-UI] Mostrando indicador de carga");
    const jpgButton = document.querySelector('[data-format="jpg"]');
    const originalText = jpgButton.textContent;
    jpgButton.textContent = "Generando PNG...";
    jpgButton.disabled = true;

    try {
      console.log("[MARP-UI] Enviando contenido al servidor");
      const response = await fetch("/markdown/generate-jpg-from-markdown", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `markdown=${encodeURIComponent(markdownContent)}`,
      });

      const rawResponse = await response.text();
      console.log("[MARP-UI] Respuesta cruda:", rawResponse);

      let result;
      try {
        result = JSON.parse(rawResponse);
      } catch (jsonError) {
        console.error(
          "[MARP-UI-ERROR] El servidor no devolvió JSON válido:",
          jsonError
        );
        throw new Error(`Respuesta inválida del servidor: ${rawResponse}`);
      }

      if (result.success) {
        console.log("[MARP-UI] PNG generado exitosamente");
        window.open(result.downloadPageUrl, "_blank");
      } else {
        console.error("[MARP-UI-ERROR] Error en la generación:", result.error);
        alert(
          "Error al generar el PNG: " + (result.error || "Error desconocido")
        );
      }
    } catch (error) {
      console.error("[MARP-UI-ERROR] Error completo:", error);
      alert("Error al generar el PNG. Revisa la consola para más detalles.");
    } finally {
      console.log("[MARP-UI] Finalizando proceso de generación");
      jpgButton.textContent = originalText;
      jpgButton.disabled = false;
    }
  }

  setTimeout(updateMarpPreview, 100);
});
