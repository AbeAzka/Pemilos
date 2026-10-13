<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

$checkPrint = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'print_card'")->fetchColumn();
if ($checkPrint == 0) {
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('print_card', '0')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        $new_status = ($_POST['value'] === '1') ? '0' : '1';
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'election_open'");
        $stmt->execute([$new_status]);
    }
}

$open = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'election_open'")->fetchColumn() === '1';
$student_weight = (float) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'weight_student'")->fetchColumn() ?: 60);
$teacher_weight = (float) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'weight_teacher'")->fetchColumn() ?: 40);

$total = (int) $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$student_total = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'student'")->fetchColumn();
$teacher_total = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'teacher'")->fetchColumn();

$voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE has_voted = 1")->fetchColumn();
$student_voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'student' AND has_voted = 1")->fetchColumn();
$teacher_voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'teacher' AND has_voted = 1")->fetchColumn();

$student_votes = (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type = 'student'")->fetchColumn();
$teacher_votes = (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type = 'teacher'")->fetchColumn();

$results = $pdo->query("
    SELECT c.id, c.candidate_number, c.name, c.photo_url,
    SUM(CASE WHEN v.voter_type = 'student' THEN 1 ELSE 0 END) AS student_n,
    SUM(CASE WHEN v.voter_type = 'teacher' THEN 1 ELSE 0 END) AS teacher_n
    FROM candidates c 
    LEFT JOIN votes v ON v.candidate_id = c.id 
    GROUP BY c.id 
    ORDER BY c.candidate_number
")->fetchAll();

foreach ($results as &$r) {
    $r['student_pct'] = $student_votes ? ($r['student_n'] / $student_votes * 100) : 0;
    $r['teacher_pct'] = $teacher_votes ? ($r['teacher_n'] / $teacher_votes * 100) : 0;
    $r['weighted'] = ($r['student_pct'] * $student_weight / 100) + ($r['teacher_pct'] * $teacher_weight / 100);
}
unset($r);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="5">
    <title>Dashboard Admin - <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        .card { background: #fff; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 1rem; }
        .progress { height: 10px; border-radius: 5px; background-color: #e2e8f0; }
        .progress-bar { background-color: #4f46e5; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <header class="w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-gauge fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — DASHBOARD</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Panitia Pemilihan</small>
            </div>
        </div>
        
        <nav class="d-flex align-items-center gap-2 flex-wrap">
            <a href="admin_add_voter.php" class="btn btn-light btn-sm fw-semibold border">Tambah Pemilih</a>
            <a href="admin_voters.php" class="btn btn-light btn-sm fw-semibold border">Status Pemilih</a>
            <a href="admin_candidates.php" class="btn btn-light btn-sm fw-semibold border">Kelola Paslon</a>
            <a href="admin_cetak.php" class="btn btn-light btn-sm fw-semibold border">Cetak Kartu</a>
            <a href="rekap_hasil.php" class="btn btn-light btn-sm fw-semibold border">Rekap Hasil</a>
            <a href="settings.php" class="btn btn-light btn-sm fw-semibold border text-primary"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <form action="admin_logout.php" method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <button type="submit" class="btn btn-danger btn-sm fw-semibold">Keluar</button>
            </form>
        </nav>
    </header>
    
    <main class="flex-grow-1 p-3 p-md-5">
        <div class="w-100 mx-auto" style="max-width: 1100px;">
            
            <!-- Kontrol Status Pemilihan -->
            <div class="card p-4 mb-4 shadow-sm d-flex flex-row justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h5 fw-bold text-dark mb-1">Status Pemilihan</h2>
                    <span class="badge <?= $open ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 text-<?= $open ? 'success' : 'danger' ?> border border-<?= $open ? 'success' : 'danger' ?> border-opacity-25 px-3 py-2">
                        <i class="fa-solid fa-circle me-1" style="font-size: 6px;"></i> <?= $open ? 'DIBUKA' : 'DITUTUP' ?>
                    </span>
                </div>
                <form method="post" class="m-0">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="value" value="<?= $open ? '1' : '0' ?>">
                    <button type="submit" class="btn <?= $open ? 'btn-danger' : 'btn-primary' ?> px-4 fw-semibold shadow-sm">
                        <?= $open ? 'Tutup Pemilihan' : 'Buka Pemilihan' ?>
                    </button>
                </form>
            </div>

            <!-- Statistik Pemilih -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card p-3 shadow-sm text-center">
                        <small class="text-muted uppercase fw-bold" style="font-size: 0.75rem;">Total Pemilih</small>
                        <span class="fs-4 fw-bold text-dark mt-1"><?= $total ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card p-3 shadow-sm text-center">
                        <small class="text-muted uppercase fw-bold" style="font-size: 0.75rem;">Siswa</small>
                        <span class="fs-4 fw-bold text-dark mt-1"><?= $student_total ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card p-3 shadow-sm text-center">
                        <small class="text-muted uppercase fw-bold" style="font-size: 0.75rem;">Guru</small>
                        <span class="fs-4 fw-bold text-dark mt-1"><?= $teacher_total ?></span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card p-3 shadow-sm text-center">
                        <small class="text-muted uppercase fw-bold" style="font-size: 0.75rem;">Sudah Memilih</small>
                        <span class="fs-4 fw-bold text-success mt-1"><?= $voted ?></span>
                    </div>
                </div>
            </div>

            <!-- Komposisi Suara -->
            <div class="card p-4 shadow-sm mb-4">
                <h2 class="h5 fw-bold text-dark mb-1">Komposisi Suara</h2>
                <p class="text-muted small mb-3">Bobot hasil akhir dihitung berdasarkan persentase: <b>Siswa <?= $student_weight ?>%</b> + <b>Guru <?= $teacher_weight ?>%</b>.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="d-block text-secondary small">Suara Siswa</span>
                            <strong class="fs-4 text-dark"><?= $student_votes ?></strong>
                            <small class="d-text text-muted d-block mt-1"><?= $student_voted ?> pemilih siswa sudah memilih</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="d-block text-secondary small">Suara Guru</span>
                            <strong class="fs-4 text-dark"><?= $teacher_votes ?></strong>
                            <small class="d-text text-muted d-block mt-1"><?= $teacher_voted ?> pemilih guru sudah memilih</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Perolehan Suara Berbobot -->
            <div class="card p-4 shadow-sm mb-4">
                <h2 class="h5 fw-bold text-dark mb-4">Perolehan Suara Berbobot</h2>
                <?php foreach ($results as $r): ?>
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark"><?= sprintf('%02d', $r['candidate_number']) ?> — <?= h($r['name']) ?></span>
                        </div>
                        <div class="row text-muted small mb-2">
                            <div class="col-4">Suara siswa: <strong class="text-dark"><?= (int)$r['student_n'] ?> (<?= number_format($r['student_pct'], 2) ?>%)</strong></div>
                            <div class="col-4">Suara guru: <strong class="text-dark"><?= (int)$r['teacher_n'] ?> (<?= number_format($r['teacher_pct'], 2) ?>%)</strong></div>
                            <div class="col-4 text-end">Hasil akhir: <strong class="text-primary fs-6"><?= number_format($r['weighted'], 2) ?>%</strong></div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= min(100, max(0, $r['weighted'])) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Catatan Privasi & Link Live Result -->
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="card p-4 shadow-sm h-100">
                        <h2 class="h6 fw-bold text-dark mb-2">Catatan Privasi</h2>
                        <p class="text-muted small mb-0">Tabel <code>votes</code> tidak menyimpan ID pemilih. Sistem hanya mencatat kategori pemilih (siswa/guru) untuk perhitungan bobot, sedangkan status keikutsertaan berada terpisah di tabel <code>voters</code>.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 shadow-sm text-center d-flex flex-column justify-content-center align-items-center text-white h-100" style="background: #4f46e5;">
                        <a href="../live.php" target="_blank" class="text-white fw-bold text-decoration-none">Buka Live Result untuk Videotron →</a>
                        <small class="text-white-50 mt-1" style="font-size: 0.75rem;">Tampilan layar besar</small>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="w-100 py-4 text-center text-muted small border-top bg-white">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>