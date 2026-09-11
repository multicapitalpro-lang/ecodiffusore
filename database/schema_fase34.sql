-- Fase 34: midia (imagem/video/audio/documento/figurinha) + mensagens apagadas no painel de
-- WhatsApp por-usuario. Aplicado em produção via script PHP standalone (scp + SSH), documentado
-- aqui pro histórico.

ALTER TABLE whatsapp_messages
    ADD COLUMN media_mimetype VARCHAR(100) NULL AFTER message_type,
    ADD COLUMN media_filename VARCHAR(255) NULL AFTER media_mimetype,
    ADD COLUMN media_size_bytes INT UNSIGNED NULL AFTER media_filename,
    ADD COLUMN media_path VARCHAR(64) NULL AFTER media_size_bytes,
    ADD COLUMN wa_key_json TEXT NULL AFTER media_path,
    ADD COLUMN is_deleted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER wa_key_json;
