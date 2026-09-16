# PILKETOS SMAN 1 GONDANG — E-Voting Online

## Isi
- Login siswa dengan username/password.
- 1 akun hanya dapat memilih sekali.
- 10 komputer dapat mengakses server/database yang sama.
- Dashboard panitia.
- Rekap suara siswa dan guru secara terpisah.
- Hasil akhir berbobot: siswa 60% + guru 40%.
- Halaman `live.php` untuk videotron dengan pembaruan otomatis.
- Database `votes` sengaja TIDAK memiliki `voter_id`, sehingga aplikasi tidak menyimpan pasangan siswa→calon.

## Instalasi
1. Siapkan hosting/server yang mendukung PHP 8+ dan MySQL/MariaDB.
2. Buat database dengan menjalankan `sql/schema.sql`.
3. Isi `config/config.php` dengan host, database, username, password.
4. Jalankan `sql/seed_demo.sql` untuk calon dan akun demo.
5. Buka `setup_admin.php`, buat akun admin, lalu HAPUS file tersebut.
6. Jika database sudah terlanjur dibuat dari versi lama, jalankan `sql/migration_60_40.sql` SEKALI di phpMyAdmin. Jika membuat database baru, cukup gunakan `sql/schema.sql` versi terbaru.
7. Arahkan domain/subdomain ke folder `public/`.
8. Aktifkan HTTPS.
9. Ganti calon dan masukkan akun siswa/guru melalui import database/admin tool yang aman.
10. Untuk videotron, buka `public/live.php`; halaman memperbarui hasil otomatis setiap 2 detik.

## Demo
Akun demo siswa:
- XII1-001 / Smago001
- XII1-002 / Smago002
- XI2-001 / Smago003

Akun admin dibuat melalui setup_admin.php.

## Untuk 950 siswa
Jangan memasukkan password dalam bentuk teks biasa ke database. Gunakan `password_hash()`.
Untuk pembuatan akun massal, buat CSV lalu jalankan skrip import server-side yang menghasilkan hash password.

## Format pemilih
- Akun siswa: `voter_type='student'`
- Akun guru: `voter_type='teacher'`
- Bobot dapat diubah di tabel `settings`, default 60 dan 40.

## Catatan penting
Ini adalah paket aplikasi dasar, bukan audit keamanan pemilu profesional. Sebelum dipakai dalam pemilihan nyata, lakukan uji beban, backup, pengujian pemulihan, pemeriksaan hak akses, dan audit kode. Pertimbangkan juga aturan sekolah mengenai kerahasiaan suara dan pengawasan pemilihan.
