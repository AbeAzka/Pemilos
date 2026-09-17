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
$viewType = isset($_GET['view']) && in_array($_GET['view'], $allowedViews) ? $_GET['view'] : 'gauge';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Meta refresh mempertahankan parameter URL view -->
    <meta http-equiv="refresh" content="60;url=?view=<?=h($viewType)?>">
    <title>LIVE RESULT — <?=h(APP_NAME)?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body{background:#081426;color:#fff;font-family:sans-serif;}
        .live{max-width:1400px;margin:auto;padding:30px}
        
        /* Header Utama dengan Logo Kiri & Kanan mengapit judul */
        .live-head{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            text-align: center;
            border-bottom: 1px solid #284566;
            padding-bottom: 20px;
        }
        .live-head img {
            height: 90px; /* Ukuran logo di header utama */
            width: auto;
            object-fit: contain;
        }
        .live-head-content {
            flex-grow: 1;
        }
        .live-title{font-size:clamp(18px,4vw,24px);font-weight:900; margin: 0;}
        .live-sub{font-size:clamp(14px,1.5vw,22px);opacity:.8; margin-top: 5px;}
        .weight{font-size:15px;margin-top:6px; opacity: 0.9;}
        
        /* Switcher Tampilan */
        .view-switcher { display: flex; justify-content: center; gap: 10px; margin: 20px 0 30px; }
        .view-switcher a { background: #10233f; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 20px; border: 1px solid #284566; font-size: 14px; transition: 0.2s;}
        .view-switcher a:hover { background: #263c58; }
        .view-switcher a.active { background: #55a7ff; color: #081426; font-weight: bold; border-color: #55a7ff; }

        .live-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
        
        /* Kartu Utama */
        .live-card{
            background:#10233f;
            border:1px solid #284566;
            border-radius:22px;
            padding:26px;
            display:flex;
            flex-direction:column;
            position: relative;
            overflow: hidden; /* Agar foto background tidak keluar border kartu */
        }

        /* Watermark Foto Paslon di Belakang */
        .card-bg-photo {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.20; /* Tingkat transparansi watermark (0.0 - 1.0) */
            z-index: 1;
            pointer-events: none;
        }

        /* Pastikan konten di dalam kartu berada di atas background foto */
        .live-number, .live-name, .visual-container, .breakdown {
            position: relative;
            z-index: 2;
        }

        .live-number{font-size:22px;font-weight:800}
        .live-name{font-size:clamp(22px,3vw,38px);font-weight:900;margin:8px 0 22px}
        
        .breakdown{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:auto;padding-top:24px;}
        .mini{background:rgba(11, 26, 46, 0.85);border-radius:14px;padding:14px;text-align:center; backdrop-filter: blur(4px);}
        .mini span{display:block;opacity:.75;font-size:14px}
        .mini b{font-size:24px}
        .footer{text-align:center;margin-top:28px;opacity:.75}
        
        /* Bar Style */
        .big{font-size:clamp(44px,7vw,84px);font-weight:900;text-align:center}
        .bar{height:25px;background:#263c58;margin-top:16px;border-radius:12px;overflow:hidden;}
        .fill{background:#55a7ff;height:100%;transition:width 0.5s ease;}

        /* Visual Wrappers */
        .visual-container { text-align: center; margin-bottom: 10px; }
        
        /* Gauge Style */
        .gauge-wrapper { position: relative; width: 100%; max-width: 300px; margin: 0 auto; }
        .gauge-svg { width: 100%; overflow: visible; }
        .gauge-text { position: absolute; bottom: 0; left: 0; width: 100%; text-align: center; font-size: clamp(34px, 5vw, 50px); font-weight: 900; line-height: 1; }

        /* Ring Style */
        .ring-wrapper { position: relative; width: 100%; max-width: 220px; margin: 0 auto; }
        .ring-svg { width: 100%; overflow: visible; }
        .ring-text { position: absolute; top: 50%; left: 0; width: 100%; transform: translateY(-50%); text-align: center; font-size: clamp(30px, 4vw, 48px); font-weight: 900; line-height: 1; }

        @media(max-width:800px){.live-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <main class="live">
        <!-- Header Utama: Logo Kiri - Teks Judul - Logo Kanan -->
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
                <!-- Foto Paslon sebagai background semi-transparan -->
                <?php if(!empty($r['photo_url'])): ?>
                    <img src="<?=h($r['photo_url'])?>" class="card-bg-photo" alt="Foto <?=h($r['name'])?>">
                <?php endif; ?>

                <div class="live-number">NOMOR URUT <?=sprintf('%02d',$r['candidate_number'])?></div>
                <div class="live-name"><?=h($r['name'])?></div>
                
                <div class="visual-container">
                    <?php if ($viewType === 'gauge'): 
                        $dash = 125.66;
                        $offset = $dash - ($dash * $p / 100);
                    ?>
                        <div class="gauge-wrapper">
                            <svg viewBox="0 0 100 55" class="gauge-svg">
                                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#263c58" stroke-width="12" stroke-linecap="round" />
                                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#55a7ff" stroke-width="12" stroke-linecap="round"
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
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#263c58" stroke-width="10" />
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#55a7ff" stroke-width="10" stroke-linecap="round"
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
            </section>
            <?php endforeach;?>
        </div>
        
        <div class="footer">Total suara: <?=$totalVotes?> · Pembaruan otomatis · <?=h($updated)?></div>
    </main>
</body>
</html>