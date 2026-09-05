-- Ecodiffusore Brasil - Painel - Fase 24
-- Conjunto de melhorias no financeiro: conta padrao explicita, transferencia entre contas,
-- edicao/exclusao de lancamento e recorrencia de conta a pagar/receber.

ALTER TABLE financial_accounts
    ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

-- Preserva o comportamento de hoje (FinancialAccount::defaultAccountId() ja escolhia a conta
-- ativa mais antiga) marcando essa mesma conta como padrao explicitamente.
UPDATE financial_accounts SET is_default = 1 WHERE active = 1 ORDER BY id LIMIT 1;

ALTER TABLE financial_transactions
    ADD COLUMN is_transfer TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN transfer_pair_id INT UNSIGNED NULL AFTER is_transfer,
    ADD COLUMN recurrence_frequency ENUM('semanal','mensal','anual') NULL AFTER transfer_pair_id,
    ADD COLUMN recurrence_parent_id INT UNSIGNED NULL AFTER recurrence_frequency,
    ADD CONSTRAINT fk_trans_transfer_pair FOREIGN KEY (transfer_pair_id)
        REFERENCES financial_transactions(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trans_recurrence_parent FOREIGN KEY (recurrence_parent_id)
        REFERENCES financial_transactions(id) ON DELETE SET NULL;
