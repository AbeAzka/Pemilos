<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// Memastikan pengaturan 'print_card' ada di database (Otomatis dibuat jika belum ada)
$checkPrint = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'print_card'")->fetchColumn();
if ($checkPrint == 0) {
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('print_card', '0')");
}

// Proses aksi form (Toggle Status Pemilihan & Cetak Kartu)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    
    // Toggle Status Pemilihan
    if ($action === 'toggle') {
        $new_status = ($_POST['value'] === '1') ? '0' : '1';
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'election_open'");
        $stmt->execute([$new_status]);
    }
}

// Ambil pengaturan sistem
$open = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'election_open'")->fetchColumn() === '1';
$print_open = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'print_card'")->fetchColumn() === '1';
$student_weight = (float) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'student_weight'")->fetchColumn();
$teacher_weight = (float) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'teacher_weight'")->fetchColumn();

// Statistik Pemilih
$total = (int) $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$student_total = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'student'")->fetchColumn();
$teacher_total = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'teacher'")->fetchColumn();

$voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE has_voted = 1")->fetchColumn();
$student_voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'student' AND has_voted = 1")->fetchColumn();
$teacher_voted = (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type = 'teacher' AND has_voted = 1")->fetchColumn();

$student_votes = (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type = 'student'")->fetchColumn();
$teacher_votes = (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type = 'teacher'")->fetchColumn();

// Perolehan Suara Kandidat
$results = $pdo->query("
    SELECT c.id, c.candidate_number, c.name, c.photo_url,
    SUM(CASE WHEN v.voter_type = 'student' THEN 1 ELSE 0 END) AS student_n,
    SUM(CASE WHEN v.voter_type = 'teacher' THEN 1 ELSE 0 END) AS teacher_n
    FROM candidates c 
    LEFT JOIN votes v ON v.candidate_id = c.id 
    GROUP BY c.id 
    ORDER BY c.candidate_number
")->fetchAll();

// Hitung persentase dan bobot akhir
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
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        .status-badge.open {
            background: #edf9f0;
            color: #176b37;
        }
        .status-badge.closed {
            background: #fff0f0;
            color: #a51d2d;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .open .status-dot { background: #176b37; }
        .closed .status-dot { background: #a51d2d; }
        
        /* Grid responsif untuk area kontrol atas */
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }
        .controls-grid .card {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — DASHBOARD</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
			<a href="admin_add_voter.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px;">Tambah Pemilih</a>
			<a href="admin_voters.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px;">Status Pemilih</a>
			<a href="admin_cetak.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px;">Cetak Kartu</a>
            <a href="admin_candidates.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">Kelola Paslon</a>
            <form action="admin_logout.php" method="post" style="margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 16px;">Keluar</button>
            </form>
        </div>
    </header>
    
    <main>
        <!-- Area Pengaturan (Status Pemilihan & Cetak Kartu) -->
        <div class="controls-grid">
            <!-- Status Pemilihan -->
            <div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="margin-bottom: 8px; font-size: 18px;">Status Pemilihan</h2>
                    <div class="status-badge <?= $open ? 'open' : 'closed' ?>">
                        <span class="status-dot"></span>
                        <span><?= $open ? 'DIBUKA' : 'DITUTUP' ?></span>
                    </div>
                </div>
                <form method="post" style="margin: 0;">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="value" value="<?= $open ? '1' : '0' ?>">
                    <button style="width: auto; padding: 10px 16px; background: <?= $open ? '#a51d2d' : '#1769e0' ?>; font-size: 14px;">
                        <?= $open ? 'Tutup Pemilihan' : 'Buka Pemilihan' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Statistik Pemilih -->
        <div class="row">
            <div class="stat">Total Pemilih<b><?= $total ?></b></div>
            <div class="stat">Siswa<b><?= $student_total ?></b></div>
            <div class="stat">Guru<b><?= $teacher_total ?></b></div>
            <div class="stat">Sudah Memilih<b><?= $voted ?></b></div>
        </div>

        <!-- Komposisi Suara -->
        <div class="card">
            <h2>Komposisi Suara</h2>
            <p class="muted">Bobot hasil akhir dihitung berdasarkan persentase: <b>Siswa <?= $student_weight ?>%</b> + <b>Guru <?= $teacher_weight ?>%</b>.</p>
            <div class="row" style="margin-top: 15px;">
                <div class="stat" style="background: #f7f9fc;">
                    Suara siswa<b><?= $student_votes ?></b>
                    <small><?= $student_voted ?> pemilih siswa sudah memilih</small>
                </div>
                <div class="stat" style="background: #f7f9fc;">
                    Suara guru<b><?= $teacher_votes ?></b>
                    <small><?= $teacher_voted ?> pemilih guru sudah memilih</small>
                </div>
            </div>
        </div>

        <!-- Perolehan Suara Berbobot -->
        <div class="card">
            <h2>Perolehan Suara Berbobot</h2>
            <?php foreach ($results as $r): ?>
                <div style="margin: 22px 0; padding-bottom: 15px; border-bottom: 1px solid #f0f3f8;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <b><?= sprintf('%02d', $r['candidate_number']) ?> — <?= h($r['name']) ?></b>
                    </div>
                    <div class="result-grid">
                        <div><span>Suara siswa</span><strong><?= (int)$r['student_n'] ?> (<?= number_format($r['student_pct'], 2) ?>%)</strong></div>
                        <div><span>Suara guru</span><strong><?= (int)$r['teacher_n'] ?> (<?= number_format($r['teacher_pct'], 2) ?>%)</strong></div>
                        <div><span>Hasil akhir</span><strong style="color: #1769e0;"><?= number_format($r['weighted'], 2) ?>%</strong></div>
                    </div>
                    <div class="bar">
                        <div class="fill" style="width: <?= min(100, max(0, $r['weighted'])) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Catatan Privasi & Link Live Result -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 18px;" class="grid-responsive">
            <div class="card" style="margin-bottom: 0;">
                <h2 style="font-size: 16px;">Catatan Privasi</h2>
                <p class="muted" style="margin: 0; font-size: 13px;">Tabel <code>votes</code> tidak menyimpan ID pemilih. Sistem hanya mencatat kategori pemilih (siswa/guru) untuk perhitungan bobot, sedangkan status keikutsertaan berada terpisah di tabel <code>voters</code>.</p>
            </div>
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; background: #123b73;">
                <a href="../live.php" target="_blank" style="color: #fff; font-weight: bold; text-decoration: none; display: block;">Buka Live Result untuk Videotron →</a>
                <small style="color: rgba(255,255,255,0.7); margin-top: 4px;">Tampilan layar besar</small>
            </div>
        </div>
    </main>
</body>
</html>