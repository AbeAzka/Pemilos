USE db_pilketos;

-- Jalankan sekali pada database yang SUDAH ada.
ALTER TABLE voters ADD COLUMN voter_type ENUM('student','teacher') NOT NULL DEFAULT 'student' AFTER class_name;
ALTER TABLE votes ADD COLUMN voter_type ENUM('student','teacher') NOT NULL DEFAULT 'student' AFTER candidate_id;
ALTER TABLE votes ADD INDEX idx_votes_type_candidate (voter_type,candidate_id);

INSERT INTO settings(setting_key,setting_value) VALUES
('student_weight','60'),('teacher_weight','40')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Semua akun lama otomatis menjadi akun siswa.
-- Untuk membuat akun guru, isi voter_type='teacher'.
