-- Fase 106: menu de atendimento automatico no WhatsApp principal do site -- reaproveita a
-- instancia unica do Admin (config('evolution.instance'), ja usada hoje so' pra ENVIAR as
-- notificacoes automaticas do sistema) tambem como o numero que RECEBE mensagem de cliente e
-- responde com o menu. Registrar essa instancia em whatsapp_instances (antes so' guardava as
-- pessoais de Licenciado/Gestor/Vendedor, Fase 33) faz o webhook conseguir achar ela por nome e
-- reaproveita a MESMA caixa de entrada (/painel/whatsapp) pra "Falar com atendente humano".
ALTER TABLE whatsapp_instances
    MODIFY COLUMN user_id INT UNSIGNED NULL,
    ADD COLUMN type ENUM('pessoal','central') NOT NULL DEFAULT 'pessoal' AFTER user_id,
    ADD COLUMN bot_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER type;

INSERT INTO whatsapp_instances (user_id, instance_name, status, type)
SELECT NULL, 'ecodiffusore', 'connecting', 'central'
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_instances WHERE instance_name = 'ecodiffusore');

-- Estado da conversa do menu automatico, por numero de WhatsApp (remote_jid) -- independente de
-- whatsapp_chats/whatsapp_messages (que so guardam o HISTORICO pra exibicao humana); esta tabela
-- e' so' a "memoria" de em que passo do menu cada contato esta agora.
CREATE TABLE whatsapp_bot_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    remote_jid VARCHAR(80) NOT NULL,
    state VARCHAR(40) NOT NULL DEFAULT 'menu_principal',
    lead_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_bot_sessions_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_bot_session_jid (remote_jid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
