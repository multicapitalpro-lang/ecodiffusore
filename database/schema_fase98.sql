-- Fase 98: Pedido a preco de custo (mostruario), so' Admin -- sem vendedor/comissao/aprovacao,
-- preco pode ir abaixo do piso normal (PricingTier). Depois do pedido pago, o Admin envia um Pix
-- pra fabrica e anexa o comprovante direto no pedido -- so' a partir dai ele entra na fila de
-- despacho da Fabrica (ver Order::forFactory()).
ALTER TABLE orders
    ADD COLUMN is_cost_price TINYINT(1) NOT NULL DEFAULT 0 AFTER seller_id,
    ADD COLUMN factory_payment_amount DECIMAL(12,2) NULL AFTER is_cost_price,
    ADD COLUMN factory_payment_proof_path VARCHAR(64) NULL AFTER factory_payment_amount,
    ADD COLUMN factory_payment_proof_original_name VARCHAR(255) NULL AFTER factory_payment_proof_path,
    ADD COLUMN factory_payment_sent_at DATETIME NULL AFTER factory_payment_proof_original_name;
