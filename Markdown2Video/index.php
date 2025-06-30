<?php
// --- INICIO DE index.php ---

// 0. INCLUIR EL AUTOLOADER DE COMPOSER (¡EL MÁS IMPORTANTE!)
// Esta línea debe ser la primera o una de las primeras para que las clases estén disponibles.
require_once __DIR__ . '/vendor/autoload.php';

// 1. CARGAR VARIABLES DE ENTORNO (SI USAS .env)
// Asegúrate de haber ejecutado: composer require vlucas/phpdotenv
try {
    // Cargar Dotenv (asume que .env está en la raíz del proyecto)
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    // Validar que las variables de entorno esenciales para la base de datos existan y no estén vacías
    // DB_PASS puede estar vacía en entornos de desarrollo si así está configurada la BD.
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // Archivo .env no encontrado. Loguear advertencia.
    // En producción, podrías querer detener la aplicación si .env es crítico y no hay defaults seguros.
    error_log("Advertencia: Archivo .env no encontrado o no se pudo cargar. Verifique la configuración. Error: " . $e->getMessage());
} catch (Dotenv\Exception\ValidationException $e) {
    // Variables de entorno requeridas faltan o están vacías.
    error_log("Error Crítico: Faltan variables de entorno requeridas (DB_HOST, DB_NAME, DB_USER) o están vacías. " . $e->getMessage());
    // Detener la aplicación es apropiado aquí, ya que la configuración es incorrecta.
    die("Error de configuración del servidor. Por favor, contacte al administrador.");
}

// 2. DEFINIR CONSTANTES IMPORTANTES
define('ROOT_PATH', __DIR__); // Directorio raíz del proyecto
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')); // URL base (ej. /markdown2video o '' si está en la raíz web)
define('APP_PATH', ROOT_PATH . '/src/');   // Ruta a tu código fuente en src/
define('VIEWS_PATH', ROOT_PATH . '/Views/'); // Views está en la raíz del proyecto

// 3. CONFIGURACIÓN DEL ENTORNO (desarrollo/producción)
// Usar una variable de entorno para esto es la mejor práctica. Default a 'production' por seguridad.
//define('ENVIRONMENT', $_ENV['APP_ENV'] ?? 'production');
define('ENVIRONMENT', 'development');

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0); // No mostrar errores en producción
    ini_set('log_errors', 1); // Loguear errores
    // Asegurarse de que el directorio 'logs' exista y sea escribible
    if (!is_dir(ROOT_PATH . '/logs')) {
        // Intentar crear el directorio
        if (!mkdir(ROOT_PATH . '/logs', 0755, true) && !is_dir(ROOT_PATH . '/logs')) {
            // Si la creación falla, loguear a la ruta por defecto de PHP si es posible
            error_log("Advertencia: No se pudo crear el directorio de logs: " . ROOT_PATH . '/logs');
        }
    }
    // Establecer el archivo de log si el directorio existe y es escribible
    if (is_dir(ROOT_PATH . '/logs') && is_writable(ROOT_PATH . '/logs')) {
        ini_set('error_log', ROOT_PATH . '/logs/phperrors.log');
    } else {
        // Si no se puede escribir en el directorio de logs, PHP usará su log por defecto (si está configurado)
        error_log("Advertencia: El directorio de logs (" . ROOT_PATH . "/logs) no es escribible o no existe.");
    }
}

// 4. MANEJADOR DE ERRORES Y EXCEPCIONES PERSONALIZADO
// Asegúrate que ErrorHandler.php esté en src/Config/ y tenga el namespace Dales\Markdown2video\Config
use Dales\Markdown2video\Config\ErrorHandler;
ErrorHandler::init(); // init() en ErrorHandler debe respetar la constante ENVIRONMENT

// 5. CONFIGURACIÓN Y INICIO DE SESIÓN SEGURA
session_set_cookie_params([
    'lifetime' => 0, // La sesión dura hasta que se cierre el navegador
    'path' => BASE_URL . '/', // El path de la cookie debe incluir BASE_URL si está en subdirectorio
    'domain' => $_SERVER['HTTP_HOST'], // O tu dominio específico
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), // Cookie solo por HTTPS
    'httponly' => true, // Prevenir acceso por JavaScript
    'samesite' => 'Lax' // Protección contra algunos tipos de CSRF
]);
if (session_status() === PHP_SESSION_NONE) { // Iniciar sesión solo si no está ya iniciada
    session_start();
}

// 6. INYECCIÓN DE DEPENDENCIAS (O CREACIÓN MANUAL DE DEPENDENCIAS PRINCIPALES)
// Asegúrate que Database.php esté en src/Config/ y tenga el namespace Dales\Markdown2video\Config
use Dales\Markdown2video\Config\Database;

$pdoConnection = null; // Inicializar
try {
    $database = new Database(); // Las credenciales se toman de $_ENV o de los defaults en Database.php
    $pdoConnection = $database->getConnection();
} catch (Exception $e) { // Captura excepciones de la conexión a la BD
    error_log("Error CRÍTICO al conectar con la base de datos: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(503); // Service Unavailable
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo "<h1>Error de Conexión a Base de Datos</h1><p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'error/db_error.php')) {
            include VIEWS_PATH . 'error/db_error.php'; // Vista amigable para error de BD
        } else {
            echo "<h1>Error del Servidor</h1><p>No se pudo conectar con la base de datos. Por favor, inténtelo más tarde.</p>";
        }
    }
    exit; // Detener ejecución si no hay conexión a BD y es crítica.
}


// 7. RUTEO DE LA PETICIÓN
$urlParam = $_GET['url'] ?? ''; // Usar operador de fusión de null para evitar notice
$urlParam = rtrim($urlParam, '/'); // Quitar barras finales
$urlParam = filter_var($urlParam, FILTER_SANITIZE_URL); // Sanear la URL
$urlSegments = $urlParam ? explode('/', $urlParam) : []; // Si $urlParam es vacío después de sanear, $urlSegments es []

// Agregar esto después de la línea 108 (después de determinar $urlSegments)
// y antes de la línea que determina el controlador por defecto

// Rutas especiales que no usan controladores
if ($urlParam === 'check-logs') {
    include ROOT_PATH . '/public/check_logs.php';
    exit;
}

// Determinar el controlador y la acción por defecto
if (empty($urlSegments)) {
    $controllerNamePart = 'Auth';       // Controlador por defecto para la raíz
    $actionName         = 'showLoginForm'; // Acción por defecto para la raíz
} else {
    $controllerNamePart = ucfirst(strtolower($urlSegments[0])); // Primer segmento es el controlador
    // Si solo hay un segmento (controlador), la acción por defecto es 'index'
    // Si hay dos o más, el segundo es la acción.
    $actionName         = isset($urlSegments[1]) && !empty($urlSegments[1]) ? strtolower($urlSegments[1]) : 'index';
}

// Construir el nombre completo de la clase del controlador
$controllerClassName = "Dales\\Markdown2video\\Controllers\\" . $controllerNamePart . 'Controller';

// Parámetros para el método del controlador (a partir del tercer segmento de la URL)
$params = array_slice($urlSegments, 2);

// Método a llamar en el controlador (puede ser sobrescrito por lógica específica de ruteo)
$methodToCall = $actionName;

// Lógica de ruteo específica para controladores y acciones
if ($controllerClassName === 'Dales\\Markdown2video\\Controllers\\AuthController') {
    // Para la URL raíz, la lógica de arriba ya establece $controllerNamePart='Auth' y $methodToCall='showLoginForm'
    if ($actionName === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') { $methodToCall = 'processLogin'; }
    elseif ($actionName === 'login' && $_SERVER['REQUEST_METHOD'] === 'GET') { $methodToCall = 'showLoginForm'; } // Cubre la raíz y /auth/login explícito
    elseif ($actionName === 'logout') { $methodToCall = 'logout'; }
    elseif ($actionName === 'register' && $_SERVER['REQUEST_METHOD'] === 'GET') { $methodToCall = 'showRegisterForm'; }
    elseif ($actionName === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') { $methodToCall = 'processRegistration'; }
} elseif ($controllerClassName === 'Dales\\Markdown2video\\Controllers\\DashboardController') {
    if ($actionName === 'index') { /* $methodToCall ya es 'index' por defecto */ }
    // Añadir más acciones para DashboardController
} elseif ($controllerClassName === 'Dales\\Markdown2video\\Controllers\\MarkdownController') {
    if ($actionName === 'create' && $_SERVER['REQUEST_METHOD'] === 'GET') { 
        $methodToCall = 'create'; 
    } elseif ($actionName === 'marp-editor' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($urlSegments[2]) && is_numeric($urlSegments[2])) {
            $methodToCall = 'showMarpEditor';
            $params = [(int)$urlSegments[2]]; // Pasar el ID del archivo como parámetro
        } else {
            $methodToCall = 'showMarpEditor';
        }
    } elseif ($actionName === 'edit' && isset($urlSegments[2]) && is_numeric($urlSegments[2]) && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'create';
        $params = [(int)$urlSegments[2]]; // Pasar el ID del archivo como parámetro
    } elseif ($actionName === 'render-marp-preview' && $_SERVER['REQUEST_METHOD'] === 'POST') { 
        $methodToCall = 'renderMarpPreview'; 
    } elseif ($actionName === 'save-marp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'saveMarpFile';
    } elseif ($actionName === 'save-markdown' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'saveMarkdownFile';
    }
    // --- RUTAS PARA GENERAR PDF DESDE HTML DEL PREVIEW ---
    elseif ($actionName === 'generate-pdf-from-html' && $_SERVER['REQUEST_METHOD'] === 'POST') { 
        $methodToCall = 'generatePdfFromHtml'; 
    } 
    // --- RUTAS PARA GENERAR PDF DESDE MARKDOWN USANDO MARPCLI ---
    elseif ($actionName === 'generate-pdf-from-markdown' && $_SERVER['REQUEST_METHOD'] === 'POST') { 
        $methodToCall = 'generatePdfFromMarkdown'; 
    }
    // --- RUTAS PARA GENERAR HTML  DESDE MARKDOWN USANDO MARPCLI ---
    elseif ($actionName === 'generate-html-from-markdown' && $_SERVER['REQUEST_METHOD'] === 'POST') { 
        $methodToCall = 'generateHtmlFromMarkdown'; 
    }
    // --- RUTAS PARA LA PÁGINA DE DESCARGA Y DESCARGA FORZADA (PUEDEN SER LAS MISMAS PARA CUALQUIER PDF) ---
    elseif ($actionName === 'download-page' && $_SERVER['REQUEST_METHOD'] === 'GET') { 
        $methodToCall = 'showPdfDownloadPage'; 
    } elseif ($actionName === 'force-download-pdf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'forceDownloadPdf';
    }
    // --- RUTAS PARA LA PÁGINA DE DESCARGA Y DESCARGA FORZADA DE HTML ---
    elseif ($actionName === 'download-html-page' && $_SERVER['REQUEST_METHOD'] === 'GET') { 
        $methodToCall = 'showHtmlDownloadPage'; 
    } elseif ($actionName === 'force-download-html' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'forceDownloadHtml';
    }
    // --- ¡NUEVAS RUTAS DE IMÁGENES EN MARKDOWNCONTROLLER! ---
    elseif ($actionName === 'upload-image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'uploadImage';
    } elseif ($actionName === 'get-user-images' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'getUserImages';
    } elseif ($actionName === 'delete-image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'deleteImage';
    }
    // --- RUTAS PARA GENERAR VIDEO MP4 ---
    elseif ($actionName === 'generate-mp4-video' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'generateMp4Video';
    }
    // --- RUTAS PARA LA PÁGINA DE DESCARGA Y DESCARGA FORZADA DE VIDEO ---
    elseif ($actionName === 'download-video-page' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'showVideoDownloadPage';
    } elseif ($actionName === 'force-download-video' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'forceDownloadVideo';
    }
    // --- RUTAS PARA GENERAR PNG Y DESCARGAR ZIP ---
    elseif ($actionName === 'generate-jpg-from-markdown' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $methodToCall = 'generateJpgFromMarkdown';
    } elseif ($actionName === 'download-jpg-page' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'showJpgDownloadPage';
    } elseif ($actionName === 'force-download-jpg' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $methodToCall = 'forceDownloadJpg';
    }
    // --- RUTAS PARA PLANTILLAS ---
    elseif ($actionName === 'create-from-template' && isset($urlSegments[2]) && is_numeric($urlSegments[2])) {
        $methodToCall = 'createFromTemplate';
        // El ID de la plantilla (el número) se pasa como parámetro al método.
        $params = [$urlSegments[2]];
    }
    elseif ($actionName === 'create-from-marp-template' && isset($urlSegments[2]) && is_numeric($urlSegments[2])) {
        $methodToCall = 'createFromMarpTemplate';
        // El ID de la plantilla (el número) se pasa como parámetro al método.
        $params = [$urlSegments[2]];
    }
}

// --- RUTAS PARA LA API ---
if ($controllerNamePart === 'Api') {
    // Rutas para el controlador de API
    if ($actionName === 'saved-files' && isset($urlSegments[2]) && $urlSegments[2] === 'delete' && isset($urlSegments[3]) && is_numeric($urlSegments[3])) {
        $controllerClassName = 'Dales\\Markdown2video\\Controllers\\ApiController';
        $methodToCall = 'deleteSavedFile';
        $params = [(int)$urlSegments[3]]; // Convertir el ID a entero
    }
    // Ruta para obtener información de un archivo
    elseif ($actionName === 'saved-files' && isset($urlSegments[2]) && $urlSegments[2] === 'info' && isset($urlSegments[3]) && is_numeric($urlSegments[3])) {
        $controllerClassName = 'Dales\\Markdown2video\\Controllers\\ApiController';
        $methodToCall = 'getFileInfo';
        $params = [(int)$urlSegments[3]]; // Convertir el ID a entero
    }
    // Ruta para obtener información de un archivo con control de acceso (público o propio)
    elseif ($actionName === 'saved-files' && isset($urlSegments[2]) && $urlSegments[2] === 'access' && isset($urlSegments[3]) && is_numeric($urlSegments[3])) {
        $controllerClassName = 'Dales\\Markdown2video\\Controllers\\ApiController';
        $methodToCall = 'getFileWithAccess';
        $params = [(int)$urlSegments[3]]; // Convertir el ID a entero
    }
}

// VERIFICAR Y EJECUTAR EL CONTROLADOR
if (class_exists($controllerClassName)) {
    try {
        $controllerInstance = null;
        // Lista de controladores que requieren la conexión PDO en su constructor
        $controllersRequiringPdo = [
            'Dales\\Markdown2video\\Controllers\\AuthController',
            'Dales\\Markdown2video\\Controllers\\DashboardController',
            'Dales\\Markdown2video\\Controllers\\MarkdownController',
            'Dales\\Markdown2video\\Controllers\\ImageController',
            'Dales\\Markdown2video\\Controllers\\ApiController',
        ];

        if (in_array($controllerClassName, $controllersRequiringPdo)) {
            if ($pdoConnection === null) { // Doble chequeo por si la conexión falló y el script no terminó antes
                throw new Exception("La conexión a la base de datos no está disponible para el controlador: " . $controllerClassName);
            }
            $controllerInstance = new $controllerClassName($pdoConnection);
        } else {
            $controllerInstance = new $controllerClassName(); // Para controladores que no necesitan PDO
        }

        if (method_exists($controllerInstance, $methodToCall)) {
            call_user_func_array([$controllerInstance, $methodToCall], $params);
        } else {
            // Método no encontrado en el controlador
            error_log("Método no encontrado: {$controllerClassName}->{$methodToCall} para URL '{$urlParam}' (Segments: " . json_encode($urlSegments) . ")");
            http_response_code(404);
            if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'error/404.php')) {
                //include VIEWS_PATH . 'error/404.php';
                echo "<h1>Error en el metodo Controlador</h1><p><strong>Tipo:</strong> " . get_class($e) . "</p>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . " (Línea: " . $e->getLine() . ")</p>";
            echo "<h2>Traza:</h2><pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
            } else {
                echo "404 - Método o Recurso no encontrado."; // Mensaje de fallback
            }
        }
    } catch (Throwable $e) { // Capturar Throwable para errores y excepciones (PHP 7+)
        error_log("Error al ejecutar controlador {$controllerClassName}->{$methodToCall}: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        http_response_code(500);
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            echo "<h1>Error en el Controlador</h1><p><strong>Tipo:</strong> " . get_class($e) . "</p>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . " (Línea: " . $e->getLine() . ")</p>";
            echo "<h2>Traza:</h2><pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
        } else {
            if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'error/500.php')) {
                include VIEWS_PATH . 'error/500.php';
            } else {
                echo "<h1>Error del Servidor</h1><p>Ocurrió un error inesperado. Por favor, inténtelo más tarde.</p>";
            }
        }
    }
} else {
    // Clase de controlador no encontrada
    error_log("Clase de controlador no encontrada: {$controllerClassName} para URL '{$urlParam}' (Segments: " . json_encode($urlSegments) . ")");
    http_response_code(404);
    if (defined('VIEWS_PATH') && file_exists(VIEWS_PATH . 'error/404.php')) {
        //include VIEWS_PATH . 'error/404.php';
        echo "<h1>Error en la clase Controlador</h1><p><strong>Tipo:</strong> " . get_class($e) . "</p>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . " (Línea: " . $e->getLine() . ")</p>";
            echo "<h2>Traza:</h2><pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        echo "404 - Página no encontrada."; // Mensaje de fallback
    }
}

// --- FIN DE index.php ---
?>
