-- Ecodiffusore Brasil - Painel - Fase 11 (inversao da hierarquia: Licenciado = dono de regiao)

INSERT INTO roles (slug, name) VALUES ('vendedor', 'Vendedor') ON DUPLICATE KEY UPDATE name = VALUES(name);
UPDATE roles SET name = 'Licenciado' WHERE slug = 'licenciado';
-- 'supervisor' fica na tabela sem uso -- nao apagar (evita qualquer FK surpresa), so para de ser atribuido.
