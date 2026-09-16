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
        
        // Validasi ekstensi gambar
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_exts)) {
            throw new Exception('Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP.');
        }
        
        // Tentukan direktori tujuan upload ke folder images/ di bawah root admin
        $upload_dir = __DIR__ . '/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $new_filename = 'candidate_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            // Mengembalikan path relatif yang disimpan ke database
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
        
        // Tambah Kandidat Baru
        if ($action === 'add_candidate') {
            $candidate_number = trim($_POST['candidate_number'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($candidate_number === '' || $name === '') {
                throw new Exception('Nomor urut dan nama kandidat wajib diisi.');
            }
            
            $photo_url = handle_photo_upload();
            if (!$photo_url) {
                $photo_url = ''; // Default kosong jika tidak upload
            }
            
            $stmt = $pdo->prepare("INSERT INTO candidates (candidate_number, name, photo_url, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$candidate_number, $name, $photo_url, $is_active]);
            $success = 'Kandidat baru berhasil ditambahkan.';
        }
        
        // Edit Kandidat
        elseif ($action === 'edit_candidate') {
            $id = (int)($_POST['id'] ?? 0);
            $candidate_number = trim($_POST['candidate_number'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || $candidate_number === '' || $name === '') {
                throw new Exception('Data kandidat tidak valid.');
            }
            
            // Ambil foto lama
            $stmt_old = $pdo->prepare("SELECT photo_url FROM candidates WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();
            $photo_url = $old_data['photo_url'] ?? '';
            
            // Cek apakah ada file foto baru yang diunggah
            $new_photo = handle_photo_upload();
            if ($new_photo) {
                $photo_url = $new_photo;
            }
            
            $stmt = $pdo->prepare("UPDATE candidates SET candidate_number = ?, name = ?, photo_url = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$candidate_number, $name, $photo_url, $is_active, $id]);
            $success = 'Data kandidat berhasil diperbarui.';
        }
        
        // Hapus Kandidat
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

// Ambil Semua Data Kandidat
$candidates = $pdo->query("SELECT * FROM candidates ORDER BY candidate_number ASC")->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Paslon OSIS</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* CSS Khusus Penataan Modal & Tombol Close */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(23, 32, 51, 0.55);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .modal-content {
            background-color: #fff;
            padding: 30px 25px 25px 25px;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 12px 35px rgba(20, 50, 90, 0.15);
            position: relative;
            animation: fadeIn 0.3s ease;
        }
        .modal-content.photo-preview {
            max-width: 400px;
            text-align: center;
        }
        .close-btn {
            position: absolute;
            right: 15px;
            top: 15px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #667085;
            background: #f3f6fb;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            transition: background 0.2s, color 0.2s;
        }
        .close-btn:hover {
            background: #eef2f7;
            color: #172033;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?> — KELOLA PASLON</div>
            <div class="sub">Panitia</div>
        </div>
        <div>
            <a href="admin.php" class="secondary" style="display:inline-block; padding:8px 14px; border-radius:10px; background:#e9eef7; color:#17365f; font-weight:700; text-decoration:none; margin-right:8px;">Dashboard</a>
            <form action="admin_logout.php" method="post" style="display:inline;">
                <button class="secondary" style="width: auto">Keluar</button>
            </form>
        </div>
    </header>
    
    <main>
        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div id="success-alert" class="success"><?= h($success) ?></div>
        <?php endif; ?>

        <!-- Tombol Pemicu Modal Tambah -->
        <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin-bottom: 5px;">Daftar Pasangan Calon</h2>
                <p class="muted" style="margin: 0;">Kelola data seluruh paslon yang terdaftar di sistem.</p>
            </div>
            <button type="button" onclick="openModal('addModal')" style="width: auto; padding: 12px 20px;">+ Tambah Paslon</button>
        </div>

        <!-- Tabel Daftar Kandidat -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Paslon</th>
                            <th>Foto</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $c): ?>
                            <tr>
                                <td><b><?= sprintf('%02d', $c['candidate_number']) ?></b></td>
                                <td><?= h($c['name']) ?></td>
                                <td>
                                    <?php if (!empty($c['photo_url'])): ?>
                                        <button type="button" onclick="openPhotoModal('../<?= h($c['photo_url']) ?>', '<?= h($c['name']) ?>')" style="background: none; border: none; color: #1769e0; cursor: pointer; padding: 0; font-weight: bold; text-decoration: underline;">Lihat Foto</button>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['is_active']): ?>
                                        <span class="badge">Aktif</span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: 700;">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <!-- Tombol Edit Modal -->
                                        <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" style="width: auto; padding: 6px 12px; font-size: 13px;">Edit</button>
                                        
                                        <!-- Form Hapus -->
                                        <form method="post" onsubmit="return confirm('Yakin ingin menghapus paslon ini?');" style="margin: 0;">
                                            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                            <input type="hidden" name="action" value="delete_candidate">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="secondary" style="background: #fee2e2; color: #b91c1c; padding: 6px 12px; font-size: 13px; width: auto;">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #667085;">Belum ada data paslon.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL TAMBAH KANDIDAT -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close-btn" onclick="closeModal('addModal')">&times;</button>
            <h2 style="margin-top:0;">Tambah Paslon Baru</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="add_candidate">
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                    <div>
                        <label>No. Urut</label>
                        <input type="number" name="candidate_number" required placeholder="1">
                    </div>
                    <div>
                        <label>Nama Paslon</label>
                        <input type="text" name="name" required placeholder="Nama Ketua & Wakil">
                    </div>
                </div>
                
                <label>Upload Foto Paslon</label>
                <input type="file" name="photo" accept="image/*" style="padding: 9px;">
                
                <div style="margin: 12px 0; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" checked style="width: auto; margin: 0;">
                    <label style="margin: 0; font-weight: normal;">Status Aktif</label>
                </div>
                
                <button type="submit" style="margin-top: 5px;">Simpan Kandidat</button>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT KANDIDAT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close-btn" onclick="closeModal('editModal')">&times;</button>
            <h2 style="margin-top:0;">Edit Data Paslon</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="edit_candidate">
                <input type="hidden" name="id" id="edit_id">
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                    <div>
                        <label>No. Urut</label>
                        <input type="number" name="candidate_number" id="edit_candidate_number" required>
                    </div>
                    <div>
                        <label>Nama Paslon</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                </div>
                
                <label>Ganti Foto Paslon (Opsional)</label>
                <input type="file" name="photo" accept="image/*" style="padding: 9px;">
                <small class="muted" style="display:block; margin-top:-10px; margin-bottom:10px;">Biarkan kosong jika tidak ingin mengubah foto.</small>
                
                <div style="margin: 12px 0; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" id="edit_is_active" style="width: auto; margin: 0;">
                    <label style="margin: 0; font-weight: normal;">Status Aktif</label>
                </div>
                
                <button type="submit" style="margin-top: 5px;">Perbarui Data</button>
            </form>
        </div>
    </div>

    <!-- MODAL LIHAT FOTO -->
    <div id="photoModal" class="modal">
        <div class="modal-content photo-preview">
            <button type="button" class="close-btn" onclick="closeModal('photoModal')">&times;</button>
            <h3 id="photoModalTitle" style="margin-top: 0; margin-bottom: 15px; font-size: 18px;">Foto Paslon</h3>
            <img id="modalImageSrc" src="" alt="Foto Paslon" style="width: 100%; max-height: 350px; object-fit: contain; border-radius: 10px; background: #f3f6fb;">
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_candidate_number').value = data.candidate_number;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            openModal('editModal');
        }

        function openPhotoModal(imageUrl, candidateName) {
            document.getElementById('modalImageSrc').src = imageUrl;
            document.getElementById('photoModalTitle').innerText = 'Foto — ' + candidateName;
            openModal('photoModal');
        }

        // Tutup modal jika user klik di luar kotak modal
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Otomatis menghilangkan notifikasi sukses setelah 5 detik
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