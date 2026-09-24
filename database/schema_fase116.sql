-- Fase 116: dados bancarios/Pix obrigatorios no primeiro acesso pra quem recebe comissao
-- (Gestor/Licenciado/Vendedor/Gerente/Supervisor) + comprovante de pagamento ao dar baixa.
ALTER TABLE users
    ADD COLUMN bank_code VARCHAR(10) NULL AFTER commission_pct,
    ADD COLUMN bank_name VARCHAR(100) NULL AFTER bank_code,
    ADD COLUMN bank_agency VARCHAR(15) NULL AFTER bank_name,
    ADD COLUMN bank_account VARCHAR(20) NULL AFTER bank_agency,
    ADD COLUMN bank_account_digit VARCHAR(5) NULL AFTER bank_account,
    ADD COLUMN bank_account_type ENUM('corrente','poupanca') NULL AFTER bank_account_digit,
    ADD COLUMN pix_key VARCHAR(140) NULL AFTER bank_account_type,
    ADD COLUMN payment_document VARCHAR(20) NULL AFTER pix_key,
    ADD COLUMN bank_data_completed_at DATETIME NULL AFTER payment_document;

-- Comprovante de "Dar baixa" em comissao. Nao reaproveita financial_attachments (FK dura pra
-- financial_transactions, que pode nao existir se FinancialAccount::defaultAccountId() vier
-- vazio -- ver FinanceController::markCommissionPaid()) -- tabela propria, mesmo shape.
CREATE TABLE commission_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commission_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(64) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commission_attach FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
