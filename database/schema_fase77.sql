-- Fase 77: push notification real (Expo Push Service) pro app mobile -- primeiro passo da
-- infra que vai gradualmente substituir o WhatsApp. Guarda o token de push por dispositivo
-- (um usuario pode ter mais de um aparelho; um token so pertence a 1 usuario por vez -- login
-- de outra pessoa no mesmo aparelho reatribui via ON DUPLICATE KEY).
CREATE TABLE device_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    expo_push_token VARCHAR(255) NOT NULL,
    platform VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    CONSTRAINT fk_device_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_device_tokens_token (expo_push_token),
    INDEX idx_device_tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
