<?php

namespace App\Models;

use App\Core\Database;

/** Fase 106: "memoria" do menu automatico de WhatsApp, por numero de contato (remote_jid) -- em
 *  que passo do menu cada pessoa esta agora. Separado de whatsapp_chats/whatsapp_messages (que so
 *  guardam o HISTORICO pra exibicao humana na caixa de entrada); esta tabela e' so' o estado da
 *  conversa do bot em si. */
class WhatsAppBotSession
{
    public static function forJid(string $remoteJid): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_bot_sessions WHERE remote_jid = :jid');
        $stmt->execute(['jid' => $remoteJid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Cria (ou reinicia do zero, se ja existia) a sessao no primeiro passo do menu. */
    public static function start(string $remoteJid): array
    {
        $existing = self::forJid($remoteJid);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                "UPDATE whatsapp_bot_sessions SET state = 'menu_principal', lead_id = NULL WHERE remote_jid = :jid"
            );
            $stmt->execute(['jid' => $remoteJid]);
            return self::forJid($remoteJid);
        }

        $stmt = Database::connection()->prepare(
            "INSERT INTO whatsapp_bot_sessions (remote_jid, state) VALUES (:jid, 'menu_principal')"
        );
        $stmt->execute(['jid' => $remoteJid]);
        return self::forJid($remoteJid);
    }

    public static function updateState(string $remoteJid, string $state, ?int $leadId = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_bot_sessions SET state = :state, lead_id = COALESCE(:lead_id, lead_id) WHERE remote_jid = :jid'
        );
        $stmt->execute(['state' => $state, 'lead_id' => $leadId, 'jid' => $remoteJid]);
    }
}
