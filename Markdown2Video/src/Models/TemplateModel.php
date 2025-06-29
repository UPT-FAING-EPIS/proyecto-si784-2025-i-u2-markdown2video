<?php
namespace Dales\Markdown2video\Models;

use PDO;

class TemplateModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todas las plantillas activas.
     * @param string|null $templateType Si se especifica, filtra por tipo de plantilla
     * @return array Array de plantillas activas
     */
    public function getActiveTemplates(?string $templateType = null): array {
        if ($templateType) {
            $sql = "SELECT id_template, title, description, preview_image_path, template_type FROM templates WHERE is_active = 1 AND template_type = :template_type ORDER BY title ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['template_type' => $templateType]);
        } else {
            $sql = "SELECT id_template, title, description, preview_image_path, template_type FROM templates WHERE is_active = 1 ORDER BY title ASC";
            $stmt = $this->pdo->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el contenido de una plantilla específica por su ID.
     */
    public function getTemplateContentById(int $id_template): ?string {
        $sql = "SELECT markdown_content FROM templates WHERE id_template = :id_template AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_template' => $id_template]);
        return $stmt->fetchColumn() ?: null;
    }
}