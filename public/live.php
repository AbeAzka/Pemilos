<?php 
require_once __DIR__.'/../config/config.php'; 
$pdo=db();

$studentWeight=(float)$pdo->query("SELECT setting_value FROM settings WHERE setting_key='student_weight'")->fetchColumn();
$teacherWeight=(float)$pdo->query("SELECT setting_value FROM settings WHERE setting_key='teacher_weight'")->fetchColumn();
$studentVotes=(int)$pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type='student'")->fetchColumn();
$teacherVotes=(int)$pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type='teacher'")->fetchColumn();
$totalVotes=$studentVotes+$teacherVotes;

$results=$pdo->query("SELECT c.candidate_number,c.name,c.photo_url,
    SUM(CASE WHEN v.voter_type='student' THEN 1 ELSE 0 END) student_n,
    SUM(CASE WHEN v.voter_type='teacher' THEN 1 ELSE 0 END) teacher_n 
    FROM candidates c 
    LEFT JOIN votes v ON v.candidate_id=c.id AND v.voter_type IN ('student','teacher') 
    WHERE c.is_active=1 GROUP BY c.id ORDER BY c.candidate_number")->fetchAll();

foreach($results as &$r){
    $r['student_pct']=$studentVotes?($r['student_n']/$studentVotes*100):0;
    $r['teacher_pct']=$teacherVotes?($r['teacher_n']/$teacherVotes*100):0;
    $r['weighted']=($r['student_pct']*$studentWeight/100)+($r['teacher_pct']*$teacherWeight/100);
}
unset($r);
$updated=date('H:i:s');

// Opsi Tampilan: bar, gauge, ring
$allowedViews = ['bar', 'gauge', 'ring'];
$viewType = isset($_GET['view']) && in_array($_GET['view'], $allowedViews) ? $_GET['view'] : 'ring';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="refresh" content="60;url=?view=<?=h($viewType)?>">
    <title>LIVE RESULT — <?=h(APP_NAME)?></title>
    
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS Variables untuk Tema Gelap Tetap */
        :root {
            --bg: #081426;
            --text: #ffffff;
            --card-bg: #10233f;
            --border: #284566;
            --primary: #55a7ff;
            --track: #263c58;
            --mini-bg: rgba(11, 26, 46, 0.85);
            --shimmer-1: #162c4a;
            --shimmer-2: #1f3b60;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: sans-serif;
            margin: 0;
        }
        
        .live { position: relative; max-width: 1400px; margin: auto; padding: 30px; }
        
        /* Header Utama */
        .live-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
        }
        .live-head img {
            height: 90px; 
            width: auto;
            object-fit: contain;
        }
        .live-head-content {
            flex-grow: 1;
        }
        .live-title { font-size: clamp(18px, 4vw, 24px); font-weight: 900; margin: 0; }
        .live-sub { font-size: clamp(14px, 1.5vw, 22px); opacity: .8; margin-top: 5px; }
        .weight { font-size: 15px; margin-top: 6px; opacity: 0.9; }
        
        /* Switcher Tampilan */
        .view-switcher { display: flex; justify-content: center; gap: 10px; margin: 20px 0 30px; }
        .view-switcher a { 
            background: var(--card-bg); 
            color: var(--text); 
            text-decoration: none; 
            padding: 8px 16px; 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            font-size: 14px; 
            transition: 0.2s;
        }
        .view-switcher a:hover { background: var(--track); }
        .view-switcher a.active { 
            background: var(--primary); 
            color: #081426; 
            font-weight: bold; 
            border-color: var(--primary); 
        }

        .live-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        
        /* Kartu Utama */
        .live-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 26px;
            display: flex;
            flex-direction: column;
            text-align: center;
            position: relative;
            overflow: hidden;
            /* Tinggi kartu ditambah agar bagian ring bisa turun lebih jauh ke bawah */
            min-height: 560px; 
        }

        /* Container Foto Paslon Background */
        .card-bg-wrapper {
            position: absolute;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
        }
        .card-bg-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 10%; /* Lebih fokus ke area wajah/kepala */
            opacity: 0;
            transition: opacity 0.8s ease;
        }
        .card-bg-photo.loaded {
            opacity: 1; 
        }

        /* Gradient Masking diturunkan */
        .card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom, 
                rgba(16, 35, 63, 0) 0%,      
                rgba(16, 35, 63, 0) 45%,     /* Transparan dipertahankan lebih jauh ke bawah */
                rgba(16, 35, 63, 0.9) 80%,   /* Mulai menggelap di area perut/dada */
                var(--card-bg) 100%          
            );
            z-index: 1;
        }

        /* Shimmer Animation */
        .shimmer {
            background: linear-gradient(90deg, var(--shimmer-1) 0%, var(--shimmer-2) 50%, var(--shimmer-1) 100%);
            background-size: 200% 100%;
            animation: shimmerEffect 1.5s infinite linear;
        }
        @keyframes shimmerEffect {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Layer Konten */
        .card-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .live-number { font-size: 22px; font-weight: 800; color: var(--primary); text-shadow: 0 2px 10px #081426, 0 0 5px #081426; }
        .live-name { font-size: clamp(22px, 3vw, 38px); font-weight: 900; margin: 8px 0 22px; text-shadow: 0 2px 10px #081426, 0 0 5px #081426; }
        
        /* Container Visual didorong paling bawah */
        .visual-container { 
            text-align: center; 
            margin-top: auto; /* Mendorong dari atas */
            margin-bottom: 8px; /* Margin diperkecil agar sangat dekat dengan kotak breakdown di bawahnya */
        }

        .breakdown { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        
        .mini { background: var(--mini-bg); border-radius: 14px; padding: 14px; text-align: center; backdrop-filter: blur(4px); box-shadow: 0 4px 6px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.1); }
        .mini span { display: block; opacity: .75; font-size: 14px; }
        .mini b { font-size: 24px; }
        .footer { text-align: center; margin-top: 28px; opacity: .75; }
        
        /* Bar Style */
        .big { font-size: clamp(34px, 7vw, 74px); font-weight: 900; text-align: center; text-shadow: 0 2px 6px rgba(0,0,0,0.8); }
        .bar { height: 25px; background: var(--track); margin-top: 16px; border-radius: 12px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3); }
        .fill { background: var(--primary); height: 100%; transition: width 0.5s ease; }

        /* Gauge Style */
        .gauge-wrapper { position: relative; width: 100%; max-width: 250px; margin: 0 auto; }
        .gauge-svg { width: 100%; overflow: visible; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5)); }
        .gauge-text { position: absolute; bottom: 0; left: 0; width: 100%; text-align: center; font-size: clamp(24px, 4vw, 40px); font-weight: 900; line-height: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }

        /* Ring Style - max width dijaga agar proporsional */
        .ring-wrapper { position: relative; width: 100%; max-width: 160px; margin: 0 auto; }
        .ring-svg { width: 100%; overflow: visible; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5)); }
        .ring-text { position: absolute; top: 50%; left: 0; width: 100%; transform: translateY(-50%); text-align: center; font-size: clamp(24px, 4vw, 34px); font-weight: 900; line-height: 1; text-shadow: 0 2px 6px rgba(0,0,0,0.8); }

        @media(max-width:800px){ .live-grid{grid-template-columns:1fr} }
    </style>
</head>
<body>
    <main class="live">
        <!-- Header Utama -->
        <div class="live-head">
            <img src="/admin/images/logo1.png" alt="Logo Kiri">
            <div class="live-head-content">
                <div class="live-title">HASIL PENGHITUNGAN SUARA</div>
                <div class="live-sub"><?=h(APP_NAME)?></div>
                <div class="weight">Bobot: Siswa <?=$studentWeight?>% · Guru <?=$teacherWeight?>%</div>
            </div>
            <img src="/admin/images/logo2.png" alt="Logo Kanan">
        </div>

        <div class="view-switcher">
            <a href="?view=bar" class="<?= $viewType === 'bar' ? 'active' : '' ?>">Progress Bar</a>
            <a href="?view=gauge" class="<?= $viewType === 'gauge' ? 'active' : '' ?>">Gauge Meter</a>
            <a href="?view=ring" class="<?= $viewType === 'ring' ? 'active' : '' ?>">Ring Circle</a>
        </div>

        <div class="live-grid">
            <?php foreach($results as $r): 
                $p = min(100, max(0, $r['weighted'])); // Pastikan persentase 0-100
            ?>
            <section class="live-card">
                
                <!-- Background Foto Paslon -->
                <div class="card-bg-wrapper shimmer">
                    <?php if(!empty($r['photo_url'])): ?>
                        <img src="<?=h($r['photo_url'])?>" class="card-bg-photo" alt="Foto <?=h($r['name'])?>" onload="this.classList.add('loaded'); this.parentElement.classList.remove('shimmer');">
                    <?php endif; ?>
                    <div class="card-overlay"></div>
                </div>

                <!-- Konten Kartu (Di atas overlay) -->
                <div class="card-content">
                    <div class="live-number">NOMOR URUT <?=sprintf('%02d',$r['candidate_number'])?></div>
                    <div class="live-name"><?=h($r['name'])?></div>
                    
                    <!-- Grafik persentase (Sekarang lebih mepet ke bawah) -->
                    <div class="visual-container">
                        <?php if ($viewType === 'gauge'): 
                            $dash = 125.66;
                            $offset = $dash - ($dash * $p / 100);
                        ?>
                            <div class="gauge-wrapper">
                                <svg viewBox="0 0 100 55" class="gauge-svg">
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="var(--track)" stroke-width="12" stroke-linecap="round" />
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="var(--primary)" stroke-width="12" stroke-linecap="round"
                                          stroke-dasharray="<?= $dash ?>" stroke-dashoffset="<?= $offset ?>" />
                                </svg>
                                <div class="gauge-text"><?=number_format($r['weighted'], 2, ',', '.')?>%</div>
                            </div>

                        <?php elseif ($viewType === 'ring'): 
                            $dash = 251.32; 
                            $offset = $dash - ($dash * $p / 100);
                        ?>
                            <div class="ring-wrapper">
                                <svg viewBox="0 0 100 100" class="ring-svg">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="var(--track)" stroke-width="10" />
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="var(--primary)" stroke-width="10" stroke-linecap="round"
                                            stroke-dasharray="<?= $dash ?>" stroke-dashoffset="<?= $offset ?>" transform="rotate(-90 50 50)" />
                                </svg>
                                <div class="ring-text"><?=number_format($r['weighted'], 2, ',', '.')?>%</div>
                            </div>

                        <?php else: ?>
                            <!-- Default Bar -->
                            <div class="big"><?=number_format($r['weighted'], 2, ',', '.')?>%</div>
                            <div class="bar">
                                <div class="fill" style="width:<?=$p?>%"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="breakdown">
                        <div class="mini">
                            <span>Suara Siswa</span>
                            <b><?=number_format($r['student_pct'], 2, ',', '.')?>%</b>
                            <small>(<?=$r['student_n']?> suara)</small>
                        </div>
                        <div class="mini">
                            <span>Suara Guru</span>
                            <b><?=number_format($r['teacher_pct'], 2, ',', '.')?>%</b>
                            <small>(<?=$r['teacher_n']?> suara)</small>
                        </div>
                    </div>
                </div>

            </section>
            <?php endforeach;?>
        </div>
        
        <div class="footer">Total suara: <?=$totalVotes?> · Pembaruan otomatis · <?=h($updated)?></div>
    </main>
</body>
</html>