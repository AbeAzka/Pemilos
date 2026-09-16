<?php
require_once __DIR__ . '/../config/config.php';

// 1. Kosongkan semua data dalam array sesi
$_SESSION = [];

// 2. Jika sesi menggunakan cookie, hapus cookie sesi di browser pengguna
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    
    setcookie(
        session_name(),
        '',
        time() - 42000, // Menggunakan time() - 42000 lebih disarankan daripada string '-42000'
        $p["path"],
        $p["domain"],
        $p["secure"],
        $p["httponly"]
    );
}

// 3. Hancurkan sesi di sisi server
session_destroy();

// 4. Arahkan pengguna kembali ke halaman login
header('Location: login.php');
exit;