-- Fase 115: saldo do banco Asaas passa a ser puxado ao vivo da API em vez de calculado pelo
-- livro-razao local (financial_transactions) -- pedido do usuario apos notar que o card da
-- ASAAS em Caixas e Bancos nao batia com o saldo real (a entrada do pedido tinha ido pra outra
-- conta por engano na hora da criacao). Sicredi/Caixa continuam manuais (sem API disponivel).
ALTER TABLE financial_accounts
    ADD COLUMN balance_source ENUM('manual','asaas') NOT NULL DEFAULT 'manual' AFTER type;

UPDATE financial_accounts SET balance_source = 'asaas' WHERE name = 'ASAAS';
