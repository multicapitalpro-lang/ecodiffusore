-- Fase 84: Licenciado pode solicitar um e-mail profissional no dominio (ex:
-- comercial.cascavel@ecodiffusorebrasil.com.br) -- entra como beneficio da assinatura ativa
-- (SubscriptionGate), nao um produto pago a parte. A Hostinger tem limite de caixas por plano
-- de hospedagem, entao o admin define uma cota maxima (custom_email_quota) e o provisionamento
-- de fato (criar a caixa no hPanel) continua manual -- sem API publica documentada da Hostinger
-- pra isso em hospedagem compartilhada.

ALTER TABLE company_settings
    ADD COLUMN custom_email_quota INT UNSIGNED NOT NULL DEFAULT 0 AFTER terms_text;

CREATE TABLE licenciado_email_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    local_part VARCHAR(100) NOT NULL,
    full_address VARCHAR(180) NOT NULL,
    status ENUM('pendente', 'ativo', 'recusado') NOT NULL DEFAULT 'pendente',
    admin_note VARCHAR(255) NULL,
    resolved_by_user_id INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_email_request_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_email_full_address (full_address),
    INDEX idx_email_request_user (user_id),
    INDEX idx_email_request_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
