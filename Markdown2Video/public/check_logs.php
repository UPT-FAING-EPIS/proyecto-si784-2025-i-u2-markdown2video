<?php
echo "<h2>Configuración de Logs de PHP</h2>";
echo "<p><strong>Archivo de log de errores:</strong> " . ini_get('error_log') . "</p>";
echo "<p><strong>Log de errores habilitado:</strong> " . (ini_get('log_errors') ? 'Sí' : 'No') . "</p>";
echo "<p><strong>Mostrar errores:</strong> " . (ini_get('display_errors') ? 'Sí' : 'No') . "</p>";
echo "<p><strong>Nivel de reporte de errores:</strong> " . ini_get('error_reporting') . "</p>";
echo "<p><strong>Directorio temporal:</strong> " . sys_get_temp_dir() . "</p>";
echo "<p><strong>Usuario actual:</strong> " . get_current_user() . "</p>";
echo "<p><strong>Directorio de trabajo:</strong> " . getcwd() . "</p>";

// Verificar si el archivo de log existe y es escribible
$logFile = ini_get('error_log');
if ($logFile) {
    echo "<p><strong>El archivo de log existe:</strong> " . (file_exists($logFile) ? 'Sí' : 'No') . "</p>";
    if (file_exists($logFile)) {
        echo "<p><strong>Es escribible:</strong> " . (is_writable($logFile) ? 'Sí' : 'No') . "</p>";
        echo "<p><strong>Tamaño del archivo:</strong> " . filesize($logFile) . " bytes</p>";
    }
}
?>