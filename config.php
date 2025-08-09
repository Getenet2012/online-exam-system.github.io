<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecwc_exam_system');

// Application configuration
define('APP_NAME', 'Ethiopian Construction Works Corporation Exam System');
define('APP_URL', '');
define('APP_ROOT', dirname(__FILE__));

// Security configuration
//define('SECRET_KEY', 'your-secret-key-here');
//define('MFA_ISSUER', 'Ethiopian Construction Works Corporation Exam System');

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session configuration
session_start();

// Timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Include functions
require_once 'functions.php';
?>