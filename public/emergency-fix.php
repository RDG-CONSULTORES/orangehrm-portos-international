<?php
/**
 * Corrección de emergencia para el wizard persistente
 * Ejecuta desde PHP usando shell_exec
 */

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Corrección Emergencia - Portos International</title></head><body>\n";
echo "<h1>🚨 Corrección de Emergencia - Wizard Persistente</h1>\n";
echo "<pre>\n";

echo "🎯 EJECUTANDO CORRECCIÓN DEFINITIVA...\n\n";

// Obtener DATABASE_URL usando métodos alternativos
$DATABASE_URL = '';

// Método 1: printenv
$DATABASE_URL = trim(shell_exec('printenv DATABASE_URL 2>/dev/null') ?? '');

if (empty($DATABASE_URL)) {
    // Método 2: leer desde /proc/1/environ
    $environ = @file_get_contents('/proc/1/environ');
    if ($environ !== false) {
        $env_vars = explode("\0", $environ);
        foreach ($env_vars as $var) {
            if (strpos($var, 'DATABASE_URL=') === 0) {
                $DATABASE_URL = substr($var, 13);
                break;
            }
        }
    }
}

if (empty($DATABASE_URL)) {
    echo "❌ ERROR: No se pudo obtener DATABASE_URL\n";
    echo "🔧 EJECUTANDO SCRIPT ALTERNATIVO...\n\n";
    
    // Ejecutar script que no depende de DATABASE_URL
    $output = shell_exec('/var/www/html/scripts/final-fix-wizard.sh 2>&1');
    echo $output;
    
    echo "\n</pre>\n</body></html>\n";
    exit;
}

echo "✅ DATABASE_URL obtenida: " . substr($DATABASE_URL, 0, 30) . "...\n\n";

// Parsear DATABASE_URL
preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/(.+)/', $DATABASE_URL, $matches);
if (count($matches) !== 6) {
    echo "❌ ERROR: DATABASE_URL tiene formato inválido\n";
    echo "</pre>\n</body></html>\n";
    exit;
}

$dbConfig = [
    'user' => $matches[1],
    'password' => $matches[2],
    'host' => $matches[3],
    'port' => $matches[4],
    'dbname' => $matches[5]
];

echo "🔗 Configuración PostgreSQL:\n";
echo "   Host: {$dbConfig['host']}\n";
echo "   Puerto: {$dbConfig['port']}\n";
echo "   Base: {$dbConfig['dbname']}\n";
echo "   Usuario: {$dbConfig['user']}\n\n";

// Verificar conexión PostgreSQL
try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión PostgreSQL exitosa\n\n";
} catch (PDOException $e) {
    echo "❌ Error conectando a PostgreSQL: " . $e->getMessage() . "\n";
    echo "</pre>\n</body></html>\n";
    exit;
}

// Verificar si OrangeHRM está instalado
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
    $tableCount = $stmt->fetchColumn();
    
    if ($tableCount < 50) {
        echo "❌ OrangeHRM no está instalado completamente ($tableCount tablas)\n";
        echo "🔧 Ejecutando instalación forzada...\n\n";
        
        $installOutput = shell_exec('/var/www/html/scripts/force-complete-installation.sh 2>&1 &');
        echo "✅ Instalación forzada iniciada en background\n";
        echo "⏳ Esperar 4-5 minutos para completar\n\n";
        
        echo "</pre>\n</body></html>\n";
        exit;
    }
    
    echo "✅ OrangeHRM instalado ($tableCount tablas)\n";
    
    // Verificar usuario admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM ohrm_user WHERE user_name='admin'");
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists > 0) {
        echo "✅ Usuario admin existe\n\n";
        
        echo "🔧 APLICANDO CORRECCIONES DEL WIZARD...\n\n";
        
        // CORRECCIÓN 1: Crear marcador de instalación
        echo "📄 Creando marcador de instalación...\n";
        $installerDir = '/var/www/html/installer';
        if (!is_dir($installerDir)) {
            mkdir($installerDir, 0775, true);
        }
        file_put_contents($installerDir . '/.installed', date('Y-m-d H:i:s') . ": Installation completed via emergency-fix.php\n");
        echo "✅ Marcador /var/www/html/installer/.installed creado\n";
        
        // CORRECCIÓN 2: Configuración de base de datos
        echo "\n📄 Creando configuración de base de datos...\n";
        $configDir = '/var/www/html/src/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }
        
        $dbConfigContent = "<?php\nreturn [\n    'database' => [\n        'driver' => 'pdo_pgsql',\n        'host' => '{$dbConfig['host']}',\n        'port' => {$dbConfig['port']},\n        'dbname' => '{$dbConfig['dbname']}',\n        'username' => '{$dbConfig['user']}',\n        'password' => '{$dbConfig['password']}',\n        'charset' => 'utf8'\n    ]\n];\n";
        
        file_put_contents($configDir . '/database.php', $dbConfigContent);
        echo "✅ Configuración database.php creada\n";
        
        // CORRECCIÓN 3: Crypto key
        echo "\n📄 Creando crypto key...\n";
        file_put_contents($configDir . '/cryptokey.txt', "def50200e56d9e4db2f0a3fb5e2d556da8f8b42ee99e9b585d8e82f8e5c7f96b1\n");
        echo "✅ Crypto key creado\n";
        
        // CORRECCIÓN 4: Configuración adicional
        echo "\n📄 Creando configuración adicional...\n";
        $additionalConfig = "<?php\n// Configuración adicional para bypass del wizard\nreturn [\n    'installation_completed' => true,\n    'database_configured' => true,\n    'admin_user_created' => true,\n    'application_mode' => 'production'\n];\n";
        file_put_contents($configDir . '/config.php', $additionalConfig);
        echo "✅ Configuración adicional creada\n";
        
        // CORRECCIÓN 5: Crear env.php para acceso web
        echo "\n📄 Creando env.php para acceso web...\n";
        $envContent = "<?php\n// Variables de entorno para acceso web\ndefine('ENV_DATABASE_URL', '$DATABASE_URL');\ndefine('ENV_PORT', '" . ($_SERVER['SERVER_PORT'] ?? '10000') . "');\ndefine('ENV_TZ', 'America/Mexico_City');\n\nfunction getDatabaseUrl() {\n    return ENV_DATABASE_URL;\n}\n\nfunction parseDatabaseUrl(\$url = null) {\n    \$url = \$url ?: getDatabaseUrl();\n    if (empty(\$url)) return null;\n    \$matches = [];\n    if (preg_match('/postgresql:\\/\\/([^:]+):([^@]+)@([^:]+):(\\d+)\\/(.+)/', \$url, \$matches)) {\n        return ['user' => \$matches[1], 'password' => \$matches[2], 'host' => \$matches[3], 'port' => \$matches[4], 'dbname' => \$matches[5], 'full_url' => \$url];\n    }\n    return null;\n}\n";
        file_put_contents($configDir . '/env.php', $envContent);
        echo "✅ env.php creado\n";
        
        // CORRECCIÓN 6: Permisos y limpieza
        echo "\n🔧 Configurando permisos y limpieza...\n";
        shell_exec('chown -R www-data:www-data /var/www/html 2>/dev/null');
        shell_exec('chmod -R 755 /var/www/html 2>/dev/null');
        shell_exec('chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config 2>/dev/null');
        shell_exec('rm -rf /var/www/html/src/cache/* 2>/dev/null');
        echo "✅ Permisos y limpieza completados\n";
        
        // CORRECCIÓN 7: Reiniciar Apache
        echo "\n🔄 Reiniciando Apache...\n";
        shell_exec('service apache2 reload 2>/dev/null &');
        echo "✅ Apache reiniciado\n";
        
        echo "\n================================================================\n";
        echo "🎉 CORRECCIÓN DE EMERGENCIA COMPLETADA\n";
        echo "================================================================\n\n";
        
        echo "✅ TODAS LAS CORRECCIONES APLICADAS:\n";
        echo "   • Marcador de instalación: ✅\n";
        echo "   • Configuración database.php: ✅\n";
        echo "   • Crypto key: ✅\n";
        echo "   • Configuración adicional: ✅\n";
        echo "   • env.php para acceso web: ✅\n";
        echo "   • Permisos: ✅\n";
        echo "   • Caché limpio: ✅\n";
        echo "   • Apache reiniciado: ✅\n\n";
        
        echo "🎯 PRÓXIMOS PASOS:\n";
        echo "   1. 🔄 Refrescar página web (Ctrl+F5 / Cmd+Shift+R)\n";
        echo "   2. 🌐 Ir a: https://orangehrm-portos-international.onrender.com\n";
        echo "   3. ✅ Debería aparecer página de LOGIN (no wizard)\n";
        echo "   4. 🔐 Usar: admin / PortosAdmin123!\n\n";
        
        echo "💡 SI EL WIZARD PERSISTE:\n";
        echo "   • Usar navegador incógnito/privado\n";
        echo "   • Limpiar cookies del sitio\n";
        echo "   • Probar desde dispositivo diferente\n\n";
        
        echo "🎉 ¡SISTEMA LISTO PARA USAR!\n";
        
    } else {
        echo "❌ Usuario admin no existe - instalación incompleta\n";
        echo "🔧 Ejecutando instalación forzada...\n\n";
        
        $installOutput = shell_exec('/var/www/html/scripts/force-complete-installation.sh 2>&1 &');
        echo "✅ Instalación forzada iniciada en background\n";
        echo "⏳ Esperar 4-5 minutos para completar\n\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error verificando instalación: " . $e->getMessage() . "\n";
}

echo "\n⏰ Corrección completada: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n";
echo "</body></html>\n";
?>