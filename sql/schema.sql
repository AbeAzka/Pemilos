CREATE DATABASE IF NOT EXISTS db_pilketos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_pilketos;

CREATE TABLE settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
);

INSERT INTO settings(setting_key,setting_value) VALUES
('election_open','0'),
('student_weight','60'),
('teacher_weight','40')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

CREATE TABLE voters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  student_name VARCHAR(120) NOT NULL,
  class_name VARCHAR(30) NOT NULL,
  voter_type ENUM('student','teacher') NOT NULL DEFAULT 'student',
  has_voted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_number INT NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  photo_url VARCHAR(500) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE votes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  voter_type ENUM('student','teacher') NOT NULL,
  anonymous_token CHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_votes_candidate FOREIGN KEY(candidate_id) REFERENCES candidates(id)
);

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_votes_candidate ON votes(candidate_id);
CREATE INDEX idx_votes_type_candidate ON votes(voter_type,candidate_id);
CREATE INDEX idx_voters_class ON voters(class_name);

-- IMPORTANT:
-- votes intentionally has no voter_id. It stores only the voter category (student/teacher),
-- which is needed for the 60:40 weighted result without directly storing
-- "student X voted for candidate Y".
