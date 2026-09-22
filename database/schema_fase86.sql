-- Fase 86: limite de colaboradores inclusos na assinatura (5, Gestor+Vendedor, sem contar o
-- Licenciado) + cobranca de vaga extra (R$25/mes, com desconto semestral/anual espelhando os
-- mesmos percentuais da assinatura base) via Mercado Pago -- mesmo modelo prepago-por-periodo de
-- licenciado_subscriptions. Tambem: registro de "paywall hit" (quem esbarrou em que ferramenta
-- bloqueada e quando) pra alimentar o banner de remarketing no painel/app -- pedido explicito do
-- usuario ("é super importante o Licenciado saber o quanto essas ferramentas são importantes").

CREATE TABLE licenciado_seat_addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licenciado_id INT UNSIGNED NOT NULL,
    plan ENUM('mensal', 'semestral', 'anual') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    status ENUM('pendente', 'ativa') NOT NULL DEFAULT 'pendente',
    amount DECIMAL(10,2) NOT NULL,
    external_reference VARCHAR(64) NOT NULL,
    mp_payment_id VARCHAR(64) NULL,
    checkout_url VARCHAR(500) NULL,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_seat_addon_licenciado FOREIGN KEY (licenciado_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_seat_addon_reference (external_reference),
    INDEX idx_seat_addon_licenciado (licenciado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_paywall_hits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    licenciado_id INT UNSIGNED NULL,
    feature VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paywall_hit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_paywall_hit_licenciado (licenciado_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
