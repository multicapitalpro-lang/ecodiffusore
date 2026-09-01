-- Ecodiffusore Brasil - Painel - Fase 12 (Gerente Nacional + Supervisor, rename gerente->gestor)

-- 1) Libera o slug 'gerente' renomeando o papel de hoje (subordinado ao licenciado, % do pool) pra 'gestor'.
UPDATE roles SET slug = 'gestor', name = 'Gestor' WHERE slug = 'gerente';

-- 2) CRITICO: commissions.role_slug e texto livre (sem FK pra roles), copiado no momento da cascata.
--    Toda linha historica com role_slug='gerente' foi gravada sob a semantica ANTIGA (hoje = gestor).
UPDATE commissions SET role_slug = 'gestor' WHERE role_slug = 'gerente';

-- 3) Novo papel nacional 'gerente' + reativa o nome de 'supervisor' (linha ja existe desde sempre).
INSERT INTO roles (slug, name) VALUES ('gerente', 'Gerente') ON DUPLICATE KEY UPDATE name = VALUES(name);
UPDATE roles SET name = 'Supervisor' WHERE slug = 'supervisor';

-- 4) Novo relacionamento Licenciado -> Supervisor responsavel (eixo independente de manager_id).
ALTER TABLE users
    ADD COLUMN supervisor_id INT UNSIGNED NULL AFTER manager_id,
    ADD CONSTRAINT fk_users_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL;
