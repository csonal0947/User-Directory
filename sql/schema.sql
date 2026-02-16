-- =============================================================
-- User Directory Database Schema
-- MySQL 8+ Compatible
-- Generates 10,000+ dummy user records
-- =============================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS user_directory
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE user_directory;

-- Drop table if exists for clean setup
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fname      VARCHAR(100) NOT NULL,
    lname      VARCHAR(100) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    status     ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
    review     VARCHAR(500) DEFAULT 'a sample review',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    INDEX idx_status (status),
    INDEX idx_fname (fname),
    INDEX idx_lname (lname),
    INDEX idx_fname_lname (fname, lname),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Generate 10,000 dummy records using stored procedure
-- =============================================================

DELIMITER //

DROP PROCEDURE IF EXISTS generate_dummy_users //

CREATE PROCEDURE generate_dummy_users()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_fname VARCHAR(100);
    DECLARE v_lname VARCHAR(100);
    DECLARE v_email VARCHAR(255);
    DECLARE v_review TEXT;
    DECLARE v_created_at TIMESTAMP;

    -- First names pool (50 names)
    DECLARE first_names JSON DEFAULT JSON_ARRAY(
        'James', 'Mary', 'Robert', 'Patricia', 'John', 'Jennifer', 'Michael', 'Linda',
        'David', 'Elizabeth', 'William', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Christopher', 'Karen', 'Charles', 'Lisa', 'Daniel', 'Nancy',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
        'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle',
        'Kenneth', 'Carol', 'Kevin', 'Amanda', 'Brian', 'Dorothy', 'George', 'Melissa',
        'Timothy', 'Deborah'
    );

    -- Last names pool (50 names)
    DECLARE last_names JSON DEFAULT JSON_ARRAY(
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
        'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
        'Carter', 'Roberts'
    );

    -- Reviews pool
    DECLARE reviews JSON DEFAULT JSON_ARRAY(
        'a sample review',
        'Excellent platform with great user experience.',
        'Very helpful directory service, highly recommended!',
        'Good service, could improve search functionality.',
        'Outstanding support team and easy to navigate.',
        'Great user interface, very intuitive design.',
        'Fast and reliable, exactly what I needed.',
        'Professional service with excellent documentation.',
        'User-friendly platform with responsive design.',
        'Impressive features and smooth performance.',
        'Highly efficient and well-organized directory.'
    );

    -- Temporarily disable checks for faster inserts
    SET @old_unique_checks = @@unique_checks;
    SET @old_foreign_key_checks = @@foreign_key_checks;
    SET UNIQUE_CHECKS = 0;
    SET FOREIGN_KEY_CHECKS = 0;
    SET autocommit = 0;

    WHILE i <= 10000 DO
        -- Pick random names
        SET v_fname = JSON_UNQUOTE(JSON_EXTRACT(first_names, CONCAT('$[', FLOOR(RAND() * 50), ']')));
        SET v_lname = JSON_UNQUOTE(JSON_EXTRACT(last_names, CONCAT('$[', FLOOR(RAND() * 50), ']')));

        -- Generate unique email
        SET v_email = CONCAT(LOWER(v_fname), '.', LOWER(v_lname), '.', i, '@example.com');

        -- Pick random review
        SET v_review = JSON_UNQUOTE(JSON_EXTRACT(reviews, CONCAT('$[', FLOOR(RAND() * 11), ']')));

        -- Random date within last 2 years
        SET v_created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 730) DAY);

        -- Insert user
        INSERT INTO users (fname, lname, email, status, review, created_at)
        VALUES (v_fname, v_lname, v_email, 'active', v_review, v_created_at);

        -- Commit every 1000 rows
        IF i % 1000 = 0 THEN
            COMMIT;
        END IF;

        SET i = i + 1;
    END WHILE;

    COMMIT;

    -- Restore settings
    SET UNIQUE_CHECKS = @old_unique_checks;
    SET FOREIGN_KEY_CHECKS = @old_foreign_key_checks;
    SET autocommit = 1;
END //

DELIMITER ;

-- Execute the procedure to generate dummy data
CALL generate_dummy_users();

-- Verify the count
SELECT COUNT(*) AS total_users FROM users;
SELECT * FROM users LIMIT 5;

-- Clean up
DROP PROCEDURE IF EXISTS generate_dummy_users;
