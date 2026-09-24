<?php

namespace App\Models;

use App\Core\Database;

class Quote
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT q.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.city AS client_city, c.state AS client_state,
                       u.name AS seller_name,
                       l.city AS lead_city, l.whatsapp AS lead_whatsapp,
                       l.vehicle_plate, l.vehicle_year, l.vehicle_brand, l.vehicle_model,
                       l.vehicle_power, l.vehicle_ecu_status, l.vehicle_reprogrammed_power, l.vehicle_has_arla
                FROM quotes q
                JOIN clients c ON c.id = q.client_id
                LEFT JOIN users u ON u.id = q.seller_id
                LEFT JOIN leads l ON l.id = q.lead_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['seller_id'])) {
            // Fase 123: orcamento sem vendedor (seller_id NULL, agora permitido) precisa continuar
            // visivel pra quem cadastrou o cliente -- SQL puro nunca casa NULL IN(...)/= , entao
            // sem esse OR o proprio Licenciado que criou o orcamento nunca o encontraria de volta.
            $sql .= ' AND (q.seller_id = :seller_id OR (q.seller_id IS NULL AND c.seller_id = :seller_id_fallback))';
            $params['seller_id'] = $filters['seller_id'];
            $params['seller_id_fallback'] = $filters['seller_id'];
        }
        if (!empty($filters['seller_ids'])) {
            $names = [];
            $fallbackNames = [];
            foreach (array_values($filters['seller_ids']) as $i => $sid) {
                $key = "sid{$i}";
                $fallbackKey = "csid{$i}";
                $names[] = ":{$key}";
                $fallbackNames[] = ":{$fallbackKey}";
                $params[$key] = $sid;
                $params[$fallbackKey] = $sid;
            }
            $sql .= ' AND (q.seller_id IN (' . implode(',', $names) . ')
                OR (q.seller_id IS NULL AND c.seller_id IN (' . implode(',', $fallbackNames) . ')))';
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND q.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['city'])) {
            $sql .= ' AND c.city LIKE :city';
            $params['city'] = '%' . $filters['city'] . '%';
        }

        $sql .= ' ORDER BY q.quote_date DESC, q.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Contagem + valor total de orcamentos criados no periodo -- espelha Order::metrics(),
     *  usado no funil de conversao (Lead -> Orcamento -> Pedido). */
    public static function metrics(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): array
    {
        $sql = 'SELECT COUNT(*) AS quote_count, COALESCE(SUM(total_value), 0) AS total_value
                FROM quotes WHERE quote_date BETWEEN :from AND :to';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        } elseif ($sellerIds !== null) {
            if (!$sellerIds) {
                return ['quote_count' => 0, 'total_value' => 0.0];
            }
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND seller_id IN (' . implode(',', $names) . ')';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return ['quote_count' => (int) $row['quote_count'], 'total_value' => (float) $row['total_value']];
    }

    /** Orcamentos criados no periodo, agrupados por vendedor -- quebra do funil de conversao. */
    public static function funnelBySeller(string $from, string $to, ?array $sellerIds = null): array
    {
        $sql = 'SELECT seller_id, COUNT(*) AS quote_count FROM quotes
                WHERE quote_date BETWEEN :from AND :to AND seller_id IS NOT NULL';
        $params = ['from' => $from, 'to' => $to];

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
            $sql .= ' AND seller_id IN (' . implode(',', $names) . ')';
        }
        $sql .= ' GROUP BY seller_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['seller_id']] = (int) $row['quote_count'];
        }
        return $result;
    }

    /** Mesma ideia de Order::countPendingPayment() -- usado no card de alerta do Dashboard. */
    public static function countPendingPayment(array $filters = []): int
    {
        $quotes = self::all($filters);
        if (!$quotes) {
            return 0;
        }

        $ids = array_map(fn ($q) => (int) $q['id'], $quotes);
        $payments = Payment::latestByPayableIds('quote', $ids);

        $count = 0;
        foreach ($quotes as $q) {
            $situation = Payment::situationFor($q, $payments[(int) $q['id']] ?? null, 'recusado');
            if (in_array($situation['slug'], ['pendente', 'expirado'], true)) {
                $count++;
            }
        }
        return $count;
    }

    /** Orcamentos em aberto vencendo em ate $days dias, pra cada lead -- usado no Kanban de Leads
     *  pra destacar quem tem orcamento esfriando (Fase: priorizacao de leads). */
    public static function expiringSoonForLeads(array $leadIds, int $days = 2): array
    {
        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));
        if (!$leadIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT lead_id, MIN(valid_until) AS valid_until FROM quotes
             WHERE lead_id IN ({$placeholders}) AND status = 'aberto' AND valid_until IS NOT NULL
               AND valid_until >= CURDATE() AND valid_until <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             GROUP BY lead_id"
        );
        $stmt->execute([...$leadIds, $days]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['lead_id']] = $row['valid_until'];
        }
        return $result;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT q.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.city AS client_city, c.state AS client_state,
                    c.seller_id AS client_seller_id,
                    u.name AS seller_name,
                    l.city AS lead_city, l.whatsapp AS lead_whatsapp, l.source AS lead_source,
                    l.vehicle_plate, l.vehicle_year, l.vehicle_brand, l.vehicle_model,
                    l.vehicle_power, l.vehicle_ecu_status, l.vehicle_reprogrammed_power, l.vehicle_has_arla
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             LEFT JOIN users u ON u.id = q.seller_id
             LEFT JOIN leads l ON l.id = q.lead_id
             WHERE q.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO quotes (client_id, lead_id, seller_id, influencer_id, status, quote_date, valid_until, total_value, notes)
                 VALUES (:client_id, :lead_id, :seller_id, :influencer_id, :status, :quote_date, :valid_until, 0, :notes)'
            );
            $stmt->execute([
                'client_id' => $data['client_id'],
                'lead_id' => $data['lead_id'] ?? null,
                'seller_id' => $data['seller_id'] ?: null,
                'influencer_id' => $data['influencer_id'] ?? null,
                'status' => $data['status'] ?? 'aberto',
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?: null,
                'notes' => $data['notes'] ?: null,
            ]);
            $quoteId = (int) $db->lastInsertId();

            foreach ($items as $item) {
                QuoteItem::create($quoteId, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
            }

            self::recalculateTotal($quoteId);
            $db->commit();

            return $quoteId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateHeaderAndItems(int $id, array $data, array $items): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE quotes SET client_id = :client_id, seller_id = :seller_id,
                    quote_date = :quote_date, valid_until = :valid_until, notes = :notes WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'client_id' => $data['client_id'],
                'seller_id' => $data['seller_id'] ?: null,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?: null,
                'notes' => $data['notes'] ?: null,
            ]);

            QuoteItem::deleteForQuote($id);
            foreach ($items as $item) {
                QuoteItem::create($id, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
            }

            self::recalculateTotal($id);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function recalculateTotal(int $id): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM quote_items WHERE quote_id = :id');
        $stmt->execute(['id' => $id]);
        $total = (float) $stmt->fetchColumn();

        $update = $db->prepare('UPDATE quotes SET total_value = :total WHERE id = :id');
        $update->execute(['total' => $total, 'id' => $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE quotes SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Cria um Pedido a partir de um orcamento aprovado e marca o orcamento como convertido */
    public static function convertToOrder(int $quoteId): int
    {
        $quote = self::find($quoteId);
        $items = QuoteItem::forQuote($quoteId);

        $orderId = Order::create([
            'client_id' => $quote['client_id'],
            'seller_id' => $quote['seller_id'],
            'influencer_id' => $quote['influencer_id'] ?? null,
            'order_date' => date('Y-m-d'),
            'notes' => 'Convertido do orçamento #' . $quoteId,
        ], array_map(fn ($i) => [
            'product_id' => $i['product_id'],
            'quantity' => $i['quantity'],
            'unit_price' => $i['unit_price'],
        ], $items));

        $stmt = Database::connection()->prepare(
            "UPDATE quotes SET status = 'convertido', converted_order_id = :order_id WHERE id = :id"
        );
        $stmt->execute(['order_id' => $orderId, 'id' => $quoteId]);

        return $orderId;
    }
}
