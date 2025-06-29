<?php
namespace Dales\Markdown2video\Controllers;

use PDO;
use Dales\Markdown2video\Models\SavedFilesModel;

class ApiController {
    private ?PDO $pdo;
    private ?SavedFilesModel $savedFilesModel = null;

    /**
     * Constructor para ApiController.
     * Se inyecta la conexión PDO y se inicializan los modelos necesarios.
     */
    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;

        // Si hay conexión a la base de datos, creamos instancias de los modelos necesarios
        if ($this->pdo) {
            $this->savedFilesModel = new SavedFilesModel($this->pdo);
        }

        // Verificar si el usuario está autenticado para todas las acciones de la API
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }
    }
    
    /**
     * Método para obtener información de un archivo guardado.
     * Ruta: /api/saved-files/info/{id}
     */
    public function getFileInfo(int $fileId): void {
        // Verificar que la solicitud sea AJAX
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'error' => 'Solicitud no permitida']);
            exit();
        }

        // Obtener el ID del usuario de la sesión
        $userId = $_SESSION['user_id'] ?? 0;

        // Verificar que el modelo esté inicializado
        if (!$this->savedFilesModel) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            exit();
        }

        // Obtener información del archivo
        $fileInfo = $this->savedFilesModel->getSavedFileByIdAndUserId($fileId, $userId);

        if ($fileInfo) {
            echo json_encode([
                'success' => true, 
                'data' => [
                    'id' => $fileInfo['id'],
                    'title' => $fileInfo['title'],
                    'file_type' => $fileInfo['file_type'],
                    'is_public' => (bool)$fileInfo['is_public'],
                    'created_at' => $fileInfo['created_at'],
                    'updated_at' => $fileInfo['updated_at']
                ]
            ]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        }
        exit();
    }

    /**
     * Método para eliminar un archivo guardado.
     * Ruta: /api/saved-files/delete/{id}
     */
    public function deleteSavedFile(int $fileId): void {
        // Verificar que la solicitud sea AJAX
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'error' => 'Solicitud no permitida']);
            exit();
        }

        // Obtener el ID del usuario de la sesión
        $userId = $_SESSION['user_id'] ?? 0;

        // Verificar que el modelo esté inicializado
        if (!$this->savedFilesModel) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
            exit();
        }

        // Intentar eliminar el archivo
        $result = $this->savedFilesModel->deleteFile($fileId, $userId);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Archivo eliminado correctamente']);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el archivo o no se encontró']);
        }
        exit();
    }
}