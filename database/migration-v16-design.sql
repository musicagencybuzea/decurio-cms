-- decurio CMS v1.4 phase 2 — Video/Canvas backgrounds (à importer UNE FOIS dans phpMyAdmin)
-- 1) Video library
CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Each album can point to a library video for its background
ALTER TABLE albums ADD COLUMN video_id INT UNSIGNED NULL DEFAULT NULL AFTER bg_preset;
