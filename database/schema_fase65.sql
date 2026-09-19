-- Ecodiffusore Brasil - Painel - Fase 65 (CRM se auto-atualiza com as acoes do comprador no
-- checkout publico: acessou o link, aceitou os termos, gerou a cobranca, pagou, ou nao pagou a
-- tempo -- tudo isso move o card do Lead sozinho no Kanban, sem o vendedor precisar arrastar)

-- Novas colunas do Kanban, encaixadas entre "Contatado" e "Convertido" (que ja existe e passa a
-- ser reutilizado como "pago"). Renumera as posicoes das colunas seguintes pra abrir espaco.
UPDATE lead_stages SET position = position + 4 WHERE slug IN ('convertido', 'descartado');
UPDATE lead_stages SET position = position + 4 WHERE slug NOT IN ('novo', 'contatado', 'convertido', 'descartado', 'checkout_acessado', 'termos_aceitos', 'pagamento_gerado', 'pagamento_pendente');

INSERT INTO lead_stages (slug, name, position) VALUES
    ('checkout_acessado', 'Checkout Acessado', 3),
    ('termos_aceitos', 'Termos Aceitos', 4),
    ('pagamento_gerado', 'Aguardando Pagamento', 5),
    ('pagamento_pendente', 'Pagamento Pendente', 6)
    ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('pagamento_pendente_aviso',
     '⏰ O cliente {cliente} gerou a cobrança do pedido #{pedido} mas ainda não pagou. Já pode dar uma cutucada! {url}',
     NULL)
    ON DUPLICATE KEY UPDATE event_key = event_key;
