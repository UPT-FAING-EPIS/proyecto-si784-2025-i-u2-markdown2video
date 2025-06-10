<?php
namespace Dales\Markdown2video\Controllers;

use PDO;
use Dompdf\Dompdf;
use Dompdf\Options;
// use Dales\Markdown2video\Utils\FFmpegHandler; // Desactivado temporalmente
// use Dales\Markdown2video\Utils\MarpCliHandler;  // Desactivado temporalmente

class MarkdownController {
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
    }

    public function create(): void {
        $base_url = BASE_URL;
        $pageTitle = "Editor de Presentación (Markdown)";
        if (empty($_SESSION['csrf_token_generate_pdf'])) {
            $_SESSION['csrf_token_generate_pdf'] = bin2hex(random_bytes(32));
        }
        $csrf_token_generate_pdf = $_SESSION['csrf_token_generate_pdf'];

        $viewPath = VIEWS_PATH . 'base_markdown.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            $this->showErrorPage("Vista del editor Markdown no encontrada: " . $viewPath);
        }
    }

    public function showMarpEditor(): void {
        $base_url = BASE_URL;
        $pageTitle = "Editor de Presentación (Marp)";
        if (empty($_SESSION['csrf_token_marp_generate'])) {
            $_SESSION['csrf_token_marp_generate'] = bin2hex(random_bytes(32));
        }
        $csrf_token_marp_generate = $_SESSION['csrf_token_marp_generate'];

        $viewPath = VIEWS_PATH . 'base_marp.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            $this->showErrorPage("Vista del editor Marp no encontrada: " . $viewPath);
        }
    }

    public function renderMarpPreview(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['markdown'])) {
            $this->sendJsonResponse(['error' => 'Petición incorrecta o falta contenido markdown.'], 400);
        }
        ob_start();
        $renderScriptPath = ROOT_PATH . '/server/render_marp.php';
        if (file_exists($renderScriptPath)) {
            include $renderScriptPath;
        } else {
            error_log("Script render_marp.php no encontrado: " . $renderScriptPath);
            ob_end_clean();
            $this->sendJsonResponse(['error' => 'Error interno (script de renderizado no encontrado).'], 500);
        }
        $output = ob_get_clean();
        echo $output;
        exit;
    }

    public function generateMarpFile(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['markdown']) || empty($input['format'])) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Petición inválida. Faltan datos.'], 400);
        }

        $markdownContent = $input['markdown'];
        $format = $input['format'];
        $allowedFormats = ['pdf', 'html', 'pptx'];
        if ($format === 'ppt') $format = 'pptx';

        if (!in_array($format, $allowedFormats)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Formato no soportado.'], 400);
        }

        try {
            $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
            $userTempDir = ROOT_PATH . '/public/temp_files/marp_output/' . $userIdForPath . '/';
            if (!is_dir($userTempDir) && !mkdir($userTempDir, 0775, true)) {
                throw new \Exception("No se pudo crear el directorio temporal para el usuario.");
            }

            $fileName = 'marp_presentation_' . time() . '.' . $format;
            $outputFilePath = $userTempDir . $fileName;

            global $markdown_for_file_generation, $format_for_file_generation, $output_path_for_file_generation;
            $markdown_for_file_generation = $markdownContent;
            $format_for_file_generation = $format;
            $output_path_for_file_generation = $outputFilePath;
            
            ob_start();
            $renderScriptPath = ROOT_PATH . '/server/render_marp.php';
            if (file_exists($renderScriptPath)) {
                include $renderScriptPath;
            } else {
                throw new \Exception("Script de renderizado de Marp no encontrado.");
            }
            $scriptOutput = ob_get_clean();

            if (!file_exists($outputFilePath) || filesize($outputFilePath) === 0) {
                throw new \Exception("El archivo no fue generado por Marp CLI. Salida del script: " . $scriptOutput);
            }

            $_SESSION['downloadable_file'] = ['fileName' => $fileName, 'fullPath' => $outputFilePath, 'deleteAfter' => true];
            $downloadUrl = BASE_URL . '/markdown/download-page/' . urlencode($fileName);
            
            $this->sendJsonResponse(['success' => true, 'downloadUrl' => $downloadUrl]);

        } catch (\Exception $e) {
            error_log("Error en generateMarpFile: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Error al generar el archivo.', 'details' => (ENVIRONMENT === 'development' ? $e->getMessage() : 'Error interno.')], 500);
        }
    }

    /*
    * ==================================================================
    *  FUNCIÓN DE GENERACIÓN DE VIDEO (DESACTIVADA TEMPORALMENTE)
    * ==================================================================
    * 
    public function generateMarpVideo(): void {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['markdown'])) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Petición inválida. Falta contenido markdown.'], 400);
            return;
        }

        $markdownContent = $input['markdown'];
        $userDir = $this->getUserTempDir();
        $videoDir = $userDir . '/video_' . uniqid();
        if (!mkdir($videoDir, 0777, true)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'No se pudo crear el directorio temporal para el video.'], 500);
            return;
        }

        // Generar imágenes de las diapositivas
        $marpHandler = new MarpCliHandler();
        $imageResult = $marpHandler->generateImages($markdownContent, $videoDir);

        if (!$imageResult['success']) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Error al generar imágenes: ' . $imageResult['error']], 500);
            return;
        }

        // Generar video desde las imágenes
        $ffmpegHandler = new FFmpegHandler();
        $videoPath = $videoDir . '/presentation.mp4';
        $videoResult = $ffmpegHandler->createVideoFromImages($imageResult['images'], $videoPath);

        if (!$videoResult['success']) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Error al generar el video: ' . $videoResult['error']], 500);
            return;
        }

        // Guardar en sesión y responder
        $fileName = basename($videoPath);
        $_SESSION['temp_file'] = [
            'path' => $videoPath,
            'name' => $fileName,
            'type' => 'video/mp4',
            'cleanup_dir' => $videoDir // Directorio completo a eliminar
        ];

        $downloadUrl = '/markdown/download?file=' . urlencode($fileName);
        $this->sendJsonResponse(['success' => true, 'downloadUrl' => $downloadUrl]);
    }
    */

    public function generatePdfFromHtml(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['html_content'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Petición incorrecta o falta contenido HTML.'], 400);
        }

        if (empty($_POST['csrf_token_generate_pdf']) || !hash_equals($_SESSION['csrf_token_generate_pdf'] ?? '', $_POST['csrf_token_generate_pdf'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Token CSRF inválido o faltante.'], 403);
        }

        $htmlContent = $_POST['html_content'];
        $userIdForPath = $_SESSION['user_id'] ?? 'guest_' . substr(session_id(), 0, 8);
        $userTempDir = ROOT_PATH . '/public/temp_files/pdfs/' . $userIdForPath . '/';
        if (!is_dir($userTempDir)) { if (!mkdir($userTempDir, 0775, true) && !is_dir($userTempDir)) { $this->sendJsonResponse(['success' => false, 'error' => 'No se pudo crear el directorio temporal.'], 500); } }

        $pdfFileName = 'preview_md_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $outputPdfFile = $userTempDir . $pdfFileName;

        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);
            $cssBaseMarkdown = file_exists(ROOT_PATH . '/public/css/base_markdown.css') ? file_get_contents(ROOT_PATH . '/public/css/base_markdown.css') : '';
            $cssHeader = file_exists(ROOT_PATH . '/public/css/header.css') ? file_get_contents(ROOT_PATH . '/public/css/header.css') : '';
            $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $cssBaseMarkdown . $cssHeader . '</style></head><body>' . $htmlContent . '</body></html>';
            $dompdf->loadHtml($fullHtml);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            if (file_put_contents($outputPdfFile, $dompdf->output()) === false) {
                throw new \Exception("No se pudo guardar el archivo PDF generado.");
            }

            $_SESSION['pdf_download_file'] = $pdfFileName;
            $_SESSION['pdf_download_full_path'] = $outputPdfFile;

            $this->sendJsonResponse(['success' => true, 'downloadPageUrl' => '/markdown/download-page/' . urlencode($pdfFileName)]);

        } catch (\Exception $e) {
            error_log("Error generando PDF con Dompdf: " . $e->getMessage());
            if (file_exists($outputPdfFile)) unlink($outputPdfFile);
            $this->sendJsonResponse(['success' => false, 'error' => 'Error al generar el archivo PDF.', 'debug' => (ENVIRONMENT === 'development' ? $e->getMessage() : 'Error interno.')], 500);
        }
    }

    public function showPdfDownloadPage(string $filenameFromUrl): void {
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
        } else { $this->showErrorPage("Archivo de descarga no válido o expirado."); exit; }
    }

    public function forceDownloadPdf(string $filenameFromUrl): void {
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
        } else { $this->showErrorPage("No se puede descargar el archivo o ha expirado."); exit; }
    }

    private function showErrorPage(string $logMessage, string $userMessage = "Error."): void {
        error_log($logMessage);
        http_response_code(500);
        $pageTitle = "Error";
        $errorMessage = $userMessage;
        if (file_exists(VIEWS_PATH . 'error/500.php')) {
            require_once VIEWS_PATH . 'error/500.php';
        } else {
            echo "<h1>$pageTitle</h1><p>$errorMessage</p>";
        }
    }

    private function sendJsonResponse(array $data, int $statusCode = 200): void {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }
        echo json_encode($data);
        exit;
    }
}