<?php require_once __DIR__.'/../config/config.php'; require_admin();$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$action=$_POST['action']??'';if($action==='toggle'){$new=$_POST['value']==='1'?'0':'1';$s=$pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key='election_open'");$s->execute([$new]);}}
$open=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='election_open'")->fetchColumn()==='1';
$studentWeight=(float)$pdo->query("SELECT setting_value FROM settings WHERE setting_key='student_weight'")->fetchColumn();
$teacherWeight=(float)$pdo->query("SELECT setting_value FROM settings WHERE setting_key='teacher_weight'")->fetchColumn();
$total=(int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$studentTotal=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type='student'")->fetchColumn();
$teacherTotal=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type='teacher'")->fetchColumn();
$voted=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE has_voted=1")->fetchColumn();
$studentVoted=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type='student' AND has_voted=1")->fetchColumn();
$teacherVoted=(int)$pdo->query("SELECT COUNT(*) FROM voters WHERE voter_type='teacher' AND has_voted=1")->fetchColumn();
$studentVotes=(int)$pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type='student'")->fetchColumn();
$teacherVotes=(int)$pdo->query("SELECT COUNT(*) FROM votes WHERE voter_type='teacher'")->fetchColumn();
$results=$pdo->query("SELECT c.id,c.candidate_number,c.name,c.photo_url,
 SUM(CASE WHEN v.voter_type='student' THEN 1 ELSE 0 END) student_n,
 SUM(CASE WHEN v.voter_type='teacher' THEN 1 ELSE 0 END) teacher_n
 FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id GROUP BY c.id ORDER BY c.candidate_number")->fetchAll();
foreach($results as &$r){$r['student_pct']=$studentVotes?($r['student_n']/$studentVotes*100):0;$r['teacher_pct']=$teacherVotes?($r['teacher_n']/$teacherVotes*100):0;$r['weighted']=($r['student_pct']*$studentWeight/100)+($r['teacher_pct']*$teacherWeight/100);}unset($r);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="5"><title>Dashboard</title><link rel="stylesheet" href="style.css"></head><body><header><div><div class="brand"><?=h(APP_NAME)?> — DASHBOARD</div><div class="sub">Panitia</div></div><form action="admin_logout.php" method="post"><button class="secondary" style="width:auto">Keluar</button></form></header><main>
<div class="card"><h2>Status Pemilihan</h2><p><b><?= $open?'DIBUKA':'DITUTUP' ?></b></p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="value" value="<?=$open?'1':'0'?>"><button><?= $open?'Tutup Pemilihan':'Buka Pemilihan' ?></button></form></div>
<div class="row"><div class="stat">Total Pemilih<b><?=$total?></b></div><div class="stat">Siswa<b><?=$studentTotal?></b></div><div class="stat">Guru<b><?=$teacherTotal?></b></div><div class="stat">Sudah Memilih<b><?=$voted?></b></div></div>
<div class="card"><h2>Komposisi Suara</h2><p class="muted">Bobot hasil akhir: <b>Siswa <?=$studentWeight?>%</b> + <b>Guru <?=$teacherWeight?>%</b>.</p><div class="row"><div class="stat">Suara siswa<b><?=$studentVotes?></b><small><?=$studentVoted?> pemilih siswa sudah memilih</small></div><div class="stat">Suara guru<b><?=$teacherVotes?></b><small><?=$teacherVoted?> pemilih guru sudah memilih</small></div></div></div>
<div class="card"><h2>Perolehan Suara Berbobot</h2><?php foreach($results as $r):?><div style="margin:22px 0"><b><?=sprintf('%02d',$r['candidate_number'])?> — <?=h($r['name'])?></b><div class="result-grid"><div><span>Suara siswa</span><strong><?= (int)$r['student_n'] ?> (<?=number_format($r['student_pct'],2)?>%)</strong></div><div><span>Suara guru</span><strong><?= (int)$r['teacher_n'] ?> (<?=number_format($r['teacher_pct'],2)?>%)</strong></div><div><span>Hasil akhir</span><strong><?=number_format($r['weighted'],2)?>%</strong></div></div><div class="bar"><div class="fill" style="width:<?=min(100,max(0,$r['weighted']))?>%"></div></div></div><?php endforeach;?></div>
<div class="card"><h2>Catatan Privasi</h2><p class="muted">Tabel <code>votes</code> tidak menyimpan ID pemilih. Sistem hanya menyimpan kategori pemilih (siswa/guru) untuk perhitungan bobot, sedangkan identitas pemilih berada terpisah di tabel <code>voters</code>.</p></div>
<div class="card"><a href="live.php" target="_blank">Buka Live Result untuk Videotron →</a></div>
</main></body></html>