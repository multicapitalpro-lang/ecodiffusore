-- Fase 45: cotacao publica de maquina agricola (sem placa) -- pedido repassado por audio do
-- WhatsApp: hoje /comprar so cobre veiculo com placa (caminhao/onibus). Maquina agricola nao
-- tem placa, entao precisa de um formulario proprio (tipo/marca/modelo/potencia/mangueira +
-- 3 fotos) sem calculo de preco automatico -- fica pendente pra equipe precificar manualmente
-- (ainda sem tabela de preco pronta pra todo tipo de maquina) e retornar pro cliente por fora.

CREATE TABLE machine_quote_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NULL,
    assigned_user_id INT UNSIGNED NULL,
    client_name VARCHAR(150) NOT NULL,
    client_whatsapp VARCHAR(30) NULL,
    client_city VARCHAR(120) NULL,
    machine_type VARCHAR(80) NOT NULL,
    brand VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    power VARCHAR(30) NULL,
    hose_measure VARCHAR(60) NULL,
    photo_general_path VARCHAR(64) NULL,
    photo_nameplate_path VARCHAR(64) NULL,
    photo_hose_path VARCHAR(64) NULL,
    status ENUM('pendente', 'respondido') NOT NULL DEFAULT 'pendente',
    quoted_price DECIMAL(10,2) NULL,
    internal_notes TEXT NULL,
    responded_by_user_id INT UNSIGNED NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_machine_quote_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_quote_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_quote_responder FOREIGN KEY (responded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_machine_quote_status (status),
    INDEX idx_machine_quote_assignee (assigned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
