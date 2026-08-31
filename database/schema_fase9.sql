-- Ecodiffusore Brasil - Painel - Fase 9 (governanca: aprovacao de desconto + auditoria)

ALTER TABLE users ADD COLUMN discount_limit_pct DECIMAL(5,2) NULL AFTER commission_pct;

CREATE TABLE IF NOT EXISTS approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    approvable_type ENUM('order','quote') NOT NULL,
    approvable_id INT UNSIGNED NOT NULL,
    requested_discount_pct DECIMAL(5,2) NOT NULL,
    status ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
    requested_by INT UNSIGNED NOT NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appr_requested FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_appr_decided FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE activity_log
    ADD COLUMN entity_type VARCHAR(40) NULL AFTER action,
    ADD COLUMN entity_id INT UNSIGNED NULL AFTER entity_type,
    ADD COLUMN before_value TEXT NULL AFTER entity_id,
    ADD COLUMN after_value TEXT NULL AFTER before_value;
