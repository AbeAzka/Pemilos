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
            $msg_type = "danger";
        } else {
            if (empty($password)) $password = $username;
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn() > 0) {
                $message = "Gagal: Username '$username' sudah terdaftar!";
                $msg_type = "danger";
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
                $msg_type = "danger";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE voters SET username = ?, student_name = ?, class_name = ?, password_hash = ?, voter_type = ? WHERE id = ?");
                    $stmt->execute([$username, $student_name, $class_name, $hashed_password, $voter_type, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE voters SET username = ?, student_name = ?, class_name = ?, voter_type = ? WHERE id = ?");
                    $stmt->execute([$username, $student_name, $class_name, $voter_type, $id]);
                }
                $message = "Data pemilih '$username' berhasil diperbarui.";
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
                $message = "Upload selesai! Berhasil: $successCount data. Dilewati: $skipCount data.";
                $msg_type = "success";
            }
        } else {
            $message = "Gagal mengunggah file CSV.";
            $msg_type = "danger";
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
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status === 'voted') $where[] = "has_voted = 1";
elseif ($status === 'unvoted') $where[] = "(has_voted = 0 OR has_voted IS NULL)";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        .card { background: #fff; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 1rem; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-users fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — PEMILIH</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Kelola Data & Status</small>
            </div>
        </div>
        <div>
            <a href="admin.php" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">Dashboard</a>
            <form action="admin_logout.php" method="post" class="d-inline m-0">
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Keluar</button>
            </form>
        </div>
    </header>

    <main class="flex-grow-1 p-3 p-md-5">
        <div class="w-100 mx-auto" style="max-width: 1100px;">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $msg_type ?> py-2 px-3 small mb-4"><?= h($message) ?></div>
            <?php endif; ?>

            <div class="card p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2 class="h5 fw-bold text-dark m-0">Daftar Pemilih</h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAdd">+ Tambah Manual</button>
                        <button type="button" class="btn btn-success btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalUpload"><i class="fa-solid fa-file-arrow-up"></i> Upload CSV</button>
                    </div>
                </div>

                <!-- Filter Data -->
                <form method="get" class="row g-2 align-items-center mb-4 pb-4 border-bottom">
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

                <small class="text-muted d-block mb-3">Menampilkan <b><?= count($voters) ?></b> dari <b><?= $total_data ?></b> data.</small>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
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
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Sudah Memilih</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Belum Memilih</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-warning btn-sm px-2 py-1 text-white fw-semibold" onclick="editVoter(<?= $v['id'] ?>, '<?= h(addslashes($v['username'])) ?>', '<?= h(addslashes($v['student_name'] ?? '')) ?>', '<?= h(addslashes($v['class_name'] ?? '')) ?>', '<?= h($v['voter_type']) ?>')">Edit</button>
                                        
                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('Yakin ingin menghapus pemilih ini?');">
                                            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                            <input type="hidden" name="action" value="delete_voter">
                                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm px-2 py-1 fw-semibold">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; if (empty($voters)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Data pemilih tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center m-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">&laquo; Prev</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&type=<?= $type ?>">Next &raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- MODAL TAMBAH -->
    <div class="modal fade" id="modalAdd" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Tambah Pemilih Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <label class="form-label small fw-bold">Kelas <small class="text-muted">(Kosongkan untuk Guru)</small></label>
                            <input type="text" name="class_name" class="form-control" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password <small class="text-muted">(Default: Username)</small></label>
                            <input type="text" name="password" class="form-control" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tipe Pemilih</label>
                            <select name="voter_type" class="form-select" required>
                                <option value="student">Siswa</option>
                                <option value="teacher">Guru</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">Simpan Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT -->
    <div class="modal fade" id="modalEdit" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Edit Data Pemilih</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <label class="form-label small fw-bold">Password Baru <small class="text-muted">(Biarkan kosong jika tidak diubah)</small></label>
                            <input type="text" name="password" class="form-control" placeholder="••••••••" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tipe Pemilih</label>
                            <select name="voter_type" id="edit_voter_type" class="form-select" required>
                                <option value="student">Siswa</option>
                                <option value="teacher">Guru</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-semibold text-white">Perbarui Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL UPLOAD CSV -->
    <div class="modal fade" id="modalUpload" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Upload Data (Format CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <strong>Format Kolom Excel/CSV:</strong>
                        <ol class="m-0 ps-3">
                            <li>Username, Nama, Kelas, Password, Tipe (student/teacher)</li>
                            <li>Gunakan ekstensi file berformat <b>.csv</b></li>
                        </ol>
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="upload_csv">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pilih File CSV</label>
                            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-semibold">Mulai Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="w-100 py-4 text-center text-muted small border-top bg-white">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editVoter(id, username, studentName, className, type) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_student_name').value = studentName;
            document.getElementById('edit_class_name').value = className;
            document.getElementById('edit_voter_type').value = type;
            new bootstrap.Modal(document.getElementById('modalEdit')).show();
        }
    </script>
</body>
</html>