<?php
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['student_id'])) {
    header('Location: vote.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = db()->prepare("SELECT * FROM voters WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $voter = $stmt->fetch();
    
    if ($voter && password_verify($password, $voter['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['student_id'] = $voter['id'];
        $_SESSION['student_name'] = $voter['student_name'];
        $_SESSION['class_name'] = $voter['class_name'];
        $_SESSION['voter_type'] = $voter['voter_type'];
        
        header('Location: vote.php');
        exit;
    }
    
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Pemilih</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome CDN untuk ikon mata -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?></div>
            <div class="sub">E-Voting Ketua OSIS</div>
        </div>
    </header>
    
    <main>
        <section class="card login">
            <h2>Login Pemilih</h2>
            <p class="muted">Masukkan akun yang diberikan panitia.</p>
            
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <label>Username</label>
                <input name="username" required autocomplete="username">
                
                <label>Password</label>
                <div style="position: relative; margin: 7px 0 15px;">
                    <input type="password" name="password" id="passwordInput" required autocomplete="current-password" style="margin: 0; padding-right: 45px;">
                    <button type="button" onclick="togglePassword()" style="position: absolute; top: 50%; right: 12px; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; padding: 0; color: #666; width: auto;">
                        <i id="toggleIcon" class="fas fa-eye"></i>
                    </button>
                </div>
                
                <button type="submit" style="margin-top: 5px;">Masuk</button>
            </form>
            
            <p style="text-align: center; margin-top: 18px">
                <a href="admin/admin_login.php">Login Panitia</a>
            </p>
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