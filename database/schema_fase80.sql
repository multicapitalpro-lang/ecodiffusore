-- Fase 80: motivo da solicitacao de desconto -- pedido do usuario pra o Vendedor justificar na
-- hora, pro Licenciado/Gerente analisarem com contexto (nao so o preco pedido).
ALTER TABLE approvals ADD COLUMN justification TEXT NULL AFTER requester_role;
