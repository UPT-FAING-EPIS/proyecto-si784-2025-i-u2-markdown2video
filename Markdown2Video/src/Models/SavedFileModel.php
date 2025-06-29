<?php
// src/Models/SavedFileModel.php
namespace Dales\Markdown2video\Models;

use PDO;
use PDOException;

class SavedFileModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Guarda un archivo en la base de datos
     * 
     * @param int $userId ID del usuario
     * @param string $title Título del archivo
     * @param string $content Contenido del archivo
     * @param string $fileType Tipo de archivo (markdown o marp)
     * @param bool $isPublic Si el archivo es público o no
     * @param int|null $templateId ID de la plantilla (opcional)
     * @param int|null $previewImageId ID de la imagen de vista previa (opcional)
     * @return int|bool ID del archivo guardado o false en caso de error
     */
    public function saveFile(int $userId, string $title, string $content, string $fileType, bool $isPublic, ?int $templateId = null, ?int $previewImageId = null): int|bool {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO saved_files (user_id, title, content, file_type, is_public, template_id, preview_image_id) 
                 VALUES (:user_id, :title, :content, :file_type, :is_public, :template_id, :preview_image_id)"
            );
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':content', $content, PDO::PARAM_STR);
            $stmt->bindParam(':file_type', $fileType, PDO::PARAM_STR);
            $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_BOOL);
            $stmt->bindParam(':template_id', $templateId, PDO::PARAM_INT);
            $stmt->bindParam(':preview_image_id', $previewImageId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("PDOException en saveFile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todos los archivos guardados de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Archivos guardados
     */
    public function getUserFiles(int $userId): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, title, file_type, is_public, modified_at, template_id, preview_image_id 
                 FROM saved_files 
                 WHERE user_id = :user_id 
                 ORDER BY modified_at DESC"
            );
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDOException en getUserFiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene un archivo guardado por su ID
     * 
     * @param int $fileId ID del archivo
     * @param int $userId ID del usuario (para verificar propiedad)
     * @return array|bool Datos del archivo o false si no existe o no pertenece al usuario
     */
    public function getFileById(int $fileId, int $userId): array|bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM saved_files 
                 WHERE id = :id AND (user_id = :user_id OR is_public = 1)"
            );
            
            $stmt->bindParam(':id', $fileId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PDOException en getFileById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza un archivo guardado
     * 
     * @param int $fileId ID del archivo
     * @param int $userId ID del usuario (para verificar propiedad)
     * @param string $title Nuevo título
     * @param string $content Nuevo contenido
     * @param bool $isPublic Nuevo estado público/privado
     * @param int|null $templateId ID de la plantilla (opcional)
     * @param int|null $previewImageId ID de la imagen de vista previa (opcional)
     * @return bool Éxito de la operación
     */
    public function updateFile(int $fileId, int $userId, string $title, string $content, bool $isPublic, ?int $templateId = null, ?int $previewImageId = null): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE saved_files 
                 SET title = :title, content = :content, is_public = :is_public, 
                     template_id = :template_id, preview_image_id = :preview_image_id 
                 WHERE id = :id AND user_id = :user_id"
            );
            
            $stmt->bindParam(':id', $fileId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':content', $content, PDO::PARAM_STR);
            $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_BOOL);
            $stmt->bindParam(':template_id', $templateId, PDO::PARAM_INT);
            $stmt->bindParam(':preview_image_id', $previewImageId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PDOException en updateFile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un archivo guardado
     * 
     * @param int $fileId ID del archivo
     * @param int $userId ID del usuario (para verificar propiedad)
     * @return bool Éxito de la operación
     */
    public function deleteFile(int $fileId, int $userId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM saved_files 
                 WHERE id = :id AND user_id = :user_id"
            );
            
            $stmt->bindParam(':id', $fileId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PDOException en deleteFile: " . $e->getMessage());
            return false;
        }
    }
}