-- Fase 32: Assinatura do Licenciado (paywall Relatorios/Financeiro), custos completos do
-- Licenciado (sempre liberado) e aceite digital de comissao por WhatsApp pra Gestor/Vendedor.
-- Aplicado em produção via script PHP standalone (scp + SSH), documentado aqui pro histórico.

CREATE TABLE IF NOT EXISTS licenciado_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan ENUM('mensal','semestral','anual') NOT NULL,
    status ENUM('pendente','ativa','expirada','cancelada') NOT NULL DEFAULT 'pendente',
    amount DECIMAL(10,2) NOT NULL,
    external_reference VARCHAR(64) NOT NULL,
    mp_payment_id VARCHAR(64) NULL,
    checkout_url VARCHAR(500) NULL,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_licenciado_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_external_reference (external_reference),
    INDEX idx_licenciado_subscriptions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS licenciado_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licenciado_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_licenciado_expenses_user FOREIGN KEY (licenciado_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_licenciado_expenses_licenciado (licenciado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN commission_accept_token VARCHAR(64) NULL,
    ADD COLUMN commission_accepted_at DATETIME NULL;
