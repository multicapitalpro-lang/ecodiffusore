-- Fase 29: Rastreio real via API dos Correios + notificacao da Fabrica em pedido novo pago.
-- Aplicado em produção via script PHP standalone (scp + SSH), documentado aqui pro histórico.

ALTER TABLE orders
    ADD COLUMN tracking_status VARCHAR(255) NULL AFTER prazo_entrega,
    ADD COLUMN tracking_status_date DATETIME NULL AFTER tracking_status,
    ADD COLUMN tracking_checked_at DATETIME NULL AFTER tracking_status_date;
