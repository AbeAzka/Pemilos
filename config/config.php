<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_pilketos');
define('DB_USER', 'root');
define('DB_PASS', '');

// Change this in production.
define('APP_NAME', 'PILKETOS SMAN 1 GONDANG');
define('BASE_URL', '');
date_default_timezone_set('Asia/Jakarta');

session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(){
    if(!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) die('Permintaan tidak valid.');
}
function require_student(){ if(empty($_SESSION['student_id'])) { header('Location: login.php'); exit; } }
function require_admin(){ if(empty($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; } }
