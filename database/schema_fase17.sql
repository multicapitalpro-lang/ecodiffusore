-- Ecodiffusore Brasil - Painel - Fase 17/18 (onboarding de Licenciado: perfil completo + contrato
-- assinado com KYC via ClickSign)

ALTER TABLE users
    ADD COLUMN razao_social VARCHAR(180) NULL,
    ADD COLUMN cnpj VARCHAR(14) NULL,
    ADD COLUMN endereco_empresa VARCHAR(255) NULL,
    ADD COLUMN cpf_representante VARCHAR(11) NULL,
    ADD COLUMN rg_representante VARCHAR(20) NULL,
    ADD COLUMN estado_civil VARCHAR(30) NULL,
    ADD COLUMN profissao VARCHAR(120) NULL,
    ADD COLUMN comprovante_residencia_path VARCHAR(64) NULL,
    ADD COLUMN licenciado_onboarding_status ENUM(
        'nao_aplicavel', 'aguardando_perfil', 'aguardando_assinatura',
        'ativo', 'assinatura_recusada', 'kyc_recusado'
    ) NOT NULL DEFAULT 'nao_aplicavel';

-- Licenciados ja existentes (criados antes desta fase) nao devem ficar bloqueados retroativamente.
UPDATE users u JOIN roles r ON r.id = u.role_id
    SET u.licenciado_onboarding_status = 'ativo' WHERE r.slug = 'licenciado';

CREATE TABLE licenciado_envelopes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    clicksign_envelope_id VARCHAR(64) NOT NULL,
    clicksign_document_id VARCHAR(64) NULL,
    clicksign_signer_id VARCHAR(64) NULL,
    signing_url VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'created',
    signed_document_path VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_licenciado_envelopes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_licenciado_envelopes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
