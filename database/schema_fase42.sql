-- Fase 42: treinamento em video obrigatorio pro Vendedor antes de liberar acesso completo ao
-- painel. Rastreamento real de progresso do player (nao so "marcar como assistido") -- so
-- video enviado como arquivo direto (mp4/webm) entra no treinamento obrigatorio, ja que
-- rastrear posicao real de reproducao de um embed do YouTube exigiria a API do YouTube (fora
-- de escopo aqui); embed continua valendo pros Videos Tutoriais do comprador (nao gated).

ALTER TABLE users
    ADD COLUMN training_completed_at DATETIME NULL AFTER must_change_password;

-- Vendedores ja existentes (criados antes desta fase) nao devem ficar bloqueados retroativamente
-- -- mesmo tratamento ja dado ao onboarding de Licenciado na Fase 18.
UPDATE users u JOIN roles r ON r.id = u.role_id
    SET u.training_completed_at = NOW() WHERE r.slug = 'vendedor';

CREATE TABLE seller_training_videos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    video_url VARCHAR(500) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_training_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    video_id INT UNSIGNED NOT NULL,
    max_percent_watched DECIMAL(5,2) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_training_progress_user_video (user_id, video_id),
    CONSTRAINT fk_training_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_progress_video FOREIGN KEY (video_id) REFERENCES seller_training_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
