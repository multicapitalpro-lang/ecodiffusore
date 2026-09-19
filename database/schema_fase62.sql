-- Ecodiffusore Brasil - Painel - Fase 62 (termos de compra: cliente precisa aceitar, no proprio
-- painel, antes de conseguir pagar)

ALTER TABLE company_settings
    ADD COLUMN terms_text LONGTEXT NULL AFTER endereco;

ALTER TABLE orders
    ADD COLUMN terms_accepted_at DATETIME NULL AFTER telemetry_path,
    ADD COLUMN terms_text_snapshot LONGTEXT NULL AFTER terms_accepted_at;
