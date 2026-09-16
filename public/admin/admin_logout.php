<?php
require_once __DIR__ . '/../../config/config.php';

// Hapus sesi admin
unset($_SESSION['admin_id']);

// Arahkan kembali ke halaman login
header('Location: admin_login.php');
exit;