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

// Ambil pesan error jika ada
$error =$_SESSION['vote_error'] ?? '';
unset($_SESSION['vote_error']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pencoblosan</title>
    <link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Penyesuaian Grid agar baris sisa 1 atau 2 berada di tengah */
        .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
        }

        .candidate-wrapper {
            flex: 0 1 280px;
            display: flex;
            flex-direction: column;
        }

        /* --- Shimmer Loading Effect (Animasi Kerlip Abu-abu) --- */
        .shimmer-wrapper {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
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
            background: #e9eef7;
            color: #1769e0;
            border: 1px solid #ccd4e0;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s;
            text-decoration: none;
            width: 100%;
        }
        .btn-vision:hover {
            background: #1769e0;
            color: #fff;
            border-color: #1769e0;
        }

        /* Styling untuk tombol submit agar terlihat disabled saat belum dipilih */
        button[type="submit"]:disabled {
            background-color: #ccd4e0;
            color: #a0aec0;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(23, 32, 51, 0.55); 
            backdrop-filter: blur(2px);
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
            border-radius: 16px; 
            position: relative; 
			text-align: justify;
			line-height: 1.6;
            box-shadow: 0 12px 35px rgba(20, 50, 90, 0.15); 
            animation: modalScaleIn 0.25s ease-out;
        }
        .close-btn { 
            position: absolute; 
            right: 20px; 
            top: 15px; 
            font-size: 24px; 
            font-weight: bold; 
            cursor: pointer; 
            color: #667085; 
            border: none;
            background: none;
            width: auto;
            padding: 0;
        }
        .close-btn:hover { color: #172033; }
        
        .modal-body h3 { margin-top: 0; color: #17365f; }
        .modal-body h4 { margin-bottom: 5px; color: #2d3748; }
        .modal-body p, .modal-body ul { font-size: 14px; color: #4a5568; line-height: 1.6; text-align: justify; }
        .modal-body ul { padding-left: 20px; margin-top: 5px; }

        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?></div>
            <div class="sub">Pencoblosan Digital</div>
        </div>
        <?php if (!$open || $has_voted):?>
        <form action="logout.php" method="post" style="margin: 0;">
            <button class="secondary" style="width: auto">Keluar</button>
        </form>
        <?php endif;?>
    </header>
    
    <main>
        <!-- Informasi Pengguna -->
        <div class="card">
            <h2>Selamat datang, <?= h($_SESSION['student_name']) ?></h2>
            <p>
                <?= ($_SESSION['voter_type'] ?? 'student') === 'teacher' 
                    ? 'Kategori: <b>Guru</b>' 
                    : 'Kelas: <b>' . h($_SESSION['class_name']) . '</b>' 
                ?>
            </p>
        </div>

        <!-- Pesan Error -->
        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Konten Berdasarkan Status Pemilihan / Hak Pilih -->
        <?php if (!$open): ?>
            <div class="card">
                <h2>Pemilihan belum dibuka</h2>
                <p class="muted">Silakan menunggu arahan panitia.</p>
            </div>
        <?php elseif ($has_voted): ?>
            <div class="card">
                <h2>Suara sudah direkam</h2>
                <p>Anda telah menggunakan hak pilih. Akun ini tidak dapat memilih lagi.</p>
                <span class="badge" style="margin-top: 10px;">SUDAH MEMILIH</span>
            </div>
        <?php else: ?>
            <form method="post" action="submit_vote.php" id="voteForm" onsubmit="return confirmVote()">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <div class="card">
                    <h2>Pilih Calon Ketua OSIS</h2>
                    <p class="muted">Pilih salah satu calon di bawah ini. Anda dapat melihat Visi & Misi terlebih dahulu.</p>
                    
                    <div class="grid">
                        <?php foreach ($candidates as$c): ?>
                            <div class="candidate-wrapper">
                                <label class="candidate" style="flex: 1; cursor: pointer; display: flex; flex-direction: column;">
                                    <input type="radio" name="candidate_id" value="<?= $c['id'] ?>" required style="display: none" onchange="pick(this)">
                                    
                                    <?php if (!empty($c['photo_url'])): ?>
                                        <!-- Penerapan Shimmer Loading -->
                                        <div class="shimmer-wrapper">
                                            <img src="../<?= h($c['photo_url']) ?>" alt="Foto calon" onload="this.classList.add('loaded')">
                                        </div>
                                    <?php else: ?>
                                        <div style="aspect-ratio: 1/1; border-radius: 10px; background: #dfe7f2; display: grid; place-items: center; font-size: 30px; font-weight: 800; color: #123b73">
                                            <?= sprintf('%02d', $c['candidate_number']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="num">NOMOR URUT <?= sprintf('%02d', $c['candidate_number']) ?></div>
                                    <div class="name"><?= h($c['name']) ?></div>
                                </label>
                                
                                <!-- Tombol Lihat Visi Misi -->
                                <button type="button" class="btn-vision" onclick="openVisionModal(<?= $c['candidate_number'] ?>, '<?= h(addslashes($c['name'])) ?>', `<?= nl2br(h($c['vision'] ?? 'Belum diisi')) ?>`, `<?= nl2br(h($c['mission'] ?? 'Belum diisi')) ?>`, `<?= nl2br(h($c['proker'] ?? 'Belum diisi')) ?>`)">
                                    <i class="fa-solid fa-file-lines"></i> Lihat Visi & Misi
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Tombol Lanjutkan dinonaktifkan secara default -->
                    <button type="submit" id="submitBtn" style="margin-top: 25px;" disabled>Lanjutkan</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <!-- MODAL VISI & MISI -->
    <div id="visionModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close-btn" onclick="closeModal('visionModal')">&times;</button>
            <div class="modal-body">
                <h3 id="modalCandidateTitle">Visi & Misi Kandidat</h3>
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
                
                <h4>VISI :</h4>
                <div id="modalVision">-</div>
                
                <h4 style="margin-top: 15px;">MISI :</h4>
                <div id="modalMission">-</div>
                
                <h4 style="margin-top: 15px;">PROGRAM KERJA :</h4>
                <div id="modalProker">-</div>
            </div>
        </div>
    </div>

    <script>
        function pick(element) {
            // Hapus class selected dari semua kandidat
            document.querySelectorAll('.candidate').forEach(el => el.classList.remove('selected'));
            // Tambahkan class selected ke kandidat yang dipilih
            element.closest('.candidate').classList.add('selected');
            
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