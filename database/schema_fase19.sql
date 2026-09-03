ALTER TABLE users
    ADD COLUMN documento_identidade_path VARCHAR(64) NULL AFTER comprovante_residencia_path,
    ADD COLUMN contrato_social_path VARCHAR(64) NULL AFTER documento_identidade_path,
    ADD COLUMN cartao_cnpj_path VARCHAR(64) NULL AFTER contrato_social_path;
