<?php

namespace App\Models;

use App\Core\Database;

/** Cotacao publica de maquina agricola (Fase 45) -- sem placa, sem calculo de preco automatico
 *  (ainda sem tabela pronta por tipo de maquina). Fica pendente ate um staff abrir, ver as
 *  fotos e digitar o preco manualmente (marca como 'respondido'), retornando pro cliente por
 *  fora (WhatsApp/telefone, nao automatizado por este sistema). */
class MachineQuoteRequest
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO machine_quote_requests
                (lead_id, assigned_user_id, client_name, client_whatsapp, client_city, machine_type,
                 brand, model, power, hose_measure, photo_general_path, photo_nameplate_path, photo_hose_path)
             VALUES
                (:lead_id, :assigned_user_id, :client_name, :client_whatsapp, :client_city, :machine_type,
                 :brand, :model, :power, :hose_measure, :photo_general_path, :photo_nameplate_path, :photo_hose_path)'
        );
        $stmt->execute([
            'lead_id' => $data['lead_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'client_name' => $data['client_name'],
            'client_whatsapp' => $data['client_whatsapp'] ?? null,
            'client_city' => $data['client_city'] ?? null,
            'machine_type' => $data['machine_type'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'power' => $data['power'] ?? null,
            'hose_measure' => $data['hose_measure'] ?? null,
            'photo_general_path' => $data['photo_general_path'],
            'photo_nameplate_path' => $data['photo_nameplate_path'],
            'photo_hose_path' => $data['photo_hose_path'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT mq.*, u.name AS assignee_name, r.name AS responder_name
             FROM machine_quote_requests mq
             LEFT JOIN users u ON u.id = mq.assigned_user_id
             LEFT JOIN users r ON r.id = mq.responded_by_user_id
             WHERE mq.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Fila de cotacoes pendentes/respondidas no escopo de staff -- $userIds: null = sem escopo
     *  (Admin), [] = nada no escopo, senao filtra por assigned_user_id. Mesmo espirito de
     *  Lead::forScope()/WarrantyRequest::forScope(). Fase 82: $includeUnassigned -- quando o
     *  GeoMatch nao acha ninguem no raio de 100km, assigned_user_id fica NULL, e antes isso so
     *  aparecia pro Admin (NULL nunca cai num IN (...)); Gerente/Supervisor/Licenciado tambem
     *  precisam ver pra poder assumir/responder (pedido explicito do usuario). */
    public static function forScope(?array $userIds, ?string $status = null, bool $includeUnassigned = false): array
    {
        $sql = 'SELECT mq.*, u.name AS assignee_name FROM machine_quote_requests mq
                LEFT JOIN users u ON u.id = mq.assigned_user_id WHERE 1=1';
        $params = [];

        if ($userIds !== null) {
            if (!$userIds && !$includeUnassigned) {
                return [];
            }
            $scopedSql = '1=0';
            if ($userIds) {
                $names = [];
                foreach (array_values($userIds) as $i => $uid) {
                    $key = "uid{$i}";
                    $names[] = ":{$key}";
                    $params[$key] = $uid;
                }
                $scopedSql = 'mq.assigned_user_id IN (' . implode(',', $names) . ')';
            }
            $sql .= ' AND (' . $scopedSql . ($includeUnassigned ? ' OR mq.assigned_user_id IS NULL' : '') . ')';
        }
        if ($status) {
            $sql .= ' AND mq.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY mq.status = "pendente" DESC, mq.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countPending(?array $userIds): int
    {
        $sql = "SELECT COUNT(*) FROM machine_quote_requests WHERE status = 'pendente'";
        $params = [];

        if ($userIds !== null) {
            if (!$userIds) {
                return 0;
            }
            $names = [];
            foreach (array_values($userIds) as $i => $uid) {
                $key = "uid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $uid;
            }
            $sql .= ' AND assigned_user_id IN (' . implode(',', $names) . ')';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function markResponded(int $id, int $userId, ?float $quotedPrice, ?string $notes): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE machine_quote_requests SET status = 'respondido', quoted_price = :price,
                internal_notes = :notes, responded_by_user_id = :user_id, responded_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'price' => $quotedPrice,
            'notes' => $notes !== '' ? $notes : null,
            'user_id' => $userId,
            'id' => $id,
        ]);
    }
}
