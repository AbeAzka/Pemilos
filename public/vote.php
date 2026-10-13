<?php
require_once __DIR__ . '/../config/config.php';
require_student();

$pdo = db();

// Cek status pemilihan dibuka/ditutup
$open =$pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'election_open'")->fetchColumn() === '1';

// Cek status apakah pemilih sudah memilih
$stmt_voter =$pdo->prepare("SELECT has_voted FROM voters WHERE id = ?");
$stmt_voter->execute([$_SESSION['student_id']]);
$has_voted = (bool)$stmt_voter->fetchColumn();

// Ambil daftar kandidat yang aktif
$candidates =$pdo->query("SELECT * FROM candidates WHERE is_active = 1 ORDER BY candidate_number")->fetchAll();
$vote_title =$pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vote_title'")->fetchColumn() ?: 'PILIH CALON KETUA OSIS';
// Ambil pesan error jika ada
$error =$_SESSION['vote_error'] ?? '';
unset($_SESSION['vote_error']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pencoblosan - <?= h(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Penyesuaian Grid agar baris sisa 1 atau 2 berada di tengah */
        .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .candidate-wrapper {
            flex: 0 1 280px;
            display: flex;
            flex-direction: column;
        }

        /* Styling Kartu Kandidat */
        .candidate-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            padding: 15px;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .candidate-card:hover {
            border-color: #4f46e5;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.1);
        }

        /* Kondisi ketika kandidat dipilih */
        input[type="radio"]:checked + .candidate-card {
            border-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.03);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }

        /* --- Shimmer Loading Effect (Animasi Kerlip Abu-abu) --- */
        .shimmer-wrapper {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 0.75rem;
            background: #f6f7f8;
            background-image: linear-gradient(
                to right,
                #f6f7f8 0%,
                #edeef1 20%,
                #f6f7f8 40%,
                #f6f7f8 100%
            );
            background-repeat: no-repeat;
            background-size: 200% 100%; 
            animation: shimmer 1.5s infinite linear;
            position: relative;
            overflow: hidden;
        }
        
        .shimmer-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0; /* Mulai dengan foto transparan (tak terlihat) */
            transition: opacity 0.3s ease-in-out;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .shimmer-wrapper img.loaded {
            opacity: 1; /* Tampilkan foto setelah berhasil di-load */
        }

        @keyframes shimmer {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
        /* ----------------------------------------------------- */

        /* Tombol & Ikon Visi Misi */
        .btn-vision {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: #eef2ff;
            color: #4f46e5;
            border: 1px solid #c7d2fe;
            padding: 8px 12px;
            border-radius: 0.5rem;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s;
            text-decoration: none;
            width: 100%;
        }
        .btn-vision:hover {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }

        /* Styling untuk tombol submit agar terlihat disabled saat belum dipilih */
        button[type="submit"]:disabled {
            background-color: #cbd5e1 !important;
            border-color: #cbd5e1 !important;
            color: #64748b !important;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Modal Styles yang disesuaikan dengan Bootstrap */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1050; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(4px);
            align-items: center; 
            justify-content: center; 
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-content { 
            background: #fff; 
            padding: 25px; 
            width: 90%; 
            max-width: 550px; 
            max-height: 85vh;
            overflow-y: auto;
            border-radius: 1rem; 
            position: relative; 
            text-align: justify;
            line-height: 1.6; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
            animation: modalScaleIn 0.25s ease-out;
        }
        .close-btn { 
            position: absolute; 
            right: 20px; 
            top: 15px; 
            font-size: 24px; 
            font-weight: bold; 
            cursor: pointer; 
            color: #64748b; 
            border: none;
            background: none;
            width: auto;
            padding: 0;
        }
        .close-btn:hover { color: #0f172a; }
        
        .modal-body h3 { margin-top: 0; color: #1e293b; font-size: 1.25rem; font-weight: 700; }
        .modal-body h4 { margin-bottom: 5px; color: #475569; font-size: 0.9rem; font-weight: 700; letter-spacing: 0.05em; }
        .modal-body div { font-size: 14px; color: #334155; line-height: 1.6; text-align: justify; }

        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-between text-dark">

    <!-- Header / Nav (Konsisten dengan halaman login) -->
    <header class="w-100 py-4 px-4 px-md-5 d-flex align-items-center justify-content-between border-bottom border-secondary-subtle bg-white bg-opacity-75 shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; background-color: #4f46e5;">
                <i class="fa-solid fa-box-archive fs-5"></i>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold text-dark tracking-wide"><?= h(APP_NAME) ?></h1>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Pencoblosan Digital</small>
            </div>
        </div>

        <?php if (!$open || $has_voted): ?>
        <form action="logout.php" method="post" class="m-0">
            <button class="btn btn-outline-secondary btn-sm px-3">Keluar</button>
        </form>
        <?php endif; ?>
    </header>
    
    <!-- Main Content -->
    <main class="flex-grow-1 d-flex flex-column align-items-center justify-content-center px-3 py-5">
        <div class="w-100" style="max-width: 900px;">
            
            <!-- Informasi Pengguna -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <h2 class="h5 fw-bold text-dark mb-1">Selamat datang, <?= h($_SESSION['student_name']) ?></h2>
                <p class="text-muted small mb-0">
                    <?= ($_SESSION['voter_type'] ?? 'student') === 'teacher' 
                        ? 'Kategori: <b class="text-dark">Guru</b>' 
                        : 'Kelas: <b class="text-dark">' . h($_SESSION['class_name']) . '</b>' 
                    ?>
                </p>
            </div>

            <!-- Pesan Error -->
            <?php if ($error): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger py-2 px-3 rounded-3 small d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="fa-solid fa-circle-exclamation fs-6 flex-shrink-0"></i>
                    <span><?= h($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Konten Berdasarkan Status Pemilihan / Hak Pilih -->
            <?php if (!$open): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="text-warning mb-3"><i class="fa-solid fa-clock fs-1"></i></div>
                    <h3 class="h4 fw-bold text-dark">Pemilihan belum dibuka</h3>
                    <p class="text-muted small mb-0">Silakan menunggu arahan panitia.</p>
                </div>
            <?php elseif ($has_voted): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="text-success mb-3"><i class="fa-solid fa-circle-check fs-1"></i></div>
                    <h3 class="h4 fw-bold text-dark">Suara sudah direkam</h3>
                    <p class="text-muted small mb-2">Anda telah menggunakan hak pilih. Akun ini tidak dapat memilih lagi.</p>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 mt-2 mx-auto" style="width: fit-content;">SUDAH MEMILIH</span>
                </div>
            <?php else: ?>
                <form method="post" action="submit_vote.php" id="voteForm" onsubmit="return confirmVote()">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                        <div class="text-center mb-4">
                            <h3 class="h4 fw-bold text-dark"><?= h($vote_title) ?></h3>
                            <p class="text-muted small mb-0">Silahkan pilih salah satu calon di bawah ini. Anda dapat melihat Visi & Misi terlebih dahulu.</p>
                        </div>
                        
                        <div class="grid">
                            <?php foreach ($candidates as$c): ?>
                                <div class="candidate-wrapper">
                                    <label style="flex: 1; display: flex; flex-direction: column;">
                                        <input type="radio" name="candidate_id" value="<?= $c['id'] ?>" required class="d-none" onchange="pick(this)">
                                        
                                        <div class="candidate-card h-100 d-flex flex-column justify-content-between">
                                            <div>
                                                <?php if (!empty($c['photo_url'])): ?>
                                                    <!-- Penerapan Shimmer Loading -->
                                                    <div class="shimmer-wrapper mb-3">
                                                        <img src="../<?= h($c['photo_url']) ?>" alt="Foto calon" onload="this.classList.add('loaded')">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="shimmer-wrapper mb-3 d-flex align-items-center justify-content-center text-indigo fw-bold fs-2" style="background: #eef2ff; color: #4f46e5;">
                                                        <?= sprintf('%02d', $c['candidate_number']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="text-uppercase fw-bold text-muted small tracking-wide" style="font-size: 0.7rem;">Nomor Urut <?= sprintf('%02d', $c['candidate_number']) ?></div>
                                                <div class="fw-bold text-dark fs-6 mt-1"><?= h($c['name']) ?></div>
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <!-- Tombol Lihat Visi Misi -->
                                    <button type="button" class="btn-vision" onclick="openVisionModal(<?= $c['candidate_number'] ?>, '<?= h(addslashes($c['name'])) ?>', `<?= nl2br(h($c['vision'] ?? 'Belum diisi')) ?>`, `<?= nl2br(h($c['mission'] ?? 'Belum diisi')) ?>`, `<?= nl2br(h($c['proker'] ?? 'Belum diisi')) ?>`)">
                                        <i class="fa-solid fa-file-lines"></i> Lihat Visi & Misi
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Tombol Lanjutkan dinonaktifkan secara default -->
                        <button type="submit" id="submitBtn" class="btn w-100 py-3 fw-semibold shadow-sm text-white mt-4" style="background-color: #4f46e5;" disabled>Lanjutkan Pilihan</button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-100 py-4 text-center text-muted small border-top border-secondary-subtle bg-white bg-opacity-50">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> & INDODEV STUDIO. Hak Cipta Dilindungi.
    </footer>

    <!-- MODAL VISI & MISI -->
    <div id="visionModal" class="modal">
        <div class="modal-content shadow-lg">
            <button type="button" class="close-btn" onclick="closeModal('visionModal')">&times;</button>
            <div class="modal-body">
                <h3 id="modalCandidateTitle">Visi & Misi Kandidat</h3>
                <hr class="border-secondary-subtle my-3">
                
                <h4 class="text-uppercase text-secondary" style="font-size: 0.75rem;">Visi :</h4>
                <div id="modalVision" class="mb-3">-</div>
                
                <h4 class="text-uppercase text-secondary" style="font-size: 0.75rem;">Misi :</h4>
                <div id="modalMission" class="mb-3">-</div>
                
                <h4 class="text-uppercase text-secondary" style="font-size: 0.75rem;">Program Kerja :</h4>
                <div id="modalProker">-</div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function pick(element) {
            // Hapus class selected dari semua kartu kandidat (jika ada styling tambahan)
            document.querySelectorAll('.candidate-card').forEach(el => el.classList.remove('border-primary'));
            // Tambahkan sorotan ke kartu yang dipilih
            element.closest('label').querySelector('.candidate-card').classList.add('border-primary');
            
            // Aktifkan tombol Lanjutkan
            document.getElementById('submitBtn').disabled = false;
        }

        function confirmVote() {
            const checkedRadio = document.querySelector('input[name=candidate_id]:checked');
            if (!checkedRadio) return false;
            return confirm('Pastikan pilihan Anda benar. Setelah dikirim, suara tidak dapat diubah. Kirim suara sekarang?');
        }

        // Fungsi Kontrol Modal Visi Misi
        function openVisionModal(number, name, vision, mission, proker) {
            document.getElementById('modalCandidateTitle').innerText = `Paslon No. ${String(number).padStart(2, '0')} - ${name}`;
            document.getElementById('modalVision').innerHTML = vision;
            document.getElementById('modalMission').innerHTML = mission;
            document.getElementById('modalProker').innerHTML = proker;
            
            document.getElementById('visionModal').style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Menutup modal jika area di luar kotak putih diklik
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>