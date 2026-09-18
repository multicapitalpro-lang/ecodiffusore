-- Fase 57b: notificacao de WhatsApp na solicitacao de liberacao de preco + pagina central
-- /painel/liberacoes (sem mudanca de schema alem do seed do texto do evento novo).
INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('liberacao_desconto_solicitada',
     NULL,
     '💰 Solicitação de liberação de preço!\n\nVendedor: {vendedor}\nCliente: {cliente}\nRegião: {regiao}\nQuantidade: {quantidade}\nPreço padrão: {preco_original}\nPreço solicitado: {preco_solicitado}\n\nAcesse pra aprovar ou recusar: {url}')
    ON DUPLICATE KEY UPDATE event_key = event_key;
