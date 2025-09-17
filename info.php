<?php
/**
 * Información básica del sistema
 * Archivo en la raíz para verificar estado
 */

// Habilitar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Información básica
$info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
];

// Verificar archivos
$files = [
    'index.php' => file_exists('index.php'),
    'web/index.php' => file_exists('web/index.php'),
    'src/config/database.php' => file_exists('src/config/database.php'),
    'installer/.installed' => file_exists('installer/.installed')
];

// Verificar base de datos
$db_status = 'Unknown';
if (file_exists('src/config/database.php')) {
    try {
        $config = include 'src/config/database.php';
        if (isset($config['database'])) {
            $db = $config['database'];
            $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['dbname']}";
            $pdo = new PDO($dsn, $db['username'], $db['password']);
            $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%'");
            $tableCount = $stmt->fetchColumn();
            $db_status = "✅ Connected - $tableCount tables";
        }
    } catch (Exception $e) {
        $db_status = "❌ Error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>OrangeHRM - System Info</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #ff6600; }
        table { border-collapse: collapse; }
        td { padding: 8px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>🔍 OrangeHRM System Information</h1>
    
    <h2>System Status</h2>
    <table>
        <?php foreach ($info as $key => $value): ?>
        <tr>
            <td><strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong></td>
            <td><?php echo htmlspecialchars($value); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>File Check</h2>
    <table>
        <?php foreach ($files as $file => $exists): ?>
        <tr>
            <td><?php echo $file; ?></td>
            <td class="<?php echo $exists ? 'success' : 'error'; ?>">
                <?php echo $exists ? '✅ Exists' : '❌ Not found'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>Database Status</h2>
    <p><?php echo $db_status; ?></p>
    
    <h2>Quick Links</h2>
    <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/web/">Web Directory</a></li>
        <li><a href="/public/">Public Directory</a></li>
        <li><a href="/phpinfo.php">PHP Info</a></li>
    </ul>
    
    <h2>Login Credentials</h2>
    <p>👤 Username: <strong>admin</strong></p>
    <p>🔑 Password: <strong>PortosAdmin123!</strong></p>
    
    <hr>
    <p><em>Portos International - OrangeHRM System</em></p>
</body>
</html>
<?php