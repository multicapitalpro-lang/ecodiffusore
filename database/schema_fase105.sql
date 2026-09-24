-- Fase 105: assinatura de contrato do Vendedor (fora do ClickSign, pra nao gerar custo extra por
-- envelope) -- Vendedor baixa o contrato personalizado, assina via gov.br, envia o arquivo assinado
-- de volta, e o Licenciado da rede dele aprova ou reprova. Reprovar volta pro estado inicial
-- (pedido explicito do usuario: sem limite de reenvio).
ALTER TABLE users
    ADD COLUMN vendedor_contract_status ENUM('nao_aplicavel','pendente_envio','aguardando_aprovacao','aprovado','reprovado')
        NOT NULL DEFAULT 'nao_aplicavel' AFTER licenciado_onboarding_status,
    ADD COLUMN vendedor_contract_path VARCHAR(64) NULL AFTER vendedor_contract_status,
    ADD COLUMN vendedor_contract_original_name VARCHAR(255) NULL AFTER vendedor_contract_path,
    ADD COLUMN vendedor_contract_sent_at DATETIME NULL AFTER vendedor_contract_original_name,
    ADD COLUMN vendedor_contract_reviewed_at DATETIME NULL AFTER vendedor_contract_sent_at,
    ADD COLUMN vendedor_contract_reviewed_by_user_id INT UNSIGNED NULL AFTER vendedor_contract_reviewed_at,
    ADD COLUMN vendedor_contract_rejection_reason TEXT NULL AFTER vendedor_contract_reviewed_by_user_id,
    ADD CONSTRAINT fk_users_vendedor_contract_reviewer FOREIGN KEY (vendedor_contract_reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Vendedores ja existentes (criados antes desta fase) nao devem ficar bloqueados retroativamente.
UPDATE users u JOIN roles r ON r.id = u.role_id
    SET u.vendedor_contract_status = 'aprovado' WHERE r.slug = 'vendedor';

INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('vendedor_contrato_enviado',
     NULL,
     '📄 {vendedor} enviou o contrato assinado -- aguardando sua aprovação. Confira em {url}'),
    ('vendedor_contrato_aprovado',
     '✅ Seu contrato foi aprovado! Acesso completo ao painel liberado. {url}',
     NULL),
    ('vendedor_contrato_reprovado',
     '⚠️ Seu contrato foi reprovado. Motivo: {motivo}. Baixe o contrato de novo, assine e reenvie em {url}',
     NULL)
    ON DUPLICATE KEY UPDATE event_key = event_key;
