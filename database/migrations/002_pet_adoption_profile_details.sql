-- Paw Net Database Migration 002: age units and structured care details.
-- Run after migration 001 on both fresh and existing databases.
-- Column checks make this safe if an earlier schema already added these fields.

USE pawnet;

SET @has_age_value := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'pets' AND COLUMN_NAME = 'age_value');
SET @ddl_age_value := IF(@has_age_value = 0, 'ALTER TABLE pets ADD COLUMN age_value DECIMAL(7,2) NULL', 'SELECT 1');
PREPARE pawnet_stmt FROM @ddl_age_value; EXECUTE pawnet_stmt; DEALLOCATE PREPARE pawnet_stmt;

SET @has_age_unit := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'pets' AND COLUMN_NAME = 'age_unit');
SET @ddl_age_unit := IF(@has_age_unit = 0, 'ALTER TABLE pets ADD COLUMN age_unit ENUM(''days'', ''months'', ''years'') NOT NULL DEFAULT ''years''', 'SELECT 1');
PREPARE pawnet_stmt FROM @ddl_age_unit; EXECUTE pawnet_stmt; DEALLOCATE PREPARE pawnet_stmt;

SET @has_sterilized := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'pets' AND COLUMN_NAME = 'sterilized_status');
SET @ddl_sterilized := IF(@has_sterilized = 0, 'ALTER TABLE pets ADD COLUMN sterilized_status ENUM(''yes'', ''no'', ''unknown'') NOT NULL DEFAULT ''unknown''', 'SELECT 1');
PREPARE pawnet_stmt FROM @ddl_sterilized; EXECUTE pawnet_stmt; DEALLOCATE PREPARE pawnet_stmt;

SET @has_dewormed := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'pets' AND COLUMN_NAME = 'dewormed_status');
SET @ddl_dewormed := IF(@has_dewormed = 0, 'ALTER TABLE pets ADD COLUMN dewormed_status ENUM(''yes'', ''no'', ''unknown'') NOT NULL DEFAULT ''unknown''', 'SELECT 1');
PREPARE pawnet_stmt FROM @ddl_dewormed; EXECUTE pawnet_stmt; DEALLOCATE PREPARE pawnet_stmt;

SET @has_trained := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'pawnet' AND TABLE_NAME = 'pets' AND COLUMN_NAME = 'trained_status');
SET @ddl_trained := IF(@has_trained = 0, 'ALTER TABLE pets ADD COLUMN trained_status ENUM(''yes'', ''no'', ''unknown'') NOT NULL DEFAULT ''unknown''', 'SELECT 1');
PREPARE pawnet_stmt FROM @ddl_trained; EXECUTE pawnet_stmt; DEALLOCATE PREPARE pawnet_stmt;

UPDATE pets
SET age_value = age
WHERE age_value IS NULL;

UPDATE pets
SET vaccination_status = CASE
    WHEN LOWER(COALESCE(vaccination_status, '')) IN ('yes', 'up to date', 'fully vaccinated', 'vaccinated') THEN 'yes'
    WHEN LOWER(COALESCE(vaccination_status, '')) IN ('no', 'not vaccinated') THEN 'no'
    ELSE 'unknown'
END;

ALTER TABLE pets
    MODIFY COLUMN age_value DECIMAL(7,2) NOT NULL DEFAULT 1.00;
