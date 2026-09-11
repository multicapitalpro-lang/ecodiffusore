-- Fase 40: CNH do comprador obrigatoria junto com o documento do veiculo, antes de gerar cobranca
-- (a fabrica precisa do documento do veiculo pra montar o pedido; a CNH passa a ser exigida no
-- mesmo momento, pelo mesmo motivo pratico de ja coletar tudo de uma vez). Aplicado em produção
-- via script PHP standalone (scp + SSH), documentado aqui pro histórico.

ALTER TABLE orders
    ADD COLUMN cnh_document_path VARCHAR(64) NULL AFTER vehicle_document_path;
