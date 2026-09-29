<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();
$error = '';
$success = '';

// Memastikan key database dasar ada
$pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('live_title', 'PEMILIHAN KETUA OSIS'),
    ('vote_title', 'PILIH CALON KETUA OSIS'),
    ('vote_mode', 'hybrid'),
    ('logo1', '/admin/images/logo1.png'),
    ('logo2', '/admin/images/logo2.png'),
    ('logo3', ''),
    ('show_logo3', '0')
");

// Proses Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        
        // 1. Update Judul Live (Videotron)
        if (isset($_POST['live_title'])) {
            $live_title = trim($_POST['live_title']);
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'live_title'")->execute([$live_title]);
        }
        
        // 2. Update Judul Halaman Vote
        if (isset($_POST['vote_title'])) {
            $vote_title = trim($_POST['vote_title']);
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vote_title'")->execute([$vote_title]);
        }
        
        // 3. Update Mode Pemilih (Hybrid vs Student Only)
        if (isset($_POST['vote_mode'])) {
            $vote_mode = trim($_POST['vote_mode']);
            if (in_array($vote_mode, ['student_only', 'hybrid'])) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vote_mode'")->execute([$vote_mode]);
            }
        }

        // 4. Update Opsi Tampil Logo 3
        $show_logo3 = isset($_POST['show_logo3']) ? '1' : '0';
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'show_logo3'")->execute([$show_logo3]);
        
        // 5. Handle Upload Logo 1, Logo 2, & Logo 3
        $upload_dir = __DIR__ . '/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach (['logo1', 'logo2', 'logo3'] as $logo_key) {
            if (isset($_FILES[$logo_key]) && $_FILES[$logo_key]['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES[$logo_key]['tmp_name'];
                $file_ext = strtolower(pathinfo($_FILES[$logo_key]['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($file_ext, $allowed)) {
                    throw new Exception("Format file untuk $logo_key tidak valid. Gunakan JPG, PNG, atau WEBP.");
                }
                
                $new_filename = $logo_key . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $path_db = '/admin/images/' . $new_filename;
                    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$path_db, $logo_key]);
                }
            }
        }
        
        $success = 'Pengaturan sistem berhasil diperbarui.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil Nilai Terbaru dari Database
$live_title = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'live_title'")->fetchColumn();
$vote_title = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vote_title'")->fetchColumn();
$vote_mode = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vote_mode'")->fetchColumn();
$logo1 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo1'")->fetchColumn();
$logo2 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo2'")->fetchColumn();
$logo3 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo3'")->fetchColumn();
$show_logo3 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'show_logo3'")->fetchColumn() === '1';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan Sistem & Live</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .header-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav-link {
            padding: 9px 14px; 
            border-radius: 8px; 
            background: #e9eef7; 
            color: #17365f; 
            font-weight: 700; 
            text-decoration: none; 
            font-size: 13px;
            transition: all 0.2s;
        }
        .nav-link:hover { background: #d0daeb; }

        /* Styling Kartu Menu Pengaturan ala Dashboard */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .setting-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 28px rgba(20, 50, 90, .08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .setting-card h3 { margin-top: 0; color: #17365f; font-size: 18px; }
        .setting-card p { color: #667085; font-size: 14px; flex-grow: 1; }
        
        /* Modal Custom Style */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(23, 32, 51, 0.55);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 15px;
            box-sizing: border-box;
        }
        .modal-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 12px 35px rgba(20, 50, 90, 0.15);
            position: relative;
            animation: modalScaleIn 0.25s ease-out;
        }
        .close-btn {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #667085;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
        }
        .close-btn:hover { color: #172033; }
        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body>
    <header style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; padding: 16px 25px; background: #123b73; color: #fff;">
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — PENGATURAN SISTEM</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div class="header-nav">
             <a href="admin.php" class="nav-link">Dashboard</a>
            <form action="admin_logout.php" method="post" style="margin: 0;">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <button class="secondary" style="width: auto; padding: 9px 14px; background: #a51d2d; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">Keluar</button>
            </form>
        </div>
    </header>
    
    <main style="max-width: 950px; margin: 30px auto; padding: 0 15px;">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>

        <div class="card">
            <h2>Pusat Konfigurasi Sistem Pemilihan</h2>
            <p class="muted">Pilih menu di bawah ini untuk mengubah pengaturan teks judul, hak pilih, serta logo instansi melalui jendela modal.</p>
            
            <div class="settings-grid">
                <!-- Card 1: Mode & Judul -->
                <div class="setting-card">
                    <h3>Mode & Judul Halaman</h3>
                    <p>Atur sistem bobot suara (Hybrid / Siswa Murni), ubah judul halaman pencoblosan, serta judul teks videotron live count.</p>
                    <button type="button" onclick="openModal('modalText')" style="margin-top: 15px; padding: 10px; border-radius: 8px; font-weight: bold;">Ubah Konfigurasi Teks</button>
                </div>

                <!-- Card 2: Logo Instansi -->
                <div class="setting-card">
                    <h3>Pengaturan Logo (1, 2 & 3)</h3>
                    <p>Kelola logo kiri, logo kanan, dan logo tengah opsional yang tampil di kartu pemilih serta berita acara rekapitulasi.</p>
                    <button type="button" onclick="openModal('modalLogo')" style="margin-top: 15px; padding: 10px; border-radius: 8px; font-weight: bold; background: #e9eef7; color: #17365f;">Kelola Logo Instansi</button>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL 1: PENGATURAN TEKS & MODE -->
    <div id="modalText" class="modal">
        <div class="modal-content">
            <button type="button" class="close-btn" onclick="closeModal('modalText')">&times;</button>
            <h3 style="color: #17365f; margin-top: 0;">Ubah Mode & Judul Teks</h3>
            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
            
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <div style="margin-bottom: 15px;">
                    <label>Mode Pemilih (Kategori Suara)</label>
                    <select name="vote_mode" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccd4e0; margin-top: 5px; background: #fff;">
                        <option value="hybrid" <?= $vote_mode === 'hybrid' ? 'selected' : '' ?>>Hybrid (Siswa 60% + Guru 40%)</option>
                        <option value="student_only" <?= $vote_mode === 'student_only' ? 'selected' : '' ?>>Hanya Siswa (100% Suara Murni)</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Judul Utama Halaman Voting (Siswa/Guru)</label>
                    <input type="text" name="vote_title" value="<?= h($vote_title) ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccd4e0; margin-top: 5px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label>Judul Teks Halaman Live (Videotron)</label>
                    <input type="text" name="live_title" value="<?= h($live_title) ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccd4e0; margin-top: 5px;">
                </div>

                <button type="submit" style="width: 100%; padding: 10px; font-weight: bold; border-radius: 8px;">Simpan Perubahan Teks</button>
            </form>
        </div>
    </div>

    <!-- MODAL 2: PENGATURAN LOGO -->
    <div id="modalLogo" class="modal">
        <div class="modal-content">
            <button type="button" class="close-btn" onclick="closeModal('modalLogo')">&times;</button>
            <h3 style="color: #17365f; margin-top: 0;">Kelola Logo Instansi</h3>
            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <div style="margin-bottom: 15px;">
                    <label>Logo Kiri (Logo 1)</label>
                    <?php if ($logo1): ?>
                        <div style="margin: 5px 0;"><img src="..<?= h($logo1) ?>" style="height: 40px; background: #f0f3f8; padding: 3px; border-radius: 4px;" alt="Logo 1"></div>
                    <?php endif; ?>
                    <input type="file" name="logo1" accept="image/*" style="padding: 6px; width: 100%; border: 1px solid #ccd4e0; border-radius: 8px; margin-top: 3px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Logo Kanan (Logo 2)</label>
                    <?php if ($logo2): ?>
                        <div style="margin: 5px 0;"><img src="..<?= h($logo2) ?>" style="height: 40px; background: #f0f3f8; padding: 3px; border-radius: 4px;" alt="Logo 2"></div>
                    <?php endif; ?>
                    <input type="file" name="logo2" accept="image/*" style="padding: 6px; width: 100%; border: 1px solid #ccd4e0; border-radius: 8px; margin-top: 3px;">
                </div>

                <div style="margin-bottom: 15px; border-top: 1px dashed #ccd4e0; padding-top: 15px;">
                    <label>Logo Tengah / Tambahan (Opsional)</label>
                    <?php if ($logo3): ?>
                        <div style="margin: 5px 0;"><img src="..<?= h($logo3) ?>" style="height: 40px; background: #f0f3f8; padding: 3px; border-radius: 4px;" alt="Logo 3"></div>
                    <?php endif; ?>
                    <input type="file" name="logo3" accept="image/*" style="padding: 6px; width: 100%; border: 1px solid #ccd4e0; border-radius: 8px; margin-top: 3px;">
                    
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="show_logo3" name="show_logo3" value="1" <?= $show_logo3 ? 'checked' : '' ?> style="width: auto; margin: 0;">
                        <label for="show_logo3" style="margin: 0; font-weight: normal; cursor: pointer; font-size: 13px;">Tampilkan Logo Tengah di Kartu & Berita Acara</label>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 10px; font-weight: bold; border-radius: 8px;">Unggah & Simpan Logo</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        // Menutup modal jika area di luar kotak putih diklik
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>