<?php 
require_once __DIR__.'/../config/config.php'; 
require_student(); 
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berhasil - <?= h(APP_NAME) ?></title>
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
        .success-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 1rem;
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-between text-dark">

    <!-- Header / Nav -->
    <header class="w-100 py-4 px-4 px-md-5 d-flex align-items-center bg-white bg-opacity-75 shadow-sm border-bottom border-secondary-subtle">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-box-archive fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark tracking-wide"><?= h(APP_NAME) ?></h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.05em;">E-Voting Ketua OSIS</small>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow-1 d-flex align-items-center justify-content-center px-3 py-5">
        <div class="w-100" style="max-width: 480px;">
            
            <div class="success-card p-4 p-md-5 shadow-sm text-center position-relative overflow-hidden">
                <div class="d-inline-flex p-3 rounded-4 bg-success bg-opacity-10 text-success mb-3 border border-success border-opacity-25">
                    <i class="fa-solid fa-circle-check fs-1"></i>
                </div>

                <h2 class="h4 fw-bold text-dark mb-2">Suara Berhasil Direkam</h2>
                <p class="text-muted small mb-3">Terima kasih telah menggunakan hak suara Anda dengan bijak.</p>

                <div class="mb-4">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 fw-semibold" style="letter-spacing: 0.05em;">SUDAH MEMILIH</span>
                </div>

                <div class="bg-light border border-secondary-subtle rounded-3 p-3 mb-4 text-start">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-warning mt-1"></i>
                        <p class="text-muted small mb-0">Silakan klik tombol keluar di bawah dan berikan perangkat kepada pemilih berikutnya.</p>
                    </div>
                </div>

                <form action="logout.php" method="post" class="m-0">
                    <button type="submit" class="btn w-100 py-2.5 fw-semibold shadow-sm text-white d-flex align-items-center justify-content-center gap-2" style="background-color: #4f46e5;">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Keluar Sesi</span>
                    </button>
                </form>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-100 py-4 text-center text-muted small border-top border-secondary-subtle bg-white bg-opacity-50">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>