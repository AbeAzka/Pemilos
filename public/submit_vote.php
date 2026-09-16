<?php require_once __DIR__.'/../config/config.php'; require_student();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: vote.php');exit;} check_csrf();
$c=(int)($_POST['candidate_id']??0);$pdo=db();
try{
 $pdo->beginTransaction();
 $open=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='election_open' FOR UPDATE")->fetchColumn()==='1';
 if(!$open) throw new Exception('Pemilihan sedang ditutup.');
 $s=$pdo->prepare("SELECT has_voted FROM voters WHERE id=? FOR UPDATE");$s->execute([$_SESSION['student_id']]);$v=$s->fetch();
 if(!$v || $v['has_voted']) throw new Exception('Akun ini sudah menggunakan hak pilih atau tidak valid.');
 $s=$pdo->prepare("SELECT id FROM candidates WHERE id=? AND is_active=1");$s->execute([$c]);if(!$s->fetch())throw new Exception('Calon tidak valid.');
 // The vote row deliberately contains no voter_id.
 $token=hash('sha256',random_bytes(32));
 $type=(($_SESSION['voter_type']??'student')==='teacher')?'teacher':'student';
 $s=$pdo->prepare("INSERT INTO votes(candidate_id,voter_type,anonymous_token) VALUES(?,?,?)");$s->execute([$c,$type,$token]);
 $s=$pdo->prepare("UPDATE voters SET has_voted=1 WHERE id=?");$s->execute([$_SESSION['student_id']]);
 $pdo->commit();
 header('Location: success.php');exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['vote_error']='Suara belum direkam: '.$e->getMessage();header('Location: vote.php');exit;}
