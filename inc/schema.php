<?php
declare(strict_types=1);

const INSTALL_TABLES = ['settings','users','categories','images','tags','image_tags','likes','comments','login_attempts'];

/** CREATE TABLE statements used by install.php */
function schema_statements(): array
{
    $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
"CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) $opt",
"CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(60) NOT NULL,
  bio VARCHAR(500) NOT NULL DEFAULT '',
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  status ENUM('active','banned') NOT NULL DEFAULT 'active',
  theme VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL,
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email (email)
) $opt",
"CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description VARCHAR(600) NOT NULL DEFAULT '',
  cover_image_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_slug (slug),
  KEY idx_parent (parent_id),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) $opt",
"CREATE TABLE IF NOT EXISTS images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  slug VARCHAR(80) NOT NULL DEFAULT '',
  description TEXT NULL,
  file_path VARCHAR(120) NOT NULL,
  thumb_path VARCHAR(120) NOT NULL,
  display_path VARCHAR(120) NOT NULL,
  mime VARCHAR(30) NOT NULL,
  width INT UNSIGNED NOT NULL,
  height INT UNSIGNED NOT NULL,
  filesize INT UNSIGNED NOT NULL DEFAULT 0,
  color CHAR(7) NOT NULL DEFAULT '#222222',
  exif TEXT NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  likes_count INT UNSIGNED NOT NULL DEFAULT 0,
  comments_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('published','pending','hidden') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL,
  KEY idx_cat (category_id, status, id),
  KEY idx_user (user_id, id),
  KEY idx_status (status, id),
  CONSTRAINT fk_img_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_img_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) $opt",
"CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_tag_slug (slug)
) $opt",
"CREATE TABLE IF NOT EXISTS image_tags (
  image_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (image_id, tag_id),
  KEY idx_tag (tag_id),
  CONSTRAINT fk_it_img FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE,
  CONSTRAINT fk_it_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) $opt",
"CREATE TABLE IF NOT EXISTS likes (
  image_id INT UNSIGNED NOT NULL,
  voter VARCHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (image_id, voter),
  KEY idx_voter (voter, created_at),
  CONSTRAINT fk_like_img FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE
) $opt",
"CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  image_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(2000) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_img (image_id, id),
  CONSTRAINT fk_cm_img FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) $opt",
"CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ip (ip, created_at),
  KEY idx_user (username, created_at)
) $opt",
    ];
}
