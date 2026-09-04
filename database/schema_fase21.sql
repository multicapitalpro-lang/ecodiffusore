ALTER TABLE orders
    ADD COLUMN vehicle_type VARCHAR(60) NULL AFTER notes,
    ADD COLUMN vehicle_plate VARCHAR(10) NULL AFTER vehicle_type,
    ADD COLUMN vehicle_document_path VARCHAR(64) NULL AFTER vehicle_plate;

-- Adiciona "reembolsado" ao leque de status de pagamento (nao havia forma de marcar isso hoje).
ALTER TABLE payments MODIFY status ENUM('pendente','pago','vencido','cancelado','reembolsado') NOT NULL DEFAULT 'pendente';
