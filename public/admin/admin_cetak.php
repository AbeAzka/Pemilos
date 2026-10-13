<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// Ambil pengaturan Logo & Tampilan dari Database[cite: 1]
$logo1 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo1'")->fetchColumn() ?: '/admin/images/logo1.png';
$logo2 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo2'")->fetchColumn() ?: '/admin/images/logo2.png';
$logo3 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo3'")->fetchColumn() ?: '';
$show_logo3 = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'show_logo3'")->fetchColumn() === '1';

// 1. Ambil parameter filter & pagination (Default per_page diubah ke 'all' agar awal halaman tampil semua)
$type = $_GET['type'] ?? 'all';
$class_filter = $_GET['class'] ?? 'all';
$per_page_input = $_GET['per_page'] ?? 'all';

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
    <title>Cetak Kartu Pemilih - <?= h(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- CSS Pagination --- */
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
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
        .pagination .disabled {
            color: #a0aec0;
            background: #f8fafc;
            cursor: not-allowed;
            border-color: #e2e8f0;
        }

        /* --- STYLE KARTU PEMILIH ASLI / KLASIK --- */
        .print-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .voter-card {
            position: relative;
            overflow: hidden;
            background-color: #ffffff;
            border: 1px solid #cbd5e0;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            page-break-inside: avoid;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
            gap: 5px;
        }
        
        .card-header img {
            height: 32px;
            width: auto;
            object-fit: contain;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 12px;
            text-align: center;
            flex-grow: 1;
            line-height: 1.2;
        }

        .card-body {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        /* Watermark Logo Tengah Transparan */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.12;
            width: 55%;
            z-index: 1;
            pointer-events: none;
        }

        /* Mode Cetak */
        @media print {
            .no-print, .pagination { 
                display: none !important; 
            }
            body {
                background: #fff !important;
            }
            .print-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .voter-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #000 !important;
                box-shadow: none !important;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Header / Nav Gaya Bootstrap Light -->
    <header class="no-print w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-id-card fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — CETAK KARTU</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Kartu Akun Pemilih</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="window.print()" class="btn btn-success btn-sm px-3 fw-semibold"><i class="fa-solid fa-print me-1"></i> Cetak Kartu</button>
            <a href="admin.php" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">Dashboard</a>
            <form action="admin_logout.php" method="post" class="m-0">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Keluar</button>
            </form>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow-1 p-3 p-md-5">
        <div class="w-100 mx-auto" style="max-width: 1200px;">
            
            <!-- UI Filter Bar Modern -->
            <div class="no-print card p-4 shadow-sm mb-4 border-0 rounded-4 bg-white">
                <form method="GET" action="" id="filterForm" class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Kategori :</label>
                        <select name="type" id="type" onchange="document.getElementById('filterForm').submit()" class="form-select">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua Tipe</option>
                            <option value="student" <?= $type === 'student' ? 'selected' : '' ?>>Siswa</option>
                            <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>Guru</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Kelas :</label>
                        <select name="class" id="class" onchange="document.getElementById('filterForm').submit()" class="form-select">
                            <option value="all">Semua Kelas</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= h($c) ?>" <?= $class_filter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Tampilkan :</label>
                        <select name="per_page" id="per_page" onchange="document.getElementById('filterForm').submit()" class="form-select">
                            <option value="all" <?= $per_page_input === 'all' ? 'selected' : '' ?>>Semua Data (Tampil Semua)</option>
                            <option value="21" <?= $per_page_input === '21' ? 'selected' : '' ?>>21 Kartu</option>
                            <option value="30" <?= $per_page_input === '30' ? 'selected' : '' ?>>30 Kartu</option>
                            <option value="50" <?= $per_page_input === '50' ? 'selected' : '' ?>>50 Kartu</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end mt-4">
                        <?php if($type !== 'all' || $class_filter !== 'all' || $per_page_input !== 'all'): ?>
                            <a href="?" class="btn btn-outline-danger btn-sm w-100 fw-semibold">Reset Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="mt-3 text-muted small">
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
                        <!-- Watermark Transparan (Logo 3) jika diaktifkan -->
                        <?php if ($show_logo3 && !empty($logo3)): ?>
                            <img src="..<?= h($logo3) ?>" class="watermark" alt="Watermark Logo">
                        <?php endif; ?>

                        <!-- Header Kartu (Logo 1 & Logo 2 di Kiri Kanan) -->
                        <div class="card-header">
                            <img src="..<?= h($logo1) ?>" alt="Logo 1">
                            <h3>KARTU PEMILIH <br> Ketua & Wakil Ketua OSIS</h3>
                            <img src="..<?= h($logo2) ?>" alt="Logo 2">
                        </div>
                        
                        <!-- Isi Kartu -->
                        <div class="card-body">
                            <div class="voter-type mb-2 fw-bold" style="font-size: 11px;"><?= strtoupper($v['voter_type']) ?></div>
                            
                            <div class="details text-start" style="font-size: 12px;"><strong>Nama</strong> : <?= h($v['name'] ?? $v['student_name'] ?? 'N/A') ?></div>
                            <div class="details text-start" style="font-size: 12px;"><strong>Kelas</strong> : <?= h($v['class_name'] ?? '-') ?></div>
                            
                            <hr class="my-2"> 
                            <div class="details text-start" style="font-size: 12px;"><strong>Username</strong> : <?= h($v['username'] ?? 'N/A') ?></div>
                            <div class="details text-start" style="font-size: 12px;"><strong>Password</strong> : <?= h($v['username']) ?></div>
                            
                            <div class="mt-2 text-muted" style="font-size: 9px; border-top: 1px solid #eee; padding-top: 4px;">
                                Simpan kartu ini & gunakan untuk login pemilihan.
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($voters)): ?>
                    <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm text-muted">
                        TIDAK ADA DATA PEMILIH DITEMUKAN.<br>
                        <small>Silakan ubah filter pencarian Anda.</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kontrol Pagination -->
            <?php if (!$is_all_pages && $total_pages > 1): ?>
                <div class="pagination no-print">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?= urlencode($type) ?>&class=<?= urlencode($class_filter) ?>&per_page=<?= urlencode($per_page_input) ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Prev</span>
                    <?php endif; ?>

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

                    <?php if ($page < $total_pages): ?>
                        <a href="?type=<?= urlencode($type) ?>&class=<?= urlencode($class_filter) ?>&per_page=<?= urlencode($per_page_input) ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="no-print w-100 py-4 text-center text-muted small border-top bg-white">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>