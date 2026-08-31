-- Ecodiffusore Brasil - Painel - Fase 7b (cobranca via Asaas)

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_type ENUM('order','quote') NOT NULL,
    payable_id INT UNSIGNED NOT NULL,
    asaas_customer_id VARCHAR(40) NOT NULL,
    asaas_charge_id VARCHAR(40) NOT NULL UNIQUE,
    method VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pendente','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente',
    checkout_url VARCHAR(255) NULL,
    pix_payload TEXT NULL,
    due_date DATE NOT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
