<?php
// server/render_marp.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function log_marp_action(string $message): void {
    $logFile = __DIR__ . '/../logs/marp_render.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// --- MODO 1: GENERACIÓN DE ARCHIVO --- 
if (isset($_SESSION['marp_generation'])) {
    $params = $_SESSION['marp_generation'];
    unset($_SESSION['marp_generation']); // Prevenir re-ejecuciones

    $markdownContent = $params['markdown'] ?? '';
    $format = $params['format'] ?? 'pdf';
    $outputFile = $params['output_path'] ?? '';

    if (empty($markdownContent) || empty($outputFile)) {
        log_marp_action("ERROR [File Gen]: Parámetros inválidos. Markdown o ruta de salida vacíos.");
        return;
    }

    $nodeExecutablePath = 'node'; 
    $marpCliScriptPath = realpath(__DIR__ . '/../node_modules/@marp-team/marp-cli/marp-cli.js');

    if (!$marpCliScriptPath) {
        log_marp_action("ERROR [File Gen]: No se encontró el script de Marp CLI.");
        return;
    }

    $outputDir = dirname($outputFile);
    $tmpMdFile = tempnam($outputDir, 'marp_md_');
    if (!$tmpMdFile) {
        log_marp_action("ERROR [File Gen]: No se pudo crear el archivo temporal en {$outputDir}");
        return;
    }
    
    rename($tmpMdFile, $tmpMdFile .= '.md');
    file_put_contents($tmpMdFile, $markdownContent);

    $command = sprintf(
        '%s "%s" %s --allow-local-files --%s -o %s',
        escapeshellcmd($nodeExecutablePath),
        $marpCliScriptPath,
        escapeshellarg($tmpMdFile),
        escapeshellarg($format),
        escapeshellarg($outputFile)
    );

    log_marp_action("INFO [File Gen]: Ejecutando comando: {$command}");

    exec($command . ' 2>&1', $cli_output, $return_status);
    
    log_marp_action("INFO [File Gen]: Salida de Marp CLI: " . implode("\n", $cli_output));
    log_marp_action("INFO [File Gen]: Código de salida de Marp CLI: {$return_status}");

    if (file_exists($tmpMdFile)) {
        unlink($tmpMdFile);
    }
    return; // Termina la ejecución para el modo de generación de archivo
}

// --- MODO 2: VISTA PREVIA EN VIVO (Fallback) ---
if (isset($_POST['markdown'])) {
    $markdownContent = $_POST['markdown'];
    $nodeExecutablePath = 'node';
    $marpCliScriptPath  = realpath(__DIR__ . '/../node_modules/@marp-team/marp-cli/marp-cli.js');

    if ($marpCliScriptPath === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Marp CLI no encontrado en el servidor.']);
        return;
    }

    $tmpMdFile = tempnam(sys_get_temp_dir(), 'marp_preview_');
    rename($tmpMdFile, $tmpMdFile .= '.md');
    file_put_contents($tmpMdFile, $markdownContent);

    $command = sprintf(
        '%s "%s" %s --html --allow-local-files -o -',
        escapeshellcmd($nodeExecutablePath),
        $marpCliScriptPath,
        escapeshellarg($tmpMdFile)
    );

    $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $process = proc_open($command, $descriptorspec, $pipes, sys_get_temp_dir());

    if (is_resource($process)) {
        fclose($pipes[0]);
        $htmlOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $return_status = proc_close($process);

        unlink($tmpMdFile);

        if ($return_status === 0) {
            header('Content-Type: text/html; charset=utf-8');
            echo $htmlOutput;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al procesar el markdown.', 'details' => $errorOutput]);
        }
    } else {
        unlink($tmpMdFile);
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo ejecutar el proceso de Marp CLI.']);
    }
    return;
}

// Si no se cumple ninguna condición
http_response_code(400);
echo json_encode(['error' => 'Petición no válida.']);
// No más exit() aquí si va a ser incluido y el controlador maneja el flujo.
// Si este script es el endpoint final, aquí iría un exit().
?>