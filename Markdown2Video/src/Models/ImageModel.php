<?php
// src/Models/ImageModel.php
namespace Dales\Markdown2video\Models;

use PDO;
use PDOException;

class ImageModel {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: ' . BASE_URL . '/auth/login'); 
            exit();
        }
    }

    public function saveImage(int $userId, string $imageName, string $originalFilename, string $imageData, string $mimeType): bool {
        // Sin cambios en esta función
        $sql = "INSERT INTO user_images (user_id, image_name, original_filename, image_data, mime_type) VALUES (:user_id, :image_name, :original_filename, :image_data, :mime_type)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':image_name', $imageName, PDO::PARAM_STR);
            $stmt->bindParam(':original_filename', $originalFilename, PDO::PARAM_STR);
            $stmt->bindParam(':image_data', $imageData, PDO::PARAM_LOB);
            $stmt->bindParam(':mime_type', $mimeType, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                error_log("Error al guardar imagen: Nombre de imagen '$imageName' ya existe para el usuario $userId.");
            } else {
                error_log("PDOException en saveImage: " . $e->getMessage());
            }
            return false;
        }
    }

    public function getImagesByUserId(int $userId): array {
        // --- CORRECCIÓ ---
        // Se cambia "SELECT id," por "SELECT id_image," para que coincida con la nueva columna.
        $sql = "SELECT id_image, image_name, original_filename, uploaded_at FROM user_images WHERE user_id = :user_id ORDER BY uploaded_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getImageByNameAndUserId(string $imageName, int $userId): ?array {
        // Sin cambios en esta función
        $sql = "SELECT image_data, mime_type FROM user_images WHERE image_name = :image_name AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['image_name' => $imageName, 'user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deleteImageByIdAndUserId(int $id_image, int $userId): bool {
        // --- CORRECCIÓN ---
        // Se cambia "WHERE id = :id" por "WHERE id_image = :id_image".
        // También se cambia el nombre del parámetro para mayor claridad.
        $sql = "DELETE FROM user_images WHERE id_image = :id_image AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_image', $id_image, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}