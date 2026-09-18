-- Ecodiffusore Brasil - Painel - Fase 61 (cadastro completo do veiculo pelo proprio cliente,
-- depois do pagamento -- placa, CNH, documento do veiculo, 3 fotos e telemetria, tudo obrigatorio
-- antes do pedido ser liberado pra fabrica)

ALTER TABLE orders
    ADD COLUMN photo1_path VARCHAR(64) NULL AFTER cnh_document_path,
    ADD COLUMN photo2_path VARCHAR(64) NULL AFTER photo1_path,
    ADD COLUMN photo3_path VARCHAR(64) NULL AFTER photo2_path,
    ADD COLUMN telemetry_path VARCHAR(64) NULL AFTER photo3_path;

INSERT INTO whatsapp_event_templates (event_key, text_self, text_network) VALUES
    ('pagamento_confirmado_cliente',
     '✅ Pagamento confirmado, {nome}! Falta um passo pra gente fabricar o seu Ecodiffusore: entre no seu painel e envie a placa do veículo, CNH, documento do veículo, 3 fotos e a telemetria. Sem isso não conseguimos enviar pra produção. Acesse: {url}',
     NULL)
    ON DUPLICATE KEY UPDATE event_key = event_key;
