<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();

// Konfigurasi Pagination
$limit = 15; // Jumlah data per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Ambil parameter filter & pencarian
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';

// Susun Query Dinamis
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "username LIKE ?";
    $params[] = "%$search%";
}

if ($status === 'voted') {
    $where[] = "has_voted = 1";
} elseif ($status === 'unvoted') {
    $where[] = "(has_voted = 0 OR has_voted IS NULL)";
}

if ($type === 'student') {
    $where[] = "voter_type = 'student'";
} elseif ($type === 'teacher') {
    $where[] = "voter_type = 'teacher'";
}

$whereClause = implode(" AND ", $where);

// Hitung Total Data untuk Pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE $whereClause");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Ambil Data Sesuai Halaman (Limit & Offset)
$query = "SELECT * FROM voters WHERE $whereClause ORDER BY username ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$voters = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Status Pemilih</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .filter-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-form select, .filter-form input {
            width: auto;
            min-width: 140px;
            margin: 0;
        }
        
        /* CSS Khusus Pagination */
        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            margin-top: 25px;
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
            text-decoration: none;
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
        .summary-text {
            font-size: 14px;
            color: #667085;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — STATUS PEMILIH</div>
            <div class="sub">Panitia Pemilihan</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="admin.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px;">Dashboard</a>
            <form action="admin_logout.php" method="post" style="margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 16px;">Keluar</button>
            </form>
        </div>
    </header>

    <main>
        <div class="card">
            <h2 style="margin-bottom: 15px;">Data & Status Partisipasi</h2>
            
            <!-- Form Filter & Pencarian -->
            <form method="get" class="filter-form" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e8edf3;">
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari username..." style="width: 200px;">
                
                <select name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="voted" <?= $status === 'voted' ? 'selected' : '' ?>>Sudah Memilih</option>
                    <option value="unvoted" <?= $status === 'unvoted' ? 'selected' : '' ?>>Belum Memilih</option>
                </select>

                <select name="type">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua Tipe</option>
                    <option value="student" <?= $type === 'student' ? 'selected' : '' ?>>Siswa</option>
                    <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>Guru</option>
                </select>
                
                <button type="submit" style="width: auto; padding: 11px 18px;">Cari</button>
                
                <?php if ($search !== '' || $status !== 'all' || $type !== 'all'): ?>
                    <a href="admin_voters.php" class="secondary" style="padding: 11px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold;">Reset</a>
                <?php endif; ?>
            </form>

            <span class="summary-text">Menampilkan <b><?= count($voters) ?></b> dari total <b><?= $total_data ?></b> data pemilih.</span>

            <!-- Tabel Data -->
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">No</th>
                            <th>Username</th>
                            <th>Tipe Pemilih</th>
                            <th>Status Partisipasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        foreach ($voters as $v): 
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= h($v['username']) ?></strong></td>
                                <td>
                                    <?= $v['voter_type'] === 'student' ? 'Siswa' : 'Guru' ?>
                                </td>
                                <td>
                                    <?php if ($v['has_voted']): ?>
                                        <span class="badge">Sudah Memilih</span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #b91c1c; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">Belum Memilih</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($voters)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #667085; padding: 30px;">
                                    Data pemilih tidak ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Kontrol Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- Tombol Prev -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">&laquo; Prev</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Prev</span>
                    <?php endif; ?>

                    <!-- Angka Halaman (Dibatasi tampilannya agar tidak terlalu panjang) -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) echo '<span>...</span>';

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>" class="<?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; 
                    
                    if ($end_page < $total_pages) echo '<span>...</span>';
                    ?>

                    <!-- Tombol Next -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </main>
</body>
</html>