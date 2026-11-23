CREATE TABLE admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE albums (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(255) NOT NULL,
 subtitle VARCHAR(255) NULL,
 is_single TINYINT(1) NOT NULL DEFAULT 0,
 release_number VARCHAR(8) NULL,
 UNIQUE KEY uniq_release_number (release_number),
 artists TEXT NULL,
 cover_path VARCHAR(500) NULL,
 links_json LONGTEXT NULL,
 socials_json LONGTEXT NULL,
 sticker_slots_json LONGTEXT NULL,
 bg_preset VARCHAR(20) NOT NULL DEFAULT 'noir',
 box_color VARCHAR(20) NOT NULL DEFAULT 'auto',
 box_transparent TINYINT(1) NOT NULL DEFAULT 0,
 music_id INT UNSIGNED NULL,
 publish_at DATETIME NULL,
 status ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_status_publish (status, publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platforms (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL,
 preferred TINYINT(1) NOT NULL DEFAULT 0,
 custom TINYINT(1) NOT NULL DEFAULT 0,
 has_api TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stickers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 image_path VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tracks (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(255) NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
('site_title','decurio – records & links'),
('tagline','record label'),
('logo_url','https://decur.io'),
('footer_text','we record, we publish, we discover');

INSERT INTO platforms(name, preferred, custom, has_api, active, sort_order) VALUES
  ('Spotify', 1, 0, 1, 1, 10),
  ('Apple Music', 1, 0, 1, 1, 20),
  ('Deezer', 1, 0, 1, 1, 30),
  ('YouTube Music', 1, 0, 1, 1, 40),
  ('Tidal', 1, 0, 0, 1, 50),
  ('Amazon Music', 1, 0, 0, 1, 60),
  ('Bandcamp', 0, 0, 0, 1, 70),
  ('Qobuz', 0, 0, 0, 1, 80),
  ('iTunes Store', 0, 0, 0, 1, 90),
  ('Napster', 0, 0, 0, 1, 100),
  ('Google Play', 0, 0, 0, 1, 110),
  ('Yandex', 0, 0, 0, 1, 120),
  ('Pandora', 0, 0, 0, 1, 130),
  ('Beatport', 0, 0, 0, 1, 140);
