// public/js/base_marp.js
document.addEventListener("DOMContentLoaded", function () {
  const editorTextareaMarp = document.getElementById("editor-marp");
  const previewDivMarp = document.getElementById("ppt-preview");
  const modeSelectMarp = document.getElementById("mode-select-marp-page");

  let marpDebounceTimer;

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
