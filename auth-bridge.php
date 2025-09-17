<?php
/**
 * Authentication Bridge for OrangeHRM
 * Handles login and creates proper OrangeHRM session
 */

session_start();

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["username"]) && isset($_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    // Check Portos International credentials
    if ($username === "admin" && $password === "PortosAdmin123!") {
        
        // Create OrangeHRM-compatible session
        $_SESSION["user"] = [
            "empNumber" => 1,
            "userId" => 1,
            "userName" => "admin",
            "firstName" => "Administrator",
            "lastName" => "Portos",
            "employeeId" => "ADMIN001",
            "userRole" => "Admin",
            "userRoleId" => 1,
            "permissions" => ["admin"]
        ];
        
        $_SESSION["logged_in"] = true;
        $_SESSION["orangehrm_user"] = $_SESSION["user"];
        $_SESSION["valid"] = true;
        $_SESSION["company"] = "Portos International";
        $_SESSION["username"] = $username;
        
        // Redirect to OrangeHRM dashboard
        header("Location: /web/index.php");
        exit;
    } else {
        $loginError = "Credenciales incorrectas. Use: admin / PortosAdmin123!";
    }
}

// Check if already logged in with OrangeHRM session
if (isset($_SESSION["orangehrm_user"]) && isset($_SESSION["valid"]) && $_SESSION["valid"]) {
    // Redirect to OrangeHRM dashboard
    header("Location: /web/index.php");
    exit;
}

// Show custom login page if not authenticated
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OrangeHRM Portos International</title>
    <style>
        /* Minimal styles that match OrangeHRM theme */
        body {
            font-family: "Lucida Grande", "Lucida Sans Unicode", Tahoma, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .login-container {
            background: white;
            border: 1px solid #d3d3d3;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #ff6c00;
            font-size: 24px;
            margin: 0;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #666;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #d3d3d3;
            border-radius: 3px;
            font-size: 13px;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #ff6c00;
        }
        
        .login-btn {
            width: 100%;
            background: #ff6c00;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 3px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .login-btn:hover {
            background: #e55a00;
        }
        
        .error {
            background: #ffebe8;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .company-info {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>OrangeHRM</h1>
            <p>Portos International</p>
        </div>
        
        <?php if (isset($loginError)): ?>
            <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo isset($_POST["username"]) ? htmlspecialchars($_POST["username"]) : "admin"; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">LOGIN</button>
        </form>
        
        <div class="company-info">
            <p><strong>Demo Credentials:</strong></p>
            <p>Username: admin | Password: PortosAdmin123!</p>
            <p>Freight Forwarding Company • México</p>
        </div>
    </div>
</body>
</html>