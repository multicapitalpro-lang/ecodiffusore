-- Ecodiffusore Brasil - Painel - Fase 2b (verificacao de e-mail no cadastro)

ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL AFTER commission_pct,
    ADD COLUMN verification_code_hash VARCHAR(255) NULL AFTER email_verified_at,
    ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_code_hash;

-- Contas ja existentes (criadas pelo admin) sao consideradas verificadas por padrao.
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL;
