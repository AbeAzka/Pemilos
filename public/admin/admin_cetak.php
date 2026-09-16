<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// 1. Konfigurasi Pagination
$limit = 21; // 21 kartu per halaman (cocok untuk 3 kolom di kertas A4)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Ambil filter tipe pemilih
$type = $_GET['type'] ?? 'all';
$whereClause = "1=1";
$params = [];

if ($type === 'student') {
    $whereClause .= " AND voter_type = 'student'";
} elseif ($type === 'teacher') {
    $whereClause .= " AND voter_type = 'teacher'";
}

// 3. Hitung Total Data untuk Pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE $whereClause");
$stmtCount->execute();
$total_data = $stmtCount->fetchColumn();
$total_pages = ceil($total_data / $limit);

// 4. Ambil Data Sesuai Halaman (Limit & Offset)
$query = "SELECT * FROM voters WHERE $whereClause ORDER BY id ASC LIMIT $limit OFFSET $offset";
$voters = $pdo->prepare($query);
$voters->execute();
$voters = $voters->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Kartu Pemilih</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* CSS Khusus Pagination */
        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            margin: 25px 0 40px 0;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #ccd4e0;
            color: #172033;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .pagination a:hover {
            background: #e9eef7;
        }
        .pagination .active {
            background: #1769e0;
            color: #fff;
            border-color: #1769e0;
        }
        .pagination .disabled {
            color: #a0aec0;
            background: #f8fafc;
            cursor: not-allowed;
            border-color: #e2e8f0;
        }

        /* Mode Cetak (Sembunyikan Elemen Non-Cetak) */
        @media print {
            .no-print, .pagination { 
                display: none !important; 
            }
        }
    </style>
</head>
<body>

    <header class="no-print">
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — CETAK KARTU</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="window.print()" style="background: #176b37; color: white; margin-left: 10px; width: auto; padding: 10px 15px;">🖨️ Cetak Halaman Ini</button>
            <a href="admin.php" class="secondary" style="display:inline-block; padding:10px 14px; border-radius:10px; background:#e9eef7; color:#17365f; font-weight:700; text-decoration:none; margin-right:8px; font-size: 14px;">Dashboard</a>
            <form action="admin_logout.php" method="post" style="display:inline; margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 15px;">Keluar</button>
            </form>
        </div>
    </header>

    <div class="no-print filter-bar" style="padding: 15px 25px; background: white; border-bottom: 1px solid #ddd; text-align: center;">
        <strong>Filter:</strong> 
        <a href="?type=all" style="<?= $type === 'all' ? 'text-decoration: underline;' : '' ?>">Semua</a> | 
        <a href="?type=student" style="<?= $type === 'student' ? 'text-decoration: underline;' : '' ?>">Siswa</a> | 
        <a href="?type=teacher" style="<?= $type === 'teacher' ? 'text-decoration: underline;' : '' ?>">Guru</a>
        <br>
        <small style="color: #666; margin-top: 8px; display: block;">Menampilkan halaman <b><?= $page ?></b> dari <b><?= $total_pages ?></b> (Total <?= $total_data ?> kartu)</small>
    </div>

    <!-- Area yang akan diprint -->
    <div class="print-container">
        <?php foreach ($voters as $v): ?>
            <div class="voter-card">
                <h3>KARTU PEMILIH</h3>
                <div class="voter-type"><?= strtoupper($v['voter_type']) ?></div>
                
                <div class="details"><strong>Username</strong> : <?= h($v['username'] ?? 'N/A') ?></div>
                <div class="details"><strong>Password</strong> : <?= h($v['username']) ?></div>
                
                <div style="margin-top: 15px; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 5px;">
                    Simpan kartu ini & gunakan untuk login.
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($voters)): ?>
            <p style="text-align:center; grid-column: 1 / -1; padding: 40px; background: #fff; border-radius: 10px;">Belum ada data pemilih.</p>
        <?php endif; ?>
    </div>

    <!-- Kontrol Pagination (Tidak akan ikut tercetak) -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination no-print">
            <!-- Tombol Prev -->
            <?php if ($page > 1): ?>
                <a href="?type=<?= $type ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php else: ?>
                <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>

            <!-- Angka Halaman (Dibatasi tampilannya) -->
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) echo '<span>...</span>';

            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?type=<?= $type ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; 
            
            if ($end_page < $total_pages) echo '<span>...</span>';
            ?>

            <!-- Tombol Next -->
            <?php if ($page < $total_pages): ?>
                <a href="?type=<?= $type ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>