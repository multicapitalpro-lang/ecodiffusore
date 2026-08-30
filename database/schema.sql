-- Ecodiffusore Brasil - Painel - Fase 1
-- Executar uma única vez no banco u719183319_ecodiffusore

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (slug, name) VALUES
    ('admin', 'Administrador'),
    ('gerente', 'Gerente'),
    ('supervisor', 'Supervisor'),
    ('licenciado', 'Licenciado / Vendedor'),
    ('cliente', 'Cliente')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    whatsapp VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    whatsapp VARCHAR(30) NOT NULL,
    city VARCHAR(120) NULL,
    truck_brand VARCHAR(60) NULL,
    message TEXT NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'landing_page',
    status ENUM('novo','contatado','convertido','descartado') NOT NULL DEFAULT 'novo',
    assigned_to_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leads_user FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário admin inicial (senha temporária: deve ser trocada no primeiro login)
-- Senha em texto puro apenas neste comentário para referência do usuário: EcoBrasil@2026
INSERT INTO users (role_id, name, email, whatsapp, password_hash, status, must_change_password)
SELECT id, 'Administrador', 'admin@ecodiffusorebrasil.com.br', NULL,
       '$2y$10$2CEBNYyQSMoUcaa/8LMkLuKjBFbbT./EAQJECJUUVkmWpPUGLcmJm', 'active', 1
FROM roles WHERE slug = 'admin'
ON DUPLICATE KEY UPDATE email = VALUES(email);
