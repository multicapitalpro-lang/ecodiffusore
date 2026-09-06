<?php

namespace App\Models;

use App\Core\Database;

class Lead
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (name, whatsapp, city, truck_brand, message, source)
             VALUES (:name, :whatsapp, :city, :truck_brand, :message, :source)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'whatsapp' => $data['whatsapp'],
            'city' => $data['city'] ?: null,
            'truck_brand' => $data['truck_brand'] ?: null,
            'message' => $data['message'] ?: null,
            'source' => $data['source'] ?? 'landing_page',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT l.*, u.name AS assigned_name FROM leads l
                      LEFT JOIN users u ON u.id = l.assigned_to_user_id
                      ORDER BY l.created_at DESC')
            ->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM leads WHERE assigned_to_user_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    /** Leads atribuidos a algum dos $userIds, opcionalmente incluindo os ainda sem responsavel */
    public static function forScope(array $userIds, bool $includeUnassigned): array
    {
        if (!$userIds && !$includeUnassigned) {
            return [];
        }

        $conditions = [];
        $params = [];

        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $conditions[] = "l.assigned_to_user_id IN ({$placeholders})";
            $params = array_merge($params, $userIds);
        }
        if ($includeUnassigned) {
            $conditions[] = 'l.assigned_to_user_id IS NULL';
        }

        $sql = 'SELECT l.*, u.name AS assigned_name FROM leads l
                LEFT JOIN users u ON u.id = l.assigned_to_user_id
                WHERE ' . implode(' OR ', $conditions) . '
                ORDER BY l.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Grava os dados do veiculo (e reconfirma o nome) capturados no wizard de orcamento em /comprar */
    public static function updateVehicleInfo(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE leads SET name = :name, vehicle_plate = :plate, vehicle_year = :year, vehicle_brand = :brand,
                vehicle_model = :model, vehicle_power = :power, vehicle_ecu_status = :ecu_status,
                vehicle_reprogrammed_power = :reprogrammed_power, vehicle_has_arla = :has_arla WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'plate' => ($data['plate'] ?? '') ?: null,
            'year' => ($data['year'] ?? '') ?: null,
            'brand' => ($data['brand'] ?? '') ?: null,
            'model' => ($data['model'] ?? '') ?: null,
            'power' => ($data['power'] ?? '') ?: null,
            'ecu_status' => in_array($data['ecu_status'] ?? '', ['original', 'reprogramado'], true) ? $data['ecu_status'] : null,
            'reprogrammed_power' => ($data['reprogrammed_power'] ?? '') ?: null,
            'has_arla' => in_array($data['has_arla'] ?? '', ['sim', 'nao'], true) ? $data['has_arla'] : null,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Data do lead mais recente ja atribuido a cada um de $userIds -- usado pelo GeoMatch pra
     *  fazer rodizio meritocratico entre Vendedores empatados no mesmo raio (quem recebeu um lead
     *  ha mais tempo entra na frente da fila). Quem nunca recebeu nenhum nao aparece no resultado
     *  (o caller trata ausencia como "sempre na frente da fila"). */
    public static function lastAssignedAt(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT assigned_to_user_id, MAX(created_at) AS last_at FROM leads
             WHERE assigned_to_user_id IN ({$placeholders}) GROUP BY assigned_to_user_id"
        );
        $stmt->execute(array_values($userIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['assigned_to_user_id']] = $row['last_at'];
        }
        return $result;
    }

    public static function assignTo(int $id, ?int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE leads SET assigned_to_user_id = :uid WHERE id = :id');
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    }
}
