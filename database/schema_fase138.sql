-- Fase 138: Nota Fiscal Automatica (ferramenta premium do Licenciado) -- Fase 1: dados fiscais
-- da empresa + creditos pre-pagos (via Mercado Pago, mesmo mecanismo ja usado pra vaga extra/
-- assinatura). A emissao de verdade via API de terceiro (Dados JAH/Notaas/NFE.io, a definir) e' a
-- Fase 2 -- por enquanto so' coleta os dados e o saldo de creditos, sem consumir nada ainda.

CREATE TABLE IF NOT EXISTS licenciado_fiscal_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licenciado_id INT UNSIGNED NOT NULL,
    razao_social VARCHAR(180) NOT NULL,
    cnpj VARCHAR(20) NOT NULL,
    inscricao_municipal VARCHAR(30) NULL,
    endereco_cep VARCHAR(10) NULL,
    endereco_logradouro VARCHAR(180) NULL,
    endereco_numero VARCHAR(20) NULL,
    endereco_complemento VARCHAR(100) NULL,
    endereco_bairro VARCHAR(100) NULL,
    endereco_cidade VARCHAR(100) NULL,
    endereco_uf CHAR(2) NULL,
    email_nota VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fiscal_licenciado (licenciado_id),
    CONSTRAINT fk_fiscal_licenciado FOREIGN KEY (licenciado_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS licenciado_nfe_credit_purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licenciado_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','ativa') NOT NULL DEFAULT 'pendente',
    external_reference VARCHAR(64) NOT NULL,
    mp_payment_id VARCHAR(60) NULL,
    checkout_url VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    UNIQUE KEY uk_nfecredit_reference (external_reference),
    CONSTRAINT fk_nfecredit_licenciado FOREIGN KEY (licenciado_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
