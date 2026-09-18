-- Ecodiffusore Brasil - Painel - Fase 60 (facilita a compra: CNH/documento do veiculo deixam de
-- travar a cobranca, passam a travar so o envio pra fabricacao)

INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('pedido_documentos_enviados',
     '📎 O cliente {cliente} enviou a CNH e o documento do veículo do pedido #{pedido} — já pode gerar a cobrança ou conferir se falta algo. {url}',
     '📎 CNH e documento do veículo do pedido #{pedido} (cliente {cliente}) foram enviados pelo cliente. {url}')
    ON DUPLICATE KEY UPDATE event_key = event_key;
