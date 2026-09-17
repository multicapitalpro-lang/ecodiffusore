-- Fase 56: Painel do Influenciador
INSERT INTO roles (slug, name) VALUES ('influenciador', 'Influenciador')
    ON DUPLICATE KEY UPDATE name = VALUES(name);

ALTER TABLE users
    ADD COLUMN influencer_commission_value DECIMAL(10,2) NULL AFTER commission_pct;

ALTER TABLE leads
    ADD COLUMN influencer_id INT UNSIGNED NULL AFTER assigned_to_user_id,
    ADD CONSTRAINT fk_leads_influencer FOREIGN KEY (influencer_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE quotes
    ADD COLUMN influencer_id INT UNSIGNED NULL AFTER lead_id,
    ADD CONSTRAINT fk_quotes_influencer FOREIGN KEY (influencer_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE orders
    ADD COLUMN influencer_id INT UNSIGNED NULL AFTER seller_id,
    ADD CONSTRAINT fk_orders_influencer FOREIGN KEY (influencer_id) REFERENCES users(id) ON DELETE SET NULL;
