<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    static $schemaEnsured = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (!$schemaEnsured) {
        $schemaEnsured = true;
        try {
            $pdo->exec('ALTER TABLE teachers ADD COLUMN grade_levels_taught VARCHAR(32) NULL');
        } catch (Throwable $e) {
        }

        try {
            $pdo->exec('ALTER TABLE teachers ADD COLUMN approval_status ENUM("Pending","Approved","Declined") NOT NULL DEFAULT "Approved"');
        } catch (Throwable $e) {
        }

        try {
            $pdo->exec('ALTER TABLE teachers ADD COLUMN approved_by INT NULL');
        } catch (Throwable $e) {
        }

        try {
            $pdo->exec('ALTER TABLE teachers ADD COLUMN approved_at DATETIME NULL');
        } catch (Throwable $e) {
        }

        try {
            $pdo->exec('ALTER TABLE teachers ADD COLUMN notif_seen_at DATETIME NULL');
        } catch (Throwable $e) {
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS grade_sections (
                    grade_section_id INT AUTO_INCREMENT PRIMARY KEY,
                    grade_level TINYINT NOT NULL,
                    section VARCHAR(20) NOT NULL,
                    status ENUM("Active","Inactive") NOT NULL DEFAULT "Active",
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_grade_sections (grade_level, section),
                    INDEX idx_grade_sections_grade (grade_level),
                    INDEX idx_grade_sections_status (status),
                    CHECK (grade_level BETWEEN 7 AND 12)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
        }

        // Phase 1: Add 'Super Admin' to teachers.role ENUM
        try {
            $pdo->exec('ALTER TABLE teachers MODIFY COLUMN role ENUM("Teacher","Admin","Super Admin") NOT NULL DEFAULT "Teacher"');
        } catch (Throwable $e) {
        }

        // Phase 2: change_requests table for approval workflow
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS change_requests (
                    request_id INT AUTO_INCREMENT PRIMARY KEY,
                    teacher_id INT NOT NULL,
                    request_type ENUM("password","account_settings","schedule_edit","schedule_deactivate","schedule_archive") NOT NULL,
                    target_id INT NULL,
                    payload JSON NOT NULL,
                    status ENUM("Pending","Approved","Rejected") NOT NULL DEFAULT "Pending",
                    reviewed_by INT NULL,
                    reviewed_at DATETIME NULL,
                    reason TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_cr_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
                    INDEX idx_cr_status (status),
                    INDEX idx_cr_teacher (teacher_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
        }

        // Phase 4: class_suspensions table
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS class_suspensions (
                    suspension_id INT AUTO_INCREMENT PRIMARY KEY,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason TEXT NULL,
                    scope ENUM("school","grade","section","subject") NOT NULL DEFAULT "school",
                    grade_level TINYINT NULL,
                    section VARCHAR(20) NULL,
                    schedule_id INT NULL,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_cs_created_by FOREIGN KEY (created_by) REFERENCES teachers(teacher_id) ON DELETE RESTRICT,
                    INDEX idx_cs_dates (start_date, end_date),
                    INDEX idx_cs_scope (scope)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
        }

        // Phase 4: Add 'Suspended' to attendance.status ENUM
        try {
            $pdo->exec('ALTER TABLE attendance MODIFY COLUMN status ENUM("Present","Late","Absent","Suspended") NOT NULL');
        } catch (Throwable $e) {
        }
    }

    return $pdo;
}
