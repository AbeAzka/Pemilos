<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();$message = '';

// Inisialisasi default jika key belum ada di database
$defaults = [
    'election_open' => '0',
    'live_title' => 'PEMILIHAN CALON KETUA',
    'logo_layout' => '2',
    'logo1' => '/admin/images/logo1.png',
    'logo2' => '/admin/images/logo2.png',
    'logo3' => '/admin/images/logo3.png',
    'print_card' => '0',
    'show_logo3' => '0',
    'student_weight' => '60',
    'teacher_weight' => '40',
    'vote_mode' => 'hybrid',
    'vote_title' => 'Pilih Calon Ketua',
    'weight_student' => '60',
    'weight_teacher' => '40'
];

foreach ($defaults as$key => $val) {$pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$key', '$val')");
}

// Proses Form Submit & Upload File
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    // 1. Simpan teks pengaturan biasa
    foreach ($_POST as $key =>$value) {
        if ($key !== 'csrf' && !str_ends_with($key, '_old')) {
            $stmt =$pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([trim($value),$key]);
        }
    }
    
    // Sinkronisasi bobot kembar
    if (isset($_POST['student_weight'])) {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'weight_student'")->execute([$_POST['student_weight']]);
    }
    if (isset($_POST['teacher_weight'])) {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'weight_teacher'")->execute([$_POST['teacher_weight']]);
    }

    // 2. Proses Upload File Logo (logo1, logo2, logo3)
    $upload_dir = __DIR__ . '/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $logo_keys = ['logo1', 'logo2', 'logo3'];
    foreach ($logo_keys as$lkey) {
        if (isset($_FILES[$lkey]) &&$_FILES[$lkey]['error'] === UPLOAD_ERR_OK) {$file_tmp = $_FILES[$lkey]['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES[$lkey]['name'], PATHINFO_EXTENSION));$allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {$new_filename = $lkey . '_' . time() . '_' . mt_rand(100, 999) . '.' . $file_ext;
                $destination = $upload_dir .$new_filename;

                if (move_uploaded_file($file_tmp,$destination)) {
                    $db_path = '/admin/images/' .$new_filename;
                    $stmt =$pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$db_path,$lkey]);
                }
            }
        }
    }

    $message = "Pengaturan sistem dan logo berhasil diperbarui!";
}

// Ambil semua pengaturan dari database
$settings = [];
$stmt =$pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {$settings[$row['setting_key']] =$row['setting_value'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan Sistem - <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        .card { background: #fff; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 1rem; }
        .logo-preview { width: 50px; height: 50px; object-fit: contain; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-gear fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — PENGATURAN</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Konfigurasi Sistem</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="admin.php" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">Dashboard</a>
			<form action="admin_logout.php" method="post" class="m-0">
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Keluar</button>
            </form>
        </div>
    </header>

    <main class="flex-grow-1 p-3 p-md-5">
        <div class="w-100 mx-auto" style="max-width: 800px;">
            <?php if ($message): ?>
                <div class="alert alert-success py-2 px-3 small mb-4 shadow-sm"><?= h($message) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <!-- SECTION 1: INFORMASI & JUDUL APLIKASI -->
                <div class="card p-4 p-md-4 shadow-sm mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="fa-solid fa-heading me-2"></i>Judul & Tampilan Utama</h2>
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Judul Pemilih (`vote_title`)</label>
                        <input type="text" name="vote_title" value="<?= h($settings['vote_title'] ?? '') ?>" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Judul Layar Live / Videotron (`live_title`)</label>
                        <input type="text" name="live_title" value="<?= h($settings['live_title'] ?? '') ?>" class="form-control" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Mode Pemilihan (`vote_mode`)</label>
                        <select name="vote_mode" class="form-select" required>
                            <option value="hybrid" <?= ($settings['vote_mode'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid (Siswa & Guru)</option>
                            <option value="online" <?= ($settings['vote_mode'] ?? '') === 'online' ? 'selected' : '' ?>>Online Penuh</option>
                            <option value="offline" <?= ($settings['vote_mode'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline / Bilik Suara</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2: PENGATURAN LOGO & KOP SURAT (DENGAN UPLOAD FILE) -->
                <div class="card p-4 p-md-4 shadow-sm mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="fa-solid fa-image me-2"></i>Logo & Kop Surat</h2>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Layout Logo (`logo_layout`)</label>
                            <select name="logo_layout" class="form-select" required>
                                <option value="0" <?= ($settings['logo_layout'] ?? '') === '0' ? 'selected' : '' ?>>0 - Tanpa Logo</option>
                                <option value="1" <?= ($settings['logo_layout'] ?? '') === '1' ? 'selected' : '' ?>>1 - Logo Tunggal</option>
                                <option value="2" <?= ($settings['logo_layout'] ?? '') === '2' ? 'selected' : '' ?>>2 - Dua Logo (Kiri & Kanan)</option>
                                <option value="3" <?= ($settings['logo_layout'] ?? '') === '3' ? 'selected' : '' ?>>3 - Tiga Logo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Tampilkan Logo 3 (`show_logo3`)</label>
                            <select name="show_logo3" class="form-select">
                                <option value="0" <?= ($settings['show_logo3'] ?? '') === '0' ? 'selected' : '' ?>>0 - Sembunyikan</option>
                                <option value="1" <?= ($settings['show_logo3'] ?? '') === '1' ? 'selected' : '' ?>>1 - Tampilkan</option>
                            </select>
                        </div>
                    </div>

                    <!-- Upload Logo 1 -->
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <?php if (!empty($settings['logo1'])): ?>
                            <img src="..<?= h($settings['logo1']) ?>" alt="Logo 1" class="logo-preview">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Upload Logo 1 (`logo1`)</label>
                            <input type="file" name="logo1" accept="image/*" class="form-control form-control-sm">
                            <input type="hidden" name="logo1_old" value="<?= h($settings['logo1'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Upload Logo 2 -->
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <?php if (!empty($settings['logo2'])): ?>
                            <img src="..<?= h($settings['logo2']) ?>" alt="Logo 2" class="logo-preview">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Upload Logo 2 (`logo2`)</label>
                            <input type="file" name="logo2" accept="image/*" class="form-control form-control-sm">
                            <input type="hidden" name="logo2_old" value="<?= h($settings['logo2'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Upload Logo 3 -->
                    <div class="mb-0 d-flex align-items-center gap-3">
                        <?php if (!empty($settings['logo3'])): ?>
                            <img src="..<?= h($settings['logo3']) ?>" alt="Logo 3" class="logo-preview">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Upload Logo 3 (`logo3`)</label>
                            <input type="file" name="logo3" accept="image/*" class="form-control form-control-sm">
                            <input type="hidden" name="logo3_old" value="<?= h($settings['logo3'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: BOBOT SUARA -->
                <div class="card p-4 p-md-4 shadow-sm mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="fa-solid fa-scale-balanced me-2"></i>Bobot Perhitungan Suara</h2>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Bobot Siswa (%) (`student_weight`)</label>
                            <input type="number" step="0.1" name="student_weight" value="<?= h($settings['student_weight'] ?? '60') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary text-uppercase fw-bold" style="font-size: 0.75rem;">Bobot Guru (%) (`teacher_weight`)</label>
                            <input type="number" step="0.1" name="teacher_weight" value="<?= h($settings['teacher_weight'] ?? '40') ?>" class="form-control" required>
                        </div>
                    </div>
                    <input type="hidden" name="weight_student" value="<?= h($settings['weight_student'] ?? '60') ?>">
                    <input type="hidden" name="weight_teacher" value="<?= h($settings['weight_teacher'] ?? '40') ?>">
                </div>

                <!-- TOMBOL SIMPAN UTAMA -->
                <button type="submit" class="btn w-100 py-3 fw-semibold text-white shadow-sm" style="background-color: #4f46e5;">Simpan Seluruh Perubahan</button>
            </form>
        </div>
    </main>

    <footer class="w-100 py-4 text-center text-muted small border-top bg-white">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>