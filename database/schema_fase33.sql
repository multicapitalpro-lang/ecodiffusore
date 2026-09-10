-- Fase 33: Painel de WhatsApp por-usuario (Licenciado/Gestor/Vendedor) -- dentro da assinatura.
-- Aplicado em produção via script PHP standalone (scp + SSH), documentado aqui pro histórico.

CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    instance_name VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'connecting',
    phone_number VARCHAR(20) NULL,
    profile_name VARCHAR(120) NULL,
    connected_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_instances_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_instance_name (instance_name),
    UNIQUE KEY uniq_instance_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_chats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance_id INT UNSIGNED NOT NULL,
    remote_jid VARCHAR(80) NOT NULL,
    name VARCHAR(150) NULL,
    is_group TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    lead_id INT UNSIGNED NULL,
    last_message_at DATETIME NULL,
    last_message_preview VARCHAR(255) NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_chats_instance FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    CONSTRAINT fk_whatsapp_chats_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_instance_jid (instance_id, remote_jid),
    INDEX idx_whatsapp_chats_instance (instance_id, last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id INT UNSIGNED NOT NULL,
    wa_message_id VARCHAR(80) NULL,
    direction ENUM('in','out') NOT NULL,
    sender_name VARCHAR(120) NULL,
    body TEXT NULL,
    message_type VARCHAR(30) NOT NULL DEFAULT 'text',
    sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_messages_chat FOREIGN KEY (chat_id) REFERENCES whatsapp_chats(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_chat_wa_message (chat_id, wa_message_id),
    INDEX idx_whatsapp_messages_chat (chat_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#8dc63f',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_tag_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_chat_tags (
    chat_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (chat_id, tag_id),
    CONSTRAINT fk_wct_chat FOREIGN KEY (chat_id) REFERENCES whatsapp_chats(id) ON DELETE CASCADE,
    CONSTRAINT fk_wct_tag FOREIGN KEY (tag_id) REFERENCES whatsapp_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
