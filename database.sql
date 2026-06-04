-- ============================================================
--  CloudCMS Database Setup Script
--  Run this file once in phpMyAdmin or MySQL CLI to create
--  all tables needed for the project.
-- ============================================================

-- 1. Create and select the database
CREATE DATABASE IF NOT EXISTS cloudcms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cloudcms;

-- ============================================================
-- TABLE 1: users
-- Stores every registered account.
-- "role" column drives the Role Management rubric requirement:
--   'admin'  → can see/edit/delete all records
--   'user'   → can only see/edit their own records
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT          NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,   -- stores a bcrypt hash, never plain text
    role        ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
);

-- ============================================================
-- TABLE 2: categories
-- A simple lookup table for file categories (e.g. Reports,
-- Images, Documents).  Used in the N:N relationship with files.
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id    INT          NOT NULL AUTO_INCREMENT,
    name  VARCHAR(100) NOT NULL UNIQUE,

    PRIMARY KEY (id)
);

-- Seed some default categories so the site works out of the box
INSERT INTO categories (name) VALUES
    ('Reports'),
    ('Images'),
    ('Documents'),
    ('Spreadsheets'),
    ('Other');

-- ============================================================
-- TABLE 3: files
-- Stores every file uploaded through the upload form.
-- "user_id" is a Foreign Key → users.id
--
-- RELATIONSHIP:  users (1) ──< (N) files
--   One user can upload many files, but each file belongs to
--   exactly one user.  This is the 1:N relationship.
-- ============================================================
CREATE TABLE IF NOT EXISTS files (
    id            INT          NOT NULL AUTO_INCREMENT,
    user_id       INT          NOT NULL,                -- FK → users.id
    original_name VARCHAR(255) NOT NULL,                -- the name the user gave the file
    stored_name   VARCHAR(255) NOT NULL,                -- the safe name we save on disk
    file_path     VARCHAR(500) NOT NULL,                -- relative path inside /uploads/
    file_size     INT          NOT NULL DEFAULT 0,      -- size in bytes
    uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Foreign Key: if a user is deleted, their files are also deleted
    CONSTRAINT fk_files_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ============================================================
-- TABLE 4: file_categories  (JUNCTION / PIVOT TABLE)
-- Links files to categories in a Many-to-Many relationship.
--
-- RELATIONSHIP:  files (N) ──< >── (N) categories
--   One file can belong to many categories, and one category
--   can contain many files.  The junction table holds the pairs.
-- ============================================================
CREATE TABLE IF NOT EXISTS file_categories (
    file_id     INT NOT NULL,   -- FK → files.id
    category_id INT NOT NULL,   -- FK → categories.id

    -- Composite primary key: the same pair can only appear once
    PRIMARY KEY (file_id, category_id),

    CONSTRAINT fk_fc_file
        FOREIGN KEY (file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_fc_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ============================================================
-- Default Admin Account
-- Password is "admin123" hashed with PHP password_hash().
-- Change this password immediately after first login!
-- To regenerate: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
-- ============================================================
INSERT INTO users (name, email, password, role) VALUES (
    'Admin',
    'admin@cloudcms.com',
    '$2y$10$SjEdkaQYGbHl/wzwa96ptuwAZoEx1ggqkx0f4GBxac6f2EvT07mTW',
    'admin'
);

-- ============================================================
-- RELATIONSHIP SUMMARY (for your assignment report)
--
--  1:N   →  users.id  ←──  files.user_id
--            One user uploads many files.
--
--  N:N   →  files  ←── file_categories ──→  categories
--            One file can have many categories;
--            one category can tag many files.
--            The junction table "file_categories" implements this.
-- ============================================================
