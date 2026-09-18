-- Ecodiffusore Brasil - Painel - Fase 58 (liberacao de preco em 2 niveis + acompanhamento de status)
--
-- Quando quem pediu foi Vendedor, a aprovacao do Gestor/Licenciado passa a ser so' o NIVEL 1 --
-- a liberacao final ainda depende de Gerente, Supervisor ou Admin (nivel 2). O status continua
-- 'pendente' durante as duas etapas; level1_approved_by/level1_approved_at registram quando (e
-- por quem) o nivel 1 foi decidido, sem mudar o status ate' o nivel 2 decidir.

ALTER TABLE approvals
    ADD COLUMN level1_approved_by INT UNSIGNED NULL AFTER requester_role,
    ADD COLUMN level1_approved_at DATETIME NULL AFTER level1_approved_by,
    ADD CONSTRAINT fk_appr_level1 FOREIGN KEY (level1_approved_by) REFERENCES users(id) ON DELETE SET NULL;

INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('liberacao_desconto_decidida',
     '📋 Atualização da sua solicitação de liberação de preço\n\nCliente: {cliente}\nPreço solicitado: {preco_solicitado}\nStatus: {status}\nDecidido por: {decidido_por}\n\nAcompanhe: {url}',
     NULL)
    ON DUPLICATE KEY UPDATE event_key = event_key;
