<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// 1. Ambil parameter filter & pagination
$type = $_GET['type'] ?? 'all';
$class_filter = $_GET['class'] ?? 'all';
$per_page_input = $_GET['per_page'] ?? '21';

// Jika 'all', set limit besar atau ambil semua; jika angka, gunakan sebagai limit
$is_all_pages = ($per_page_input === 'all');
$limit = $is_all_pages ? PHP_INT_MAX : (int)$per_page_input;
if ($limit <= 0) $limit = 21;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * ($is_all_pages ? 1 : $limit);

$whereClause = "1=1";
$params = [];

// Filter tipe pemilih
if ($type === 'student') {
    $whereClause .= " AND voter_type = 'student'";
} elseif ($type === 'teacher') {
    $whereClause .= " AND voter_type = 'teacher'";
}

// Filter kelas
if ($class_filter !== 'all') {
    $whereClause .= " AND class_name = :class_name";
    $params['class_name'] = $class_filter;
}

// 2. Hitung Total Data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE $whereClause");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();

$total_pages = $is_all_pages ? 1 : ceil($total_data / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// 3. Ambil Data (Sesuai Halaman atau Semua)
if ($is_all_pages) {
    $query = "SELECT * FROM voters WHERE $whereClause ORDER BY class_name ASC, id ASC";
    $votersStmt = $pdo->prepare($query);
    $votersStmt->execute($params);
} else {
    $query = "SELECT * FROM voters WHERE $whereClause ORDER BY class_name ASC, id ASC LIMIT $limit OFFSET $offset";
    $votersStmt = $pdo->prepare($query);
    $votersStmt->execute($params);
}
$voters = $votersStmt->fetchAll();

// 4. Ambil daftar kelas untuk dropdown filter
$stmtClasses = $pdo->query("SELECT DISTINCT class_name FROM voters WHERE class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC");
$classes = $stmtClasses->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Kartu Pemilih</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* CSS Pagination */
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

        /* --- UI FILTER BAR MODERN --- */
        .filter-bar-modern {
            background: #ffffff;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            margin-bottom: 20px;
        }

        .filter-form-group {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-item label {
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }

        .custom-select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e0;
            background-color: #f8fafc;
            color: #2d3748;
            font-size: 14px;
            font-weight: 500;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 130px;
        }

        .custom-select:hover {
            border-color: #a0aec0;
        }

        .custom-select:focus {
            border-color: #1769e0;
            box-shadow: 0 0 0 2px rgba(23, 105, 224, 0.2);
            background-color: #ffffff;
        }
        
        .filter-stats {
            color: #718096;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .filter-stats b {
            color: #2d3748;
        }

        /* --- CSS DESAIN KARTU --- */
        .voter-card {
            position: relative;
            overflow: hidden;
            background-color: #ffffff;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .card-header img {
            height: 35px;
            width: auto;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 14px;
            text-align: center;
            flex-grow: 1;
        }

        .card-body {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            width: 60%;
            z-index: 1;
            pointer-events: none;
        }

        /* Mode Cetak */
        @media print {
            .no-print, .pagination { 
                display: none !important; 
            }
            .voter-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <header class="no-print">
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — CETAK KARTU PEMILIH</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="window.print()" style="background: #176b37; color: white; margin-left: 10px; width: auto; padding: 10px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px;">🖨️ Cetak Halaman Aktif</button>
            <a href="admin.php" class="secondary" style="display:inline-block; padding:10px 14px; border-radius:8px; background:#e9eef7; color:#17365f; font-weight:700; text-decoration:none; margin-right:8px; font-size: 14px;">Dashboard</a>
            <form action="admin_logout.php" method="post" style="display:inline; margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 15px; border-radius: 8px;">Keluar</button>
            </form>
        </div>
    </header>

    <!-- UI Filter & Opsi Tampilan -->
    <div class="no-print filter-bar-modern">
        <form method="GET" action="" id="filterForm" class="filter-form-group">
            <div class="filter-item">
                <label for="type">Kategori :</label>
                <select name="type" id="type" onchange="document.getElementById('filterForm').submit()" class="custom-select">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua Tipe</option>
                    <option value="student" <?= $type === 'student' ? 'selected' : '' ?>>Siswa</option>
                    <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>Guru</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="class">Kelas :</label>
                <select name="class" id="class" onchange="document.getElementById('filterForm').submit()" class="custom-select">
                    <option value="all">Semua Kelas</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= h($c) ?>" <?= $class_filter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="per_page">Per Halaman :</label>
                <select name="per_page" id="per_page" onchange="document.getElementById('filterForm').submit()" class="custom-select">
                    <option value="21" <?= $per_page_input === '21' ? 'selected' : '' ?>>21 Kartu</option>
                    <option value="30" <?= $per_page_input === '30' ? 'selected' : '' ?>>30 Kartu</option>
                    <option value="50" <?= $per_page_input === '50' ? 'selected' : '' ?>>50 Kartu</option>
                    <option value="all" <?= $per_page_input === 'all' ? 'selected' : '' ?>>Semua Data</option>
                </select>
            </div>
            
            <?php if($type !== 'all' || $class_filter !== 'all' || $per_page_input !== '21'): ?>
                <a href="?" style="font-size: 13px; color: #e53e3e; text-decoration: none; padding: 6px 12px; border-radius: 6px; background: #fff5f5; border: 1px solid #fed7d7; font-weight: 600;">✖ Reset Filter</a>
            <?php endif; ?>
        </form>
        
        <div class="filter-stats">
            <?php if ($is_all_pages): ?>
                Menampilkan <b>seluruh data</b> sekaligus (Total <b><?= $total_data ?></b> kartu pemilih)
            <?php else: ?>
                Menampilkan halaman <b><?= $page ?></b> dari <b><?= $total_pages ?></b> (Total <b><?= $total_data ?></b> kartu pemilih)
            <?php endif; ?>
        </div>
    </div>

    <!-- Area Kartu Pemilih -->
    <div class="print-container">
        <?php foreach ($voters as $v): ?>
            <div class="voter-card">
                <!-- Watermark Background -->
                <img src="/admin/images/watermark.png" class="watermark" alt="watermark">

                <!-- Header Kartu -->
                <div class="card-header">
                    <img src="/admin/images/logo1.png" alt="Logo Kiri">
                    <h3>KARTU PEMILIH <br> Ketua dan Wakil Ketua OSIS Tahun 2026/2027</h3>
                    <img src="/admin/images/logo2.png" alt="Logo Kanan">
                </div>
                
                <!-- Isi Kartu -->
                <div class="card-body">
                    <div class="voter-type" style="margin-bottom: 10px; font-weight: bold;"><?= strtoupper($v['voter_type']) ?></div>
                    
                    <div class="details" style="text-align: left;"><strong>Nama</strong> : <?= h($v['student_name'] ?? 'N/A') ?></div>
                    <div class="details" style="text-align: left;"><strong>Kelas</strong> : <?= h($v['class_name'] ?? '-') ?></div>
                    
                    <hr> 
                    <div class="details" style="text-align: left;"><strong>Username</strong> : <?= h($v['username'] ?? 'N/A') ?></div>
                    <div class="details" style="text-align: left;"><strong>Password</strong> : <?= h($v['username']) ?></div>
                    
                    <div style="margin-top: 15px; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 5px;">
                        Simpan kartu ini & gunakan untuk login.
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($voters)): ?>
            <p style="text-align:center; grid-column: 1 / -1; padding: 40px; background: #fff; border-radius: 10px; color: #718096; font-size: 16px;">
                TIDAK ADA DATA PEMILIH DITEMUKAN.<br>
                <small>Silakan ubah filter pencarian Anda.</small>
            </p>
        <?php endif; ?>
    </div>

    <!-- Kontrol Pagination -->
    <?php if (!$is_all_pages && $total_pages > 1): ?>
        <div class="pagination no-print">
            <!-- Tombol Prev -->
            <?php if ($page > 1): ?>
                <a href="?type=<?= urlencode($type) ?>&class=<?= urlencode($class_filter) ?>&per_page=<?= urlencode($per_page_input) ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php else: ?>
                <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>

            <!-- Angka Halaman -->
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) echo '<span>...</span>';

            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?type=<?= urlencode($type) ?>&class=<?= urlencode($class_filter) ?>&per_page=<?= urlencode($per_page_input) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; 
            
            if ($end_page < $total_pages) echo '<span>...</span>';
            ?>

            <!-- Tombol Next -->
            <?php if ($page < $total_pages): ?>
                <a href="?type=<?= urlencode($type) ?>&class=<?= urlencode($class_filter) ?>&per_page=<?= urlencode($per_page_input) ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>