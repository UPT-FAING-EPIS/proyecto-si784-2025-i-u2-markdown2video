// public/js/resizable_images.js - Versión avanzada

/**
 * Función que busca imágenes y las hace interactivas (movibles, redimensionables, rotables).
 * @param {HTMLElement} targetElement - El contenedor donde buscar imágenes.
 */
function makeImagesInteractive(targetElement) {
    if (!targetElement) return;

    const images = targetElement.querySelectorAll('img');

    images.forEach(img => {
        // Evitamos volver a procesar una imagen
        if (img.parentElement.classList.contains('interactive-image-container')) {
            return;
        }

        // 1. Creamos los contenedores y controles
        const container = document.createElement('div');
        container.className = 'interactive-image-container';

        const rotateHandle = document.createElement('div');
        rotateHandle.className = 'handle rotate-handle';

        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'handle resize-handle';
        
        container.appendChild(rotateHandle);
        container.appendChild(resizeHandle);

        // Envolvemos la imagen
        img.parentNode.insertBefore(container, img);
        container.appendChild(img);

        // 2. Variables para almacenar el estado de la transformación
        let isResizing = false;
        let isRotating = false;
        let isDragging = false;
        
        let startX, startY, startWidth, startHeight;
        let startAngle, centerX, centerY;
        let startLeft, startTop;

        // --- LÓGICA DE REDIMENSIONAMIENTO ---
        resizeHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            startX = e.clientX;
            startY = e.clientY;
            startWidth = parseInt(document.defaultView.getComputedStyle(container).width, 10);
            startHeight = parseInt(document.defaultView.getComputedStyle(container).height, 10);
        });

        // --- LÓGICA DE ROTACIÓN ---
        rotateHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isRotating = true;
            const rect = container.getBoundingClientRect();
            centerX = rect.left + rect.width / 2;
            centerY = rect.top + rect.height / 2;
            const startVectorX = e.clientX - centerX;
            const startVectorY = e.clientY - centerY;
            startAngle = Math.atan2(startVectorY, startVectorX) * (180 / Math.PI);
            container.style.transformOrigin = 'center center';
        });

        // --- LÓGICA DE MOVIMIENTO (DRAG) ---
        img.addEventListener('mousedown', function(e) {
            // Solo iniciamos el arrastre si no estamos redimensionando o rotando
            if (!isResizing && !isRotating) {
                e.preventDefault();
                isDragging = true;
                startLeft = container.offsetLeft;
                startTop = container.offsetTop;
                startX = e.clientX;
                startY = e.clientY;
                // Hacemos el contenedor 'absolute' para poder moverlo libremente
                if (container.style.position !== 'absolute') {
                    container.style.position = 'absolute';
                    container.style.left = startLeft + 'px';
                    container.style.top = startTop + 'px';
                }
            }
        });

        // --- EVENTOS GLOBALES DE MOVIMIENTO Y SOLTADO ---
        document.addEventListener('mousemove', function(e) {
            if (isResizing) {
                const newWidth = startWidth + (e.clientX - startX);
                const newHeight = startHeight + (e.clientY - startY);
                container.style.width = newWidth > 50 ? newWidth + 'px' : '50px';
                container.style.height = newHeight > 50 ? newHeight + 'px' : '50px';
            } else if (isRotating) {
                const currentVectorX = e.clientX - centerX;
                const currentVectorY = e.clientY - centerY;
                const currentAngle = Math.atan2(currentVectorY, currentVectorX) * (180 / Math.PI);
                const rotation = currentAngle - startAngle;
                container.style.transform = `rotate(${rotation}deg)`;
            } else if (isDragging) {
                const newLeft = startLeft + (e.clientX - startX);
                const newTop = startTop + (e.clientY - startY);
                container.style.left = newLeft + 'px';
                container.style.top = newTop + 'px';
            }
        });

        document.addEventListener('mouseup', function() {
            isResizing = false;
            isRotating = false;
            isDragging = false;
        });
    });
}