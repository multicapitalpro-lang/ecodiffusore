-- Fase 35: foto de perfil (cache de URL), apagar mensagem enviada, e a lista de conversas nao
-- tinha como saber que um lead novo pode ser criado direto do WhatsApp. Aplicado em produção via
-- script PHP standalone (scp + SSH), documentado aqui pro histórico.

ALTER TABLE whatsapp_chats
    ADD COLUMN profile_pic_url VARCHAR(600) NULL AFTER name,
    ADD COLUMN profile_pic_checked_at DATETIME NULL AFTER profile_pic_url;
