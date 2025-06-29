<?php
// src/Controllers/SavedFileController.php
namespace Dales\Markdown2video\Controllers;

use Dales\Markdown2video\Models\SavedFileModel;
use PDO;

class SavedFileController {
    private SavedFileModel $savedFileModel;
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->savedFileModel = new SavedFileModel($pdo);
    }

    /**
     * Guarda un archivo en la base de datos
     */
    public function saveFile() {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        // Verificar si se recibieron los datos necesarios
        if (!isset($_POST['title']) || !isset($_POST['content']) || !isset($_POST['file_type']) || !isset($_POST['is_public'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $fileType = $_POST['file_type'];
        $isPublic = (bool)$_POST['is_public'];
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : null;
        $previewImageId = isset($_POST['preview_image_id']) ? (int)$_POST['preview_image_id'] : null;

        // Validar datos
        if (empty($title) || empty($content) || !in_array($fileType, ['markdown', 'marp'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            return;
        }

        // Guardar el archivo
        $fileId = $this->savedFileModel->saveFile($userId, $title, $content, $fileType, $isPublic, $templateId, $previewImageId);

        if ($fileId) {
            echo json_encode(['success' => true, 'message' => 'Archivo guardado correctamente', 'file_id' => $fileId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo']);
        }
    }

    /**
     * Obtiene todos los archivos guardados del usuario actual
     */
    public function getUserFiles() {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        $userId = $_SESSION['user_id'];

        // Obtener los archivos del usuario
        $files = $this->savedFileModel->getUserFiles($userId);

        echo json_encode(['success' => true, 'files' => $files]);
    }

    /**
     * Obtiene un archivo guardado por su ID
     */
    public function getFile() {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        // Verificar si se recibió el ID del archivo
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de archivo no especificado']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $fileId = (int)$_GET['id'];

        // Obtener el archivo
        $file = $this->savedFileModel->getFileById($fileId, $userId);

        if ($file) {
            echo json_encode(['success' => true, 'file' => $file]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado o sin permisos']);
        }
    }

    /**
     * Actualiza un archivo guardado
     */
    public function updateFile() {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        // Verificar si se recibieron los datos necesarios
        if (!isset($_POST['id']) || !isset($_POST['title']) || !isset($_POST['content']) || !isset($_POST['is_public'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $fileId = (int)$_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $isPublic = (bool)$_POST['is_public'];
        $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : null;
        $previewImageId = isset($_POST['preview_image_id']) ? (int)$_POST['preview_image_id'] : null;

        // Validar datos
        if (empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            return;
        }

        // Actualizar el archivo
        $success = $this->savedFileModel->updateFile($fileId, $userId, $title, $content, $isPublic, $templateId, $previewImageId);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Archivo actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el archivo']);
        }
    }

    /**
     * Elimina un archivo guardado
     */
    public function deleteFile() {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        // Verificar si se recibió el ID del archivo
        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de archivo no especificado']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $fileId = (int)$_POST['id'];

        // Eliminar el archivo
        $success = $this->savedFileModel->deleteFile($fileId, $userId);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Archivo eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el archivo']);
        }
    }
}