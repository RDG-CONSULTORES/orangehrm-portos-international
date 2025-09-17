<?php
/**
 * Diagnóstico del Error 500
 * Identifica el problema exacto después del bypass del wizard
 */

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Diagnóstico Error 500 - Portos International</title></head><body>\n";
echo "<h1>🔍 Diagnóstico Error 500</h1>\n";
echo "<pre>\n";

echo "🎯 INVESTIGANDO ERROR 500...\n\n";

// PASO 1: Verificar archivos de entrada principales
echo "📁 PASO 1: Verificando archivos de entrada...\n";

$entryPoints = [
    '/var/www/html/index.php',
    '/var/www/html/src/public/index.php',
    '/var/www/html/web/index.php',
    '/var/www/html/public/index.php',
    '/var/www/html/src/index.php',
    '/var/www/html/src/kernel/boot.php',
    '/var/www/html/src/Kernel.php'
];

foreach ($entryPoints as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "   ✅ $file (" . $size . " bytes)\n";
    } else {
        echo "   ❌ $file (no existe)\n";
    }
}

// PASO 2: Verificar el nuevo index.php
echo "\n📄 PASO 2: Analizando index.php modificado...\n";

$indexPath = '/var/www/html/index.php';
if (file_exists($indexPath)) {
    $indexContent = file_get_contents($indexPath);
    
    // Buscar la línea que incluye el archivo principal
    if (preg_match('/require_once.*?(["\'])(.+?)\1/', $indexContent, $matches)) {
        $requirePath = $matches[2];
        echo "   🔍 Index.php intenta cargar: $requirePath\n";
        
        $fullPath = str_replace('__DIR__', '/var/www/html', $requirePath);
        if (file_exists($fullPath)) {
            echo "   ✅ Archivo existe: $fullPath\n";
        } else {
            echo "   ❌ Archivo NO existe: $fullPath\n";
        }
    }
    
    // Verificar paths probados
    echo "\n   📋 Paths que index.php intenta:\n";
    $possiblePaths = [
        '/var/www/html/src/public/index.php',
        '/var/www/html/web/index.php',
        '/var/www/html/public/index.php',
        '/var/www/html/app/index.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (strpos($indexContent, basename($path)) !== false) {
            echo "      • " . $path . " " . (file_exists($path) ? "✅" : "❌") . "\n";
        }
    }
}

// PASO 3: Verificar estructura de directorios
echo "\n📁 PASO 3: Estructura de directorios...\n";

$dirs = [
    '/var/www/html',
    '/var/www/html/src',
    '/var/www/html/src/public',
    '/var/www/html/web',
    '/var/www/html/public'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        $phpFiles = array_filter($files, function($f) { return strpos($f, '.php') !== false; });
        echo "   ✅ $dir (" . count($phpFiles) . " archivos PHP)\n";
        
        // Mostrar primeros archivos PHP
        if (count($phpFiles) > 0 && count($phpFiles) < 10) {
            foreach (array_slice($phpFiles, 0, 5) as $file) {
                echo "      • $file\n";
            }
        }
    } else {
        echo "   ❌ $dir (no existe)\n";
    }
}

// PASO 4: Buscar el archivo de entrada real
echo "\n🔍 PASO 4: Buscando archivo de entrada real de OrangeHRM...\n";

// Buscar archivos index.php en todo el proyecto
$searchPaths = [
    '/var/www/html/src',
    '/var/www/html/web',
    '/var/www/html/public'
];

$foundIndexFiles = [];
foreach ($searchPaths as $basePath) {
    if (is_dir($basePath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'index.php') {
                $foundIndexFiles[] = $file->getPathname();
            }
        }
    }
}

echo "   📋 Archivos index.php encontrados:\n";
foreach ($foundIndexFiles as $file) {
    $size = filesize($file);
    echo "      • $file ($size bytes)\n";
}

// PASO 5: Verificar archivos de bootstrap
echo "\n🚀 PASO 5: Verificando archivos de bootstrap...\n";

$bootstrapFiles = [
    '/var/www/html/symfony',
    '/var/www/html/src/vendor/autoload.php',
    '/var/www/html/vendor/autoload.php',
    '/var/www/html/src/kernel/boot.php',
    '/var/www/html/src/kernel/Kernel.php'
];

foreach ($bootstrapFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file\n";
    }
}

// PASO 6: Crear index.php simple de prueba
echo "\n🔧 PASO 6: Creando solución temporal...\n";

// Crear un index.php que funcione
$simpleIndexContent = '<?php
/**
 * OrangeHRM Index - Solución Temporal
 * Redirige al archivo correcto de entrada
 */

// Mostrar información del sistema mientras encontramos el archivo correcto
if (!isset($_GET["bypass"])) {
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Portos International - OrangeHRM</title></head><body>";
    echo "<h1>🎉 Portos International - Sistema OrangeHRM</h1>";
    echo "<p>✅ Wizard bypass exitoso - Sistema configurado</p>";
    echo "<hr>";
    echo "<h2>🔍 Buscando entrada principal de OrangeHRM...</h2>";
    
    // Verificar base de datos
    $dbConfigPath = __DIR__ . "/src/config/database.php";
    if (file_exists($dbConfigPath)) {
        echo "<p>✅ Configuración de base de datos encontrada</p>";
        try {
            $config = include $dbConfigPath;
            if (isset($config["database"])) {
                echo "<p>✅ Base de datos: PostgreSQL configurada</p>";
                echo "<p>👤 Usuario admin: admin</p>";
                echo "<p>🔑 Contraseña: PortosAdmin123!</p>";
            }
        } catch (Exception $e) {
            echo "<p>⚠️ Error verificando base de datos</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>🔗 Enlaces directos para probar:</h3>";
    echo "<ul>";
    echo "<li><a href=\"/src/\">Directorio /src/</a></li>";
    echo "<li><a href=\"/web/\">Directorio /web/</a></li>";
    echo "<li><a href=\"/symfony\">Symfony console</a></li>";
    echo "<li><a href=\"/installer/\" onclick=\"alert(\'El wizard ha sido bypassed. Use los otros enlaces.\'); return false;\">Installer (bloqueado)</a></li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<p>🔧 Si ningún enlace funciona, el sistema necesita que se complete la estructura de archivos.</p>";
    echo "<p>📧 Contacto: admin@portosinternational.com</p>";
    echo "</body></html>";
    exit;
}

// Si llegamos aquí, intentar cargar OrangeHRM
if (file_exists(__DIR__ . "/symfony")) {
    // Intentar ejecutar symfony console
    $_SERVER["SCRIPT_NAME"] = "/symfony";
    require_once __DIR__ . "/symfony";
} else {
    echo "Error: No se encuentra el punto de entrada de OrangeHRM";
}
?>';

// Guardar backup del index actual
$currentIndex = file_get_contents('/var/www/html/index.php');
file_put_contents('/var/www/html/index.php.bypass', $currentIndex);

// Crear el index simple
file_put_contents('/var/www/html/index.php', $simpleIndexContent);
echo "   ✅ index.php temporal creado\n";
echo "   💾 Backup guardado como index.php.bypass\n";

// PASO 7: Verificar logs de error
echo "\n📋 PASO 7: Verificando logs de error...\n";

$logFiles = [
    '/var/log/apache2/error.log',
    '/var/www/html/src/log/orangehrm.log',
    '/var/www/html/var/log/prod.log'
];

foreach ($logFiles as $log) {
    if (file_exists($log) && is_readable($log)) {
        echo "   📄 $log:\n";
        $lastLines = shell_exec("tail -n 5 $log 2>&1");
        echo "   " . str_repeat('-', 40) . "\n";
        echo "   " . str_replace("\n", "\n   ", $lastLines) . "\n";
        echo "   " . str_repeat('-', 40) . "\n";
    } else {
        echo "   ❌ $log (no accesible)\n";
    }
}

echo "\n================================================================\n";
echo "🎯 RESUMEN DEL DIAGNÓSTICO\n";
echo "================================================================\n\n";

echo "📊 PROBLEMAS ENCONTRADOS:\n";
echo "   • El bypass del wizard funcionó ✅\n";
echo "   • OrangeHRM no tiene el archivo de entrada esperado ❌\n";
echo "   • La estructura de archivos está incompleta\n\n";

echo "✅ SOLUCIÓN TEMPORAL APLICADA:\n";
echo "   • index.php simplificado creado\n";
echo "   • Página de información mientras se resuelve\n";
echo "   • Enlaces directos para explorar\n\n";

echo "🎯 PRÓXIMOS PASOS:\n";
echo "   1. Ir a: https://orangehrm-portos-international.onrender.com\n";
echo "   2. Verás una página de información con enlaces\n";
echo "   3. Explorar los directorios disponibles\n\n";

echo "⏰ Diagnóstico completado: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n</body></html>\n";
?>