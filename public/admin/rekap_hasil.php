<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// 1. Ambil Pengaturan Bobot (Default 60 Siswa, 40 Guru)
$weight_student = 60;
$weight_teacher = 40;

$stmtSettings =$pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('weight_student', 'weight_teacher')");
while ($row =$stmtSettings->fetch()) {
    if ($row['setting_key'] === 'weight_student') $weight_student = (float)$row['setting_value'];
    if ($row['setting_key'] === 'weight_teacher') $weight_teacher = (float)$row['setting_value'];
}

// 2. Hitung Statistik DPT & Partisipasi
$stats = [
    'student' => ['dpt' => 0, 'voted' => 0],
    'teacher' => ['dpt' => 0, 'voted' => 0],
    'total'   => ['dpt' => 0, 'voted' => 0]
];

$stmtVoters =$pdo->query("SELECT voter_type, COUNT(*) as total_dpt, SUM(has_voted) as total_voted FROM voters GROUP BY voter_type");
while ($row =$stmtVoters->fetch()) {
    $type =$row['voter_type'] === 'teacher' ? 'teacher' : 'student';
    $stats[$type]['dpt'] = (int)$row['total_dpt'];$stats[$type]['voted'] = (int)$row['total_voted'];
    
    $stats['total']['dpt'] += (int)$row['total_dpt'];
    $stats['total']['voted'] += (int)$row['total_voted'];
}

// Total Suara Masuk (Sebagai penyebut persentase bobot)
$total_valid_student_votes =$stats['student']['voted'];
$total_valid_teacher_votes =$stats['teacher']['voted'];

// 3. Ambil Data Kandidat & Hitung Suara
$candidates =$pdo->query("SELECT * FROM candidates WHERE is_active = 1 ORDER BY candidate_number")->fetchAll();

// Ambil jumlah suara per kandidat berdasarkan tipe pemilih
// Asumsi: tabel `votes` memiliki kolom `candidate_id` dan `voter_type`
$stmtVotes =$pdo->query("SELECT candidate_id, voter_type, COUNT(*) as vote_count FROM votes GROUP BY candidate_id, voter_type");
$raw_votes = [];
while ($row =$stmtVotes->fetch()) {
    $raw_votes[$row['candidate_id']][$row['voter_type']] = (int)$row['vote_count'];
}

// Proses perhitungan bobot untuk setiap kandidat
$results = [];
foreach ($candidates as $c) {$c_id = $c['id'];$suara_siswa = $raw_votes[$c_id]['student'] ?? 0;
    $suara_guru = $raw_votes[$c_id]['teacher'] ?? 0;
    
    // Hitung Persentase per kategori
    $pct_siswa =$total_valid_student_votes > 0 ? ($suara_siswa / $total_valid_student_votes) * 100 : 0;
    $pct_guru =$total_valid_teacher_votes > 0 ? ($suara_guru / $total_valid_teacher_votes) * 100 : 0;
    
    // Hitung Skor Akhir (Siswa 60% + Guru 40%)
    $skor_akhir = ($pct_siswa * ($weight_student / 100)) + ($pct_guru * ($weight_teacher / 100));$results[] = [
        'number' => $c['candidate_number'],
        'name' => $c['name'],
        'suara_siswa' => $suara_siswa,
        'suara_guru' => $suara_guru,
        'total_suara_masuk' => $suara_siswa +$suara_guru,
        'skor_akhir' => $skor_akhir
    ];
}

// Urutkan berdasarkan skor akhir tertinggi (Pemenang di atas)
usort($results, function($a,$b) {
    return $b['skor_akhir'] <=>$a['skor_akhir'];
});
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekapitulasi Hasil Pemilihan</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .report-section {
            margin-bottom: 30px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .report-table th, .report-table td {
            border: 1px solid #cbd5e0;
            padding: 12px;
            text-align: center;
        }
        .report-table th {
            background-color: #f8fafc;
            color: #17365f;
            font-weight: bold;
        }
        .report-table td.left-align {
            text-align: left;
        }
        .highlight-score {
            font-size: 18px;
            font-weight: 900;
            color: #176b37;
        }

        /* --- MODE CETAK (KOP SURAT) --- */
        .print-only {
            display: none;
        }
        
        @media print {
            body {
                background: #fff;
                color: #000;
                font-family: 'Times New Roman', Times, serif; /* Font resmi laporan */
            }
            .no-print, header { 
                display: none !important; 
            }
            .card {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
            }
            .print-only {
                display: block;
            }
            
            /* Desain Kop Surat */
            .kop-surat {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 3px solid #000;
                padding-bottom: 15px;
                margin-bottom: 25px;
            }
            .kop-surat img {
                height: 90px;
                width: auto;
            }
            .kop-text {
                text-align: center;
                flex-grow: 1;
            }
            .kop-text h2 { margin: 0; font-size: 20px; font-weight: bold; text-transform: uppercase; }
            .kop-text h3 { margin: 4px 0; font-size: 18px; font-weight: bold; }
            .kop-text p { margin: 0; font-size: 14px; }
            
            /* Tanda Tangan */
            .signature-area {
                display: flex;
                justify-content: space-between;
                margin-top: 60px;
                page-break-inside: avoid;
            }
            .signature-box {
                text-align: center;
                width: 250px;
            }
            .signature-box .name {
                margin-top: 80px;
                font-weight: bold;
                
            }
        }
    </style>
</head>
<body>

    <header class="no-print">
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — REKAP HASIL</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="window.print()" style="background: #176b37; color: white; margin-left: 10px; width: auto; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px;">🖨️ Cetak Berita Acara</button>
            <a href="admin.php" class="secondary" style="display:inline-block; padding:10px 14px; border-radius:8px; background:#e9eef7; color:#17365f; font-weight:700; text-decoration:none; margin-right:8px; font-size: 14px;">Dashboard</a>
			 <form action="admin_logout.php" method="post" style="margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 16px;">Keluar</button>
            </form>
		</div>
    </header>

    <main>
        <div class="card">
            
            <!-- KOP SURAT (Hanya tampil saat diprint) -->
            <div class="kop-surat print-only">
                <img src="/admin/images/logo1.png" alt="Logo Kiri">
                <div class="kop-text">
                    <h2>PANITIA PEMILIHAN KETUA OSIS</h2>
                    <h3>SMA NEGERI 1 GONDANG</h3>
                    <p>Jl. Raya Gondang - Nganjuk, Kecamatan Gondang, Kabupaten Nganjuk, Jawa Timur</p>
                </div>
                <img src="/admin/images/logo2.png" alt="Logo Kanan">
            </div>

            <div class="print-only" style="text-align: center; margin-bottom: 25px;">
                <h3 style="text-decoration: underline; margin-bottom: 5px;">BERITA ACARA HASIL PEMILIHAN</h3>
                <p style="margin: 0;">Tahun Ajaran 2026/2027</p>
            </div>

            <!-- TABEL 1: STATISTIK PARTISIPASI -->
            <div class="report-section">
                <h3 style="margin-bottom: 10px; color: #123b73;">A. Statistik Partisipasi (Turnout)</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Kategori Pemilih</th>
                            <th>Total DPT (Terdaftar)</th>
                            <th>Hadir (Memilih)</th>
                            <th>Golput (Tidak Memilih)</th>
                            <th>Persentase Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="left-align"><b>Siswa</b> (Bobot <?= $weight_student ?>%)</td>
                            <td><?= $stats['student']['dpt'] ?></td>
                            <td><?= $stats['student']['voted'] ?></td>
                            <td><?= $stats['student']['dpt'] -$stats['student']['voted'] ?></td>
                            <td><?= $stats['student']['dpt'] > 0 ? round(($stats['student']['voted'] /$stats['student']['dpt']) * 100, 2) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="left-align"><b>Guru / Staf</b> (Bobot <?= $weight_teacher ?>%)</td>
                            <td><?= $stats['teacher']['dpt'] ?></td>
                            <td><?= $stats['teacher']['voted'] ?></td>
                            <td><?= $stats['teacher']['dpt'] -$stats['teacher']['voted'] ?></td>
                            <td><?= $stats['teacher']['dpt'] > 0 ? round(($stats['teacher']['voted'] /$stats['teacher']['dpt']) * 100, 2) : 0 ?>%</td>
                        </tr>
                        <tr style="background-color: #f7f9fc; font-weight: bold;">
                            <td class="left-align">TOTAL KESELURUHAN</td>
                            <td><?= $stats['total']['dpt'] ?></td>
                            <td><?= $stats['total']['voted'] ?></td>
                            <td><?= $stats['total']['dpt'] -$stats['total']['voted'] ?></td>
                            <td><?= $stats['total']['dpt'] > 0 ? round(($stats['total']['voted'] /$stats['total']['dpt']) * 100, 2) : 0 ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TABEL 2: PEROLEHAN SUARA & SKOR AKHIR -->
            <div class="report-section">
                <h3 style="margin-bottom: 10px; color: #123b73;">B. Rekapitulasi Perolehan Suara</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th rowspan="2">No. Urut</th>
                            <th rowspan="2">Nama Pasangan Calon</th>
                            <th colspan="3">Jumlah Suara Masuk (Mentah)</th>
                            <th rowspan="2">Skor Akhir (Berbobot)</th>
                        </tr>
                        <tr>
                            <th>Suara Siswa</th>
                            <th>Suara Guru</th>
                            <th>Total Suara</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $index =>$r): ?>
                            <tr style="<?= $index === 0 ? 'background-color: #f0fdf4;' : '' ?>">
                                <td><h2><?= sprintf('%02d', $r['number']) ?></h2></td>
                                <td class="left-align" style="font-size: 16px; font-weight: 600;">
                                    <?= h($r['name']) ?>
                                    <?php if($index === 0): ?>
                                        <br><span class="badge" style="margin-top:5px; background:#176b37; color:white;">TERPILIH</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $r['suara_siswa'] ?></td>
                                <td><?= $r['suara_guru'] ?></td>
                                <td><b><?= $r['total_suara_masuk'] ?></b></td>
                                <td class="highlight-score"><?= number_format($r['skor_akhir'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="6" class="muted">Belum ada data kandidat.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TANDA TANGAN (Hanya tampil saat diprint) -->
            <div class="signature-area print-only">
                <div class="signature-box">
                    <p>Mengetahui,<br>Kepala SMAN 1 Gondang</p>
                    <p class="name">Agus Susilo, S.Pd., M.E.</p>
                    <p style="margin:0; font-size: 14px;">NIP. 19700818 199802 1 009</p>
                </div>
                <div class="signature-box">
                    <p>Nganjuk, <?= date('d F Y') ?><br>Ketua Panitia Pemilihan</p>
                    <p class="name">( ........................................ )</p>
                    <p style="margin:0; font-size: 14px;">NIS. </p>
                </div>
            </div>

        </div>
    </main>

</body>
</html>