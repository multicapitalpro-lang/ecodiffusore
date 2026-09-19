-- Ecodiffusore Brasil - Painel - Fase 63 (link publico do pedido -- vendedor manda direto pro
-- comprador, sem precisar de login: ve os valores, aceita os Termos de Compra, escolhe a forma de
-- pagamento e paga, tudo numa pagina so, mobile-first)

ALTER TABLE orders
    ADD COLUMN public_token VARCHAR(48) NULL AFTER id,
    ADD UNIQUE KEY uq_orders_public_token (public_token);
