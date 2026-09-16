<?php
require_once __DIR__ . '/../../config/config.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = db()->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        
        header('Location: admin.php');
        exit;
    }
    
    $error = 'Login panitia tidak valid.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Panitia</title>
    <link rel="stylesheet" href="../style.css">
    <!-- Font Awesome CDN untuk ikon mata -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
		<div>
			<div class="brand"><?= h(APP_NAME) ?> — PANITIA</div>
			<div class="sub">E-Voting Ketua OSIS</div>
		</div>
    </header>
    
    <main>
        <section class="card login">
            <h2 class="title">Login Panitia</h2>
            
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <label>Username</label>
                <input name="username" required autocomplete="username">
                
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" required autocomplete="current-password">
                    <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                        <i id="toggleIcon" class="fas fa-eye"></i>
                    </button>
                </div>
                
                <button type="submit" style="margin-top: 15px;">Masuk</button>
            </form>
        </section>
    </main>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>