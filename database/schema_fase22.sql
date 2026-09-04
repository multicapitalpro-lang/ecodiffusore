ALTER TABLE goals
    ADD COLUMN reward_description VARCHAR(255) NULL AFTER target_value,
    ADD COLUMN reward_amount DECIMAL(12,2) NULL AFTER reward_description,
    ADD COLUMN reward_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER reward_amount;
