<?php
/**
 * Analizador del wizard persistente
 * Investiga qué está verificando OrangeHRM para mostrar el wizard
 */

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Análisis Wizard - Portos International</title></head><body>\n";
echo "<h1>🔍 Análisis del Wizard Persistente</h1>\n";
echo "<pre>\n";

echo "🎯 INVESTIGANDO POR QUÉ EL WIZARD PERSISTE...\n\n";

// PASO 1: Analizar archivos del instalador
echo "📁 PASO 1: Analizando archivos del instalador...\n";

$installerFiles = [
    '/var/www/html/installer/index.php',
    '/var/www/html/installer/api/install.php',
    '/var/www/html/installer/client/dist/index.html',
    '/var/www/html/installer/.installed',
    '/var/www/html/installer/.installation_completed'
];

foreach ($installerFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "   ✅ $file ($size bytes)\n";
        
        // Si es un archivo PHP pequeño, mostrar contenido
        if (strpos($file, '.php') !== false && $size < 2000) {
            echo "   📄 Contenido:\n";
            $content = file_get_contents($file);
            echo "   " . str_repeat('-', 50) . "\n";
            echo "   " . htmlspecialchars(substr($content, 0, 1000)) . "\n";
            echo "   " . str_repeat('-', 50) . "\n\n";
        }
    } else {
        echo "   ❌ $file (no existe)\n";
    }
}

// PASO 2: Analizar código del instalador principal
echo "\n📋 PASO 2: Analizando lógica del instalador...\n";

$installerIndexPath = '/var/www/html/installer/index.php';
if (file_exists($installerIndexPath)) {
    $installerCode = file_get_contents($installerIndexPath);
    echo "   📄 Tamaño del installer: " . strlen($installerCode) . " caracteres\n";
    
    // Buscar patrones de verificación
    $patterns = [
        'installed' => '/\.installed|installation.*complete|setup.*done/i',
        'database' => '/database.*config|db.*setup|connection.*test/i',
        'redirect' => '/redirect|header.*location|welcome.*screen/i',
        'condition' => '/if.*\(|condition|check.*install/i'
    ];
    
    foreach ($patterns as $type => $pattern) {
        $matches = [];
        preg_match_all($pattern, $installerCode, $matches);
        echo "   🔍 Patrones '$type': " . count($matches[0]) . " encontrados\n";
        
        if (count($matches[0]) > 0 && count($matches[0]) < 10) {
            foreach (array_unique($matches[0]) as $match) {
                echo "      - " . htmlspecialchars(trim($match)) . "\n";
            }
        }
    }
} else {
    echo "   ❌ No se puede acceder al installer/index.php\n";
}

// PASO 3: Verificar configuración de OrangeHRM
echo "\n⚙️ PASO 3: Verificando configuración OrangeHRM...\n";

$configFiles = [
    '/var/www/html/src/config/database.php',
    '/var/www/html/src/config/config.php', 
    '/var/www/html/src/config/installation.php',
    '/var/www/html/src/config/cryptokey.txt',
    '/var/www/html/.installation_complete'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        echo "   ✅ " . basename($file) . " (" . strlen($content) . " chars)\n";
        
        // Mostrar contenido de archivos pequeños
        if (strlen($content) < 500) {
            echo "      📄 Contenido:\n";
            echo "      " . str_repeat('-', 30) . "\n";
            echo "      " . htmlspecialchars($content) . "\n";
            echo "      " . str_repeat('-', 30) . "\n";
        }
    } else {
        echo "   ❌ " . basename($file) . " (no existe)\n";
    }
}

// PASO 4: Verificar base de datos
echo "\n🗄️ PASO 4: Verificando base de datos...\n";

try {
    $dbConfig = [
        'host' => 'dpg-d34pm0ffte5s73abeq0g-a.oregon-postgres.render.com',
        'port' => 5432,
        'dbname' => 'orangehrm_portos',
        'username' => 'orangehrm_user',
        'password' => 'A5xg14Ns2M4QUQ7bu0fE2GsU6WFzyOaX'
    ];
    
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Listar todas las tablas
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename LIKE 'ohrm_%' ORDER BY tablename");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   📊 Tablas OrangeHRM encontradas (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        // Contar registros en cada tabla
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "      ✅ $table ($count registros)\n";
        } catch (Exception $e) {
            echo "      ⚠️ $table (error contando)\n";
        }
    }
    
    // Verificar usuario admin específicamente
    if (in_array('ohrm_user', $tables)) {
        echo "\n   👤 Verificando usuario admin:\n";
        $stmt = $pdo->query("SELECT user_name, user_role_id, status, deleted FROM ohrm_user WHERE user_name='admin'");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "      ✅ Usuario admin encontrado:\n";
            foreach ($admin as $key => $value) {
                echo "         - $key: $value\n";
            }
        } else {
            echo "      ❌ Usuario admin NO encontrado\n";
        }
    }
    
    // Verificar configuración del sistema
    if (in_array('ohrm_config', $tables)) {
        echo "\n   ⚙️ Configuración del sistema:\n";
        $stmt = $pdo->query("SELECT key, value FROM ohrm_config LIMIT 10");
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($configs as $config) {
            echo "      - {$config['key']}: {$config['value']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Error conectando a base de datos: " . $e->getMessage() . "\n";
}

// PASO 5: Análisis de URLs y redirects
echo "\n🌐 PASO 5: Analizando comportamiento web...\n";

// Verificar si hay redirecciones configuradas
$htaccessPath = '/var/www/html/.htaccess';
if (file_exists($htaccessPath)) {
    $htaccess = file_get_contents($htaccessPath);
    echo "   📄 .htaccess encontrado (" . strlen($htaccess) . " chars)\n";
    
    if (strpos($htaccess, 'installer') !== false || strpos($htaccess, 'redirect') !== false) {
        echo "   ⚠️ .htaccess contiene referencias a installer:\n";
        $lines = explode("\n", $htaccess);
        foreach ($lines as $i => $line) {
            if (stripos($line, 'installer') !== false || stripos($line, 'redirect') !== false) {
                echo "      Línea " . ($i + 1) . ": " . htmlspecialchars(trim($line)) . "\n";
            }
        }
    }
} else {
    echo "   ℹ️ No hay .htaccess\n";
}

// Verificar index.php principal
$mainIndexPath = '/var/www/html/index.php';
if (file_exists($mainIndexPath)) {
    $mainIndex = file_get_contents($mainIndexPath);
    echo "   📄 index.php principal (" . strlen($mainIndex) . " chars)\n";
    
    if (stripos($mainIndex, 'installer') !== false) {
        echo "   ⚠️ index.php principal contiene referencias a installer\n";
        
        // Buscar líneas específicas
        $lines = explode("\n", $mainIndex);
        foreach ($lines as $i => $line) {
            if (stripos($line, 'installer') !== false) {
                echo "      Línea " . ($i + 1) . ": " . htmlspecialchars(trim($line)) . "\n";
            }
        }
    }
} else {
    echo "   ❌ index.php principal no encontrado\n";
}

echo "\n================================================================\n";
echo "🎯 RESUMEN DEL ANÁLISIS\n";
echo "================================================================\n\n";

echo "📊 ESTADO ACTUAL:\n";
echo "   • Base de datos: " . (isset($tables) ? count($tables) . " tablas" : "Error") . "\n";
echo "   • Usuario admin: " . (isset($admin) && $admin ? "✅ Existe" : "❌ Problema") . "\n";
echo "   • Archivos config: " . (file_exists('/var/www/html/src/config/database.php') ? "✅ Existe" : "❌ Falta") . "\n";
echo "   • Marcador .installed: " . (file_exists('/var/www/html/installer/.installed') ? "✅ Existe" : "❌ Falta") . "\n\n";

echo "🔍 POSIBLES CAUSAS DEL WIZARD:\n";
echo "   1. 🔄 OrangeHRM verifica algo específico que no hemos configurado\n";
echo "   2. 📄 El index.php principal redirige automáticamente al installer\n";
echo "   3. ⚙️ Falta alguna configuración específica en base de datos\n";
echo "   4. 🌐 Hay una redirección a nivel de servidor web\n\n";

echo "🎯 PRÓXIMOS PASOS SUGERIDOS:\n";
echo "   1. 🔧 Modificar index.php principal para forzar bypass\n";
echo "   2. 📋 Crear las tablas faltantes que OrangeHRM espera\n";
echo "   3. ⚙️ Completar configuración específica del sistema\n\n";

echo "💡 ESTE ANÁLISIS nos dará las pistas exactas para resolver el wizard\n";

echo "\n⏰ Análisis completado: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n</body></html>\n";
?>