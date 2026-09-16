<?php
require_once __DIR__ . '/../config/config.php';
require_student();

$pdo = db();

// Cek status pemilihan dibuka/ditutup
$open = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'election_open'")->fetchColumn() === '1';

// Cek status apakah pemilih sudah memilih
$stmt_voter = $pdo->prepare("SELECT has_voted FROM voters WHERE id = ?");
$stmt_voter->execute([$_SESSION['student_id']]);
$has_voted = (bool) $stmt_voter->fetchColumn();

// Ambil daftar kandidat yang aktif
$candidates = $pdo->query("SELECT * FROM candidates WHERE is_active = 1 ORDER BY candidate_number")->fetchAll();

// Ambil pesan error jika ada
$error = $_SESSION['vote_error'] ?? '';
unset($_SESSION['vote_error']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pencoblosan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div>
            <div class="brand"><?= h(APP_NAME) ?></div>
            <div class="sub">Pencoblosan Digital</div>
        </div>
        <form action="logout.php" method="post" style="margin: 0;">
            <button class="secondary" style="width: auto">Keluar</button>
        </form>
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
            <form method="post" action="submit_vote.php" onsubmit="return confirmVote()">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                
                <div class="card">
                    <h2>Pilih Calon Ketua OSIS</h2>
                    <p class="muted">Pilih salah satu calon di bawah ini.</p>
                    
                    <div class="grid">
                        <?php foreach ($candidates as $c): ?>
                            <label class="candidate">
                                <input type="radio" name="candidate_id" value="<?= $c['id'] ?>" required style="display: none" onchange="pick(this)">
                                
                                <?php if (!empty($c['photo_url'])): ?>
                                    <img src="../public/<?= h($c['photo_url']) ?>" alt="Foto calon">
                                <?php else: ?>
                                    <div style="aspect-ratio: 1/1; border-radius: 10px; background: #dfe7f2; display: grid; place-items: center; font-size: 30px; font-weight: 800; color: #123b73">
                                        <?= sprintf('%02d', $c['candidate_number']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="num">NOMOR URUT <?= sprintf('%02d', $c['candidate_number']) ?></div>
                                <div class="name"><?= h($c['name']) ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" style="margin-top: 20px;">Lanjutkan</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <script>
        function pick(element) {
            document.querySelectorAll('.candidate').forEach(el => el.classList.remove('selected'));
            element.closest('.candidate').classList.add('selected');
        }

        function confirmVote() {
            const checkedRadio = document.querySelector('input[name=candidate_id]:checked');
            if (!checkedRadio) return false;
            return confirm('Pastikan pilihan Anda benar. Setelah dikirim, suara tidak dapat diubah. Kirim suara sekarang?');
        }
    </script>
</body>
</html>