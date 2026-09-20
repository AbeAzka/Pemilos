<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();
$message = '';
$msg_type = '';

// ==========================================
// 1. PROSES POST (TAMBAH, EDIT, HAPUS & UPLOAD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    // Aksi: Tambah Satu Pemilih Manual
    if ($action === 'add_single') {
        $username = trim($_POST['username'] ?? '');
        $student_name = trim($_POST['student_name'] ?? '');
        $class_name = trim($_POST['class_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $voter_type = $_POST['voter_type'] ?? 'student';
        
        if (empty($username) || empty($student_name)) {
            $message = "Username dan Nama Lengkap tidak boleh kosong.";
            $msg_type = "error";
        } else {
            if (empty($password)) $password = $username;
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn() > 0) {
                $message = "Gagal: Username '$username' sudah terdaftar!";
                $msg_type = "error";
            } else {
                $stmt = $pdo->prepare("INSERT INTO voters (username, password_hash, student_name, class_name, voter_type, has_voted) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$username, $hashed_password, $student_name, $class_name, $voter_type]);
                $message = "Berhasil: Pemilih '$username' berhasil ditambahkan!";
                $msg_type = "success";
            }
        }
    } 
    // Aksi: Edit Data Pemilih
    elseif ($action === 'edit_voter') {
        $id = (int)$_POST['id'];
        $username = trim($_POST['username'] ?? '');
        $student_name = trim($_POST['student_name'] ?? '');
        $class_name = trim($_POST['class_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $voter_type = $_POST['voter_type'] ?? 'student';

        if (!empty($username) && !empty($student_name)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            
            if ($stmt->fetchColumn() > 0) {
                $message = "Gagal Edit: Username '$username' sudah digunakan pemilih lain!";
                $msg_type = "error";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE voters SET username = ?, student_name = ?, class_name = ?, password_hash = ?, voter_type = ? WHERE id = ?");
                    $stmt->execute([$username, $student_name, $class_name, $hashed_password, $voter_type, $id]);
                    $message = "Data dan Password pemilih '$username' berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("UPDATE voters SET username = ?, student_name = ?, class_name = ?, voter_type = ? WHERE id = ?");
                    $stmt->execute([$username, $student_name, $class_name, $voter_type, $id]);
                    $message = "Data pemilih '$username' berhasil diperbarui.";
                }
                $msg_type = "success";
            }
        }
    }
    // Aksi: Hapus Data Pemilih
    elseif ($action === 'delete_voter') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM voters WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = "Satu data pemilih berhasil dihapus.";
        $msg_type = "success";
    }
    // Aksi: Upload CSV Excel
    elseif ($action === 'upload_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['csv_file']['tmp_name'];
            
            if (($handle = fopen($fileTmp, "r")) !== FALSE) {
                $successCount = 0;
                $skipCount = 0;
                
                fgetcsv($handle, 1000, ","); 
                
                // Format Kolom Excel: Username, Nama, Kelas, Password, Tipe
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $username = trim($data[0] ?? '');
                    $student_name = trim($data[1] ?? '');
                    $class_name = trim($data[2] ?? '');
                    $password = trim($data[3] ?? $username); // Jika kosong, gunakan username
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $voter_type = strtolower(trim($data[4] ?? 'student'));

                    if (!empty($username) && !empty($student_name)) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE username = ?");
                        $stmt->execute([$username]);
                        
                        if ($stmt->fetchColumn() == 0) {
                            $stmt = $pdo->prepare("INSERT INTO voters (username, password_hash, student_name, class_name, voter_type, has_voted) VALUES (?, ?, ?, ?, ?, 0)");
                            $stmt->execute([$username, $hashed_password, $student_name, $class_name, $voter_type]);
                            $successCount++;
                        } else {
                            $skipCount++;
                        }
                    }
                }
                fclose($handle);
                $message = "Upload selesai! Berhasil: $successCount data. Dilewati (Duplikat/Kosong): $skipCount data.";
                $msg_type = "success";
            }
        } else {
            $message = "Gagal mengunggah file CSV.";
            $msg_type = "error";
        }
    }
}

// ==========================================
// 2. LOGIKA PAGINATION & FILTER
// ==========================================
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(username LIKE ? OR student_name LIKE ? OR class_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status === 'voted') {
    $where[] = "has_voted = 1";
} elseif ($status === 'unvoted') {
    $where[] = "(has_voted = 0 OR has_voted IS NULL)";
}
if ($type === 'student' || $type === 'teacher') {
    $where[] = "voter_type = ?";
    $params[] = $type;
}

$whereClause = implode(" AND ", $where);

// Hitung total untuk pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE $whereClause");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Fetch data
$query = "SELECT * FROM voters WHERE $whereClause ORDER BY class_name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$voters = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Pemilih</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .filter-form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e8edf3; }
        .filter-form select, .filter-form input { width: auto; min-width: 140px; margin: 0; }
        .pagination { display: flex; gap: 6px; justify-content: center; align-items: center; margin-top: 25px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; background: #fff; border: 1px solid #ccd4e0; color: #172033; text-decoration: none; font-size: 14px; font-weight: bold; }
        .pagination a:hover { background: #e9eef7; }
        .pagination .active { background: #1769e0; color: #fff; border-color: #1769e0; }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
        .alert.success { background: #edf9f0; color: #176b37; border: 1px solid #c3e6cb; }
        .alert.error { background: #fff0f0; color: #a51d2d; border: 1px solid #f5c6cb; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; overflow-y: auto; padding: 20px 0; }
        .modal-content { background: #fff; padding: 25px; width: 90%; max-width: 450px; border-radius: 12px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); margin: auto; }
        .close-btn { position: absolute; right: 20px; top: 20px; font-size: 24px; font-weight: bold; cursor: pointer; color: #666; }
        .close-btn:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccd4e0; border-radius: 8px; box-sizing: border-box; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; border: none; cursor: pointer; color: #fff; }
        .btn-edit { background: #f59e0b; }
        .btn-delete { background: #ef4444; }
        .btn-edit:hover { background: #d97706; }
        .btn-delete:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — PEMILIH</div>
            <div class="sub">Kelola Data & Status</div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="admin.php" class="secondary" style="padding: 10px 16px; border-radius: 10px; background: #e9eef7; color: #17365f; font-weight: 700; text-decoration: none; font-size: 14px;">Dashboard</a>
            <form action="admin_logout.php" method="post" style="margin: 0;">
                <button class="secondary" style="width: auto; padding: 10px 16px;">Keluar</button>
            </form>
        </div>
    </header>

    <main>
        <?php if ($message !== ''): ?>
            <div class="alert <?= $msg_type ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0;">Daftar Pemilih</h2>
                <div>
                    <button onclick="openModal('modalAdd')" style="background: #1769e0; color: white; width: auto; padding: 10px 15px; font-size: 14px;">Tambah Manual</button>
                    <button onclick="openModal('modalUpload')" style="background: #176b37; color: white; width: auto; padding: 10px 15px; font-size: 14px;">Upload CSV</button>
                </div>
            </div>

            <!-- Filter Data -->
            <form method="get" class="filter-form">
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari username/nama/kelas..." style="width: 250px;">
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
                <button type="submit" style="width: auto; padding: 11px 18px;">Filter</button>
                <?php if ($search !== '' || $status !== 'all' || $type !== 'all'): ?>
                    <a href="admin_voters.php" class="secondary" style="padding: 11px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold;">Reset</a>
                <?php endif; ?>
            </form>

            <span style="font-size: 14px; color: #667085; margin-bottom: 15px; display: block;">Menampilkan <b><?= count($voters) ?></b> dari <b><?= $total_data ?></b> data.</span>

            <!-- Tabel Data -->
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Kelas</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = $offset + 1; foreach ($voters as $v): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= h($v['username']) ?></strong></td>
                                <td><?= h($v['student_name'] ?? '-') ?></td>
                                <td><?= h($v['class_name'] ?? '-') ?></td>
                                <td><?= $v['voter_type'] === 'student' ? 'Siswa' : 'Guru' ?></td>
                                <td>
                                    <?php if ($v['has_voted']): ?>
                                        <span class="badge">Sudah Memilih</span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #b91c1c; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">Belum Memilih</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button class="btn-action btn-edit" onclick="editVoter(<?= $v['id'] ?>, '<?= h(addslashes($v['username'])) ?>', '<?= h(addslashes($v['student_name'] ?? '')) ?>', '<?= h(addslashes($v['class_name'] ?? '')) ?>', '<?= h($v['voter_type']) ?>')">Edit</button>
                                    
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus pemilih <?= h(addslashes($v['student_name'] ?? $v['username'])) ?>?');">
                                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                        <input type="hidden" name="action" value="delete_voter">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                        <button type="submit" class="btn-action btn-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; if (empty($voters)): ?>
                            <tr><td colspan="7" style="text-align: center; color: #667085; padding: 30px;">Data pemilih tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">&laquo; Prev</a><?php endif; ?>
                    <?php
                    $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) echo '<span>...</span>';
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; 
                    if ($end_page < $total_pages) echo '<span>...</span>';
                    ?>
                    <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">Next &raquo;</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL 1: Tambah Manual -->
    <div id="modalAdd" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalAdd')">&times;</span>
            <h3 style="margin-top: 0;">Tambah Pemilih Baru</h3>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="add_single">
                
                <div class="form-group">
                    <label>Username / NIS / NIP</label>
                    <input type="text" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="student_name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Kelas <small>(Kosongkan untuk Guru)</small></label>
                    <input type="text" name="class_name" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password <small>(Kosongkan agar sama dengan username)</small></label>
                    <input type="text" name="password" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Tipe Pemilih</label>
                    <select name="voter_type" required>
                        <option value="student">Siswa</option>
                        <option value="teacher">Guru</option>
                    </select>
                </div>
                <button type="submit" style="width: 100%; padding: 12px; margin-top: 10px; background: #1769e0; color: #fff;">Simpan Data</button>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Edit Data -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalEdit')">&times;</span>
            <h3 style="margin-top: 0;">Edit Data Pemilih</h3>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="edit_voter">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Username / NIS / NIP</label>
                    <input type="text" name="username" id="edit_username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="student_name" id="edit_student_name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <input type="text" name="class_name" id="edit_class_name" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password Baru <small>(Kosongkan jika tidak ingin diubah)</small></label>
                    <input type="text" name="password" placeholder="***" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Tipe Pemilih</label>
                    <select name="voter_type" id="edit_voter_type" required>
                        <option value="student">Siswa</option>
                        <option value="teacher">Guru</option>
                    </select>
                </div>
                <button type="submit" style="width: 100%; padding: 12px; margin-top: 10px; background: #f59e0b; color: #fff; font-weight: bold; border: none; border-radius: 8px;">Perbarui Data</button>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Upload CSV -->
    <div id="modalUpload" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalUpload')">&times;</span>
            <h3 style="margin-top: 0;">Upload Data (Format CSV)</h3>
            
            <div style="background: #f7f9fc; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px; border: 1px dashed #ccc;">
                <strong>Cara Upload dari Excel:</strong>
                <ol style="margin: 5px 0 0 15px; padding: 0;">
                    <li>Buat 5 kolom di Excel secara berurutan: <b>Username</b>, <b>Nama Lengkap</b>, <b>Kelas</b>, <b>Password</b>, <b>Tipe (student/teacher)</b>.</li>
                    <li>Biarkan baris ke-1 sebagai judul (Header).</li>
                    <li>Pilih menu <i>File > Save As</i>, lalu pilih format <b>CSV (Comma delimited) (*.csv)</b>.</li>
                </ol>
            </div>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="upload_csv">
                
                <div class="form-group">
                    <label>Pilih File CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required style="padding: 8px;">
                </div>
                
                <button type="submit" style="width: 100%; padding: 12px; margin-top: 10px; background: #176b37; color: #fff;">Mulai Upload</button>
            </form>
        </div>
    </div>

    <!-- SCRIPT UNTUK KONTROL MODAL -->
    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function editVoter(id, username, studentName, className, type) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_student_name').value = studentName;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_voter_type').value = type;
            openModal('modalEdit');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>

</body>
</html>