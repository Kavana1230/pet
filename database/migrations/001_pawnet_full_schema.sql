-- Paw Net Database Migration 001: Full Schema Extension
-- Preserves existing tables (conversations, messages) and adds users, pets, pet_images, applications, audit logs, and admin messages.

USE pawnet;

-- 1. Users table (role-based: buyer, seller, admin)
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(40) NULL DEFAULT '',
    address VARCHAR(255) NULL DEFAULT '',
    role ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
    status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
    password_hash VARCHAR(255) NOT NULL,
    favorites_json JSON NULL,
    settings_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Pets table (supports multiple statuses including reserved, soft-deleted)
CREATE TABLE IF NOT EXISTS pets (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    owner_id VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    breed VARCHAR(120) NOT NULL,
    type VARCHAR(32) NOT NULL,
    sex ENUM('male', 'female', 'unknown') NOT NULL DEFAULT 'unknown',
    age INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    health_info TEXT NULL,
    vaccination_status VARCHAR(120) NULL DEFAULT 'Up to date',
    description TEXT NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    status ENUM('available', 'reserved', 'sold', 'deleted') NOT NULL DEFAULT 'available',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    sold_at DATETIME(6) NULL DEFAULT NULL,
    deleted_at DATETIME(6) NULL DEFAULT NULL,
    KEY idx_pets_owner (owner_id),
    KEY idx_pets_status (status),
    KEY idx_pets_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Pet images table (multiple images per pet, primary flag, reordering)
CREATE TABLE IF NOT EXISTS pet_images (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    pet_id VARCHAR(64) NOT NULL,
    image_url TEXT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_pet_images_pet (pet_id),
    KEY idx_pet_images_order (pet_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Applications table (formal state machine: pending -> accepted/rejected -> meeting_confirmed -> completed/cancelled)
CREATE TABLE IF NOT EXISTS applications (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    pet_id VARCHAR(64) NOT NULL,
    buyer_id VARCHAR(64) NOT NULL,
    seller_id VARCHAR(64) NOT NULL,
    conversation_id CHAR(48) NULL DEFAULT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'meeting_confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    meeting_confirmed_at DATETIME(6) NULL DEFAULT NULL,
    UNIQUE KEY uq_application_pet_buyer (pet_id, buyer_id),
    KEY idx_application_buyer (buyer_id, created_at),
    KEY idx_application_seller (seller_id, created_at),
    KEY idx_application_pet (pet_id),
    KEY idx_application_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Extend conversations table if columns are missing
SET @exist_app_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'conversations' AND COLUMN_NAME = 'application_id'
);
SET @sql_add_app_col := IF(@exist_app_col = 0, 'ALTER TABLE conversations ADD COLUMN application_id VARCHAR(64) NULL AFTER seller_name;', 'SELECT 1;');
PREPARE stmt FROM @sql_add_app_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_status_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'conversations' AND COLUMN_NAME = 'status'
);
SET @sql_add_status_col := IF(@exist_status_col = 0, 'ALTER TABLE conversations ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT \'active\' AFTER created_at;', 'SELECT 1;');
PREPARE stmt FROM @sql_add_status_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Admin audit logs
CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    admin_id VARCHAR(64) NOT NULL,
    admin_name VARCHAR(120) NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id VARCHAR(64) NOT NULL,
    details_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_audit_time (created_at),
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Admin messages / warnings to users
CREATE TABLE IF NOT EXISTS admin_messages (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    admin_id VARCHAR(64) NOT NULL,
    admin_name VARCHAR(120) NOT NULL DEFAULT 'Paw Net Admin',
    subject VARCHAR(191) NOT NULL,
    message_text TEXT NOT NULL,
    is_warning TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME(6) NULL DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_admin_msg_recipient (recipient_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
