<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

$weight_student = 60;
$weight_teacher = 40;

$stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('weight_student', 'weight_teacher', 'logo_layout', 'logo1', 'logo2', 'logo3')");
$logo_layout = '2'; // Default 2 logo
$logo1 = '/admin/images/logo1.png';
$logo2 = '/admin/images/logo2.png';
$logo3 = '';

while ($row = $stmtSettings->fetch()) {
    if ($row['setting_key'] === 'weight_student') $weight_student = (float)$row['setting_value'];
    if ($row['setting_key'] === 'weight_teacher') $weight_teacher = (float)$row['setting_value'];
    if ($row['setting_key'] === 'logo_layout') $logo_layout = $row['setting_value'];
    if ($row['setting_key'] === 'logo1') $logo1 = $row['setting_value'];
    if ($row['setting_key'] === 'logo2') $logo2 = $row['setting_value'];
    if ($row['setting_key'] === 'logo3') $logo3 = $row['setting_value'];
}

$stats = [
    'student' => ['dpt' => 0, 'voted' => 0],
    'teacher' => ['dpt' => 0, 'voted' => 0],
    'total'   => ['dpt' => 0, 'voted' => 0]
];

$stmtVoters = $pdo->query("SELECT voter_type, COUNT(*) as total_dpt, SUM(has_voted) as total_voted FROM voters GROUP BY voter_type");
while ($row = $stmtVoters->fetch()) {
    $type = $row['voter_type'] === 'teacher' ? 'teacher' : 'student';
    $stats[$type]['dpt'] = (int)$row['total_dpt'];
    $stats[$type]['voted'] = (int)$row['total_voted'];
    
    $stats['total']['dpt'] += (int)$row['total_dpt'];
    $stats['total']['voted'] += (int)$row['total_voted'];
}

$total_valid_student_votes = $stats['student']['voted'];
$total_valid_teacher_votes = $stats['teacher']['voted'];

$candidates = $pdo->query("SELECT * FROM candidates WHERE is_active = 1 ORDER BY candidate_number")->fetchAll();

$stmtVotes = $pdo->query("SELECT candidate_id, voter_type, COUNT(*) as vote_count FROM votes GROUP BY candidate_id, voter_type");
$raw_votes = [];
while ($row = $stmtVotes->fetch()) {
    $raw_votes[$row['candidate_id']][$row['voter_type']] = (int)$row['vote_count'];
}

$results = [];
foreach ($candidates as $c) {
    $c_id = $c['id'];
    $suara_siswa = $raw_votes[$c_id]['student'] ?? 0;
    $suara_guru = $raw_votes[$c_id]['teacher'] ?? 0;
    
    $pct_siswa = $total_valid_student_votes > 0 ? ($suara_siswa / $total_valid_student_votes) * 100 : 0;
    $pct_guru = $total_valid_teacher_votes > 0 ? ($suara_guru / $total_valid_teacher_votes) * 100 : 0;
    
    $skor_akhir = ($pct_siswa * ($weight_student / 100)) + ($pct_guru * ($weight_teacher / 100));
    $results[] = [
        'number' => $c['candidate_number'],
        'name' => $c['name'],
        'suara_siswa' => $suara_siswa,
        'suara_guru' => $suara_guru,
        'total_suara_masuk' => $suara_siswa + $suara_guru,
        'skor_akhir' => $skor_akhir
    ];
}

usort($results, function($a, $b) {
    return $b['skor_akhir'] <=> $a['skor_akhir'];
});
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekapitulasi Hasil Pemilihan - <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .report-table th, .report-table td { border: 1px solid #cbd5e0; padding: 10px; text-align: center; }
        .report-table th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; }
        .print-only { display: none; }
        
        @media print {
            body { background: #fff; color: #000; font-family: 'Times New Roman', Times, serif; }
            .no-print, header { display: none !important; }
            .card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .print-only { display: block; }
            .kop-surat { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 25px; }
            .kop-surat img { height: 80px; width: auto; object-fit: contain; }
            .kop-text { text-align: center; flex-grow: 1; }
            .kop-text h2 { margin: 0; font-size: 18px; font-weight: bold; text-transform: uppercase; }
            .kop-text h3 { margin: 4px 0; font-size: 16px; font-weight: bold; }
            .kop-text p { margin: 0; font-size: 12px; }
            .signature-area { display: flex; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
            .signature-box { text-align: center; width: 250px; }
            .signature-box .name { margin-top: 70px; font-weight: bold; }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <header class="no-print w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-file-invoice fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — REKAP HASIL</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Berita Acara Resmi</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="window.print()" class="btn btn-success btn-sm px-3 fw-semibold"><i class="fa-solid fa-print me-1"></i> Cetak Berita Acara</button>
            <a href="admin.php" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">Dashboard</a>
            <form action="admin_logout.php" method="post" class="m-0">
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Keluar</button>
            </form>
        </div>
    </header>

    <main class="flex-grow-1 p-3 p-md-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 900px;">
            
            <!-- KOP SURAT (Dinamis Berdasarkan Pengaturan Logo) -->
            <div class="kop-surat print-only">
                <?php if ($logo_layout === '1' || $logo_layout === '2' || $logo_layout === '3'): ?>
                    <img src="..<?= h($logo1) ?>" alt="Logo Kiri">
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <?php if ($logo_layout === '3' && !empty($logo3)): ?>
                    <img src="..<?= h($logo3) ?>" alt="Logo Tengah">
                <?php endif; ?>

                <div class="kop-text">
                    <h2>PANITIA PEMILIHAN KETUA OSIS</h2>
                    <h3>SMA NEGERI 1 GONDANG</h3>
                    <p>Jl. Raya Gondang - Nganjuk, Kecamatan Gondang, Kabupaten Nganjuk, Jawa Timur</p>
                </div>

                <?php if ($logo_layout === '2' || $logo_layout === '3'): ?>
                    <img src="..<?= h($logo2) ?>" alt="Logo Kanan">
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
            </div>

            <div class="print-only text-center mb-4">
                <h4 class="text-decoration-underline fw-bold mb-1" style="font-size: 16px;">BERITA ACARA HASIL PEMILIHAN</h4>
                <p class="m-0" style="font-size: 14px;">Tahun Ajaran 2026/2027</p>
            </div>

            <!-- TAMPILAN LAYAR UTAMA (NON-PRINT) -->
            <div class="no-print text-center mb-4">
                <h2 class="h4 fw-bold text-dark">Rekapitulasi Hasil Pemilihan Berbobot</h2>
                <p class="text-muted small">Statistik kehadiran pemilih serta hasil akhir penghitungan suara.</p>
            </div>

            <!-- TABEL 1: STATISTIK PARTISIPASI -->
            <div class="mb-5">
                <h3 class="h6 fw-bold text-primary text-uppercase mb-2">A. Statistik Partisipasi (Turnout)</h3>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Kategori Pemilih</th>
                                <th>Total DPT</th>
                                <th>Hadir</th>
                                <th>Golput</th>
                                <th>Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-start"><b>Siswa</b> (Bobot <?= $weight_student ?>%)</td>
                                <td><?= $stats['student']['dpt'] ?></td>
                                <td><?= $stats['student']['voted'] ?></td>
                                <td><?= $stats['student']['dpt'] - $stats['student']['voted'] ?></td>
                                <td><?= $stats['student']['dpt'] > 0 ? round(($stats['student']['voted'] / $stats['student']['dpt']) * 100, 2) : 0 ?>%</td>
                            </tr>
                            <tr>
                                <td class="text-start"><b>Guru / Staf</b> (Bobot <?= $weight_teacher ?>%)</td>
                                <td><?= $stats['teacher']['dpt'] ?></td>
                                <td><?= $stats['teacher']['voted'] ?></td>
                                <td><?= $stats['teacher']['dpt'] - $stats['teacher']['voted'] ?></td>
                                <td><?= $stats['teacher']['dpt'] > 0 ? round(($stats['teacher']['voted'] / $stats['teacher']['dpt']) * 100, 2) : 0 ?>%</td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td class="text-start">TOTAL KESELURUHAN</td>
                                <td><?= $stats['total']['dpt'] ?></td>
                                <td><?= $stats['total']['voted'] ?></td>
                                <td><?= $stats['total']['dpt'] - $stats['total']['voted'] ?></td>
                                <td><?= $stats['total']['dpt'] > 0 ? round(($stats['total']['voted'] / $stats['total']['dpt']) * 100, 2) : 0 ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABEL 2: PEROLEHAN SUARA & SKOR AKHIR -->
            <div class="mb-4">
                <h3 class="h6 fw-bold text-primary text-uppercase mb-2">B. Rekapitulasi Perolehan Suara</h3>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="align-middle">No. Urut</th>
                                <th rowspan="2" class="align-middle">Nama Pasangan Calon</th>
                                <th colspan="3">Jumlah Suara Masuk</th>
                                <th rowspan="2" class="align-middle">Skor Akhir</th>
                            </tr>
                            <tr>
                                <th>Siswa</th>
                                <th>Guru</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $r): ?>
                                <tr class="<?= $index === 0 ? 'table-success bg-opacity-10' : '' ?>">
                                    <td><span class="fw-bold fs-5"><?= sprintf('%02d', $r['number']) ?></span></td>
                                    <td class="text-start fw-semibold">
                                        <?= h($r['name']) ?>
                                        <?php if($index === 0): ?>
                                            <br><span class="badge bg-success mt-1">TERPILIH</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $r['suara_siswa'] ?></td>
                                    <td><?= $r['suara_guru'] ?></td>
                                    <td><b><?= $r['total_suara_masuk'] ?></b></td>
                                    <td><span class="text-success fw-bold fs-5"><?= number_format($r['skor_akhir'], 2) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($results)): ?>
                                <tr><td colspan="6" class="text-muted py-3">Belum ada data kandidat.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TANDA TANGAN (Hanya tampil saat dicetak) -->
            <div class="signature-area print-only">
                <div class="signature-box">
                    <p>Mengetahui,<br>Kepala SMAN 1 Gondang</p>
                    <p class="name">Agus Susilo, S.Pd., M.E.</p>
                    <p class="m-0" style="font-size: 12px;">NIP. 19700818 199802 1 009</p>
                </div>
                <div class="signature-box">
                    <p>Nganjuk, <?= date('d F Y') ?><br>Ketua Panitia Pemilihan</p>
                    <p class="name">( ........................................ )</p>
                    <p class="m-0" style="font-size: 12px;">NIS. </p>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>