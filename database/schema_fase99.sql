-- Fase 99: aprovacao de documentos do veiculo antes do pedido ir pra fabrica. Ate agora, assim
-- que o cliente terminava de enviar CNH/documento/fotos/telemetria pelo painel dele, o pedido ja
-- aparecia direto na fila da Fabrica (Order::forFactory()). Agora precisa de uma revisao manual
-- de Licenciado/Gerente/Admin antes -- pra corrigir qualquer dado errado antes de mandar pra
-- fabricacao (peca e' personalizada pro caminhao do cliente).
ALTER TABLE orders
    ADD COLUMN documents_approved_at DATETIME NULL AFTER telemetry_path,
    ADD COLUMN documents_approved_by_user_id INT UNSIGNED NULL AFTER documents_approved_at,
    ADD CONSTRAINT fk_orders_documents_approved_by FOREIGN KEY (documents_approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
