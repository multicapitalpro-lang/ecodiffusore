-- Ecodiffusore Brasil - Painel - Fase 2 (CRM/Vendas + Financeiro basico)
-- Executar uma unica vez, depois do schema.sql da Fase 1

ALTER TABLE users ADD COLUMN commission_pct DECIMAL(5,2) NULL AFTER status;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    document VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    whatsapp VARCHAR(30) NULL,
    city VARCHAR(120) NULL,
    state CHAR(2) NULL,
    address VARCHAR(255) NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    price_cash DECIMAL(10,2) NOT NULL,
    price_installment DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (sku, name, price_cash, price_installment, cost_price) VALUES
    ('ECO-SCANIA-2018', 'Linha Scania (até 2018)', 2836.00, 529.83, 1200.00),
    ('ECO-VOLVO', 'Linha Volvo (FH, FM, VM...)', 2876.00, 537.17, 1200.00),
    ('ECO-IVECO', 'Linha Iveco', 2916.00, 544.50, 1200.00),
    ('ECO-MERCEDES', 'Linha Mercedes', 2935.00, 548.17, 1200.00),
    ('ECO-VOLVO-ROBOCOP', 'Volvo Robocop', 3043.00, 568.33, 1300.00),
    ('ECO-METEOR', 'Linha Meteor', 3106.10, 581.67, 1300.00),
    ('ECO-SCANIA-NTG', 'Scania NTG', 3131.00, 584.83, 1300.00),
    ('ECO-DAF', 'Linha DAF', 3131.00, 584.83, 1300.00)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NULL,
    status ENUM('em_andamento','atendido','verificado','cancelado') NOT NULL DEFAULT 'em_andamento',
    order_date DATE NOT NULL,
    total_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_orders_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pendente','pago') NOT NULL DEFAULT 'pendente',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comm_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_comm_seller FOREIGN KEY (seller_id) REFERENCES users(id),
    UNIQUE KEY uniq_order_commission (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('caixa','banco') NOT NULL DEFAULT 'caixa',
    initial_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO financial_accounts (name, type, initial_balance)
SELECT 'Caixa Principal', 'caixa', 0
WHERE NOT EXISTS (SELECT 1 FROM financial_accounts);

CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    type ENUM('entrada','saida') NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    status ENUM('pendente','pago','conciliado') NOT NULL DEFAULT 'pendente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trans_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id),
    CONSTRAINT fk_trans_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
