<?php
require_once __DIR__ . '/../../config/config.php';
require_admin();

$pdo = db();
$error = '';
$success = '';

// Helper function untuk handle upload file foto
function handle_photo_upload() {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = $_FILES['photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_exts)) {
            throw new Exception('Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP.');
        }
        
        $upload_dir = __DIR__ . '/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $new_filename = 'candidate_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            return '/admin/images/' . $new_filename;
        } else {
            throw new Exception('Gagal mengunggah file foto.');
        }
    }
    return null;
}

// Proses aksi form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_candidate') {
            $candidate_number = trim($_POST['candidate_number'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $vision = trim($_POST['vision'] ?? '');
            $mission = trim($_POST['mission'] ?? '');
            $proker = trim($_POST['proker'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($candidate_number === '' || $name === '') {
                throw new Exception('Nomor urut dan nama kandidat wajib diisi.');
            }
            
            $photo_url = handle_photo_upload();
            if (!$photo_url) {
                $photo_url = '';
            }
            
            $stmt = $pdo->prepare("INSERT INTO candidates (candidate_number, name, vision, mission, proker, photo_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$candidate_number, $name, $vision, $mission, $proker, $photo_url, $is_active]);
            $success = 'Kandidat baru berhasil ditambahkan.';
        }
        elseif ($action === 'edit_candidate') {
            $id = (int)($_POST['id'] ?? 0);
            $candidate_number = trim($_POST['candidate_number'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $vision = trim($_POST['vision'] ?? '');
            $mission = trim($_POST['mission'] ?? '');
            $proker = trim($_POST['proker'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || $candidate_number === '' || $name === '') {
                throw new Exception('Data kandidat tidak valid.');
            }
            
            $stmt_old = $pdo->prepare("SELECT photo_url FROM candidates WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();
            $photo_url = $old_data['photo_url'] ?? '';
            
            $new_photo = handle_photo_upload();
            if ($new_photo) {
                $photo_url = $new_photo;
            }
            
            $stmt = $pdo->prepare("UPDATE candidates SET candidate_number = ?, name = ?, vision = ?, mission = ?, proker = ?, photo_url = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$candidate_number, $name, $vision, $mission, $proker, $photo_url, $is_active, $id]);
            $success = 'Data kandidat berhasil diperbarui.';
        }
        elseif ($action === 'delete_candidate') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Kandidat berhasil dihapus.';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$candidates = $pdo->query("SELECT * FROM candidates ORDER BY candidate_number ASC")->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Paslon OSIS - <?= h(APP_NAME) ?></title>
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
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Header / Nav -->
    <header class="w-100 py-3 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom bg-white shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-users-rectangle fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark"><?= h(APP_NAME) ?> — KELOLA PASLON</h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Panel Panitia</small>
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
        <div class="w-100 mx-auto" style="max-width: 1100px;">
            
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small mb-4 shadow-sm"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div id="success-alert" class="alert alert-success py-2 px-3 small mb-4 shadow-sm"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="card p-4 shadow-sm mb-4 d-flex flex-row justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h5 fw-bold text-dark mb-1">Daftar Pasangan Calon</h2>
                    <p class="text-muted small mb-0">Kelola data seluruh paslon beserta visi, misi, dan program kerjanya.</p>
                </div>
                <button type="button" class="btn btn-primary fw-semibold px-4" onclick="const modal = new bootstrap.Modal(document.getElementById('addModal')); modal.show();">
                    <i class="fa-solid fa-plus me-1"></i> Tambah Paslon
                </button>
            </div>

            <!-- Tabel Data -->
            <div class="card p-4 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3">No</th>
                                <th class="py-3">Nama Paslon</th>
                                <th class="py-3">Foto</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $c): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sprintf('%02d', $c['candidate_number']) ?></td>
                                    <td class="fw-semibold text-dark"><?= h($c['name']) ?></td>
                                    <td>
                                        <?php if (!empty($c['photo_url'])): ?>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-underline fw-semibold" onclick="openPhotoModal('../<?= h($c['photo_url']) ?>', '<?= h($c['name']) ?>')">Lihat Foto</button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['is_active']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-warning btn-sm px-3 fw-semibold text-dark" onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">Edit</button>
                                            
                                            <form method="post" onsubmit="return confirm('Yakin ingin menghapus paslon ini?');" class="m-0">
                                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                                <input type="hidden" name="action" value="delete_candidate">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($candidates)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada data paslon.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL TAMBAH KANDIDAT -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <div class="modal-header border-0 pb-0">
                    <h3 class="modal-title h5 fw-bold">Tambah Paslon Baru</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="add_candidate">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">No. Urut</label>
                                <input type="number" name="candidate_number" class="form-control" required placeholder="1">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small fw-bold">Nama Paslon</label>
                                <input type="text" name="name" class="form-control" required placeholder="Nama Ketua & Wakil">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Visi</label>
                            <textarea name="vision" class="form-control" rows="2" placeholder="Tuliskan visi paslon..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Misi</label>
                            <textarea name="mission" class="form-control" rows="3" placeholder="Tuliskan poin-poin misi paslon..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Program Kerja (Proker)</label>
                            <textarea name="proker" class="form-control" rows="3" placeholder="Tuliskan program kerja paslon..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Upload Foto Paslon</label>
                            <input type="file" name="photo" accept="image/*" class="form-control">
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="addActiveCheck" checked>
                            <label class="form-check-label" for="addActiveCheck">Status Aktif</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Simpan Kandidat</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT KANDIDAT -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <div class="modal-header border-0 pb-0">
                    <h3 class="modal-title h5 fw-bold">Edit Data Paslon</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="action" value="edit_candidate">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">No. Urut</label>
                                <input type="number" name="candidate_number" id="edit_candidate_number" class="form-control" required>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small fw-bold">Nama Paslon</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Visi</label>
                            <textarea name="vision" id="edit_vision" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Misi</label>
                            <textarea name="mission" id="edit_mission" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Program Kerja (Proker)</label>
                            <textarea name="proker" id="edit_proker" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Ganti Foto Paslon (Opsional)</label>
                            <input type="file" name="photo" accept="image/*" class="form-control">
                            <small class="text-muted d-block mt-1">Biarkan kosong jika tidak ingin mengubah foto.</small>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Status Aktif</label>
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark">Perbarui Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT FOTO -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 p-3 text-center">
                <div class="modal-header border-0 pb-0">
                    <h3 id="photoModalTitle" class="modal-title h6 fw-bold">Foto Paslon</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img id="modalImageSrc" src="" alt="Foto Paslon" class="img-fluid rounded-3 bg-light border p-2" style="max-height: 350px; object-fit: contain; width: 100%;">
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
        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_candidate_number').value = data.candidate_number;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_vision').value = data.vision || '';
            document.getElementById('edit_mission').value = data.mission || '';
            document.getElementById('edit_proker').value = data.proker || '';
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        function openPhotoModal(imageUrl, candidateName) {
            document.getElementById('modalImageSrc').src = imageUrl;
            document.getElementById('photoModalTitle').innerText = 'Foto — ' + candidateName;
            
            const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
            photoModal.show();
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            const successAlert = document.getElementById('success-alert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>