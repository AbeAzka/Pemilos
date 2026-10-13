<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

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
    elseif ($action === 'delete_voter') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM voters WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = "Satu data pemilih berhasil dihapus.";
        $msg_type = "success";
    }
    elseif ($action === 'upload_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['csv_file']['tmp_name'];
            
            if (($handle = fopen($fileTmp, "r")) !== FALSE) {
                $successCount = 0;
                $skipCount = 0;
                
                fgetcsv($handle, 1000, ","); 
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $username = trim($data[0] ?? '');
                    $student_name = trim($data[1] ?? '');
                    $class_name = trim($data[2] ?? '');
                    $password = trim($data[3] ?? $username);
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

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE $whereClause");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();
$total_pages = ceil($total_data / $limit);

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
    <title>Kelola Pemilih - <?= h(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN -->
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
        .card {
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 1rem;
        }
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
        }
        .pagination a:hover {
            background: #e9eef7;
        }
        .pagination .active {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Header / Nav -->
    <header class="w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-address-book fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — PEMILIH</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Kelola Data & Status</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
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
            
            <?php if ($message !== ''): ?>
                <div class="alert <?= $msg_type === 'success' ? 'alert-success' : 'alert-danger' ?> py-2 px-3 small mb-4 shadow-sm"><?= h($message) ?></div>
            <?php endif; ?>

            <div class="card p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2 class="h5 fw-bold text-dark mb-0">Daftar Pemilih</h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm fw-semibold px-3" onclick="const modal = new bootstrap.Modal(document.getElementById('modalAdd')); modal.show();">
                            <i class="fa-solid fa-user-plus me-1"></i> Tambah Manual
                        </button>
                        <button type="button" class="btn btn-success btn-sm fw-semibold px-3" onclick="const modal = new bootstrap.Modal(document.getElementById('modalUpload')); modal.show();">
                            <i class="fa-solid fa-file-csv me-1"></i> Upload CSV
                        </button>
                    </div>
                </div>

                <!-- Filter Data Form -->
                <form method="get" class="row g-3 mb-4 pb-4 border-bottom align-items-center">
                    <div class="col-md-4">
                        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Cari username/nama/kelas..." class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="voted" <?= $status === 'voted' ? 'selected' : '' ?>>Sudah Memilih</option>
                            <option value="unvoted" <?= $status === 'unvoted' ? 'selected' : '' ?>>Belum Memilih</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua Tipe</option>
                            <option value="student" <?= $type === 'student' ? 'selected' : '' ?>>Siswa</option>
                            <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>Guru</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold">Filter</button>
                        <?php if ($search !== '' || $status !== 'all' || $type !== 'all'): ?>
                            <a href="admin_voters.php" class="btn btn-outline-secondary btn-sm w-100 fw-semibold">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="text-muted small mb-3">Menampilkan <b><?= count($voters) ?></b> dari <b><?= $total_data ?></b> data.</div>

                <!-- Tabel Data -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="py-3">No</th>
                                <th class="py-3">Username</th>
                                <th class="py-3">Nama Lengkap</th>
                                <th class="py-3">Kelas</th>
                                <th class="py-3">Tipe</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach ($voters as $v): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong class="text-dark"><?= h($v['username']) ?></strong></td>
                                    <td><?= h($v['student_name'] ?? '-') ?></td>
                                    <td><?= h($v['class_name'] ?? '-') ?></td>
                                    <td><?= $v['voter_type'] === 'student' ? 'Siswa' : 'Guru' ?></td>
                                    <td>
                                        <?php if ($v['has_voted']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Sudah Memilih</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Belum Memilih</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-warning btn-sm px-3 fw-semibold text-dark" onclick="editVoter(<?= $v['id'] ?>, '<?= h(addslashes($v['username'])) ?>', '<?= h(addslashes($v['student_name'] ?? '')) ?>', '<?= h(addslashes($v['class_name'] ?? '')) ?>', '<?= h($v['voter_type']) ?>')">Edit</button>
                                            
                                            <form method="post" class="m-0" onsubmit="return confirm('Yakin ingin menghapus pemilih <?= h(addslashes($v['student_name'] ?? $v['username'])) ?>?');">
                                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                                <input type="hidden" name="action" value="delete_voter">
                                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; if (empty($voters)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Data pemilih tidak ditemukan.</td></tr>
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
        </div>
    </main>

    <!-- MODAL 1: Tambah Manual -->
    <div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <div class="modal-header border-0 pb-0">
                    <h3 class="modal-title h5 fw-bold">Tambah Pemilih Baru</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="add_single">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Username / NIS / NIP</label>
                            <input type="text" name="username" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" name="student_name" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kelas <span class="text-muted fw-normal">(Kosongkan untuk Guru)</span></label>
                            <input type="text" name="class_name" class="form-control" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password <span class="text-muted fw-normal">(Kosongkan agar sama dengan username)</span></label>
                            <input type="text" name="password" class="form-control" autocomplete="off">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Tipe Pemilih</label>
                            <select name="voter_type" class="form-select" required>
                                <option value="student">Siswa</option>
                                <option value="teacher">Guru</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Simpan Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: Edit Data -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <div class="modal-header border-0 pb-0">
                    <h3 class="modal-title h5 fw-bold">Edit Data Pemilih</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="edit_voter">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Username / NIS / NIP</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" name="student_name" id="edit_student_name" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kelas</label>
                            <input type="text" name="class_name" id="edit_class_name" class="form-control" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password Baru <span class="text-muted fw-normal">(Kosongkan jika tidak ingin diubah)</span></label>
                            <input type="text" name="password" class="form-control" placeholder="***" autocomplete="off">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Tipe Pemilih</label>
                            <select name="voter_type" id="edit_voter_type" class="form-select" required>
                                <option value="student">Siswa</option>
                                <option value="teacher">Guru</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark">Perbarui Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 3: Upload CSV -->
    <div class="modal fade" id="modalUpload" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <div class="modal-header border-0 pb-0">
                    <h3 class="modal-title h5 fw-bold">Upload Data (Format CSV)</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <strong>Cara Upload dari Excel:</strong>
                        <ol class="mb-0 ps-3">
                            <li>Buat 5 kolom di Excel secara berurutan: <b>Username</b>, <b>Nama Lengkap</b>, <b>Kelas</b>, <b>Password</b>, <b>Tipe (student/teacher)</b>.</li>
                            <li>Biarkan baris ke-1 sebagai judul (Header).</li>
                            <li>Pilih menu <i>File > Save As</i>, lalu pilih format <b>CSV (Comma delimited) (*.csv)</b>.</li>
                        </ol>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="upload_csv">
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Pilih File CSV</label>
                            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">Mulai Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="w-100 py-4 text-center text-muted small border-top bg-white">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editVoter(id, username, studentName, className, type) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_student_name').value = studentName;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_voter_type').value = type;
            
            const editModal = new bootstrap.Modal(document.getElementById('modalEdit'));
            editModal.show();
        }
    </script>
</body>
</html>