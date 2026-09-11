<?php

namespace App\Models;

use App\Core\Database;

class WhatsAppMessage
{
    public static function forChat(int $chatId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM whatsapp_messages WHERE chat_id = :chat_id ORDER BY sent_at ASC LIMIT :limit'
        );
        $stmt->bindValue('chat_id', $chatId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Idempotente por (chat_id, wa_message_id) -- o mesmo evento de webhook pode chegar mais de
     *  uma vez (reentrega padrao de qualquer webhook), e a sincronizacao inicial roda de novo toda
     *  vez que o usuario abre a tela. wa_message_id nulo (mensagens mandadas por nos, cujo id real
     *  so a Evolution devolve) sempre insere -- sem chave pra deduplicar contra.
     *  $mediaMimetype/$mediaFilename/$mediaSizeBytes/$waKeyJson: so preenchidos pra mensagens de
     *  midia (ver WhatsAppSync::extractMediaInfo) -- wa_key_json guarda o "key" INTEIRO da mensagem
     *  original, exigido pra reconverter a midia em base64 depois (ver
     *  EvolutionApiClient::fetchMediaBase64). */
    public static function create(
        int $chatId,
        ?string $waMessageId,
        string $direction,
        ?string $senderName,
        ?string $body,
        string $type,
        string $sentAt,
        ?string $mediaMimetype = null,
        ?string $mediaFilename = null,
        ?int $mediaSizeBytes = null,
        ?string $waKeyJson = null
    ): int {
        if ($waMessageId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT id, wa_key_json, media_mimetype FROM whatsapp_messages WHERE chat_id = :chat_id AND wa_message_id = :wa_id'
            );
            $stmt->execute(['chat_id' => $chatId, 'wa_id' => $waMessageId]);
            $existing = $stmt->fetch();
            if ($existing) {
                // Backfill: mensagem de midia sincronizada ANTES da Fase 34 nao tinha wa_key_json
                // gravado (coluna nao existia); alem disso, o payload do WEBHOOK ao vivo as vezes
                // chega mais enxuto que o endpoint findMessages via REST (confirmado: um documento
                // recebido ao vivo gravou o key mas sem mimetype/fileName/tamanho, que so vieram
                // completos numa consulta posterior). Por isso o gatilho e' "falta mimetype", nao so
                // "falta key" -- cobre os dois casos, sempre completando com o que a nova mensagem
                // trouxer de mais informacao (COALESCE preserva o que ja tinha se o novo vier vazio).
                // Ver WhatsAppMessage::hasIncompleteMedia(), usado por
                // WhatsAppInboxController::show() pra saber quando vale a pena puxar de novo.
                if (($mediaMimetype !== null || $waKeyJson !== null) && empty($existing['media_mimetype'])) {
                    $upd = Database::connection()->prepare(
                        'UPDATE whatsapp_messages SET
                            media_mimetype = COALESCE(:mime, media_mimetype),
                            media_filename = COALESCE(:fname, media_filename),
                            media_size_bytes = COALESCE(:size, media_size_bytes),
                            wa_key_json = COALESCE(:key, wa_key_json)
                         WHERE id = :id'
                    );
                    $upd->execute([
                        'mime' => $mediaMimetype,
                        'fname' => $mediaFilename,
                        'size' => $mediaSizeBytes,
                        'key' => $waKeyJson,
                        'id' => $existing['id'],
                    ]);
                }
                return (int) $existing['id'];
            }
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_messages
                (chat_id, wa_message_id, direction, sender_name, body, message_type, sent_at,
                 media_mimetype, media_filename, media_size_bytes, wa_key_json)
             VALUES (:chat_id, :wa_id, :direction, :sender, :body, :type, :sent_at,
                     :media_mimetype, :media_filename, :media_size_bytes, :wa_key_json)'
        );
        $stmt->execute([
            'chat_id' => $chatId,
            'wa_id' => $waMessageId,
            'direction' => $direction,
            'sender' => $senderName,
            'body' => $body,
            'type' => $type,
            'sent_at' => $sentAt,
            'media_mimetype' => $mediaMimetype,
            'media_filename' => $mediaFilename,
            'media_size_bytes' => $mediaSizeBytes,
            'wa_key_json' => $waKeyJson,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** Cache local -- so baixa da Evolution na primeira vez que alguem abre a midia (ver
     *  WhatsAppInboxController::media()), fica salvo pra sempre depois. */
    public static function setMediaPath(int $id, string $storedName, ?string $mimetype = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_messages SET media_path = :path, media_mimetype = COALESCE(:mime, media_mimetype) WHERE id = :id'
        );
        $stmt->execute(['path' => $storedName, 'mime' => $mimetype, 'id' => $id]);
    }

    /** Existe midia sem mimetype gravado nesse chat -- seja por ter sido importada antes da Fase 34
     *  (sem key nenhum) ou por ter chegado via webhook com payload mais enxuto que o findMessages via
     *  REST (key presente, mas sem mimetype/fileName/tamanho)? Usado por
     *  WhatsAppInboxController::show() pra saber quando vale puxar as mensagens de novo so pra
     *  backfillar -- uma vez completo, para de disparar sozinho (ver create()). */
    public static function hasIncompleteMedia(int $chatId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM whatsapp_messages
             WHERE chat_id = :chat_id AND media_mimetype IS NULL
               AND message_type IN ('imageMessage','videoMessage','audioMessage','documentMessage','stickerMessage')
             LIMIT 1"
        );
        $stmt->execute(['chat_id' => $chatId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Mensagem apagada pra todos (protocolMessage/REVOKE recebido via webhook ou sincronizacao) --
     *  zera o corpo (a Evolution ja nao manda o conteudo original de qualquer forma) e marca
     *  is_deleted, pra a thread mostrar "Mensagem apagada" em vez do texto/midia antigos. */
    public static function markDeleted(int $chatId, string $waMessageId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_messages SET is_deleted = 1, body = NULL WHERE chat_id = :chat_id AND wa_message_id = :wa_id'
        );
        $stmt->execute(['chat_id' => $chatId, 'wa_id' => $waMessageId]);
    }
}
