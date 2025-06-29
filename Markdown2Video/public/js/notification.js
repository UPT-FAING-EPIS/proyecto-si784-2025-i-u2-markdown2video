/**
 * Sistema de notificaciones para Markdown2Video
 */
const NotificationSystem = {
    /**
     * Muestra una notificación en la pantalla
     * @param {string} message - El mensaje a mostrar
     * @param {string} type - El tipo de notificación (info, success, warning, error, viewing-public)
     * @param {number} duration - Duración en milisegundos (por defecto 3000ms)
     */
    show: function(message, type = 'info', duration = 3000) {
        // Crear el elemento de notificación
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Añadir al DOM
        document.body.appendChild(notification);
        
        // Configurar la animación de entrada
        notification.style.animation = 'fadeIn 0.3s ease-in-out';
        
        // Configurar el temporizador para eliminar la notificación
        setTimeout(() => {
            // Cambiar a animación de salida
            notification.style.animation = 'fadeOut 0.3s ease-in-out';
            
            // Eliminar después de que termine la animación
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, duration);
        
        return notification;
    },
    
    /**
     * Muestra una notificación de información
     */
    info: function(message, duration = 3000) {
        return this.show(message, 'info', duration);
    },
    
    /**
     * Muestra una notificación de éxito
     */
    success: function(message, duration = 3000) {
        return this.show(message, 'success', duration);
    },
    
    /**
     * Muestra una notificación de advertencia
     */
    warning: function(message, duration = 3000) {
        return this.show(message, 'warning', duration);
    },
    
    /**
     * Muestra una notificación de error
     */
    error: function(message, duration = 3000) {
        return this.show(message, 'error', duration);
    },
    
    /**
     * Muestra una notificación de visualización de archivo público
     */
    viewingPublic: function(message, duration = 5000) {
        return this.show(message, 'viewing-public', duration);
    }
};