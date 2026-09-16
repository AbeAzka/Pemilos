USE db_pilketos;
INSERT INTO candidates(candidate_number,name,photo_url) VALUES
(1,'Calon Ketua OSIS 01',''),(2,'Calon Ketua OSIS 02',''),(3,'Calon Ketua OSIS 03','');
INSERT INTO voters(username,password_hash,student_name,class_name,voter_type) VALUES
('XII1-001','$2y$12$CsY0.qJ.D7qXNVonYOcvKOqjKhBgPyLIkbNDqdpSXbp/xNN5RKaFa','Siswa 001','XII-1','student'),
('XII1-002','$2y$12$BFk9xU.HxwMgvAVdTpuaV.cljtQTG6BIct5fpdOxT6bYbwJCt0k1a','Siswa 002','XII-1','student'),
('XI2-001','$2y$12$MSigiozdz/nZhqN4PBqRceQZ8bxQ6eJXEZ//gzUEEs7rBAvR1aN/.','Siswa 003','XI-2','student');
