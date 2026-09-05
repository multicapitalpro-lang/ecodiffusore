-- Ecodiffusore Brasil - Painel - Fase 23
-- Correlaciona pagamento de comissao com o financeiro de verdade: ate aqui, "Dar baixa" numa
-- comissao so trocava o status na tabela commissions, sem gerar nenhuma saida em Caixas e Bancos
-- nem entrar no DRE -- o saldo/relatorio ficava mentindo (nao refletia o dinheiro que realmente
-- saiu pra pagar vendedor/gestor/licenciado/supervisor/gerente).

ALTER TABLE commissions
    ADD COLUMN financial_transaction_id INT UNSIGNED NULL AFTER status,
    ADD CONSTRAINT fk_commissions_transaction FOREIGN KEY (financial_transaction_id)
        REFERENCES financial_transactions(id) ON DELETE SET NULL;
