-- Fase 121: limite de cheque especial por conta financeira -- usado so' como informacao de
-- credito disponivel (nunca soma no saldo real, que continua vindo so' de
-- financial_transactions). O Banco Sicredi tem R$1.000,00 confirmado pelo usuario.
ALTER TABLE financial_accounts
    ADD COLUMN overdraft_limit DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER initial_balance;

UPDATE financial_accounts SET overdraft_limit = 1000.00 WHERE name = 'Banco Sicredi';
