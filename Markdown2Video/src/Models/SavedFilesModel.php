<?php
namespace Dales\Markdown2video\Models;

use PDO;
use PDOException;

class SavedFilesModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todos los archivos guardados de un usuario específico.
     * @param int $userId ID del usuario
     * @return array Array de archivos guardados
     */
    public function getSavedFilesByUserId(int $userId): array {
        try {
            $sql = "SELECT id, title, file_type, updated_at, is_public FROM saved_files WHERE user_id = :user_id ORDER BY updated_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en SavedFilesModel::getSavedFilesByUserId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene un archivo guardado por su ID y el ID del usuario.
     * @param int $fileId ID del archivo
     * @param int $userId ID del usuario
     * @return array|null Datos del archivo o null si no se encuentra
     */
    public function getSavedFileByIdAndUserId(int $fileId, int $userId): ?array {
        try {
            $sql = "SELECT * FROM saved_files WHERE id = :id AND user_id = :user_id LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $fileId,
                'user_id' => $userId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error en SavedFilesModel::getSavedFileByIdAndUserId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Guarda un nuevo archivo o actualiza uno existente.
     * @param int $userId ID del usuario
     * @param string $title Título del archivo
     * @param string $content Contenido del archivo
     * @param string $fileType Tipo de archivo (markdown o marp)
     * @param bool $isPublic Si el archivo es público o no
     * @param int|null $fileId ID del archivo si es una actualización
     * @return int|bool ID del archivo creado/actualizado o false en caso de error
     */
    public function saveFile(int $userId, string $title, string $content, string $fileType, bool $isPublic, ?int $fileId = null): int|bool {
        try {
            if ($fileId) {
                // Actualizar archivo existente
                $sql = "UPDATE saved_files SET title = :title, content = :content, file_type = :file_type, is_public = :is_public, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'title' => $title,
                    'content' => $content,
                    'file_type' => $fileType,
                    'is_public' => $isPublic ? 1 : 0,
                    'id' => $fileId,
                    'user_id' => $userId
                ]);
                return $stmt->rowCount() > 0 ? $fileId : false;
            } else {
                // Crear nuevo archivo
                $sql = "INSERT INTO saved_files (user_id, title, content, file_type, is_public) VALUES (:user_id, :title, :content, :file_type, :is_public)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'user_id' => $userId,
                    'title' => $title,
                    'content' => $content,
                    'file_type' => $fileType,
                    'is_public' => $isPublic ? 1 : 0
                ]);
                return $this->pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Error en SavedFilesModel::saveFile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un archivo guardado.
     * @param int $fileId ID del archivo
     * @param int $userId ID del usuario
     * @return bool True si se eliminó correctamente, false en caso contrario
     */
    public function deleteFile(int $fileId, int $userId): bool {
        try {
            $sql = "DELETE FROM saved_files WHERE id = :id AND user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $fileId,
                'user_id' => $userId
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en SavedFilesModel::deleteFile: " . $e->getMessage());
            return false;
        }
    }
}