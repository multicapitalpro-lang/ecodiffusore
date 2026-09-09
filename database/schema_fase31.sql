-- Fase 31: Preco negociavel por faixa (substitui por completo a tabela por quantidade da Fase 24).
-- Aplicado em produção via script PHP standalone (scp + SSH), documentado aqui pro histórico.
-- ATENCAO: este script e destrutivo pras 9 linhas antigas de pricing_tiers (quantidade) -- so
-- seguro porque confirmado que user_commission_tiers estava vazia (0 linhas) no momento da migracao,
-- ou seja nenhum Vendedor tinha comissao por faixa configurada ainda.

ALTER TABLE pricing_tiers
    ADD COLUMN min_price DECIMAL(10,2) NULL AFTER min_qty,
    ADD COLUMN max_price DECIMAL(10,2) NULL AFTER min_price,
    MODIFY COLUMN min_qty INT UNSIGNED NULL,
    MODIFY COLUMN unit_price DECIMAL(10,2) NULL;

ALTER TABLE pricing_tiers DROP INDEX min_qty;

DELETE FROM pricing_tiers;

INSERT INTO pricing_tiers (min_price, max_price, licenciado_commission_pct, cost_price, tax_pct) VALUES
    (3450.00, 3549.99, 10.00, 2200.00, 9.00),
    (3550.00, 4249.99, 15.00, 2200.00, 9.00),
    (4250.00, NULL, 20.00, 2200.00, 9.00);
