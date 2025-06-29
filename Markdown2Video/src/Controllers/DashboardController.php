<?php
// Define el namespace correcto basado en tu estructura y configuración de composer.json
namespace Dales\Markdown2video\Controllers;

// --- ¡NUEVO! ---
// Se importa el TemplateModel para poder obtener las plantillas.
use Dales\Markdown2video\Models\TemplateModel;
use Dales\Markdown2video\Models\SavedFileModel;
use PDO;

class DashboardController {

    private ?PDO $pdo;
    
    // --- ¡NUEVO! ---
    // Se añade la propiedad para el modelo de plantillas.
    private ?TemplateModel $templateModel = null;
    private ?SavedFileModel $savedFileModel = null;

    /**
     * Constructor para DashboardController.
     * Se inyecta la conexión PDO y se inicializan los modelos necesarios.
     * ¡CRÍTICO: Verifica si el usuario está autenticado!
     */
    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;

        // --- ¡NUEVO! ---
        // Si hay conexión a la base de datos, creamos una instancia del TemplateModel.
        if ($this->pdo) {
            $this->templateModel = new TemplateModel($this->pdo);
            $this->savedFileModel = new SavedFileModel($this->pdo);
        }

        // --- VERIFICACIÓN DE AUTENTICACIÓN (Tu código original, se mantiene) ---
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
    }

    /**
     * Método que se ejecuta para la ruta principal del dashboard (ej. /dashboard).
     * Obtiene datos y carga la vista principal del dashboard.
     */
    public function index(): void {
        // 1. Obtener datos necesarios para la vista (Tu código original)
        $userId = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Usuario';

        //    - Datos específicos del dashboard (ej. historial reciente, estadísticas)
        $historicalData = []; // Array vacío como placeholder (Tu código original)

        // Obtenemos las plantillas separadas por tipo
        $markdownTemplates = $this->templateModel ? $this->templateModel->getActiveTemplates('markdown') : [];
        $marpTemplates = $this->templateModel ? $this->templateModel->getActiveTemplates('marp') : [];

        // 2. Preparar variables que la vista necesitará (Tu código original)
        $pageTitle = "Dashboard Principal";
        $welcomeMessage = "¡Bienvenido de nuevo, " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "!";
        $base_url = BASE_URL;

        // 3. Cargar la vista del dashboard (Tu código original)
        $viewPath = VIEWS_PATH . 'dashboard.php';

        if (file_exists($viewPath)) {
            // Ahora, las variables $pageTitle, $welcomeMessage, $historicalData y la nueva $templates
            // estarán disponibles dentro del archivo de la vista.
            require_once $viewPath;
        } else {
            error_log("Error Crítico: Archivo de vista no encontrado: " . $viewPath);
            if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'error/500.php')) {
                 http_response_code(500);
                 include VIEWS_PATH . 'error/500.php';
            } else {
                http_response_code(500);
                echo "Error interno del servidor: no se pudo cargar la interfaz del dashboard.";
            }
        }
    }

}