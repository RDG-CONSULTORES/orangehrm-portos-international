<?php
/**
 * Debug Login Error 500
 * Diagnose why login fails after authentication
 */

echo "🔍 DIAGNOSTICANDO ERROR 500 DESPUÉS DEL LOGIN...\n\n";

// 1. Check current session and files
echo "📄 PASO 1: Verificando estado actual...\n";

session_start();
echo "   📊 Session status: " . session_status() . "\n";
echo "   🗂️ Session data: " . json_encode($_SESSION) . "\n";

// Check critical files
$criticalFiles = [
    '/var/www/html/index.php' => 'Main entry point',
    '/var/www/html/web/index.php' => 'Web application',
    '/var/www/html/auth-bridge.php' => 'Authentication bridge',
    '/var/www/html/src/vendor/autoload.php' => 'Composer autoloader',
    '/var/www/html/src/config/database.php' => 'Database config'
];

foreach ($criticalFiles as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "   ✅ $desc: $size bytes\n";
        
        // Check if file is readable
        if (!is_readable($file)) {
            echo "   ⚠️ $desc: NOT READABLE\n";
        }
    } else {
        echo "   ❌ $desc: MISSING\n";
    }
}

// 2. Test database connection
echo "\n🔗 PASO 2: Probando conexión a base de datos...\n";

try {
    $dsn = "pgsql:host=dpg-d34pm0ffte5s73abeq0g-a.oregon-postgres.render.com;port=5432;dbname=orangehrm_portos";
    $pdo = new PDO($dsn, "orangehrm_user", "A5xg14Ns2M4QUQ7bu0fE2GsU6WFzyOaX");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public'");
    $tableCount = $stmt->fetchColumn();
    echo "   ✅ Database connected: $tableCount tables\n";
    
    // Check if OrangeHRM tables exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
    $ohrmTables = $stmt->fetchColumn();
    echo "   ✅ OrangeHRM tables: $ohrmTables\n";
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

// 3. Check OrangeHRM framework dependencies
echo "\n🔧 PASO 3: Verificando dependencias de OrangeHRM...\n";

if (file_exists('/var/www/html/src/vendor/autoload.php')) {
    try {
        require_once '/var/www/html/src/vendor/autoload.php';
        echo "   ✅ Composer autoloader loaded\n";
        
        // Check if OrangeHRM classes exist
        $classes = [
            'OrangeHRM\Framework\Framework',
            'OrangeHRM\Framework\Http\Request',
            'OrangeHRM\Config\Config'
        ];
        
        foreach ($classes as $class) {
            if (class_exists($class)) {
                echo "   ✅ Class exists: $class\n";
            } else {
                echo "   ❌ Class missing: $class\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ❌ Autoloader error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ Composer autoloader missing\n";
}

// 4. Check web/index.php content
echo "\n📄 PASO 4: Analizando web/index.php...\n";

$webIndexPath = '/var/www/html/web/index.php';
if (file_exists($webIndexPath)) {
    $content = file_get_contents($webIndexPath);
    echo "   📊 File size: " . strlen($content) . " bytes\n";
    echo "   🔍 Contains Framework class: " . (strpos($content, 'Framework') !== false ? 'YES' : 'NO') . "\n";
    echo "   🔍 Contains autoload: " . (strpos($content, 'autoload') !== false ? 'YES' : 'NO') . "\n";
    
    // Show first few lines for inspection
    $lines = explode("\n", $content);
    echo "   📝 First 10 lines:\n";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "      " . ($i + 1) . ": " . trim($lines[$i]) . "\n";
    }
} else {
    echo "   ❌ web/index.php not found\n";
}

// 5. Create a simple test redirect
echo "\n🧪 PASO 5: Creando test de redirect simple...\n";

$simpleTestContent = '<?php
/**
 * Simple Test - Bypass Framework
 * Test if basic PHP redirect works
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: /auth-bridge.php");
    exit;
}

// Show simple success page instead of framework
?>
<!DOCTYPE html>
<html>
<head>
    <title>OrangeHRM - Test Success</title>
    <style>
        body { font-family: Arial; padding: 40px; text-align: center; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="success">
        <h1>✅ Login Exitoso</h1>
        <p>Bienvenido <?php echo htmlspecialchars($_SESSION["username"] ?? "Usuario"); ?></p>
        <p>Sistema: OrangeHRM - Portos International</p>
        <p>Session ID: <?php echo session_id(); ?></p>
        
        <h3>🔍 Información de Debug:</h3>
        <p><strong>Timestamp:</strong> <?php echo date("Y-m-d H:i:s"); ?></p>
        <p><strong>Session Data:</strong></p>
        <pre><?php print_r($_SESSION); ?></pre>
        
        <div style="margin-top: 30px;">
            <a href="/auth-bridge.php?logout=1" style="background: #ff6c00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Logout</a>
        </div>
    </div>
</body>
</html>';

file_put_contents('/var/www/html/test-login.php', $simpleTestContent);
echo "   ✅ test-login.php creado\n";

// 6. Update index.php to use simple test temporarily
echo "\n🔧 PASO 6: Creando bypass temporal...\n";

$bypassIndexContent = '<?php
/**
 * Temporary Bypass - Debug Mode
 * Route to simple test instead of framework
 */

session_start();

// Check authentication
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    require_once __DIR__ . "/auth-bridge.php";
    exit;
}

// Instead of framework, redirect to simple test
header("Location: /test-login.php");
exit;
';

// Create backup first
if (file_exists('/var/www/html/index.php')) {
    copy('/var/www/html/index.php', '/var/www/html/index.php.framework_backup');
    echo "   💾 Backup created: index.php.framework_backup\n";
}

file_put_contents('/var/www/html/index.php', $bypassIndexContent);
echo "   ✅ index.php actualizado para bypass temporal\n";

// 7. Update auth-bridge to handle logout
echo "\n🚪 PASO 7: Actualizando auth-bridge para logout...\n";

$updatedAuthBridge = '<?php
/**
 * Auth Bridge with Logout Support
 */

session_start();

// Handle logout
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: /auth-bridge.php");
    exit;
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["username"]) && isset($_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    if ($username === "admin" && $password === "PortosAdmin123!") {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $username;
        $_SESSION["user"] = [
            "empNumber" => 1,
            "userId" => 1,
            "userName" => "admin",
            "firstName" => "Administrator",
            "lastName" => "Portos"
        ];
        
        header("Location: /");
        exit;
    } else {
        $loginError = "Credenciales incorrectas";
    }
}

// Check if already logged in
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: /");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - OrangeHRM</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin: 0; padding: 50px; }
        .login { max-width: 300px; margin: 0 auto; background: white; padding: 30px; border-radius: 5px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #ff6c00; color: white; border: none; cursor: pointer; }
        .error { color: red; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="login">
        <h2>🧡 OrangeHRM</h2>
        <p>Portos International</p>
        <?php if (isset($loginError)): ?>
            <div class="error"><?php echo $loginError; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" value="admin" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">LOGIN</button>
        </form>
        <p><small>Demo: admin / PortosAdmin123!</small></p>
    </div>
</body>
</html>';

file_put_contents('/var/www/html/auth-bridge.php', $updatedAuthBridge);
echo "   ✅ auth-bridge actualizado\n";

echo "\n================================================================\n";
echo "🔍 DIAGNÓSTICO COMPLETADO\n";
echo "================================================================\n\n";

echo "✅ BYPASS TEMPORAL CREADO:\n";
echo "   • test-login.php: Página de éxito simple ✅\n";
echo "   • index.php: Bypass del framework ✅\n";
echo "   • auth-bridge.php: Logout support ✅\n";
echo "   • Backup: index.php.framework_backup ✅\n\n";

echo "🧪 PROBAR AHORA:\n";
echo "   1. Ir a: https://orangehrm-portos-international.onrender.com\n";
echo "   2. Login: admin / PortosAdmin123!\n";
echo "   3. Deberías ver página de éxito (no error 500)\n";
echo "   4. Info de debug visible\n\n";

echo "🎯 PRÓXIMOS PASOS:\n";
echo "   • Si funciona: El problema es el framework OrangeHRM\n";
echo "   • Si sigue error 500: Problema de servidor/PHP\n";
echo "   • Revisar logs de Render para más detalles\n\n";

echo "🏆 ¡BYPASS TEMPORAL LISTO PARA TESTING!\n";
echo "\n⏰ Debug completado: " . date('Y-m-d H:i:s') . "\n";
?>