-- Fase 95: Indicacoes Premiadas -- quinta das 7 ferramentas premium aprovadas. Licenciado/Gestor/
-- Vendedor registra uma indicacao (pessoa que poderia virar Licenciado/Gestor/Vendedor), depois
-- vincula essa indicacao ao cadastro real quando alguem (staff) criar a conta pela tela normal de
-- Usuarios (sem mudar o fluxo de cadastro existente). Quando o indicado vira ativo, o indicador e'
-- avisado e pode registrar/pagar uma premiacao -- mesmo espirito de reward_description/
-- reward_amount/reward_paid ja usado em goals, mas sem depender de Pedido nenhum (nao e'
-- comissao de venda).
CREATE TABLE referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(20) NULL,
    target_role ENUM('licenciado','gestor','vendedor') NOT NULL DEFAULT 'vendedor',
    notes VARCHAR(255) NULL,
    status ENUM('indicado','em_contato','cadastrado','ativo','descartado') NOT NULL DEFAULT 'indicado',
    converted_user_id INT UNSIGNED NULL,
    reward_description VARCHAR(255) NULL,
    reward_amount DECIMAL(12,2) NULL,
    reward_paid TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_referrals_converted_user FOREIGN KEY (converted_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_referrals_referrer (referrer_id),
    INDEX idx_referrals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
