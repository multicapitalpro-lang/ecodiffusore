-- Fase 97: Mural de Conquistas em tempo real -- setima e ultima das 7 ferramentas premium
-- aprovadas. Feed compartilhado da rede do Licenciado (ele + Gestor + Vendedor) com eventos
-- positivos: venda fechada, meta batida, certificacao conquistada, indicacao ativada.
CREATE TABLE team_feed_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    licenciado_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    type ENUM('venda','meta','certificacao','indicacao') NOT NULL,
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_feed_licenciado FOREIGN KEY (licenciado_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_feed_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team_feed_licenciado (licenciado_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guarda de dedup pra "meta batida" nunca postar a mesma conquista 2x (mesmo espirito de
-- pace_alert_sent_at, Fase 94).
ALTER TABLE goals ADD COLUMN achievement_posted_at DATETIME NULL AFTER pace_alert_sent_at;
