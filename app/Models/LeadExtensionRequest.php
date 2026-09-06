<?php

namespace App\Models;

use App\Core\Database;

/**
 * Historico de justificativas do Vendedor pra estender o prazo de 30 dias de um Lead (Fase 36) --
 * extensao e' self-service (nao precisa de aprovacao), esse registro e' so pra auditoria: o
 * Licenciado da rede, o Supervisor dele, o Gerente e o Admin conseguem ver o "porque" de cada
 * extensao feita.
 */
class LeadExtensionRequest
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lead_extension_requests
                (lead_id, requested_by_user_id, justification, attachment_path, previous_expires_at, new_expires_at)
             VALUES (:lead_id, :requested_by_user_id, :justification, :attachment_path, :previous_expires_at, :new_expires_at)'
        );
        $stmt->execute([
            'lead_id' => $data['lead_id'],
            'requested_by_user_id' => $data['requested_by_user_id'],
            'justification' => $data['justification'],
            'attachment_path' => $data['attachment_path'] ?: null,
            'previous_expires_at' => $data['previous_expires_at'] ?: null,
            'new_expires_at' => $data['new_expires_at'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** $sellerIds: escopo por rede (downline de quem esta vendo) -- filtra pelo VENDEDOR
     *  responsavel pelo lead no momento de cada pedido de extensao (requested_by_user_id), nao
     *  pelo dono atual do lead (que pode ja ter mudado). null = sem escopo (Admin). */
    public static function all(?array $sellerIds = null): array
    {
        $sql = "SELECT ler.*, l.name AS lead_name, l.city AS lead_city, l.whatsapp AS lead_whatsapp,
                    u.name AS requested_by_name
                FROM lead_extension_requests ler
                JOIN leads l ON l.id = ler.lead_id
                LEFT JOIN users u ON u.id = ler.requested_by_user_id
                WHERE 1=1";
        $params = [];

        if ($sellerIds !== null) {
            if (!$sellerIds) {
                return [];
            }
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND ler.requested_by_user_id IN (' . implode(',', $names) . ')';
        }

        $sql .= ' ORDER BY ler.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
