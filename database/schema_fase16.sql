-- Ecodiffusore Brasil - Painel - Fase 16 (orcamento publico por placa passa a gerar Orcamento de
-- verdade no CRM, nao so um Lead)

ALTER TABLE quotes
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER client_id,
    ADD CONSTRAINT fk_quotes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL;
