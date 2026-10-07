<?php
// Table definitions, used by install.php
return [
"CREATE TABLE IF NOT EXISTS offices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(8) NOT NULL DEFAULT '',
  late_after CHAR(5) NOT NULL DEFAULT '09:00',
  close_at CHAR(5) NOT NULL DEFAULT '17:00',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'leader',
  office_id INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_id INT NOT NULL,
  code VARCHAR(20) NOT NULL DEFAULT '',
  full_name VARCHAR(160) NOT NULL,
  stage VARCHAR(40) NOT NULL DEFAULT 'New Member',
  rank_name VARCHAR(60) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'active',
  joined_date DATE NULL,
  dob DATE NULL,
  pin_hash VARCHAR(255) NULL,
  data LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  updated_by INT NULL,
  INDEX (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_id INT NOT NULL,
  member_id INT NOT NULL,
  date DATE NOT NULL,
  sign_in DATETIME NULL,
  sign_out DATETIME NULL,
  excused TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  by_user INT NULL,
  updated_at DATETIME NOT NULL,
  photo_in MEDIUMTEXT NULL,
  photo_out MEDIUMTEXT NULL,
  UNIQUE KEY member_day (member_id, date),
  INDEX office_day (office_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS weekend_sessions (
  office_id INT NOT NULL,
  date DATE NOT NULL,
  PRIMARY KEY (office_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS finance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  member_id INT NULL,
  type VARCHAR(20) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) PRIMARY KEY,
  v LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS login_attempts (
  ip VARCHAR(45) NOT NULL,
  at DATETIME NOT NULL,
  INDEX (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS followups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  office_id INT NOT NULL,
  date DATE NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'call',
  outcome VARCHAR(500) NOT NULL DEFAULT '',
  next_step VARCHAR(255) NOT NULL DEFAULT '',
  by_user INT NULL,
  by_name VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX (member_id), INDEX (office_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS prospects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  invited_by VARCHAR(160) NOT NULL DEFAULT '',
  source VARCHAR(60) NOT NULL DEFAULT '',
  first_contact DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  member_id INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  by_user INT NULL,
  INDEX (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS performance (
  member_id INT NOT NULL,
  month CHAR(7) NOT NULL,
  office_id INT NOT NULL,
  pv DECIMAL(12,2) NOT NULL DEFAULT 0,
  bv DECIMAL(12,2) NOT NULL DEFAULT 0,
  sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  target_pv DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  by_user INT NULL,
  PRIMARY KEY (member_id, month),
  INDEX (office_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS trainings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_id INT NOT NULL,
  date DATE NOT NULL,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  by_user INT NULL,
  INDEX (office_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS training_attendance (
  training_id INT NOT NULL,
  kind CHAR(1) NOT NULL,
  person_id INT NOT NULL,
  PRIMARY KEY (training_id, kind, person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS fines_paid (
  member_id INT NOT NULL,
  month CHAR(7) NOT NULL,
  paid_at DATETIME NOT NULL,
  by_user INT NULL,
  PRIMARY KEY (member_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  user_id INT NULL,
  user_name VARCHAR(120) NOT NULL DEFAULT '',
  office_id INT NULL,
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  INDEX (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS password_resets (
  user_id INT NOT NULL PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
