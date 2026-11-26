-- decurio CMS v1.4 phase 2 — Statistics (à importer UNE FOIS dans phpMyAdmin)
-- Table of anonymous link clicks. NO IP address, NO user-agent, NO cookies: GDPR-safe by design.
CREATE TABLE IF NOT EXISTS link_clicks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  album_id INT UNSIGNED NULL DEFAULT NULL,
  source ENUM('main','release') NOT NULL DEFAULT 'main',
  platform VARCHAR(80) NOT NULL DEFAULT '',
  action_word VARCHAR(30) NULL DEFAULT NULL,
  position TINYINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_clicks_ts (ts),
  KEY idx_clicks_album (album_id),
  KEY idx_clicks_album_ts (album_id, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
