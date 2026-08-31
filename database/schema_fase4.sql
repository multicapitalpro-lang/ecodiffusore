-- Ecodiffusore Brasil - Painel - Fase 4 (Remessa e Retorno + Relatorios agendados)

CREATE TABLE IF NOT EXISTS remittances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    type ENUM('pagar','receber') NOT NULL,
    payment_method VARCHAR(60) NULL,
    status ENUM('aberta','enviada','retornada') NOT NULL DEFAULT 'aberta',
    sent_at DATETIME NULL,
    returned_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remittance_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id),
    CONSTRAINT fk_remittance_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS remittance_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    remittance_id INT UNSIGNED NOT NULL,
    transaction_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remitem_remittance FOREIGN KEY (remittance_id) REFERENCES remittances(id) ON DELETE CASCADE,
    CONSTRAINT fk_remitem_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id),
    UNIQUE KEY uniq_remittance_transaction (remittance_id, transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(60) NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    frequency ENUM('diario','semanal','mensal') NOT NULL DEFAULT 'mensal',
    channel ENUM('email') NOT NULL DEFAULT 'email',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_sent_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
