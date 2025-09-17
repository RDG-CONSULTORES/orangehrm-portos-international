<?php
/**
 * Instalador manual para OrangeHRM Portos International
 * Ejecutar directamente en browser: /public/manual-install.php
 */

// Intentar cargar configuración de entorno
$envConfigPath = '/var/www/html/src/config/env.php';
if (file_exists($envConfigPath)) {
    require_once $envConfigPath;
    $DATABASE_URL = getDatabaseUrl();
} else {
    // Variables de configuración - intentar múltiples fuentes
    $DATABASE_URL = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? '';
    
    // Si aún no está disponible, intentar leer desde configuración del sistema
    if (empty($DATABASE_URL)) {
        // Intentar ejecutar un comando para obtener la variable
        $DATABASE_URL = trim(shell_exec('echo $DATABASE_URL 2>/dev/null') ?? '');
    }
}

if (empty($DATABASE_URL)) {
    // Mostrar información de diagnóstico
    echo "<!DOCTYPE html>\n";
    echo "<html><head><meta charset='utf-8'><title>Diagnóstico Manual Installer</title></head><body>\n";
    echo "<h1>🔍 Diagnóstico - Variables de Entorno</h1>\n";
    echo "<pre>\n";
    echo "❌ ERROR: DATABASE_URL no encontrada en variables de entorno\n\n";
    echo "🔍 DIAGNÓSTICO:\n";
    echo "• \$_ENV['DATABASE_URL']: " . (isset($_ENV['DATABASE_URL']) ? "✅ Encontrada" : "❌ No encontrada") . "\n";
    echo "• \$_SERVER['DATABASE_URL']: " . (isset($_SERVER['DATABASE_URL']) ? "✅ Encontrada" : "❌ No encontrada") . "\n";
    echo "• getenv('DATABASE_URL'): " . (getenv('DATABASE_URL') ? "✅ Encontrada" : "❌ No encontrada") . "\n";
    echo "• shell_exec: " . (shell_exec('echo $DATABASE_URL 2>/dev/null') ? "✅ Disponible" : "❌ No disponible") . "\n\n";
    echo "🛠️ POSIBLES SOLUCIONES:\n";
    echo "1. Verificar que Apache esté configurado para pasar variables de entorno\n";
    echo "2. Reiniciar el contenedor/aplicación\n";
    echo "3. Verificar configuración de Render\n\n";
    echo "🔧 CONFIGURACIÓN NECESARIA EN APACHE:\n";
    echo "   PassEnv DATABASE_URL\n";
    echo "   O bien:\n";
    echo "   SetEnv DATABASE_URL valor\n\n";
    echo "🌐 Volver a: https://orangehrm-portos-international.onrender.com\n";
    echo "</pre>\n";
    echo "</body></html>\n";
    exit;
}

// Parsear DATABASE_URL
preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/(.+)/', $DATABASE_URL, $matches);
if (count($matches) !== 6) {
    die('❌ ERROR: DATABASE_URL tiene formato inválido');
}

$dbConfig = [
    'user' => $matches[1],
    'password' => $matches[2], 
    'host' => $matches[3],
    'port' => $matches[4],
    'dbname' => $matches[5]
];

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='utf-8'><title>Instalador Manual Portos International</title></head><body>\n";
echo "<h1>🚀 Instalador Manual - Portos International</h1>\n";
echo "<pre>\n";

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
    echo "✅ Conexión PostgreSQL exitosa\n";
} catch (PDOException $e) {
    die("❌ Error conectando a PostgreSQL: " . $e->getMessage());
}

// Verificar si OrangeHRM ya está instalado
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
    $tableCount = $stmt->fetchColumn();
    
    if ($tableCount > 0) {
        echo "⚠️ OrangeHRM ya está instalado ($tableCount tablas encontradas)\n";
        echo "🔧 Verificando configuración...\n";
        
        // Verificar usuario admin
        $stmt = $pdo->query("SELECT COUNT(*) FROM ohrm_user WHERE user_name='admin'");
        $adminExists = $stmt->fetchColumn();
        
        if ($adminExists > 0) {
            echo "✅ Usuario admin existe\n";
            
            // Crear archivo .installed para ocultar wizard
            $installedPath = '/var/www/html/installer/.installed';
            if (!file_exists(dirname($installedPath))) {
                mkdir(dirname($installedPath), 0775, true);
            }
            file_put_contents($installedPath, date('Y-m-d H:i:s') . ": Installation completed via manual installer\n");
            
            echo "✅ Marcador de instalación creado\n";
            echo "\n";
            echo "🎉 SISTEMA YA ESTÁ INSTALADO Y FUNCIONAL\n";
            echo "🔄 ACCIÓN: Ir a la página principal -> https://orangehrm-portos-international.onrender.com\n";
            echo "👤 Usuario: admin\n";
            echo "🔑 Contraseña: PortosAdmin123!\n";
            
        } else {
            echo "❌ Usuario admin no existe - instalación incompleta\n";
        }
    } else {
        echo "❌ OrangeHRM no está instalado\n";
        echo "🔧 Iniciando instalación automática...\n";
        
        // Aquí ejecutaríamos la instalación, pero por seguridad solo mostraremos instrucciones
        echo "\n";
        echo "📋 PASOS PARA INSTALACIÓN MANUAL:\n";
        echo "1. Acceder al wizard: https://orangehrm-portos-international.onrender.com/installer\n";
        echo "2. Seleccionar 'Fresh Installation'\n";
        echo "3. Configurar base de datos:\n";
        echo "   - Database Type: PostgreSQL\n";
        echo "   - Host: {$dbConfig['host']}\n";
        echo "   - Port: {$dbConfig['port']}\n";
        echo "   - Database Name: {$dbConfig['dbname']}\n";
        echo "   - Username: {$dbConfig['user']}\n";
        echo "   - Password: [usar contraseña de DATABASE_URL]\n";
        echo "4. Configurar admin:\n";
        echo "   - Username: admin\n";
        echo "   - Password: PortosAdmin123!\n";
        echo "   - Email: admin@portosinternational.com\n";
        echo "5. Configurar organización:\n";
        echo "   - Organization Name: Portos International\n";
        echo "   - Country: Mexico\n";
        echo "   - Timezone: America/Mexico_City\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error verificando instalación: " . $e->getMessage() . "\n";
}

// Verificar archivos de configuración
echo "\n🔍 VERIFICACIÓN DE ARCHIVOS:\n";

$configPath = '/var/www/html/src/config/database.php';
if (file_exists($configPath)) {
    echo "✅ $configPath existe\n";
} else {
    echo "❌ $configPath NO existe\n";
    echo "🔧 Creando configuración básica...\n";
    
    if (!file_exists(dirname($configPath))) {
        mkdir(dirname($configPath), 0775, true);
    }
    
    $configContent = "<?php\nreturn [\n    'database' => [\n        'driver' => 'pdo_pgsql',\n        'host' => '{$dbConfig['host']}',\n        'port' => {$dbConfig['port']},\n        'dbname' => '{$dbConfig['dbname']}',\n        'username' => '{$dbConfig['user']}',\n        'password' => '{$dbConfig['password']}',\n        'charset' => 'utf8'\n    ]\n];\n";
    
    if (file_put_contents($configPath, $configContent)) {
        echo "✅ Configuración de base de datos creada\n";
    } else {
        echo "❌ No se pudo crear configuración de base de datos\n";
    }
}

echo "\n";
echo "⏰ Diagnóstico completado: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>\n";
echo "</body></html>\n";
?>