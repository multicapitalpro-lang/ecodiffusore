INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('licenciado_contrato_pendente',
     '📄 Olá {nome}! Um novo contrato foi gerado pra você assinar no painel Ecodiffusore Brasil. Acesse e conclua a assinatura: {url}',
     NULL)
    ON DUPLICATE KEY UPDATE event_key = event_key;
