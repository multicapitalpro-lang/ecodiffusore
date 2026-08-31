-- Ecodiffusore Brasil - Painel - Fase 3 (Caixas e Bancos / Contas a Pagar no padrao Bling)

CREATE TABLE IF NOT EXISTS financial_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('entrada','saida','ambos') NOT NULL DEFAULT 'ambos',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES financial_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grupos (pais, usados so como optgroup) + categorias filhas selecionaveis
INSERT INTO financial_categories (id, parent_id, name, type) VALUES
    (1, NULL, 'Custos', 'ambos'),
    (2, NULL, 'Despesas Administrativas', 'saida'),
    (3, NULL, 'Despesas com Pessoal', 'saida'),
    (4, NULL, 'Despesas Comerciais', 'saida'),
    (5, NULL, 'Impostos e Taxas', 'saida'),
    (6, NULL, 'Outras Despesas', 'saida'),
    (7, NULL, 'Vendas', 'entrada'),
    (8, NULL, 'Financeiro', 'entrada'),
    (9, NULL, 'Outras Receitas', 'entrada')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO financial_categories (parent_id, name, type) VALUES
    (1, 'Custo das mercadorias vendidas', 'saida'),
    (1, 'Custo dos produtos vendidos', 'saida'),
    (1, 'Custo dos serviços prestados', 'saida'),
    (1, 'Compra de insumos e matéria prima', 'saida'),
    (1, 'Compras de fornecedores', 'saida'),
    (2, 'Aluguel', 'saida'),
    (2, 'Água, luz e telefone', 'saida'),
    (2, 'Material de escritório', 'saida'),
    (2, 'Softwares e assinaturas', 'saida'),
    (3, 'Salários', 'saida'),
    (3, 'Comissões', 'saida'),
    (3, 'Encargos sociais', 'saida'),
    (3, 'Benefícios', 'saida'),
    (4, 'Marketing e publicidade', 'saida'),
    (4, 'Frete e logística', 'saida'),
    (4, 'Taxas de cartão / gateway', 'saida'),
    (5, 'Imposto de renda', 'saida'),
    (5, 'Impostos sobre vendas', 'saida'),
    (5, 'Contribuição social sobre lucro líquido', 'saida'),
    (5, 'Taxas bancárias', 'saida'),
    (6, 'Devoluções de vendas', 'saida'),
    (6, 'Descontos incondicionais', 'saida'),
    (6, 'Despesas diversas', 'saida'),
    (7, 'Venda de produtos', 'entrada'),
    (7, 'Venda de serviços', 'entrada'),
    (8, 'Rendimentos de aplicação', 'entrada'),
    (8, 'Empréstimos recebidos', 'entrada'),
    (9, 'Reembolsos', 'entrada'),
    (9, 'Receitas diversas', 'entrada')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS financial_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(64) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attach_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE financial_transactions
    DROP COLUMN category,
    ADD COLUMN category_id INT UNSIGNED NULL AFTER type,
    ADD COLUMN client_id INT UNSIGNED NULL AFTER order_id,
    ADD COLUMN issue_date DATE NULL AFTER due_date,
    ADD COLUMN competencia DATE NULL AFTER issue_date,
    ADD COLUMN payment_method VARCHAR(60) NULL AFTER competencia,
    ADD COLUMN document_number VARCHAR(60) NULL AFTER payment_method,
    ADD COLUMN interest_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER document_number,
    ADD COLUMN penalty_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER interest_pct,
    ADD CONSTRAINT fk_trans_category FOREIGN KEY (category_id) REFERENCES financial_categories(id),
    ADD CONSTRAINT fk_trans_client FOREIGN KEY (client_id) REFERENCES clients(id);
