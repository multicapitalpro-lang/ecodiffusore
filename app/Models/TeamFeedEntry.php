<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/** Fase 97: Mural de Conquistas -- feed compartilhado da rede de um Licenciado (ele + Gestor +
 *  Vendedor). Cada linha e' um evento positivo ja acontecido (venda fechada, meta batida,
 *  certificacao, indicacao ativada) -- inserido pelo App\Core\TeamFeed nos pontos exatos onde
 *  cada evento se confirma, nunca editado depois. */
class TeamFeedEntry
{
    public const TYPE_ICONS = ['venda' => '🎉', 'meta' => '🏆', 'certificacao' => '✅', 'indicacao' => '🎁'];

    public static function create(int $licenciadoId, ?int $actorUserId, string $type, string $message): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO team_feed_entries (licenciado_id, actor_user_id, type, message) VALUES (:lid, :aid, :type, :message)'
        );
        $stmt->execute(['lid' => $licenciadoId, 'aid' => $actorUserId, 'type' => $type, 'message' => $message]);
    }

    /** $sinceId = 0 pega os mais recentes (pra carga inicial da tela); $sinceId > 0 pega so' o
     *  que e' mais novo que isso (pra polling incremental). */
    public static function forLicenciado(int $licenciadoId, int $sinceId = 0, int $limit = 30): array
    {
        $db = Database::connection();
        if ($sinceId > 0) {
            $stmt = $db->prepare('SELECT * FROM team_feed_entries WHERE licenciado_id = :lid AND id > :sid ORDER BY id DESC');
            $stmt->execute(['lid' => $licenciadoId, 'sid' => $sinceId]);
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare('SELECT * FROM team_feed_entries WHERE licenciado_id = :lid ORDER BY id DESC LIMIT :lim');
        $stmt->bindValue(':lid', $licenciadoId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
