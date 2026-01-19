CREATE DATABASE IF NOT EXISTS `articles_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `articles_db`;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `articles` (`title`, `summary`, `content`, `status`, `published_at`)
VALUES
('Mengenal ThinkPHP', 'Langkah pertama menjalankan kerangka kerja.', 'Ini contoh artikel pertama untuk menguji alur CRUD pada ThinkPHP. Silakan edit atau hapus sesuai kebutuhan.', 'published', NOW()),
('Belajar ORM', 'Latihan query builder & model.', 'Contoh artikel kedua untuk menguji filter dan pagination.', 'draft', NULL);
