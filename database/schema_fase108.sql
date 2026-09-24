-- Fase 108: Inscricao Estadual no faturamento do pedido a preco de custo (Fase 101/102) --
-- pedido explicito do usuario, faltava esse campo. OPCIONAL (nem toda empresa tem IE -- isenta ou
-- consumidor final -- nunca exigido no formulario/validacao).
ALTER TABLE orders
    ADD COLUMN cost_price_billing_state_registration VARCHAR(20) NULL AFTER cost_price_billing_document;
