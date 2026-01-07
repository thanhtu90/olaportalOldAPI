-- Combined Database Schema for Subscription Management System
-- Includes:
-- 1. Initial schema creation
-- 2. Partitioning and performance optimizations
-- 3. Compliance enhancements
-- 4. Existing database integration

-- =============================================
-- Initial Schema Creation
-- =============================================

-- Customers table
-- Note: The customers table creation is removed as we're using the existing customer table
-- See the "Existing Database Integration" section at the end of this file for modifications
-- to the existing customer table

-- Subscription Plans table
CREATE TABLE subscription_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_interval ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annually') NOT NULL,
    trial_days INT UNSIGNED DEFAULT 0,
    features JSON,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_plan_uuid (uuid),
    INDEX idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Billing Addresses table (separate from customer for PCI compliance)
CREATE TABLE billing_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_billing_customer (customer_id),
    CONSTRAINT fk_billing_customer FOREIGN KEY (customer_id) 
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Methods table
CREATE TABLE payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_type ENUM('credit_card', 'debit_card', 'bank_account', 'paypal', 'other') NOT NULL,
    payment_token VARCHAR(255) NOT NULL, -- Tokenized payment info from payment processor
    last_four VARCHAR(4),
    expiry_date VARCHAR(7), -- MM/YYYY format
    card_type VARCHAR(50),
    billing_address_id BIGINT UNSIGNED,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('active', 'expired', 'invalid') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_payment_customer (customer_id),
    INDEX idx_payment_status (status),
    CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_address FOREIGN KEY (billing_address_id)
        REFERENCES billing_addresses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriptions table
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    vendor_id INT NOT NULL,
    subscription_plan_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED,
    status ENUM('pending', 'active', 'past_due', 'canceled', 'expired') NOT NULL DEFAULT 'pending',
    next_billing_date DATE NOT NULL,
    last_billing_date DATE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE,
    cancellation_date DATE,
    cancellation_reason VARCHAR(255),
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    current_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    metadata JSON,
    PRIMARY KEY (id),
    UNIQUE KEY uk_subscription_uuid (uuid),
    INDEX idx_subscription_customer (customer_id),
    INDEX idx_subscription_vendor (vendor_id),
    INDEX idx_subscription_plan (subscription_plan_id),
    INDEX idx_subscription_payment (payment_method_id),
    INDEX idx_subscription_status (status),
    INDEX idx_subscription_billing (next_billing_date),
    CONSTRAINT fk_subscription_customer FOREIGN KEY (customer_id)
        REFERENCES customer (id) ON DELETE CASCADE,
    CONSTRAINT fk_subscription_plan FOREIGN KEY (subscription_plan_id)
        REFERENCES subscription_plans (id) ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_payment FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processing Plans table
CREATE TABLE processing_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    frequency ENUM('daily', 'weekly', 'monthly', 'custom') NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processing_window_start TIME,
    processing_window_end TIME,
    max_batch_size INT UNSIGNED NOT NULL DEFAULT 1000,
    retry_strategy JSON,
    PRIMARY KEY (id),
    INDEX idx_processing_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Batches table
CREATE TABLE batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL,
    processing_plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'canceled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scheduled_time TIMESTAMP NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    processed_items INT UNSIGNED NOT NULL DEFAULT 0,
    success_items INT UNSIGNED NOT NULL DEFAULT 0,
    failed_items INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT,
    metadata JSON,
    PRIMARY KEY (id),
    UNIQUE KEY uk_batch_uuid (uuid),
    INDEX idx_batch_status (status),
    INDEX idx_batch_scheduled (scheduled_time),
    INDEX idx_batch_processing_plan (processing_plan_id),
    CONSTRAINT fk_batch_processing_plan FOREIGN KEY (processing_plan_id)
        REFERENCES processing_plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Batch Items table
CREATE TABLE batch_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'processing', 'success', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT,
    result_data JSON,
    PRIMARY KEY (id),
    UNIQUE KEY uk_batch_subscription (batch_id, subscription_id),
    INDEX idx_batch_item_batch (batch_id),
    INDEX idx_batch_item_subscription (subscription_id),
    INDEX idx_batch_item_status (status),
    CONSTRAINT fk_batch_item_batch FOREIGN KEY (batch_id)
        REFERENCES batches (id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_item_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table
CREATE TABLE transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    batch_item_id BIGINT UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending', 'processing', 'success', 'failed', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'pending',
    transaction_type ENUM('charge', 'refund', 'credit') NOT NULL DEFAULT 'charge',
    payment_processor VARCHAR(50) NOT NULL,
    processor_transaction_id VARCHAR(255),
    processor_response_code VARCHAR(50),
    processor_response_message TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    metadata JSON,
    PRIMARY KEY (id),
    UNIQUE KEY uk_transaction_uuid (uuid),
    INDEX idx_transaction_subscription (subscription_id),
    INDEX idx_transaction_batch_item (batch_item_id),
    INDEX idx_transaction_status (status),
    INDEX idx_transaction_created (created_at),
    INDEX idx_transaction_type (transaction_type),
    CONSTRAINT fk_transaction_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions (id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_batch_item FOREIGN KEY (batch_item_id)
        REFERENCES batch_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs table
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36),
    action VARCHAR(50) NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create common query views
CREATE VIEW vw_subscriptions_due_next_24h AS
SELECT s.* 
FROM subscriptions s
WHERE s.status = 'active' 
  AND s.next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY);

CREATE VIEW vw_customer_subscription_summary AS
SELECT 
    c.id AS customer_id,
    c.email,
    c.first_name,
    c.last_name,
    COUNT(s.id) AS total_subscriptions,
    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_subscriptions,
    SUM(CASE WHEN s.status = 'active' THEN s.current_price ELSE 0 END) AS monthly_recurring_revenue
FROM customers c
LEFT JOIN subscriptions s ON c.id = s.customer_id
GROUP BY c.id, c.email, c.first_name, c.last_name;

-- =============================================
-- Partitioning and Performance Optimizations
-- =============================================

-- Add partitioning to transactions table
ALTER TABLE transactions PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p_transactions_current VALUES LESS THAN (YEAR(CURDATE()) * 100 + MONTH(CURDATE()) + 1),
    PARTITION p_transactions_future VALUES LESS THAN MAXVALUE
);

-- Add partitioning to batches table
ALTER TABLE batches PARTITION BY RANGE (UNIX_TIMESTAMP(scheduled_time)) (
    PARTITION p_batches_current VALUES LESS THAN (UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY))),
    PARTITION p_batches_future VALUES LESS THAN MAXVALUE
);

-- Add additional performance indexes
-- For customer searching
ALTER TABLE customers ADD FULLTEXT INDEX ft_customer_name (first_name, last_name);

-- For subscription filtering by date ranges
CREATE INDEX idx_subscription_date_range ON subscriptions (next_billing_date, status);

-- For batch item performance
CREATE INDEX idx_batch_item_composite ON batch_items (batch_id, status, subscription_id);

-- For transaction reporting
CREATE INDEX idx_transaction_date_amount ON transactions (created_at, amount, status);

-- For subscription auditing
CREATE INDEX idx_subscription_updated ON subscriptions (updated_at);

-- Create archive tables for historical data
CREATE TABLE transactions_archive LIKE transactions;
ALTER TABLE transactions_archive REMOVE PARTITIONING;

CREATE TABLE batches_archive LIKE batches;
ALTER TABLE batches_archive REMOVE PARTITIONING;

-- =============================================
-- Stored Procedures and Functions
-- =============================================

DELIMITER $$

-- Transaction partition maintenance procedure
CREATE PROCEDURE sp_maintain_transaction_partitions()
BEGIN
    DECLARE current_year INT;
    DECLARE current_month INT;
    DECLARE next_partition_value INT;
    DECLARE partition_exists INT;
    
    -- Calculate current year-month and next partition value
    SET current_year = YEAR(CURDATE());
    SET current_month = MONTH(CURDATE());
    SET next_partition_value = (current_year * 100 + current_month + 1);
    
    -- Check if the next month partition already exists
    SELECT COUNT(*) INTO partition_exists
    FROM information_schema.partitions 
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND partition_name = CONCAT('p_transactions_', next_partition_value);
    
    -- If next month partition doesn't exist, create it
    IF partition_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE transactions REORGANIZE PARTITION p_transactions_future INTO (
            PARTITION p_transactions_', next_partition_value, ' VALUES LESS THAN (', next_partition_value + 1, '),
            PARTITION p_transactions_future VALUES LESS THAN MAXVALUE
        )');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- Remove partitions older than 24 months (if needed)
    SET @old_partition_date = current_year * 100 + current_month - 24;
    
    SELECT COUNT(*) INTO partition_exists
    FROM information_schema.partitions 
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND partition_name = CONCAT('p_transactions_', @old_partition_date);
    
    IF partition_exists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE transactions DROP PARTITION p_transactions_', @old_partition_date);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Batch partition maintenance procedure
CREATE PROCEDURE sp_maintain_batch_partitions()
BEGIN
    DECLARE tomorrow_unix BIGINT;
    DECLARE next_week_unix BIGINT;
    DECLARE partition_exists INT;
    
    -- Calculate unix timestamps for partition boundaries
    SET tomorrow_unix = UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY));
    SET next_week_unix = UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY));
    
    -- Check if the next week partition already exists
    SELECT COUNT(*) INTO partition_exists
    FROM information_schema.partitions 
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND partition_name = CONCAT('p_batches_nextweek');
    
    -- If next week partition doesn't exist, create it
    IF partition_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE batches REORGANIZE PARTITION p_batches_future INTO (
            PARTITION p_batches_nextweek VALUES LESS THAN (', next_week_unix, '),
            PARTITION p_batches_future VALUES LESS THAN MAXVALUE
        )');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- Remove partitions older than 3 months
    SET @old_partition_date = UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 MONTH));
    
    SELECT COUNT(*) INTO partition_exists
    FROM information_schema.partitions 
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND partition_name = CONCAT('p_batches_old');
    
    IF partition_exists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE batches DROP PARTITION p_batches_old');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Create archiving procedures
CREATE PROCEDURE sp_archive_transactions(IN months_to_keep INT)
BEGIN
    DECLARE archive_date DATE;
    SET archive_date = DATE_SUB(CURDATE(), INTERVAL months_to_keep MONTH);
    
    -- Insert old records into archive
    INSERT INTO transactions_archive
    SELECT * FROM transactions
    WHERE created_at < archive_date;
    
    -- Delete archived records from main table
    DELETE FROM transactions 
    WHERE created_at < archive_date;
END$$

CREATE PROCEDURE sp_archive_batches(IN months_to_keep INT)
BEGIN
    DECLARE archive_date DATE;
    SET archive_date = DATE_SUB(CURDATE(), INTERVAL months_to_keep MONTH);
    
    -- Insert old records into archive
    INSERT INTO batches_archive
    SELECT * FROM batches
    WHERE created_at < archive_date;
    
    -- Delete archived records from main table
    DELETE FROM batches 
    WHERE created_at < archive_date;
END$$

-- =============================================
-- Compliance Enhancements
-- =============================================

-- Data masking function for PCI compliance
CREATE FUNCTION fn_mask_pii(input_text VARCHAR(255), mask_char CHAR(1), visible_chars INT)
RETURNS VARCHAR(255)
DETERMINISTIC
BEGIN
    DECLARE result VARCHAR(255);
    DECLARE text_length INT;
    
    IF input_text IS NULL THEN
        RETURN NULL;
    END IF;
    
    SET text_length = CHAR_LENGTH(input_text);
    
    IF text_length <= visible_chars THEN
        RETURN input_text;
    END IF;
    
    SET result = CONCAT(
        LEFT(input_text, visible_chars),
        REPEAT(mask_char, text_length - visible_chars)
    );
    
    RETURN result;
END$$

-- GDPR data export procedure
CREATE PROCEDURE sp_export_customer_data(IN customer_uuid VARCHAR(36))
BEGIN
    -- Declare variables to hold customer ID
    DECLARE customer_id BIGINT UNSIGNED;
    
    -- Get customer ID from UUID
    SELECT id INTO customer_id
    FROM customers
    WHERE uuid = customer_uuid;
    
    -- Return customer personal data
    SELECT
        'customer_data' AS data_section,
        c.uuid,
        c.email,
        c.first_name,
        c.last_name,
        c.phone,
        c.created_at,
        c.updated_at,
        c.gdpr_consent,
        c.marketing_consent,
        c.consent_date
    FROM customers c
    WHERE c.id = customer_id;
    
    -- Return customer billing addresses
    SELECT
        'billing_addresses' AS data_section,
        ba.id,
        ba.address_line1,
        ba.address_line2,
        ba.city,
        ba.state,
        ba.postal_code,
        ba.country,
        ba.is_default,
        ba.created_at,
        ba.updated_at
    FROM billing_addresses ba
    WHERE ba.customer_id = customer_id;
    
    -- Return masked payment methods
    SELECT
        'payment_methods' AS data_section,
        pm.id,
        pm.payment_type,
        pm.last_four,
        pm.expiry_date,
        pm.card_type,
        pm.is_default,
        pm.status,
        pm.created_at,
        pm.updated_at
    FROM payment_methods pm
    WHERE pm.customer_id = customer_id;
    
    -- Return subscriptions
    SELECT
        'subscriptions' AS data_section,
        s.uuid,
        s.status,
        s.billing_period_start,
        s.next_billing_date,
        s.last_billing_date,
        s.billing_period_end,
        s.cancellation_date,
        s.cancellation_reason,
        s.quantity,
        s.current_price,
        s.created_at,
        s.updated_at
    FROM subscriptions s
    WHERE s.customer_id = customer_id;
    
    -- Return transaction history (without sensitive data)
    SELECT
        'transactions' AS data_section,
        t.uuid,
        t.amount,
        t.currency,
        t.status,
        t.transaction_type,
        t.created_at,
        t.updated_at,
        t.completed_at
    FROM transactions t
    JOIN subscriptions s ON t.subscription_id = s.id
    WHERE s.customer_id = customer_id;
END$$

-- GDPR right to be forgotten procedure
CREATE PROCEDURE sp_gdpr_forget_customer(IN customer_uuid VARCHAR(36))
BEGIN
    -- Declare variables
    DECLARE customer_id BIGINT UNSIGNED;
    
    -- Get customer ID from UUID
    SELECT id INTO customer_id
    FROM customers
    WHERE uuid = customer_uuid;
    
    -- Create audit trail before deletion
    INSERT INTO audit_logs (entity_type, entity_id, action, old_values, created_at)
    SELECT 
        'customers', 
        customer_uuid, 
        'gdpr_forget_requested', 
        JSON_OBJECT(
            'id', id,
            'uuid', uuid,
            'email', email,
            'request_date', CURRENT_TIMESTAMP
        ),
        CURRENT_TIMESTAMP
    FROM customers
    WHERE id = customer_id;
    
    -- Update customer record to anonymize PII
    UPDATE customers
    SET 
        email = CONCAT('deleted_', UUID()),
        first_name = NULL,
        last_name = NULL,
        phone = NULL,
        status = 'deleted',
        gdpr_consent = FALSE,
        marketing_consent = FALSE
    WHERE id = customer_id;
    
    -- Anonymize billing addresses
    UPDATE billing_addresses
    SET 
        address_line1 = 'DELETED',
        address_line2 = NULL,
        city = 'DELETED',
        state = 'DELETED',
        postal_code = 'DELETED'
    WHERE customer_id = customer_id;
    
    -- Anonymize payment methods (mark as invalid but keep for transaction history)
    UPDATE payment_methods
    SET 
        payment_token = 'DELETED',
        last_four = NULL,
        expiry_date = NULL,
        card_type = NULL,
        status = 'invalid'
    WHERE customer_id = customer_id;
    
    -- Log completion
    INSERT INTO audit_logs (entity_type, entity_id, action, new_values, created_at)
    VALUES (
        'customers', 
        customer_uuid, 
        'gdpr_forget_completed', 
        JSON_OBJECT('completion_date', CURRENT_TIMESTAMP),
        CURRENT_TIMESTAMP
    );
END$$

-- PCI compliance placeholder encryption functions (note: keys would be managed externally)
CREATE FUNCTION fn_encrypt_placeholder(input_text VARCHAR(255)) 
RETURNS VARCHAR(255)
DETERMINISTIC
BEGIN
    -- Note: In a real implementation, this would use proper encryption
    -- This is a placeholder to demonstrate the concept
    RETURN CONCAT('ENCRYPTED:', input_text);
END$$

CREATE FUNCTION fn_decrypt_placeholder(encrypted_text VARCHAR(255)) 
RETURNS VARCHAR(255)
DETERMINISTIC
BEGIN
    -- Note: In a real implementation, this would use proper decryption
    -- This is a placeholder to demonstrate the concept
    IF LEFT(encrypted_text, 10) = 'ENCRYPTED:' THEN
        RETURN SUBSTRING(encrypted_text, 11);
    ELSE
        RETURN encrypted_text;
    END IF;
END$$

DELIMITER ;

-- =============================================
-- Triggers
-- =============================================

DELIMITER $$

-- Add data retention policy trigger for GDPR compliance
CREATE TRIGGER trg_customers_gdpr_delete
BEFORE DELETE ON customers
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (entity_type, entity_id, action, old_values, created_at)
    VALUES ('customers', OLD.uuid, 'gdpr_delete', 
        JSON_OBJECT(
            'id', OLD.id,
            'uuid', OLD.uuid,
            'email', OLD.email,
            'deletion_date', CURRENT_TIMESTAMP
        ),
        CURRENT_TIMESTAMP
    );
END$$

-- Add PCI compliance audit triggers
CREATE TRIGGER trg_payment_methods_audit
AFTER UPDATE ON payment_methods
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_at)
    VALUES (
        'payment_methods', 
        CONCAT(NEW.id), 
        'update', 
        JSON_OBJECT(
            'payment_type', OLD.payment_type,
            'is_default', OLD.is_default,
            'status', OLD.status
        ),
        JSON_OBJECT(
            'payment_type', NEW.payment_type,
            'is_default', NEW.is_default,
            'status', NEW.status
        ),
        CURRENT_TIMESTAMP
    );
END$$

CREATE TRIGGER trg_transactions_audit
AFTER UPDATE ON transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_at)
    VALUES (
        'transactions', 
        NEW.uuid, 
        'update', 
        JSON_OBJECT(
            'status', OLD.status,
            'amount', OLD.amount
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'amount', NEW.amount
        ),
        CURRENT_TIMESTAMP
    );
END$$

DELIMITER ;

-- =============================================
-- Scheduled Events
-- =============================================

DELIMITER $$

-- Create event to maintain partitions automatically
CREATE EVENT evt_maintain_partitions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP + INTERVAL 1 HOUR
DO
BEGIN
    CALL sp_maintain_transaction_partitions();
    CALL sp_maintain_batch_partitions();
END$$

DELIMITER ;

-- =============================================
-- Additional Compliance Views
-- =============================================

-- Create masked view for customer PII data
CREATE VIEW vw_customers_masked AS
SELECT 
    id,
    uuid,
    fn_mask_pii(email, '*', 3) AS email,
    fn_mask_pii(first_name, '*', 1) AS first_name,
    fn_mask_pii(last_name, '*', 1) AS last_name,
    fn_mask_pii(phone, '*', 4) AS phone,
    created_at,
    updated_at,
    gdpr_consent,
    marketing_consent,
    consent_date,
    status
FROM customers;

-- Create masked view for payment methods
CREATE VIEW vw_payment_methods_masked AS
SELECT 
    pm.id,
    pm.customer_id,
    pm.payment_type,
    '************' AS payment_token,
    pm.last_four,
    pm.expiry_date,
    pm.card_type,
    pm.billing_address_id,
    pm.is_default,
    pm.status,
    pm.created_at,
    pm.updated_at
FROM payment_methods pm;

-- Create compliance reporting views
CREATE VIEW vw_gdpr_consent_status AS
SELECT
    COUNT(*) AS total_customers,
    SUM(CASE WHEN gdpr_consent = TRUE THEN 1 ELSE 0 END) AS gdpr_consent_granted,
    SUM(CASE WHEN marketing_consent = TRUE THEN 1 ELSE 0 END) AS marketing_consent_granted,
    SUM(CASE WHEN consent_date IS NOT NULL THEN 1 ELSE 0 END) AS consent_date_recorded
FROM customers
WHERE status = 'active';

CREATE VIEW vw_pci_sensitive_data_access AS
SELECT
    al.id,
    al.entity_type,
    al.entity_id,
    al.user_id,
    al.action,
    al.ip_address,
    al.created_at
FROM audit_logs al
WHERE al.entity_type IN ('payment_methods', 'transactions', 'customers')
  AND al.action IN ('view', 'export', 'update', 'delete')
ORDER BY al.created_at DESC;

-- =============================================
-- Existing Database Integration
-- =============================================

-- Alter existing customer table to add subscription management columns
-- This script integrates the new columns needed for the subscription system into the existing customer table

-- Add UUID column and modify existing columns' types where necessary
ALTER TABLE `customer`
ADD COLUMN `uuid` VARCHAR(36) AFTER `id`,
MODIFY COLUMN `email` VARCHAR(255) NOT NULL,
MODIFY COLUMN `first_name` VARCHAR(100) NOT NULL,
MODIFY COLUMN `last_name` VARCHAR(100) NOT NULL;

-- Update all existing customers to have UUID values
UPDATE `customer` SET `uuid` = UUID() WHERE `uuid` IS NULL;

-- Make UUID NOT NULL and create unique index
ALTER TABLE `customer` 
MODIFY COLUMN `uuid` VARCHAR(36) NOT NULL,
ADD UNIQUE INDEX `uk_customer_uuid` (`uuid`);

-- Add subscription management and GDPR compliance columns
ALTER TABLE `customer`
ADD COLUMN `gdpr_consent` BOOLEAN NOT NULL DEFAULT FALSE AFTER `gender`,
ADD COLUMN `marketing_consent` BOOLEAN NOT NULL DEFAULT FALSE AFTER `gdpr_consent`,
ADD COLUMN `consent_date` TIMESTAMP NULL AFTER `marketing_consent`,
ADD COLUMN `fivserv_security_token` VARCHAR(255) NULL COMMENT 'Token used for authenticating with Fiserv payment processing service';

-- Convert status to proper ENUM type (preserve existing data)
-- First add a temporary column 
ALTER TABLE `customer`
ADD COLUMN `status_enum` ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active' AFTER `status`;

-- Update new status column based on existing boolean status
UPDATE `customer` 
SET `status_enum` = CASE 
    WHEN `status` = 1 THEN 'active' 
    ELSE 'suspended' 
END;

-- Drop old status column and rename new one
ALTER TABLE `customer`
DROP COLUMN `status`,
CHANGE COLUMN `status_enum` `status` ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active';

-- Add appropriate indexes for performance
ALTER TABLE `customer`
ADD UNIQUE INDEX `uk_customer_email` (`email`(255)),
ADD INDEX `idx_customer_status` (`status`),
ADD INDEX `idx_customer_fivserv_token` (`fivserv_security_token`);

-- Rename columns to match the subscription schema naming
ALTER TABLE `customer`
CHANGE COLUMN `timestamp` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
CHANGE COLUMN `lastmod` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Optional: Add a comment to the table
ALTER TABLE `customer` COMMENT = 'Customer table enhanced for subscription management with GDPR compliance';

-- Note: With the existing customer table integration, the 'customers' table creation in this schema
-- should only be executed if the customer table does not already exist. 