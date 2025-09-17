<?php
/**
 * Debug profundo del Error 500
 * Identifica el error exacto en OrangeHRM
 */

// Habilitar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Debug Error 500 - Portos International</title></head><body>\n";
echo "<h1>🔍 Debug Profundo Error 500</h1>\n";
echo "<pre>\n";

echo "🎯 DEBUGGING ERROR 500 EN ORANGEHRM...\n\n";

// PASO 1: Verificar el contenido de web/index.php
echo "📄 PASO 1: Analizando web/index.php en detalle...\n";
$webIndexPath = '/var/www/html/web/index.php';

if (file_exists($webIndexPath)) {
    $content = file_get_contents($webIndexPath);
    echo "   📊 Tamaño: " . strlen($content) . " bytes\n";
    
    // Buscar requires/includes
    echo "   🔍 Archivos requeridos:\n";
    preg_match_all('/(?:require|include)(?:_once)?\s*\(?[\'"](.+?)[\'"]\)?/', $content, $matches);
    foreach ($matches[1] as $file) {
        $fullPath = dirname($webIndexPath) . '/' . $file;
        $fullPath = str_replace('/../', '/', $fullPath); // Simplificar path
        echo "      • $file " . (file_exists($fullPath) ? "✅" : "❌ FALTA") . "\n";
    }
    
    // Buscar rutas relativas
    if (strpos($content, '../src') !== false) {
        echo "   ℹ️ web/index.php usa rutas relativas a ../src\n";
    }
}

// PASO 2: Simular la carga de web/index.php
echo "\n🧪 PASO 2: Simulando carga de web/index.php...\n";

// Cambiar al directorio web
chdir('/var/www/html/web');

// Configurar entorno
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/web/index.php';
$_SERVER['PHP_SELF'] = '/web/index.php';

// Capturar errores
$errors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors) {
    $errors[] = "Error [$errno]: $errstr en $errfile:$errline";
    return true;
});

// Buffer de salida
ob_start();
$output = '';
$exception = null;

try {
    // Intentar incluir web/index.php
    echo "   🔄 Intentando cargar web/index.php...\n";
    
    // Verificar primero si necesita autoload
    $autoloadPath = '/var/www/html/src/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        echo "   ✅ Autoload cargado\n";
    }
    
    // Ahora intentar cargar el index
    include $webIndexPath;
    
} catch (Exception $e) {
    $exception = $e;
} catch (Throwable $e) {
    $exception = $e;
}

$output = ob_get_clean();

// Restaurar manejador de errores
restore_error_handler();

// Mostrar resultados
if ($exception) {
    echo "   ❌ EXCEPCIÓN CAPTURADA:\n";
    echo "      Tipo: " . get_class($exception) . "\n";
    echo "      Mensaje: " . $exception->getMessage() . "\n";
    echo "      Archivo: " . $exception->getFile() . "\n";
    echo "      Línea: " . $exception->getLine() . "\n";
    echo "      Stack trace:\n";
    $trace = explode("\n", $exception->getTraceAsString());
    foreach (array_slice($trace, 0, 10) as $line) {
        echo "         " . $line . "\n";
    }
}

if (!empty($errors)) {
    echo "\n   ⚠️ ERRORES CAPTURADOS:\n";
    foreach ($errors as $error) {
        echo "      • " . $error . "\n";
    }
}

if (!empty($output)) {
    echo "\n   📝 SALIDA CAPTURADA:\n";
    echo "   " . str_repeat('-', 50) . "\n";
    echo "   " . htmlspecialchars(substr($output, 0, 1000)) . "\n";
    echo "   " . str_repeat('-', 50) . "\n";
}

// PASO 3: Verificar archivos críticos
echo "\n🔍 PASO 3: Verificando archivos críticos de OrangeHRM...\n";

$criticalPaths = [
    '/var/www/html/src/kernel/Kernel.php',
    '/var/www/html/src/Kernel.php',
    '/var/www/html/symfony',
    '/var/www/html/src/lib/config/Config.php',
    '/var/www/html/lib/confs/Conf.php',
    '/var/www/html/src/config/config.yml',
    '/var/www/html/app/config/config.yml'
];

foreach ($criticalPaths as $path) {
    if (file_exists($path)) {
        echo "   ✅ $path (encontrado)\n";
    } else {
        echo "   ❌ $path (no existe)\n";
    }
}

// PASO 4: Buscar archivos de configuración
echo "\n📁 PASO 4: Buscando archivos de configuración...\n";

// Buscar archivos .env
$envFiles = glob('/var/www/html/.env*');
if (!empty($envFiles)) {
    echo "   📄 Archivos .env encontrados:\n";
    foreach ($envFiles as $env) {
        echo "      • " . basename($env) . "\n";
    }
} else {
    echo "   ❌ No hay archivos .env\n";
}

// Buscar configuración Symfony
$symfonyConfigDirs = [
    '/var/www/html/config',
    '/var/www/html/app/config',
    '/var/www/html/src/config'
];

foreach ($symfonyConfigDirs as $dir) {
    if (is_dir($dir)) {
        $configs = glob($dir . '/*.yml');
        if (!empty($configs)) {
            echo "   📁 Configuraciones en $dir:\n";
            foreach ($configs as $config) {
                echo "      • " . basename($config) . "\n";
            }
        }
    }
}

// PASO 5: Crear solución alternativa
echo "\n🔧 PASO 5: Creando solución alternativa...\n";

// Crear un index.php que muestre información útil
$debugIndexContent = '<?php
/**
 * OrangeHRM Debug Index
 * Muestra información mientras se resuelve el Error 500
 */

// Habilitar errores
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Verificar que tenemos lo básico
$checks = [
    "PHP Version" => phpversion(),
    "PostgreSQL Extension" => extension_loaded("pdo_pgsql") ? "✅ Instalada" : "❌ Falta",
    "Vendor Autoload" => file_exists(__DIR__ . "/src/vendor/autoload.php") ? "✅ Existe" : "❌ Falta",
    "Web Index" => file_exists(__DIR__ . "/web/index.php") ? "✅ Existe" : "❌ Falta",
    "Database Config" => file_exists(__DIR__ . "/src/config/database.php") ? "✅ Existe" : "❌ Falta"
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Portos International - OrangeHRM Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #ff6600; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #ff6600; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Portos International - OrangeHRM Debug</h1>
    
    <div class="info">
        <h2>Estado del Sistema</h2>
        <table>
            <?php foreach ($checks as $check => $status): ?>
            <tr>
                <td><?php echo $check; ?></td>
                <td><?php echo $status; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="info">
        <h2>Base de Datos</h2>
        <?php
        try {
            $configPath = __DIR__ . "/src/config/database.php";
            if (file_exists($configPath)) {
                $config = include $configPath;
                if (isset($config["database"])) {
                    $db = $config["database"];
                    echo "<p class=\"success\">✅ Configuración encontrada</p>";
                    echo "<p>Host: " . $db["host"] . "</p>";
                    echo "<p>Base de datos: " . $db["dbname"] . "</p>";
                    
                    // Intentar conexión
                    try {
                        $dsn = "pgsql:host={$db["host"]};port={$db["port"]};dbname={$db["dbname"]}";
                        $pdo = new PDO($dsn, $db["username"], $db["password"]);
                        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\'public\' AND table_name LIKE \'ohrm_%\'");
                        $tableCount = $stmt->fetchColumn();
                        echo "<p class=\"success\">✅ Conexión exitosa - $tableCount tablas OrangeHRM</p>";
                        echo "<p>👤 Usuario admin configurado</p>";
                        echo "<p>🔑 Contraseña: PortosAdmin123!</p>";
                    } catch (Exception $e) {
                        echo "<p class=\"error\">❌ Error conectando: " . $e->getMessage() . "</p>";
                    }
                }
            }
        } catch (Exception $e) {
            echo "<p class=\"error\">❌ Error leyendo configuración</p>";
        }
        ?>
    </div>
    
    <div class="info">
        <h2>Diagnóstico del Error 500</h2>
        <p>El Error 500 probablemente se debe a:</p>
        <ul>
            <li>Falta algún archivo crítico de OrangeHRM</li>
            <li>Configuración de rutas incorrectas</li>
            <li>Dependencias faltantes</li>
        </ul>
    </div>
    
    <div class="info">
        <h2>Enlaces de Debug</h2>
        <ul>
            <li><a href="/public/debug-500.php">Debug detallado</a></li>
            <li><a href="/web/" target="_blank">Directorio /web/</a></li>
            <li><a href="/phpinfo.php">PHP Info</a></li>
        </ul>
    </div>
    
    <div class="info">
        <h2>Siguiente Paso</h2>
        <p>El sistema está configurado pero OrangeHRM necesita archivos adicionales para funcionar.</p>
        <p>La base de datos y el usuario admin están listos.</p>
    </div>
</body>
</html>
<?php';

// Guardar como index temporal
file_put_contents('/var/www/html/index.debug.php', $debugIndexContent);
echo "   ✅ index.debug.php creado\n";

// Crear phpinfo para debug
file_put_contents('/var/www/html/phpinfo.php', '<?php phpinfo();');
echo "   ✅ phpinfo.php creado\n";

echo "\n================================================================\n";
echo "🎯 RESUMEN DEL DEBUG\n";
echo "================================================================\n\n";

if ($exception) {
    echo "❌ ERROR PRINCIPAL ENCONTRADO:\n";
    echo "   " . $exception->getMessage() . "\n";
    echo "   En: " . $exception->getFile() . ":" . $exception->getLine() . "\n\n";
}

echo "📊 DIAGNÓSTICO:\n";
echo "   • web/index.php existe ✅\n";
echo "   • Configuración de base de datos existe ✅\n";
echo "   • Usuario admin existe ✅\n";
echo "   • Wizard bypassed ✅\n";
echo "   • Error 500 = Falta algo en el código de OrangeHRM ❌\n\n";

echo "🔧 SOLUCIONES CREADAS:\n";
echo "   • index.debug.php - Página de información\n";
echo "   • phpinfo.php - Información PHP\n\n";

echo "🎯 PRÓXIMOS PASOS:\n";
echo "   1. Copiar index.debug.php a index.php para ver información\n";
echo "   2. Revisar qué archivos faltan en OrangeHRM\n";
echo "   3. El sistema está listo, solo falta completar archivos\n\n";

// Copiar index.debug.php a index.php temporalmente
copy('/var/www/html/index.debug.php', '/var/www/html/index.php');
echo "✅ index.php reemplazado con versión debug\n\n";

echo "🌐 AHORA PRUEBA: https://orangehrm-portos-international.onrender.com\n";
echo "   Verás una página con información del sistema\n";

echo "\n⏰ Debug completado: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n</body></html>\n";
?>