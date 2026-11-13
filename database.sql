CREATE DATABASE IF NOT EXISTS wagertrack
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wagertrack;

CREATE TABLE IF NOT EXISTS daily_records (
    record_date DATE NOT NULL PRIMARY KEY,
    result ENUM('win', 'loss') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cash_additions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    description VARCHAR(160) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cash_additions_date (transaction_date, id)
);
