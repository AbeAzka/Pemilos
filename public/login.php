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
    <title>Login Pemilih - <?= h(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 1rem;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.15);
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-between text-dark">

    <!-- Header / Nav -->
    <header class="w-100 py-4 px-4 px-md-5 d-flex align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-indigo text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-box-archive fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark tracking-wide"><?= h(APP_NAME) ?></h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Sistem Voting Digital by Indodev Studio</small>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow-1 d-flex align-items-center justify-content-center px-3 py-4">
        <div class="w-100" style="max-width: 420px;">
            
            <div class="login-card p-4 p-md-5 shadow-sm position-relative overflow-hidden">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-4 bg-indigo bg-opacity-10 text-indigo mb-3" style="background-color: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                        <i class="fa-solid fa-user-lock fs-3"></i>
                    </div>
                    <h2 class="h4 fw-bold text-dark">Login Pemilih</h2>
                    <p class="text-muted small mb-0">Masukkan akun yang diberikan panitia untuk mulai memilih.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger py-2 px-3 rounded-3 small d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="fa-solid fa-circle-exclamation fs-6 flex-shrink-0"></i>
                        <span><?= h($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-secondary-subtle text-muted"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control border-secondary-subtle ps-2" required autocomplete="username" placeholder="Contoh: 12345 / nama">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-secondary-subtle text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="passwordInput" class="form-control border-secondary-subtle border-end-0 ps-2" required autocomplete="current-password" placeholder="••••••••">
                            <button type="button" onclick="togglePassword()" class="btn btn-light border border-secondary-subtle text-muted px-3" id="toggleBtn">
                                <i id="toggleIcon" class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn w-100 py-2.5 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2 text-white" style="background-color: #4f46e5;">
                        <span>Masuk ke E-Voting</span>
                        <i class="fa-solid fa-arrow-right-to-bracket small"></i>
                    </button>
                </form>

                <div class="mt-4 pt-4 border-top border-secondary-subtle text-center">
                    <a href="admin/admin_login.php" class="text-decoration-none text-muted small d-inline-flex align-items-center gap-2 transition hover-text-dark">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Login Panitia / Admin</span>
                    </a>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-100 py-4 text-center text-muted small">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script Toggle Password -->
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