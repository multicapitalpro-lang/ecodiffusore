-- Fase 92: Calendario compartilhado da equipe (reuniao/visita/instalacao/tarefa) -- primeira das
-- 7 novas ferramentas premium aprovadas pelo usuario pra justificar subir a assinatura de R$150
-- pra R$200/mes. Ferramenta inteira dentro do paywall (SubscriptionGate, bloqueio total, mesmo
-- tratamento do WhatsApp integrado), disponivel no painel web e (proxima etapa) no app.

CREATE TABLE calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    event_type ENUM('reuniao', 'visita', 'instalacao', 'tarefa', 'outro') NOT NULL DEFAULT 'outro',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    location VARCHAR(255) NULL,
    lead_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    status ENUM('agendado', 'concluido', 'cancelado') NOT NULL DEFAULT 'agendado',
    reminder_sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendar_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_calendar_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_calendar_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_calendar_owner_date (owner_id, starts_at),
    INDEX idx_calendar_date (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participante alem do dono -- permite reuniao de equipe (varias pessoas no mesmo evento) e o
-- Licenciado/Gestor enxergar a agenda de quem esta na rede dele sem precisar ser o "dono".
CREATE TABLE calendar_event_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_participant_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_participant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_event_participant (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
