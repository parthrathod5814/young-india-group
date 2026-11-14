-- =====================================================================
--  Young India Group - Deven Rabari Foundation  |  Admin Dashboard
--  young_india_group.sql   (structure only - NO demo / static data)
--
--  HOW TO IMPORT
--    phpMyAdmin : Import tab -> choose this file -> Go
--    Terminal   : mysql -u root -p < young_india_group.sql
--
--  Needs MySQL 5.7+ / 8.x  or  MariaDB 10.3+  (XAMPP / WAMP / cPanel ok)
--  Safe to import again: every table uses CREATE TABLE IF NOT EXISTS,
--  so existing data is never deleted.
--
--  CONTENTS
--    1. admins              6. inquiries (contact messages)
--    2. blood_requests      7. pending_donors / pending_volunteers (temporary tables)
--    3. donors              8. backups, notification_reads, settings, audit_log
--    4. volunteers          9. views (record codes + dashboard counts)
--    5. activities         10. procedures (approve a pending sign-up)
--
--  RECORD CODES (REQ-0001, DNR-0001 ...) are not stored. The views in
--  section 9 build them from the id, so they can never get out of step.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `young_india_group`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `young_india_group`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ---------------------------------------------------------------------
-- 1. ADMINS  (Admin Controls menu)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `phone`         VARCHAR(20)  NOT NULL,
  `role`          ENUM('Super Admin','Secretariat Admin','Blood Desk Admin','Content Editor','Viewer') NOT NULL DEFAULT 'Viewer',
  `status`        ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  `password_hash` VARCHAR(255) NULL COMMENT 'PHP password_hash() value. NULL until the admin sets a password. Never store plain text.',
  `last_login_at` DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. BLOOD REQUESTS  (Blood Emergency Hub: add / view / manage / live feed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient`      VARCHAR(120) NOT NULL,
  `blood_group`  ENUM('O-','O+','A-','A+','B-','B+','AB-','AB+') NOT NULL,
  `units`        TINYINT UNSIGNED NOT NULL,
  `hospital`     VARCHAR(120) NOT NULL,
  `ward`         VARCHAR(120) NULL COMMENT 'Ward / Bed',
  `contact`      VARCHAR(20)  NOT NULL COMMENT 'Attendant phone',
  `runner`       VARCHAR(100) NULL COMMENT 'Runner / Coordinator',
  `status`       ENUM('Active','In-Transit','Fulfilled') NOT NULL DEFAULT 'Active',
  `fulfilled_at` DATETIME NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blood_status_created` (`status`, `created_at`),
  KEY `idx_blood_group` (`blood_group`),
  KEY `idx_blood_hospital` (`hospital`),
  KEY `idx_blood_patient` (`patient`),
  CONSTRAINT `chk_blood_units` CHECK (`units` BETWEEN 1 AND 10),
  CONSTRAINT `fk_blood_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. DONORS  (Donor Registry)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donors` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `blood_group`   ENUM('O-','O+','A-','A+','B-','B+','AB-','AB+') NOT NULL,
  `phone`         VARCHAR(20)  NOT NULL,
  `age`           TINYINT UNSIGNED NOT NULL,
  `area`          VARCHAR(60)  NOT NULL COMMENT 'Area / Taluka',
  `last_donation` DATE NULL,
  `status`        ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `approved_by`   INT UNSIGNED NULL COMMENT 'Admin who approved the website sign-up (NULL = added by an admin)',
  `approved_at`   DATETIME NULL,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_donors_group_area` (`blood_group`, `area`),
  KEY `idx_donors_status` (`status`),
  KEY `idx_donors_phone` (`phone`),
  KEY `idx_donors_name` (`name`),
  CONSTRAINT `chk_donors_age` CHECK (`age` BETWEEN 18 AND 60),
  CONSTRAINT `fk_donors_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_donors_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. VOLUNTEERS  (Volunteer Network)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `volunteers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `phone`        VARCHAR(20)  NOT NULL,
  `area`         VARCHAR(60)  NOT NULL COMMENT 'Area / Ward',
  `role`         VARCHAR(80)  NOT NULL COMMENT 'Primary role',
  `availability` ENUM('Weekend','Emergency SOS','4-8 hrs / week') NOT NULL,
  `status`       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by`  INT UNSIGNED NULL,
  `approved_at`  DATETIME NULL, 
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vol_status_area` (`status`, `area`),
  KEY `idx_vol_role` (`role`),
  KEY `idx_vol_phone` (`phone`),
  KEY `idx_vol_name` (`name`),
  CONSTRAINT `fk_vol_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_vol_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. ACTIVITIES  (Activities & News, description = rich text / HTML)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activities` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(200) NOT NULL,
  `category`      ENUM('Blood Drive','Medical Camp','Education','Environment','Youth Leadership','Disaster Relief') NOT NULL,
  `area`          VARCHAR(60)  NOT NULL COMMENT 'Area / Taluka',
  `activity_date` DATE NOT NULL,
  `metric`        VARCHAR(100) NULL COMMENT 'Impact metric, e.g. 1,420 Units',
  `status`        ENUM('Published','Draft') NOT NULL DEFAULT 'Draft',
  `description`   MEDIUMTEXT NOT NULL COMMENT 'HTML from the rich text editor. Clean it on the server before saving.',
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_status_date` (`status`, `activity_date`),
  KEY `idx_act_category` (`category`),
  KEY `idx_act_area` (`area`),
  KEY `idx_act_title` (`title`),
  CONSTRAINT `fk_act_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. INQUIRIES  (Contact Inquiries from the website contact form)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  NOT NULL,
  `type`       VARCHAR(60)  NOT NULL COMMENT 'Nature of inquiry',
  `status`     ENUM('New','In Progress','Resolved') NOT NULL DEFAULT 'New',
  `message`    TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_by` INT UNSIGNED NULL COMMENT 'Set only when an admin adds the inquiry by hand',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inq_status_created` (`status`, `created_at`),
  KEY `idx_inq_type` (`type`),
  KEY `idx_inq_phone` (`phone`),
  CONSTRAINT `fk_inq_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 7. TEMPORARY TABLES FOR WEBSITE SIGN-UPS
--    The website forms insert here. Nothing reaches `donors` / `volunteers`
--    until an admin presses Approve (see the procedures in section 10).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pending_donors` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `blood_group`   ENUM('O-','O+','A-','A+','B-','B+','AB-','AB+') NOT NULL,
  `phone`         VARCHAR(20)  NOT NULL,
  `age`           TINYINT UNSIGNED NOT NULL,
  `area`          VARCHAR(60)  NOT NULL,
  `last_donation` DATE NULL,
  `ip_address`    VARCHAR(45) NULL,
  `submitted_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pdonor_submitted` (`submitted_at`),
  KEY `idx_pdonor_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pending_volunteers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `phone`        VARCHAR(20)  NOT NULL,
  `area`         VARCHAR(60)  NOT NULL,
  `role`         VARCHAR(80)  NOT NULL,
  `availability` ENUM('Weekend','Emergency SOS','4-8 hrs / week') NOT NULL,
  `ip_address`   VARCHAR(45) NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pvol_submitted` (`submitted_at`),
  KEY `idx_pvol_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 8. SYSTEM TABLES
-- ---------------------------------------------------------------------

-- Backup & Recovery history. The backup itself is a file saved on the server.
CREATE TABLE IF NOT EXISTS `backups` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `note`          VARCHAR(255) NULL,
  `type`          ENUM('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `file_path`     VARCHAR(255) NOT NULL COMMENT 'Where the .sql / .json file is stored on the server',
  `size_bytes`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `record_counts` JSON NULL COMMENT 'e.g. {"donors":45,"volunteers":32}',
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backups_created` (`created_at`),
  CONSTRAINT `fk_backups_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which notifications each admin has already read (the bell button)
CREATE TABLE IF NOT EXISTS `notification_reads` (
  `admin_id` INT UNSIGNED NOT NULL,
  `item_key` VARCHAR(80) NOT NULL COMMENT 'e.g. blood:12, inq:7, pend:d:3, backup:none',
  `read_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`, `item_key`),
  CONSTRAINT `fk_notif_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Simple key / value settings.
--   admin_passkey_hash : password_hash() of the Manage Admins pass key
--   auto_backup        : 'on' or 'off'
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who did what and when (add / update / delete / approve / login / restore)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED NULL,
  `action`     VARCHAR(40) NOT NULL,
  `entity`     VARCHAR(40) NOT NULL COMMENT 'Table name, e.g. donors',
  `entity_id`  INT UNSIGNED NULL,
  `details`    JSON NULL COMMENT 'Old / new values',
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity`, `entity_id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------
-- 9. VIEWS  (use these in PHP so every row already has its record code)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_admins` AS
  SELECT CONCAT('ADM-', LPAD(t.id, 4, '0')) AS code, t.id, t.name, t.email, t.phone, t.role, t.status, t.last_login_at, t.created_at, t.updated_at
  FROM `admins` t;

CREATE OR REPLACE VIEW `v_blood_requests` AS
  SELECT CONCAT('REQ-', LPAD(t.id, 4, '0')) AS code, t.* FROM `blood_requests` t;

CREATE OR REPLACE VIEW `v_donors` AS
  SELECT CONCAT('DNR-', LPAD(t.id, 4, '0')) AS code, t.* FROM `donors` t;

CREATE OR REPLACE VIEW `v_volunteers` AS
  SELECT CONCAT('VOL-', LPAD(t.id, 4, '0')) AS code, t.* FROM `volunteers` t;

CREATE OR REPLACE VIEW `v_activities` AS
  SELECT CONCAT('ACT-', LPAD(t.id, 4, '0')) AS code, t.* FROM `activities` t;

CREATE OR REPLACE VIEW `v_inquiries` AS
  SELECT CONCAT('INQ-', LPAD(t.id, 4, '0')) AS code, t.* FROM `inquiries` t;

CREATE OR REPLACE VIEW `v_pending_donors` AS
  SELECT CONCAT('TMP-D-', t.id) AS code, t.* FROM `pending_donors` t;

CREATE OR REPLACE VIEW `v_pending_volunteers` AS
  SELECT CONCAT('TMP-V-', t.id) AS code, t.* FROM `pending_volunteers` t;

-- One row with every number the dashboard cards and buttons need
CREATE OR REPLACE VIEW `v_dashboard_stats` AS
  SELECT
    (SELECT COUNT(*) FROM `volunteers`)                               AS total_volunteers,
    (SELECT COUNT(*) FROM `donors`)                                   AS verified_donors,
    (SELECT COUNT(*) FROM `blood_requests` WHERE `status` = 'Active')    AS urgent_blood,
    (SELECT COUNT(*) FROM `blood_requests` WHERE `status` = 'Fulfilled') AS lives_saved,
    (SELECT COUNT(*) FROM `activities`)                               AS civic_drives,
    (SELECT COUNT(*) FROM `pending_donors`)                           AS pending_donors,
    (SELECT COUNT(*) FROM `pending_volunteers`)                       AS pending_volunteers,
    (SELECT COUNT(*) FROM `inquiries` WHERE `status` = 'New')         AS new_inquiries;


-- ---------------------------------------------------------------------
-- 10. PROCEDURES  (Approve = move the sign-up into the real table, all or nothing)
--     PHP:  CALL approve_donor(:pending_id, :admin_id);
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `approve_donor`;
DROP PROCEDURE IF EXISTS `approve_volunteer`;

DELIMITER //

CREATE PROCEDURE `approve_donor`(IN p_pending_id INT UNSIGNED, IN p_admin_id INT UNSIGNED)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  INSERT INTO `donors` (`name`, `blood_group`, `phone`, `age`, `area`, `last_donation`, `status`, `approved_by`, `approved_at`)
    SELECT `name`, `blood_group`, `phone`, `age`, `area`, `last_donation`, 'Active', p_admin_id, NOW()
    FROM `pending_donors` WHERE `id` = p_pending_id;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pending donor not found';
  END IF;

  DELETE FROM `pending_donors` WHERE `id` = p_pending_id;

  COMMIT;
  SELECT LAST_INSERT_ID() AS new_donor_id;
END//

CREATE PROCEDURE `approve_volunteer`(IN p_pending_id INT UNSIGNED, IN p_admin_id INT UNSIGNED)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  INSERT INTO `volunteers` (`name`, `phone`, `area`, `role`, `availability`, `status`, `approved_by`, `approved_at`)
    SELECT `name`, `phone`, `area`, `role`, `availability`, 'Approved', p_admin_id, NOW()
    FROM `pending_volunteers` WHERE `id` = p_pending_id;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pending volunteer not found';
  END IF;

  DELETE FROM `pending_volunteers` WHERE `id` = p_pending_id;

  COMMIT;
  SELECT LAST_INSERT_ID() AS new_volunteer_id;
END//

DELIMITER ;


-- ---------------------------------------------------------------------
-- OPTIONAL FIRST-TIME SETUP (kept as comments on purpose - no data is inserted)
-- Run these yourself once, with your own values, after importing:
--
--   -- the first Super Admin (create the hash in PHP: echo password_hash('your-password', PASSWORD_DEFAULT);)
--   INSERT INTO admins (name, email, phone, role, status, password_hash)
--   VALUES ('Your Name', 'you@example.org', '9825000000', 'Super Admin', 'Active', '<paste hash here>');
--
--   -- the pass key for the Manage Admins page (also a password_hash() value)
--   INSERT INTO settings (setting_key, setting_value) VALUES ('admin_passkey_hash', '<paste hash here>');
-- ---------------------------------------------------------------------
