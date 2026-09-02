-- Ecodiffusore Brasil - Painel - Fase 19 (aprovacao manual de cadastro de Licenciado, endereco
-- estruturado em campos separados, celular/telefone fixo)

ALTER TABLE users
    DROP COLUMN endereco_empresa,
    ADD COLUMN endereco_cep VARCHAR(9) NULL,
    ADD COLUMN endereco_logradouro VARCHAR(180) NULL,
    ADD COLUMN endereco_numero VARCHAR(20) NULL,
    ADD COLUMN endereco_complemento VARCHAR(100) NULL,
    ADD COLUMN endereco_bairro VARCHAR(100) NULL,
    ADD COLUMN endereco_cidade VARCHAR(100) NULL,
    ADD COLUMN endereco_uf CHAR(2) NULL,
    ADD COLUMN celular VARCHAR(20) NULL,
    ADD COLUMN telefone_fixo VARCHAR(20) NULL,
    ADD COLUMN licenciado_rejection_reason TEXT NULL,
    MODIFY COLUMN licenciado_onboarding_status ENUM(
        'nao_aplicavel', 'aguardando_perfil', 'aguardando_assinatura',
        'aguardando_aprovacao', 'ativo', 'assinatura_recusada', 'kyc_recusado'
    ) NOT NULL DEFAULT 'nao_aplicavel';
