<?php
// Jalankan sekali di server CLI untuk membuat INSERT akun massal dari array.
// Jangan pernah menyimpan password plaintext setelah distribusi akun.
$students=[
 ['XII-1','001','Siswa 001','Smago001'],
 ['XII-1','002','Siswa 002','Smago002'],
];
foreach($students as [$class,$absen,$name,$password]){
  $username=str_replace('-','',$class).'-'.$absen;
  $hash=password_hash($password,PASSWORD_DEFAULT);
  echo "INSERT INTO voters(username,password_hash,student_name,class_name) VALUES ("
    ."'".addslashes($username)."','".addslashes($hash)."','".addslashes($name)."','".addslashes($class)."');\n";
}
?>
