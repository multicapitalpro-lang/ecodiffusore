-- Ecodiffusore Brasil - Painel - Fase 8 (cadastro de cliente mais completo, no padrao Bling)

ALTER TABLE clients
    ADD COLUMN person_type ENUM('fisica','juridica') NOT NULL DEFAULT 'fisica' AFTER document,
    ADD COLUMN state_registration VARCHAR(30) NULL AFTER person_type,
    ADD COLUMN credit_limit_type ENUM('ilimitado','zero','valor') NOT NULL DEFAULT 'ilimitado' AFTER address,
    ADD COLUMN credit_limit_value DECIMAL(12,2) NULL AFTER credit_limit_type,
    ADD COLUMN payment_terms VARCHAR(100) NULL AFTER credit_limit_value,
    ADD COLUMN seller_id INT UNSIGNED NULL AFTER payment_terms,
    ADD CONSTRAINT fk_clients_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL;
