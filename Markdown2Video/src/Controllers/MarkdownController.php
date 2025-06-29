<?php

namespace Dales\Markdown2video\Controllers;

use PDO;
use Dompdf\Dompdf;
use Dompdf\Options;
use Dales\Markdown2video\Models\ImageModel;

class MarkdownController
{
    private ?PDO $pdo;

    // Se añade la propiedad para guardar la instancia del ImageModel.
    private ?ImageModel $imageModel = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;

        if ($this->pdo) {
            $this->imageModel = new ImageModel($this->pdo);
        }

        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
    }

    /**
     */
    // Reemplaza el método create() en src/Controllers/MarkdownController.php

    public function create(): void
    {
        $base_url = BASE_URL;
        $pageTitle = "Editor de Presentación (Markdown)";

        // Token para PDF
        if (empty($_SESSION['csrf_token_generate_pdf'])) {
            $_SESSION['csrf_token_generate_pdf'] = bin2hex(random_bytes(32));
        }
        $csrf_token_generate_pdf = $_SESSION['csrf_token_generate_pdf'];

        // --- CORRECCIÓN: Se añade la creación del token para acciones de imágenes ---
        if (empty($_SESSION['csrf_token_image_action'])) {
            $_SESSION['csrf_token_image_action'] = bin2hex(random_bytes(32));
        }
        $csrf_token_image_action = $_SESSION['csrf_token_image_action'];

        $viewPath = VIEWS_PATH . 'base_markdown.php';
        if (file_exists($viewPath)) {
            // Ahora ambas variables ($csrf_token_generate_pdf y $csrf_token_image_action)
            // existen y se pasan a la vista.
            require_once $viewPath;
        } else {
            $this->showErrorPage("Vista del editor Markdown no encontrada: " . $viewPath);
        }
    }

    /**
     * Muestra el editor para Marp.
     * Ruta: GET /markdown/marp-editor
     * Puede recibir un parámetro template_id para cargar una plantilla
     */
    public function showMarpEditor(): void
    {
        $base_url = BASE_URL;
        $pageTitle = "Editor de Presentación (Marp)";
        if (empty($_SESSION['csrf_token_marp_generate'])) { // Token diferente si es necesario
            $_SESSION['csrf_token_marp_generate'] = bin2hex(random_bytes(32));
        }
        $csrf_token_marp_generate = $_SESSION['csrf_token_marp_generate'];
        
        // Verificar si se ha solicitado cargar una plantilla
        $initialContent = '';
        if (isset($_GET['template_id']) && is_numeric($_GET['template_id'])) {
            $templateId = (int)$_GET['template_id'];
            
            // Verificamos que el modelo de plantillas exista
            if ($this->pdo) {
                $templateModel = new \Dales\Markdown2video\Models\TemplateModel($this->pdo);
                
                // Obtenemos el contenido de la plantilla desde la base de datos
                $templateContent = $templateModel->getTemplateContentById($templateId);
                
                if ($templateContent !== null) {
                    $initialContent = $templateContent;
                }
            }
        }

        $viewPath = VIEWS_PATH . 'base_marp.php'; // Asume que es Views/base_marp.php
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            $this->showErrorPage("Vista del editor Marp no encontrada: " . $viewPath);
        }
    }

    /**
     */
    public function renderMarpPreview(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['markdown'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Petición incorrecta o falta contenido markdown.']);
            exit;
        }
        ob_start();
        $renderScriptPath = ROOT_PATH . '/server/render_marp.php';
        if (file_exists($renderScriptPath)) {
            include $renderScriptPath;
        } else {
            error_log("Script render_marp.php no encontrado: " . $renderScriptPath);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'Error interno (script de renderizado no encontrado).']);
        }
        $output = ob_get_clean();
        echo $output;
        exit;
    }

    // --- MÉTODOS PARA IMÁGENES (estos ya estaban bien, ahora funcionarán) ---

    public function uploadImage(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Petición inválida.']);
            exit;
        }

        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_image_action'], $_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Error de seguridad (CSRF).']);
            exit;
        }

        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al subir el archivo.']);
            exit;
        }
        if (empty($_POST['image_name'])) {
            echo json_encode(['success' => false, 'error' => 'El nombre de la imagen es obligatorio.']);
            exit;
        }

        $imageName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['image_name']);
        if (empty($imageName)) {
            echo json_encode(['success' => false, 'error' => 'El nombre de la imagen contiene caracteres no válidos.']);
            exit;
        }

        $file = $_FILES['image_file'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowedMimes) || $file['size'] > 5 * 1024 * 1024) { // 5MB Limit
            echo json_encode(['success' => false, 'error' => 'Archivo no permitido o demasiado grande (máx 5MB).']);
            exit;
        }

        $imageData = file_get_contents($file['tmp_name']);
        if ($this->imageModel->saveImage($_SESSION['user_id'], $imageName, $file['name'], $imageData, $file['type'])) {
            echo json_encode(['success' => true, 'message' => 'Imagen subida correctamente.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen. El nombre ya podría existir.']);
        }
        exit;
    }

    public function getUserImages(): void
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode([]);
            exit;
        }
        $images = $this->imageModel->getImagesByUserId($_SESSION['user_id']);
        echo json_encode($images);
        exit;
    }


    public function deleteImage(): void
    {
        // Establecemos la cabecera JSON al principio.
        header('Content-Type: application/json');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Petición inválida.']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Si el JSON está mal formado o vacío
            if (json_last_error() !== JSON_ERROR_NONE || !$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datos de entrada inválidos.']);
                exit;
            }

            if (empty($data['csrf_token']) || !hash_equals($_SESSION['csrf_token_image_action'], $data['csrf_token'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Error de seguridad (CSRF).']);
                exit;
            }

            if (empty($data['id_image']) || !is_numeric($data['id_image'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Falta el ID de la imagen o es inválido.']);
                exit;
            }

            // Si todas las validaciones pasan, intentamos borrar.
            $wasDeleted = $this->imageModel->deleteImageByIdAndUserId((int)$data['id_image'], $_SESSION['user_id']);

            if ($wasDeleted) {
                // Éxito real
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Imagen eliminada correctamente.']);
            } else {
                // La consulta se ejecutó pero no borró nada (ID no encontrado o no pertenece al usuario)
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la imagen: no se encontró o no te pertenece.']);
            }
        } catch (\Throwable $e) {
            // Capturamos cualquier otro error inesperado
            error_log("Error en deleteImage: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ocurrió un error inesperado en el servidor.']);
        }

        // El exit ya no es estrictamente necesario aquí si no hay más código, pero es buena práctica.
        exit;
    }

    /**
     */
    public function generatePdfFromHtml(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['html_content'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Petición incorrecta o falta contenido HTML.']);
            exit;
        }
        if (empty($_POST['csrf_token_generate_pdf']) || !hash_equals($_SESSION['csrf_token_generate_pdf'] ?? '', $_POST['csrf_token_generate_pdf'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o faltante.']);
            exit;
        }

        try {
            $htmlContent = $_POST['html_content'];
            $userId = $_SESSION['user_id'];
            $pattern = '/<img src="([^"]*\/image\/serve\/([^"]+))"/i';

            $callback = function ($matches) use ($userId) {
                $originalSrc = $matches[1];
                $imageName = urldecode($matches[2]);
                $imageDetails = $this->imageModel->getImageByNameAndUserId($imageName, $userId);

                if ($imageDetails) {
                    $imageData = $imageDetails['image_data'];
                    $mimeType = $imageDetails['mime_type'];
                    $finalImageData = $imageData;

                    if (extension_loaded('gd')) {
                        $sourceImage = @imagecreatefromstring($imageData);

                        if ($sourceImage !== false) {
                            $maxImageWidthInPdf = 650;
                            $originalWidth = imagesx($sourceImage);

                            if ($originalWidth > $maxImageWidthInPdf) {
                                $originalHeight = imagesy($sourceImage);

                                // --- CORRECCIÓN DE LA FÓRMULA ---
                                $ratio = $originalHeight / $originalWidth;
                                $newWidth = $maxImageWidthInPdf;
                                $newHeight = $newWidth * $ratio;

                                // --- CORRECCIÓN CLAVE: Redondeamos los valores a enteros ---
                                $newWidthInt = (int) round($newWidth);
                                $newHeightInt = (int) round($newHeight);

                                $resizedImage = imagecreatetruecolor($newWidthInt, $newHeightInt);

                                if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
                                    imagealphablending($resizedImage, false);
                                    imagesavealpha($resizedImage, true);
                                    $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                                    imagefilledrectangle($resizedImage, 0, 0, $newWidthInt, $newHeightInt, $transparent);
                                }

                                imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidthInt, $newHeightInt, $originalWidth, $originalHeight);

                                ob_start();
                                switch ($mimeType) {
                                    case 'image/png':
                                        imagepng($resizedImage);
                                        break;
                                    case 'image/gif':
                                        imagegif($resizedImage);
                                        break;
                                    default:
                                        imagejpeg($resizedImage, null, 85);
                                        break;
                                }
                                $resizedImageData = ob_get_clean();

                                if ($resizedImageData) {
                                    $finalImageData = $resizedImageData;
                                }

                                imagedestroy($resizedImage);
                            }
                            imagedestroy($sourceImage);
                        } else {
                            error_log("Advertencia: No se pudo procesar la imagen '{$imageName}'.");
                        }
                    }

                    $base64Image = base64_encode($finalImageData);
                    $dataUri = 'data:' . $mimeType . ';base64,' . $base64Image;
                    return str_replace($originalSrc, $dataUri, $matches[0]);
                }
                return $matches[0];
            };

            $boundCallback = $callback->bindTo($this, $this);
            $htmlContent = preg_replace_callback($pattern, $boundCallback, $htmlContent);

            $clean_html = $htmlContent;

            $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            $userTempDir = ROOT_PATH . '/public/temp_files/pdfs/' . $userIdForPath . '/';
            if (!is_dir($userTempDir)) {
                if (!mkdir($userTempDir, 0775, true) && !is_dir($userTempDir)) {
                    exit;
                }
            }
            $pdfFileName = 'preview_md_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
            $outputPdfFile = $userTempDir . $pdfFileName;

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $cssPdf = file_exists(ROOT_PATH . '/public/css/pdf_styles.css') ? file_get_contents(ROOT_PATH . '/public/css/pdf_styles.css') : '';
            $fullHtml = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Documento</title>';
            $fullHtml .= '<style>' . $cssPdf . '</style>';
            $fullHtml .= '</head><body>' . $clean_html . '</body></html>';
            $dompdf->loadHtml($fullHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            if (file_put_contents($outputPdfFile, $dompdf->output()) === false) {
                throw new \Exception("No se pudo guardar el archivo PDF generado.");
            }
            $_SESSION['pdf_download_file'] = $pdfFileName;
            $_SESSION['pdf_download_full_path'] = $outputPdfFile;
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'PDF generado.', 'downloadPageUrl' => BASE_URL . '/markdown/download-page/' . urlencode($pdfFileName)]);
            exit;
        } catch (\Throwable $e) {
            error_log("ERROR FATAL en generatePdfFromHtml: " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Error interno al generar el PDF.']);
            exit;
        }
    }

    public function showPdfDownloadPage(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionFile = $_SESSION['pdf_download_file'] ?? null;
        $expectedSessionPath = $_SESSION['pdf_download_full_path'] ?? null;
        $currentExpectedDiskPath = ROOT_PATH . '/public/temp_files/pdfs/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionFile === $filename && $expectedSessionPath === $currentExpectedDiskPath && file_exists($currentExpectedDiskPath)) {
            $base_url = BASE_URL;
            $pageTitle = "Descargar PDF: " . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
            $downloadLink = BASE_URL . '/markdown/force-download-pdf/' . urlencode($filename);
            $actual_filename = $filename;
            require_once VIEWS_PATH . '/download_pdf.php';
        } else {
            // Manejo de error básico
            http_response_code(404);
            echo "Archivo no encontrado o sesión inválida.";
            exit;
        }
    }

    public function forceDownloadPdf(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionPath = $_SESSION['pdf_download_full_path'] ?? null;
        $currentDiskPath = ROOT_PATH . '/public/temp_files/pdfs/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionPath === $currentDiskPath && file_exists($currentDiskPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($currentDiskPath));
            flush();
            readfile($currentDiskPath);
            unlink($currentDiskPath);
            unset($_SESSION['pdf_download_file'], $_SESSION['pdf_download_full_path']);
            exit;
        } else {
            http_response_code(404);
            echo "Archivo no encontrado o acceso no autorizado.";
            exit;
        }
    }

    /**
     * Genera un video MP4 a partir del contenido Marp
     */
    public function generateMp4Video()
    {
        error_log('[MARP-VIDEO] Iniciando generación de video MP4');
        try {
            $markdownContent = $_POST['markdown'] ?? '';
            error_log('[MARP-VIDEO] Longitud del contenido Markdown recibido: ' . strlen($markdownContent));

            $userId = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            error_log('[MARP-VIDEO] ID de usuario: ' . $userId);

            $userTempDir = ROOT_PATH . '/public/temp_files/videos/' . $userId . '/';
            error_log('[MARP-VIDEO] Directorio temporal: ' . $userTempDir);

            if (!is_dir($userTempDir)) {
                error_log('[MARP-VIDEO] Creando directorio temporal');
                mkdir($userTempDir, 0775, true);
            }

            $mdFilePath = $userTempDir . 'presentation_' . time() . '.md';
            error_log('[MARP-VIDEO] Guardando markdown en: ' . $mdFilePath);
            file_put_contents($mdFilePath, $markdownContent);

            $outputVideoPath = $userTempDir . 'video_' . time() . '.mp4';
            error_log('[MARP-VIDEO] Ruta de salida del video: ' . $outputVideoPath);

            $this->mdToVideo($mdFilePath, $outputVideoPath);
            error_log('[MARP-VIDEO] Video generado exitosamente');

            // Guardar información del video en sesión para la página de descarga
            $_SESSION['video_download_file'] = basename($outputVideoPath);
            $_SESSION['video_download_full_path'] = $outputVideoPath;

            error_log("[VIDEO DEBUG] Video generado exitosamente - Guardado en sesión");

            // URL para previsualizar el video
            $videoPreviewUrl = BASE_URL . '/public/temp_files/videos/' . $userId . '/' . basename($outputVideoPath);
            error_log("[VIDEO DEBUG] URL de previsualización: " . $videoPreviewUrl);

            $videoFileName = basename($outputVideoPath);

            ob_clean(); // Limpia cualquier buffer de salida
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Video MP4 generado exitosamente.',
                'videoUrl' => $videoPreviewUrl,
                'downloadPageUrl' => BASE_URL . '/markdown/download-video-page/' . urlencode($videoFileName)
            ]);
            exit;
        } catch (\Exception $e) {
            error_log('[MARP-VIDEO-ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    private function mdToVideo(string $mdFilePath, string $outputVideoPath): void
    {
        try {
            error_log('[MARP-VIDEO] Iniciando conversión de Markdown a video');
            error_log('[MARP-VIDEO] Archivo MD de entrada: ' . $mdFilePath);
            error_log('[MARP-VIDEO] Archivo de video de salida: ' . $outputVideoPath);

            $tempDir = dirname($outputVideoPath);

            error_log('[MARP-VIDEO] Convirtiendo Markdown a imágenes PNG');
            $marpCmd = "marp --html --images png $mdFilePath";
            exec($marpCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Error al generar imágenes PNG con marp: " . implode("\n", $output));
            }

            // Obtener lista de imágenes PNG generadas
            $dir = dirname($mdFilePath);
            $baseName = basename($mdFilePath, '.md');
            $pngFiles = glob("$dir/$baseName.*.png");

            if (empty($pngFiles)) {
                throw new \Exception("No se encontraron imágenes PNG generadas");
            }

            // Ordenar las imágenes por número de slide
            natsort($pngFiles);

            // Crear archivo de lista para ffmpeg
            $listFile = tempnam(sys_get_temp_dir(), 'marp_video');
            $listContent = '';

            foreach ($pngFiles as $pngFile) {
                $listContent .= "file '$pngFile'\n";
                $listContent .= "duration 5\n"; // 5 segundos por slide
            }

            file_put_contents($listFile, $listContent);

            // Convertir imágenes a video con ffmpeg
            $ffmpegCmd = "ffmpeg -f concat -safe 0 -i $listFile -c:v libx264 -pix_fmt yuv420p -y $outputVideoPath";
            exec($ffmpegCmd, $output, $returnCode);

            unlink($listFile);

            if ($returnCode !== 0) {
                throw new \Exception("Error al convertir imágenes a video: " . implode("\n", $output));
            }

            // Limpiar imágenes temporales
            foreach ($pngFiles as $pngFile) {
                unlink($pngFile);
            }

            echo json_encode([
                'success' => true,
                'videoPath' => str_replace(ROOT_PATH, BASE_URL, $outputVideoPath)
            ]);
        } catch (\Exception $e) {
            error_log('[MARP-VIDEO-ERROR] ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Muestra la página de descarga de video
     */
    public function showVideoDownloadPage(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionFile = $_SESSION['video_download_file'] ?? null;
        $expectedSessionPath = $_SESSION['video_download_full_path'] ?? null;
        $currentExpectedDiskPath = ROOT_PATH . '/public/temp_files/videos/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionFile === $filename && $expectedSessionPath === $currentExpectedDiskPath && file_exists($currentExpectedDiskPath)) {
            $base_url = BASE_URL;
            $pageTitle = "Descargar Video: " . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
            $downloadLink = BASE_URL . '/markdown/force-download-video/' . urlencode($filename);
            $actual_filename = $filename;
            $videoPreviewUrl = BASE_URL . '/public/temp_files/videos/' . $userIdForPath . '/' . $filename;

            require_once VIEWS_PATH . '/download_video.php';
        } else {
            http_response_code(404);
            echo "Video no encontrado o sesión inválida.";
            exit;
        }
    }

    /**
     * Fuerza la descarga del video MP4
     */
    public function forceDownloadVideo(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionPath = $_SESSION['video_download_full_path'] ?? null;
        $currentDiskPath = ROOT_PATH . '/public/temp_files/videos/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionPath === $currentDiskPath && file_exists($currentDiskPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: video/mp4');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($currentDiskPath));
            flush();
            readfile($currentDiskPath);

            // Limpiar archivo temporal después de la descarga
            unlink($currentDiskPath);
            unset($_SESSION['video_download_file'], $_SESSION['video_download_full_path']);
            exit;
        } else {
            http_response_code(404);
            echo "Video no encontrado o acceso no autorizado.";
            exit;
        }
    }

    private function showErrorPage(string $logMessage, string $userMessage = "Error."): void
    {
        error_log($logMessage);
        http_response_code(500);
        // Aquí podrías incluir una vista de error genérica
        echo "<h1>Error</h1><p>$userMessage</p>";
    }

    //NUEVA FUNCIONA PARA PLANTILLAS DE MARKDOWN
    // Añade este método a src/Controllers/MarkdownController.php

    // Pega este método DENTROde la clase MarkdownController

    public function createFromTemplate(int $templateId): void
    {
        // Verificamos que el modelo de plantillas exista. 
        // Como no lo inicializamos en el constructor de MarkdownController,
        // lo creamos aquí temporalmente.
        if (!$this->pdo) {
            $this->showErrorPage("No hay conexión a la base de datos para cargar la plantilla.");
            return;
        }
        $templateModel = new \Dales\Markdown2video\Models\TemplateModel($this->pdo);

        // Obtenemos el contenido de la plantilla desde la base de datos
        $templateContent = $templateModel->getTemplateContentById($templateId);

        if ($templateContent === null) {
            // Si la plantilla no existe o está inactiva, redirigir al dashboard
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        // Preparamos las variables necesarias para la vista del editor
        $base_url = BASE_URL;
        $pageTitle = "Editor - Desde Plantilla";

        // Generamos los tokens CSRF
        if (empty($_SESSION['csrf_token_generate_pdf'])) {
            $_SESSION['csrf_token_generate_pdf'] = bin2hex(random_bytes(32));
        }
        $csrf_token_generate_pdf = $_SESSION['csrf_token_generate_pdf'];
        if (empty($_SESSION['csrf_token_image_action'])) {
            $_SESSION['csrf_token_image_action'] = bin2hex(random_bytes(32));
        }
        $csrf_token_image_action = $_SESSION['csrf_token_image_action'];

        // Esta es la variable que pasará el contenido de la plantilla a la vista
        $initialContent = $templateContent;

        // Cargamos la vista del editor, pasándole todas las variables
        $viewPath = VIEWS_PATH . 'base_markdown.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            $this->showErrorPage("La vista del editor Markdown no se ha encontrado.");
        }
    }

    /**
     * Genera un PDF a partir del contenido Markdown usando MarpCLI
     */
    public function generatePdfFromMarkdown(): void
    {
        error_log('[MARP-PDF] Iniciando generación de PDF desde Markdown');
        try {
            $markdownContent = $_POST['markdown'] ?? '';
            error_log('[MARP-PDF] Longitud del contenido Markdown recibido: ' . strlen($markdownContent));

            $userId = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            error_log('[MARP-PDF] ID de usuario: ' . $userId);

            $userTempDir = ROOT_PATH . '/public/temp_files/pdfs/' . $userId . '/';
            error_log('[MARP-PDF] Directorio temporal: ' . $userTempDir);

            if (!is_dir($userTempDir)) {
                error_log('[MARP-PDF] Creando directorio temporal');
                mkdir($userTempDir, 0775, true);
            }

            $mdFilePath = $userTempDir . 'presentation_' . time() . '.md';
            error_log('[MARP-PDF] Guardando markdown en: ' . $mdFilePath);
            file_put_contents($mdFilePath, $markdownContent);

            $pdfFileName = 'marp_pdf_' . time() . '.pdf';
            $outputPdfPath = $userTempDir . $pdfFileName;
            error_log('[MARP-PDF] Ruta de salida del PDF: ' . $outputPdfPath);

            // Generar PDF usando MarpCLI
            $marpCmd = "marp --pdf $mdFilePath -o $outputPdfPath";
            exec($marpCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Error al generar PDF con marp: " . implode("\n", $output));
            }

            // Guardar información del PDF en sesión para la página de descarga
            $_SESSION['pdf_download_file'] = $pdfFileName;
            $_SESSION['pdf_download_full_path'] = $outputPdfPath;

            error_log("[PDF DEBUG] PDF generado exitosamente - Guardado en sesión");

            ob_clean(); // Limpia cualquier buffer de salida
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'PDF generado exitosamente.',
                'downloadPageUrl' => BASE_URL . '/markdown/download-page/' . urlencode($pdfFileName)
            ]);
            exit;
        } catch (\Exception $e) {
            error_log('[MARP-PDF-ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Genera un archivo HTML a partir del contenido Markdown usando MarpCLI
     */
    public function generateHtmlFromMarkdown(): void
    {
        error_log('[MARP-HTML] Iniciando generación de HTML desde Markdown');
        try {
            $markdownContent = $_POST['markdown'] ?? '';
            error_log('[MARP-HTML] Longitud del contenido Markdown recibido: ' . strlen($markdownContent));

            $userId = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            error_log('[MARP-HTML] ID de usuario: ' . $userId);

            $userTempDir = ROOT_PATH . '/public/temp_files/html/' . $userId . '/';
            error_log('[MARP-HTML] Directorio temporal: ' . $userTempDir);

            if (!is_dir($userTempDir)) {
                error_log('[MARP-HTML] Creando directorio temporal');
                mkdir($userTempDir, 0775, true);
            }

            $mdFilePath = $userTempDir . 'presentation_' . time() . '.md';
            error_log('[MARP-HTML] Guardando markdown en: ' . $mdFilePath);
            file_put_contents($mdFilePath, $markdownContent);

            $htmlFileName = 'marp_html_' . time() . '.html';
            $outputHtmlPath = $userTempDir . $htmlFileName;
            error_log('[MARP-HTML] Ruta de salida del HTML: ' . $outputHtmlPath);

            // Generar HTML usando MarpCLI
            $marpCmd = "marp --html $mdFilePath -o $outputHtmlPath";
            exec($marpCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Error al generar HTML con marp: " . implode("\n", $output));
            }

            // Guardar información del HTML en sesión para la página de descarga
            $_SESSION['html_download_file'] = $htmlFileName;
            $_SESSION['html_download_full_path'] = $outputHtmlPath;

            error_log("[HTML DEBUG] HTML generado exitosamente - Guardado en sesión");

            ob_clean(); // Limpia cualquier buffer de salida
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'HTML generado exitosamente.',
                'downloadPageUrl' => BASE_URL . '/markdown/download-html-page/' . urlencode($htmlFileName)
            ]);
            exit;
        } catch (\Exception $e) {
            error_log('[MARP-HTML-ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Muestra la página de descarga para archivos HTML generados
     */
    public function showHtmlDownloadPage(): void
    {
        $base_url = BASE_URL;
        $pageTitle = "Descargar Presentación HTML";

        // Obtener el nombre del archivo de la URL
        $urlSegments = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $encodedFilename = end($urlSegments);
        $filename = urldecode($encodedFilename);

        // Verificar que el archivo existe en la sesión
        if (isset($_SESSION['html_download_file']) && $_SESSION['html_download_file'] === $filename) {
            $downloadLink = BASE_URL . '/markdown/force-download-html/' . $encodedFilename;
            $actual_filename = $filename;

            // Usar la misma vista que para PDF pero con título y enlaces adaptados
            require_once VIEWS_PATH . 'download_pdf.php';
        } else {
            $this->showErrorPage("Archivo HTML no encontrado o sesión expirada.");
        }
    }

    /**
     * Fuerza la descarga del archivo HTML generado
     */
    public function forceDownloadHtml(): void
    {
        // Obtener el nombre del archivo de la URL
        $urlSegments = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $encodedFilename = end($urlSegments);
        $filename = urldecode($encodedFilename);

        // Verificar que el archivo existe en la sesión
        if (isset($_SESSION['html_download_file']) && 
            $_SESSION['html_download_file'] === $filename && 
            isset($_SESSION['html_download_full_path']) && 
            file_exists($_SESSION['html_download_full_path'])) {

            $currentDiskPath = $_SESSION['html_download_full_path'];

            // Configurar cabeceras para forzar descarga
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($currentDiskPath));
            flush();
            readfile($currentDiskPath);
            // No eliminamos el archivo para permitir descargas múltiples
            // unlink($currentDiskPath);
            // unset($_SESSION['html_download_file'], $_SESSION['html_download_full_path']);
            exit;
        } else {
            http_response_code(404);
            echo "Archivo HTML no encontrado o acceso no autorizado.";
            exit;
        }
    }
    
    /**
     * Genera imágenes PNG a partir del contenido Markdown usando MarpCLI
     * y las comprime en un archivo ZIP para su descarga
     */
    public function generateJpgFromMarkdown(): void
    {
        error_log('[MARP-PNG] Iniciando generación de PNG desde Markdown');
        try {
            $markdownContent = $_POST['markdown'] ?? '';
            error_log('[MARP-PNG] Longitud del contenido Markdown recibido: ' . strlen($markdownContent));

            $userId = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            error_log('[MARP-PNG] ID de usuario: ' . $userId);

            // Crear directorios temporales para imágenes y ZIP
            $userTempDir = ROOT_PATH . '/public/temp_files/png/' . $userId . '/';
            $userImagesDir = $userTempDir . 'images_' . time() . '/';
            error_log('[MARP-PNG] Directorio temporal: ' . $userTempDir);
            error_log('[MARP-PNG] Directorio de imágenes: ' . $userImagesDir);

            if (!is_dir($userTempDir)) {
                error_log('[MARP-PNG] Creando directorio temporal');
                mkdir($userTempDir, 0775, true);
            }
            
            if (!is_dir($userImagesDir)) {
                error_log('[MARP-PNG] Creando directorio de imágenes');
                mkdir($userImagesDir, 0775, true);
            }

            // Guardar el markdown en un archivo temporal
            $mdFilePath = $userTempDir . 'presentation_' . time() . '.md';
            error_log('[MARP-PNG] Guardando markdown en: ' . $mdFilePath);
            file_put_contents($mdFilePath, $markdownContent);

            // Generar imágenes PNG usando MarpCLI
            $outputPattern = 'slide-%d.png';
            $outputFile = $userImagesDir . $outputPattern;
            error_log('[MARP-PNG] Patrón de salida de imágenes: ' . $outputFile);
            
            // Comando para generar imágenes PNG con nombres específicos
            $marpCmd = "marp $mdFilePath --images png --image-scale 1.0 --output=$outputFile";
            error_log('[MARP-PNG] Ejecutando comando: ' . $marpCmd);
            exec($marpCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Error al generar imágenes PNG con marp: " . implode("\n", $output));
            }

            // Verificar que se generaron imágenes
            $pngFiles = glob("$userImagesDir" . "slide-*.png");
            if (empty($pngFiles)) {
                error_log('[MARP-PNG] No se encontraron imágenes con patrón: ' . "$userImagesDir" . "slide-*.png");
                // Intentar buscar con cualquier patrón de PNG como respaldo
                $pngFiles = glob("$userImagesDir*.png");
                if (empty($pngFiles)) {
                    throw new \Exception("No se generaron imágenes PNG");
                }
                error_log('[MARP-PNG] Se encontraron ' . count($pngFiles) . ' imágenes con patrón alternativo');
            } else {
                error_log('[MARP-PNG] Se encontraron ' . count($pngFiles) . ' imágenes con el patrón esperado');
            }

            // Crear archivo ZIP con las imágenes
            $zipFileName = 'slides_' . time() . '.zip';
            $zipFilePath = $userTempDir . $zipFileName;
            error_log('[MARP-PNG] Creando archivo ZIP: ' . $zipFilePath);

            // Intentar usar ZipArchive si está disponible
            if (class_exists('ZipArchive')) {
                error_log('[MARP-PNG] Usando ZipArchive para crear el ZIP');
                $zip = new \ZipArchive();
                if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== TRUE) {
                    throw new \Exception("No se pudo crear el archivo ZIP con ZipArchive");
                }

                // Añadir cada imagen al ZIP
                foreach ($pngFiles as $pngFile) {
                    $zip->addFile($pngFile, basename($pngFile));
                }
                $zip->close();
            } else {
                // Alternativa usando el comando zip de línea de comandos
                error_log('[MARP-PNG] ZipArchive no disponible, usando comando zip');
                
                // Cambiar al directorio de imágenes
                $currentDir = getcwd();
                chdir($userImagesDir);
                
                // Crear un comando zip que incluya todas las imágenes PNG
                $zipCommand = "zip -j \"$zipFilePath\" *.png";
                error_log('[MARP-PNG] Ejecutando comando: ' . $zipCommand);
                
                exec($zipCommand, $output, $returnCode);
                
                // Volver al directorio original
                chdir($currentDir);
                
                if ($returnCode !== 0) {
                    throw new \Exception("Error al crear el archivo ZIP con el comando zip: " . implode("\n", $output));
                }
                
                error_log('[MARP-PNG] Archivo ZIP creado exitosamente con comando zip');
            }

            // Guardar información del ZIP en sesión para la página de descarga
            $_SESSION['jpg_download_file'] = $zipFileName;
            $_SESSION['jpg_download_full_path'] = $zipFilePath;

            error_log("[PNG DEBUG] Imágenes PNG generadas y comprimidas exitosamente - Guardado en sesión");

            ob_clean(); // Limpia cualquier buffer de salida
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Imágenes PNG generadas exitosamente.',
                'downloadPageUrl' => BASE_URL . '/markdown/download-jpg-page/' . urlencode($zipFileName)
            ]);
            exit;
        } catch (\Exception $e) {
            error_log('[MARP-PNG-ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Muestra la página de descarga para archivos ZIP con imágenes PNG
     */
    public function showJpgDownloadPage(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionFile = $_SESSION['jpg_download_file'] ?? null;
        $expectedSessionPath = $_SESSION['jpg_download_full_path'] ?? null;
        $currentExpectedDiskPath = ROOT_PATH . '/public/temp_files/png/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionFile === $filename && $expectedSessionPath === $currentExpectedDiskPath && file_exists($currentExpectedDiskPath)) {
            $base_url = BASE_URL;
            $pageTitle = "Descargar Imágenes PNG: " . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
            $downloadLink = BASE_URL . '/markdown/force-download-jpg/' . urlencode($filename);
            $actual_filename = $filename;
            require_once VIEWS_PATH . '/download_pdf.php';
        } else {
            // Manejo de error básico
            http_response_code(404);
            echo "Archivo no encontrado o sesión inválida.";
            exit;
        }
    }

    /**
     * Fuerza la descarga del archivo ZIP con imágenes PNG
     */
    public function forceDownloadJpg(string $filenameFromUrl): void
    {
        $filename = basename(urldecode($filenameFromUrl));
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $expectedSessionPath = $_SESSION['jpg_download_full_path'] ?? null;
        $currentDiskPath = ROOT_PATH . '/public/temp_files/png/' . $userIdForPath . '/' . $filename;

        if ($expectedSessionPath === $currentDiskPath && file_exists($currentDiskPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($currentDiskPath));
            flush();
            readfile($currentDiskPath);
            // No eliminamos el archivo para permitir descargas múltiples
            // unlink($currentDiskPath);
            // unset($_SESSION['jpg_download_file'], $_SESSION['jpg_download_full_path']);
            exit;
        } else {
            http_response_code(404);
            echo "Archivo ZIP no encontrado o acceso no autorizado.";
            exit;
        }
    }

}
