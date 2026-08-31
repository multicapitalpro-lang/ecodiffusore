-- Ecodiffusore Brasil - Painel - Fase 7a (hierarquia de equipe + comissao em cascata)

ALTER TABLE users
    ADD COLUMN manager_id INT UNSIGNED NULL AFTER role_id,
    ADD CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE commissions
    ADD COLUMN beneficiary_id INT UNSIGNED NULL AFTER seller_id,
    ADD COLUMN role_slug VARCHAR(20) NULL AFTER beneficiary_id,
    ADD CONSTRAINT fk_comm_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES users(id);

UPDATE commissions SET beneficiary_id = seller_id, role_slug = 'licenciado' WHERE beneficiary_id IS NULL;

ALTER TABLE commissions
    MODIFY COLUMN beneficiary_id INT UNSIGNED NOT NULL,
    MODIFY COLUMN role_slug VARCHAR(20) NOT NULL,
    DROP INDEX uniq_order_commission,
    ADD UNIQUE KEY uniq_order_beneficiary (order_id, beneficiary_id);
