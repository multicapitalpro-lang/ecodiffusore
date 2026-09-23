-- Fase 101: dados de faturamento/entrega especificos do pedido a preco de custo (mostruario) --
-- pra quem a fabrica deve emitir a nota fiscal e pra onde entregar, ja que esse tipo de pedido
-- pode nao ter (ou nao usar) o endereco do "cliente" cadastrado no CRM normal.
ALTER TABLE orders
    ADD COLUMN cost_price_billing_name VARCHAR(180) NULL AFTER factory_payment_sent_at,
    ADD COLUMN cost_price_billing_document VARCHAR(20) NULL AFTER cost_price_billing_name,
    ADD COLUMN cost_price_delivery_address VARCHAR(255) NULL AFTER cost_price_billing_document;
