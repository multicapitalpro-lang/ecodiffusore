-- Fase 94: Alerta preditivo de meta -- quarta das 7 ferramentas premium aprovadas pelo usuario.
-- Registra quando o ultimo alerta de "ritmo atrasado" foi enviado pra essa meta, pra nao repetir
-- o aviso toda vez que a rotina lazy roda (mesmo padrao de reminder_sent_at do Calendario/Fase92).
ALTER TABLE goals ADD COLUMN pace_alert_sent_at DATETIME NULL AFTER reward_paid;
