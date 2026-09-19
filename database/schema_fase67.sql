-- Ecodiffusore Brasil - Painel - Fase 67 (codigo proprio por Licenciado, pra Gerente/Admin
-- acharem rapido em /painel/licenciados -- ex: LIC-0001)

ALTER TABLE users
    ADD COLUMN licenciado_code VARCHAR(10) NULL AFTER role_id,
    ADD UNIQUE KEY uq_users_licenciado_code (licenciado_code);

-- Backfill dos licenciados ja existentes, em ordem de cadastro (feito via script PHP separado,
-- nao aqui, pra formatar o zero-padding certo -- ver migrate_fase67.php no historico de deploy).
