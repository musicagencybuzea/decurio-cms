-- decurio CMS · v1.2 migration — import in phpMyAdmin (one single run)
-- Safe to keep existing data: all changes are additive.
-- Run once. If a previous partial import failed, adjust before re-running.

ALTER TABLE albums
  ADD COLUMN subtitle VARCHAR(255) NULL AFTER title,
  ADD COLUMN is_single TINYINT(1) NOT NULL DEFAULT 0 AFTER subtitle,
  ADD COLUMN release_number VARCHAR(8) NULL AFTER is_single,
  ADD UNIQUE KEY uniq_release_number (release_number),
  ADD COLUMN socials_json LONGTEXT NULL AFTER links_json,
  ADD COLUMN sticker_slots_json LONGTEXT NULL AFTER socials_json,
  ADD COLUMN bg_preset VARCHAR(20) NOT NULL DEFAULT 'noir' AFTER sticker_slots_json,
  ADD COLUMN box_color VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER bg_preset,
  ADD COLUMN box_transparent TINYINT(1) NOT NULL DEFAULT 0 AFTER box_color,
  ADD COLUMN music_id INT UNSIGNED NULL AFTER box_transparent,
  ADD COLUMN publish_at DATETIME NULL AFTER music_id,
  MODIFY status ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  ADD KEY idx_status_publish (status, publish_at);

CREATE TABLE IF NOT EXISTS platforms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  preferred TINYINT(1) NOT NULL DEFAULT 0,
  custom TINYINT(1) NOT NULL DEFAULT 0,
  has_api TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stickers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
