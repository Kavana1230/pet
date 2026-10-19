CREATE DATABASE IF NOT EXISTS pawnet
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pawnet;

CREATE TABLE IF NOT EXISTS conversations (
    id CHAR(48) NOT NULL,
    pet_id VARCHAR(64) NOT NULL,
    pet_name VARCHAR(120) NOT NULL,
    buyer_id VARCHAR(64) NOT NULL,
    buyer_name VARCHAR(120) NOT NULL,
    seller_id VARCHAR(64) NOT NULL,
    seller_name VARCHAR(120) NOT NULL,
    listing_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_conversation_pet_buyer (pet_id, buyer_id),
    KEY idx_conversation_buyer_latest (buyer_id, created_at),
    KEY idx_conversation_seller_latest (seller_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id CHAR(48) NOT NULL,
    conversation_id CHAR(48) NOT NULL,
    sender_id VARCHAR(64) NOT NULL,
    sender_name VARCHAR(120) NOT NULL,
    message_text TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    read_at DATETIME(6) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_message_conversation_time (conversation_id, created_at, id),
    KEY idx_message_unread (conversation_id, sender_id, read_at),
    CONSTRAINT fk_message_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
