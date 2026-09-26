-- Fase 136: preferencias de notificacao (opt-out, JSON esparso -- so guarda o que esta
-- DESATIVADO; evento novo no futuro nasce habilitado pra todo mundo sem precisar de migracao) +
-- marca se o usuario ja passou pela tela de Perfil ao menos uma vez (gate de primeiro acesso no
-- app, so' dispara uma vez).
ALTER TABLE users
    ADD COLUMN notification_prefs TEXT NULL AFTER bank_data_completed_at,
    ADD COLUMN profile_reviewed_at DATETIME NULL AFTER notification_prefs;
