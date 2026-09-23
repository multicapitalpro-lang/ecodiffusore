-- Fase 102: endereco de entrega do pedido a preco de custo (Fase 101) vira campos estruturados
-- (CEP/rua/numero/complemento/bairro/cidade/UF), mesmo padrao ja usado pro endereco do cliente em
-- Order::forFactory() -- pedido explicito do usuario, campo unico de texto livre nao servia.
-- Confirmado antes de rodar: 0 pedidos tinham cost_price_delivery_address preenchido em producao.
ALTER TABLE orders
    DROP COLUMN cost_price_delivery_address,
    ADD COLUMN cost_price_delivery_zip_code VARCHAR(9) NULL AFTER cost_price_billing_document,
    ADD COLUMN cost_price_delivery_street VARCHAR(180) NULL AFTER cost_price_delivery_zip_code,
    ADD COLUMN cost_price_delivery_number VARCHAR(20) NULL AFTER cost_price_delivery_street,
    ADD COLUMN cost_price_delivery_complement VARCHAR(100) NULL AFTER cost_price_delivery_number,
    ADD COLUMN cost_price_delivery_neighborhood VARCHAR(100) NULL AFTER cost_price_delivery_complement,
    ADD COLUMN cost_price_delivery_city VARCHAR(100) NULL AFTER cost_price_delivery_neighborhood,
    ADD COLUMN cost_price_delivery_state VARCHAR(2) NULL AFTER cost_price_delivery_city;
