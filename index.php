<?php
/**
 * OrangeHRM Main Entry Point - Portos International
 * Bypasses installer and goes directly to functional system
 */

// Force installation to be considered complete
// This is the main entry point that was redirecting to the installer

// Set proper working directory
chdir(__DIR__);

// Ensure proper server variables
if (!isset($_SERVER["REQUEST_URI"])) {
    $_SERVER["REQUEST_URI"] = "/";
}

if (!isset($_SERVER["HTTP_HOST"])) {
    $_SERVER["HTTP_HOST"] = $_SERVER["SERVER_NAME"] ?? "localhost";
}

// Check if this is a direct file request
$requestUri = $_SERVER["REQUEST_URI"];
$filePath = __DIR__ . $requestUri;

// If requesting a specific PHP file that exists, include it
if (preg_match("/\.php$/", $requestUri) && file_exists($filePath) && $requestUri !== "/index.php") {
    require_once $filePath;
    exit;
}

// For all other requests, route to web application
if (file_exists(__DIR__ . "/web/index.php")) {
    require_once __DIR__ . "/web/index.php";
} else {
    // Fallback
    header("Content-Type: text/html; charset=UTF-8");
    echo "<!DOCTYPE html>";
    echo "<html><head><title>OrangeHRM - Error</title></head>";
    echo "<body style=\"font-family: Arial; padding: 50px; text-align: center;\">";
    echo "<h1 style=\"color: #ff6600;\">❌ Error</h1>";
    echo "<p>Web application not found.</p>";
    echo "</body></html>";
}
