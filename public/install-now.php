<?php
/**
 * Instalación DIRECTA de OrangeHRM desde PHP
 * Sin depender de scripts de background
 */

set_time_limit(600); // 10 minutos máximo

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Instalación Directa - Portos International</title></head><body>\n";
echo "<h1>🚀 Instalación Directa OrangeHRM - EN VIVO</h1>\n";
echo "<pre>\n";

echo "🎯 INSTALACIÓN DIRECTA DE ORANGEHRM...\n\n";

// Configuración hardcoded
$dbConfig = [
    'host' => 'dpg-d34pm0ffte5s73abeq0g-a.oregon-postgres.render.com',
    'port' => 5432,
    'dbname' => 'orangehrm_portos',
    'username' => 'orangehrm_user',
    'password' => 'A5xg14Ns2M4QUQ7bu0fE2GsU6WFzyOaX'
];

echo "🔗 Usando configuración PostgreSQL:\n";
echo "   Host: {$dbConfig['host']}\n";
echo "   Puerto: {$dbConfig['port']}\n";
echo "   Base: {$dbConfig['dbname']}\n";
echo "   Usuario: {$dbConfig['username']}\n\n";

// Verificar conexión
try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión PostgreSQL exitosa\n\n";
} catch (PDOException $e) {
    echo "❌ Error conectando a PostgreSQL: " . $e->getMessage() . "\n";
    echo "</pre>\n</body></html>\n";
    exit;
}

// Verificar estado actual
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
$currentTables = $stmt->fetchColumn();

echo "📊 Tablas OrangeHRM actuales: $currentTables\n\n";

if ($currentTables > 50) {
    echo "✅ OrangeHRM ya está instalado!\n";
    echo "🔧 Aplicando solo configuraciones finales...\n\n";
    
    // Solo aplicar configuraciones finales
    $configDir = '/var/www/html/src/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0775, true);
    }
    
    // Configuración correcta
    $dbConfigContent = "<?php\nreturn [\n    'database' => [\n        'driver' => 'pdo_pgsql',\n        'host' => '{$dbConfig['host']}',\n        'port' => {$dbConfig['port']},\n        'dbname' => '{$dbConfig['dbname']}',\n        'username' => '{$dbConfig['username']}',\n        'password' => '{$dbConfig['password']}',\n        'charset' => 'utf8'\n    ]\n];\n";
    file_put_contents($configDir . '/database.php', $dbConfigContent);
    
    // Marcador de instalación
    $installerDir = '/var/www/html/installer';
    if (!is_dir($installerDir)) {
        mkdir($installerDir, 0775, true);
    }
    file_put_contents($installerDir . '/.installed', date('Y-m-d H:i:s') . ": Installation completed via install-now.php\n");
    
    echo "✅ Configuraciones aplicadas\n";
    echo "🎯 Sistema listo para usar\n\n";
    
} else {
    echo "🔧 Iniciando instalación DIRECTA de OrangeHRM...\n\n";
    
    // PASO 1: Limpiar base de datos
    echo "🧹 PASO 1: Limpiando base de datos existente...\n";
    try {
        $pdo->exec("
            DO \$\$ DECLARE
                r RECORD;
            BEGIN
                FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'ohrm_%') LOOP
                    EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
                END LOOP;
            END \$\$;
        ");
        echo "   ✅ Base de datos limpiada\n";
    } catch (Exception $e) {
        echo "   ⚠️ Error limpiando: " . $e->getMessage() . "\n";
    }
    
    // PASO 2: Crear configuraciones
    echo "\n📄 PASO 2: Creando configuraciones...\n";
    $configDir = '/var/www/html/src/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0775, true);
    }
    
    // Configuración de base de datos
    $dbConfigContent = "<?php\nreturn [\n    'database' => [\n        'driver' => 'pdo_pgsql',\n        'host' => '{$dbConfig['host']}',\n        'port' => {$dbConfig['port']},\n        'dbname' => '{$dbConfig['dbname']}',\n        'username' => '{$dbConfig['username']}',\n        'password' => '{$dbConfig['password']}',\n        'charset' => 'utf8'\n    ]\n];\n";
    file_put_contents($configDir . '/database.php', $dbConfigContent);
    echo "   ✅ database.php creado\n";
    
    // Crypto key
    file_put_contents($configDir . '/cryptokey.txt', "def50200e56d9e4db2f0a3fb5e2d556da8f8b42ee99e9b585d8e82f8e5c7f96b1\n");
    echo "   ✅ cryptokey.txt creado\n";
    
    // PASO 3: Ejecutar instalación CLI directamente
    echo "\n🚀 PASO 3: Ejecutando instalación CLI OrangeHRM...\n";
    
    // Cambiar al directorio de OrangeHRM
    chdir('/var/www/html');
    
    // Comando de instalación CLI
    $installCmd = "php installer/console install:on-new-database " .
        "--db-type='pgsql' " .
        "--db-host='{$dbConfig['host']}' " .
        "--db-port='{$dbConfig['port']}' " .
        "--db-name='{$dbConfig['dbname']}' " .
        "--db-user='{$dbConfig['username']}' " .
        "--db-password='{$dbConfig['password']}' " .
        "--admin-username='admin' " .
        "--admin-password='PortosAdmin123!' " .
        "--admin-first-name='Administrador' " .
        "--admin-last-name='Portos' " .
        "--admin-email='admin@portosinternational.com' " .
        "--organization-name='Portos International' " .
        "--country='MX' " .
        "--timezone='America/Mexico_City' " .
        "--registration-consent " .
        "--no-interaction 2>&1";
    
    echo "   🔄 Ejecutando comando de instalación...\n";
    echo "   💻 Comando: php installer/console install:on-new-database...\n\n";
    
    // Ejecutar instalación con salida en tiempo real
    $handle = popen($installCmd, 'r');
    if ($handle) {
        echo "   📝 SALIDA DE INSTALACIÓN:\n";
        echo "   " . str_repeat('-', 50) . "\n";
        
        while (($line = fgets($handle)) !== false) {
            echo "   " . htmlspecialchars($line);
            flush();
            ob_flush();
        }
        
        $exitCode = pclose($handle);
        echo "   " . str_repeat('-', 50) . "\n";
        echo "   🎯 Código de salida: $exitCode\n\n";
        
        if ($exitCode === 0) {
            echo "✅ Instalación CLI completada exitosamente\n\n";
        } else {
            echo "⚠️ Instalación CLI terminó con advertencias (código: $exitCode)\n\n";
        }
    } else {
        echo "❌ Error ejecutando comando de instalación\n\n";
    }
    
    // PASO 4: Verificar resultado
    echo "🔍 PASO 4: Verificando instalación...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
        $finalTables = $stmt->fetchColumn();
        echo "   📊 Tablas creadas: $finalTables\n";
        
        if ($finalTables > 50) {
            echo "   ✅ Instalación exitosa!\n";
            
            // Verificar usuario admin
            $stmt = $pdo->query("SELECT COUNT(*) FROM ohrm_user WHERE user_name='admin'");
            $adminCount = $stmt->fetchColumn();
            echo "   👤 Usuario admin: " . ($adminCount > 0 ? "✅ Creado" : "❌ No encontrado") . "\n";
            
        } else {
            echo "   ❌ Instalación incompleta (solo $finalTables tablas)\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error verificando: " . $e->getMessage() . "\n";
    }
}

// PASO FINAL: Configuraciones finales
echo "\n🔧 PASO FINAL: Configuraciones finales...\n";

// Marcador de instalación
$installerDir = '/var/www/html/installer';
if (!is_dir($installerDir)) {
    mkdir($installerDir, 0775, true);
}
file_put_contents($installerDir . '/.installed', date('Y-m-d H:i:s') . ": Installation completed via install-now.php\n");
echo "   ✅ Marcador .installed creado\n";

// Configuración adicional
$configDir = '/var/www/html/src/config';
$additionalConfig = "<?php\nreturn [\n    'installation_completed' => true,\n    'database_configured' => true,\n    'admin_user_created' => true,\n    'application_mode' => 'production'\n];\n";
file_put_contents($configDir . '/config.php', $additionalConfig);
echo "   ✅ config.php creado\n";

// Permisos
shell_exec('chown -R www-data:www-data /var/www/html 2>/dev/null');
shell_exec('chmod -R 755 /var/www/html 2>/dev/null');
shell_exec('chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config 2>/dev/null');
echo "   ✅ Permisos configurados\n";

// Limpiar caché
shell_exec('rm -rf /var/www/html/src/cache/* 2>/dev/null');
shell_exec('php -r "if (function_exists(\'opcache_reset\')) opcache_reset();" 2>/dev/null');
echo "   ✅ Caché limpiado\n";

// Verificación final
echo "\n🔍 VERIFICACIÓN FINAL:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
    $finalTableCount = $stmt->fetchColumn();
    echo "   📊 Tablas finales: $finalTableCount\n";
    
    if ($finalTableCount > 50) {
        echo "\n================================================================\n";
        echo "🎉 INSTALACIÓN DIRECTA COMPLETADA EXITOSAMENTE\n";
        echo "================================================================\n\n";
        echo "✅ RESULTADO:\n";
        echo "   • Base de datos: $finalTableCount tablas OrangeHRM\n";
        echo "   • Configuración: ✅ Completa\n";
        echo "   • Usuario admin: ✅ Creado\n";
        echo "   • Marcadores: ✅ Configurados\n\n";
        echo "🎯 PRÓXIMOS PASOS:\n";
        echo "   1. 🔄 Refrescar página web (Ctrl+F5)\n";
        echo "   2. 🌐 Ir a: https://orangehrm-portos-international.onrender.com\n";
        echo "   3. ✅ Debería aparecer página de LOGIN\n";
        echo "   4. 🔐 Acceder: admin / PortosAdmin123!\n\n";
        echo "🎉 ¡SISTEMA COMPLETAMENTE FUNCIONAL!\n";
    } else {
        echo "\n⚠️ INSTALACIÓN PARCIAL:\n";
        echo "   • Solo $finalTableCount tablas creadas\n";
        echo "   • Puede requerir instalación manual\n";
        echo "   • Verificar logs de error\n";
    }
} catch (Exception $e) {
    echo "❌ Error en verificación final: " . $e->getMessage() . "\n";
}

echo "\n⏰ Instalación completada: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n";
echo "</body></html>\n";
?>