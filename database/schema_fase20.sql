CREATE TABLE lead_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO lead_stages (slug, name, position, is_default) VALUES
    ('novo', 'Novo', 1, 1),
    ('contatado', 'Contatado', 2, 1),
    ('convertido', 'Convertido', 3, 1),
    ('descartado', 'Descartado', 4, 1);

-- Deixa de ser ENUM fixo pra aceitar os slugs das colunas customizadas que qualquer
-- usuario da equipe pode criar agora (ver App\Models\LeadStage).
ALTER TABLE leads MODIFY status VARCHAR(40) NOT NULL DEFAULT 'novo';
