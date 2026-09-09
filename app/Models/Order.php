<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Notifier;
use PDO;

class Order
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT o.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.city AS client_city, c.state AS client_state,
                    u.name AS seller_name,
                    (SELECT GROUP_CONCAT(p.name SEPARATOR ", ") FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = o.id) AS product_names,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS total_qty
                FROM orders o
                JOIN clients c ON c.id = o.client_id
                LEFT JOIN users u ON u.id = o.seller_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND o.order_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND o.order_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['seller_id'])) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $filters['seller_id'];
        }
        if (!empty($filters['seller_ids'])) {
            $names = [];
            foreach (array_values($filters['seller_ids']) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND o.seller_id IN (' . implode(',', $names) . ')';
        }
        if (!empty($filters['client_id'])) {
            $sql .= ' AND o.client_id = :client_id';
            $params['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['city'])) {
            $sql .= ' AND c.city LIKE :city';
            $params['city'] = '%' . $filters['city'] . '%';
        }

        $sql .= ' ORDER BY o.order_date DESC, o.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Quantos pedidos (no escopo de $filters) estao com pagamento pendente ou expirado agora --
     *  usado no card de alerta do Dashboard. Situacao derivada (nao e' so status do pedido),
     *  entao reaproveita a mesma logica de Payment::situationFor ja usada em Pedidos/Orcamentos. */
    public static function countPendingPayment(array $filters = []): int
    {
        $orders = self::all($filters);
        if (!$orders) {
            return 0;
        }

        $ids = array_map(fn ($o) => (int) $o['id'], $orders);
        $payments = Payment::latestByPayableIds('order', $ids);

        $count = 0;
        foreach ($orders as $o) {
            $situation = Payment::situationFor($o, $payments[(int) $o['id']] ?? null);
            if (in_array($situation['slug'], ['pendente', 'expirado'], true)) {
                $count++;
            }
        }
        return $count;
    }

    /** Classifica os pedidos do escopo em "pendente" (pendente/expirado) e "pago" -- mesma logica
     *  de Payment::situationFor ja usada em countPendingPayment()/OrderController::attachPaymentSituation(),
     *  so que aqui tambem soma o VALOR total de cada grupo e agrega por vendedor (pra quebra por
     *  regiao no Dashboard: DashboardController ja converte by_seller -> estado/cidade/licenciado
     *  usando users.city/state, mesmo padrao geografico do resto do painel). Pedidos cancelados ou
     *  reembolsados nao entram em nenhum dos dois grupos. */
    public static function paymentSituationSummary(array $filters = []): array
    {
        $pending = ['count' => 0, 'total_value' => 0.0, 'by_seller' => []];
        $paid = ['count' => 0, 'total_value' => 0.0, 'by_seller' => []];

        $orders = self::all($filters);
        if (!$orders) {
            return ['pending' => $pending, 'paid' => $paid];
        }

        $ids = array_map(fn ($o) => (int) $o['id'], $orders);
        $payments = Payment::latestByPayableIds('order', $ids);

        foreach ($orders as $o) {
            $situation = Payment::situationFor($o, $payments[(int) $o['id']] ?? null);
            $value = (float) $o['total_value'];
            $sellerId = (int) ($o['seller_id'] ?? 0);

            if ($situation['slug'] === 'pago') {
                $paid['count']++;
                $paid['total_value'] += $value;
                if ($sellerId > 0) {
                    $paid['by_seller'][$sellerId] = ($paid['by_seller'][$sellerId] ?? 0) + $value;
                }
            } elseif (in_array($situation['slug'], ['pendente', 'expirado'], true)) {
                $pending['count']++;
                $pending['total_value'] += $value;
                if ($sellerId > 0) {
                    $pending['by_seller'][$sellerId] = ($pending['by_seller'][$sellerId] ?? 0) + $value;
                }
            }
        }

        return ['pending' => $pending, 'paid' => $paid];
    }

    /** Serie diaria de valor pedido, separada em pendente/pago (mesma classificacao de
     *  paymentSituationSummary(), mas dia a dia) -- usado no segundo grafico do Dashboard, alem do
     *  grafico ja existente de "total vs periodo anterior" (dailySeries()). */
    public static function dailySeriesBySituation(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): array
    {
        $filters = ['from' => $from, 'to' => $to];
        if ($sellerId !== null) {
            $filters['seller_id'] = $sellerId;
        } elseif ($sellerIds !== null) {
            $filters['seller_ids'] = $sellerIds;
        }

        $orders = self::all($filters);
        $pending = [];
        $paid = [];
        $total = [];

        if ($orders) {
            $ids = array_map(fn ($o) => (int) $o['id'], $orders);
            $payments = Payment::latestByPayableIds('order', $ids);

            foreach ($orders as $o) {
                if ($o['status'] === 'cancelado') {
                    continue;
                }
                $situation = Payment::situationFor($o, $payments[(int) $o['id']] ?? null);
                $value = (float) $o['total_value'];
                $day = $o['order_date'];

                $total[$day] = ($total[$day] ?? 0) + $value;
                if ($situation['slug'] === 'pago') {
                    $paid[$day] = ($paid[$day] ?? 0) + $value;
                } elseif (in_array($situation['slug'], ['pendente', 'expirado'], true)) {
                    $pending[$day] = ($pending[$day] ?? 0) + $value;
                }
            }
        }

        return ['total' => $total, 'pending' => $pending, 'paid' => $paid];
    }

    /** Busca por numero do pedido, nome do cliente ou placa do veiculo -- usado na busca global
     *  do painel. $sellerIds null = sem escopo (Admin). */
    public static function search(string $term, ?array $sellerIds = null): array
    {
        $termCondition = 'c.name LIKE :term1 OR o.vehicle_plate LIKE :term2';
        $params = ['term1' => '%' . $term . '%', 'term2' => '%' . $term . '%'];
        if (ctype_digit($term)) {
            $termCondition .= ' OR o.id = :order_id';
            $params['order_id'] = (int) $term;
        }
        $conditions = ["({$termCondition})"];

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
            $conditions[] = 'o.seller_id IN (' . implode(',', $names) . ')';
        }

        $sql = 'SELECT o.*, c.name AS client_name, u.name AS seller_name
                FROM orders o JOIN clients c ON c.id = o.client_id LEFT JOIN users u ON u.id = o.seller_id
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY o.order_date DESC LIMIT 20';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.city AS client_city, c.state AS client_state,
                    u.name AS seller_name
             FROM orders o
             JOIN clients c ON c.id = o.client_id
             LEFT JOIN users u ON u.id = o.seller_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public static function create(array $data, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO orders (client_id, seller_id, status, order_date, total_value, notes, vehicle_type, vehicle_plate, vehicle_document_path)
                 VALUES (:client_id, :seller_id, :status, :order_date, 0, :notes, :vehicle_type, :vehicle_plate, :vehicle_document_path)'
            );
            $stmt->execute([
                'client_id' => $data['client_id'],
                'seller_id' => $data['seller_id'] ?: null,
                'status' => $data['status'] ?? 'em_andamento',
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?: null,
                'vehicle_type' => !empty($data['vehicle_type']) ? $data['vehicle_type'] : null,
                'vehicle_plate' => !empty($data['vehicle_plate']) ? $data['vehicle_plate'] : null,
                'vehicle_document_path' => $data['vehicle_document_path'] ?? null,
            ]);
            $orderId = (int) $db->lastInsertId();

            foreach ($items as $item) {
                OrderItem::create($orderId, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
            }

            self::recalculateTotal($orderId);
            $db->commit();

            return $orderId;
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
                'UPDATE orders SET client_id = :client_id, seller_id = :seller_id,
                    order_date = :order_date, notes = :notes, vehicle_type = :vehicle_type,
                    vehicle_plate = :vehicle_plate' . (isset($data['vehicle_document_path']) ? ', vehicle_document_path = :vehicle_document_path' : '') . '
                 WHERE id = :id'
            );
            $params = [
                'id' => $id,
                'client_id' => $data['client_id'],
                'seller_id' => $data['seller_id'] ?: null,
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?: null,
                'vehicle_type' => $data['vehicle_type'] ?: null,
                'vehicle_plate' => $data['vehicle_plate'] ?: null,
            ];
            if (isset($data['vehicle_document_path'])) {
                $params['vehicle_document_path'] = $data['vehicle_document_path'];
            }
            $stmt->execute($params);

            OrderItem::deleteForOrder($id);
            foreach ($items as $item) {
                OrderItem::create($id, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
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
        $stmt = $db->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $id]);
        $total = (float) $stmt->fetchColumn();

        $update = $db->prepare('UPDATE orders SET total_value = :total WHERE id = :id');
        $update->execute(['total' => $total, 'id' => $id]);
    }

    /**
     * Cancela automaticamente pedidos "em_andamento" cuja cobranca mais recente ainda esta
     * pendente ha mais de 24h -- pra nao acumular fila de pedido parado esperando pagamento que
     * nunca vem. Chamado de forma "preguicosa" (a cada carregamento da lista de Pedidos) porque
     * o projeto nao tem infraestrutura de cron ainda; nao e um agendamento fixo de verdade, mas
     * cobre o caso pratico ja que a tela e acessada com frequencia.
     */
    public static function expireStalePending(): int
    {
        $sql = "UPDATE orders o
                INNER JOIN (
                    SELECT payable_id, MAX(created_at) AS max_created
                    FROM payments
                    WHERE payable_type = 'order'
                    GROUP BY payable_id
                ) latest ON latest.payable_id = o.id
                INNER JOIN payments p ON p.payable_id = latest.payable_id AND p.created_at = latest.max_created AND p.payable_type = 'order'
                SET o.status = 'cancelado'
                WHERE o.status = 'em_andamento'
                  AND p.status = 'pendente'
                  AND p.created_at < (NOW() - INTERVAL 24 HOUR)";

        return Database::connection()->exec($sql);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function updateTracking(int $id, ?string $trackingCode, ?string $trackingCarrier, ?string $prazoEntrega = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE orders SET tracking_code = :code, tracking_carrier = :carrier, prazo_entrega = :prazo WHERE id = :id'
        );
        $stmt->execute([
            'code' => $trackingCode !== '' ? $trackingCode : null,
            'carrier' => $trackingCarrier !== '' ? $trackingCarrier : null,
            'prazo' => $prazoEntrega !== '' ? $prazoEntrega : null,
            'id' => $id,
        ]);
    }

    /** So pedidos PAGOS (verificado) e ainda NAO entregues -- a fila de acao da fabrica. Nunca
     *  mostra cancelado/em_andamento/atendido, nem dado financeiro (valor/comissao/vendedor). */
    public static function forFactory(): array
    {
        return Database::connection()->query(
            "SELECT o.id, o.order_date, o.tracking_carrier, o.tracking_code, o.prazo_entrega,
                    o.nfe_status, o.nfe_pdf_url, o.notes, o.vehicle_type, o.vehicle_plate,
                    c.name AS client_name, c.whatsapp AS client_whatsapp, c.document AS client_document,
                    c.email AS client_email,
                    c.zip_code, c.street, c.number, c.complement, c.neighborhood, c.city, c.state,
                    (SELECT GROUP_CONCAT(p.name, ' (x', oi.quantity, ')' SEPARATOR ', ')
                     FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = o.id) AS produtos
             FROM orders o JOIN clients c ON c.id = o.client_id
             WHERE o.status = 'verificado' AND o.delivered_at IS NULL ORDER BY o.order_date DESC"
        )->fetchAll();
    }

    /** Grava o resultado da emissao de NF-e (App\Core\AsaasClient::createInvoice()/getInvoice())
     *  -- $status e' o texto cru da Asaas (SCHEDULED/SYNCHRONIZED/AUTHORIZED/PROCESSING/CANCELLED/
     *  ERROR), $pdfUrl geralmente so vem preenchido depois da aprovacao municipal (ver
     *  NfeStatusChecker::processDue(), que reconsulta as pendentes). */
    public static function updateNfe(int $id, ?string $invoiceId, ?string $status, ?string $pdfUrl): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE orders SET nfe_invoice_id = :invoice_id, nfe_status = :status, nfe_pdf_url = :pdf_url WHERE id = :id'
        );
        $stmt->execute([
            'invoice_id' => $invoiceId,
            'status' => $status,
            'pdf_url' => $pdfUrl,
            'id' => $id,
        ]);
    }

    /** Pedidos com NF-e criada na Asaas mas ainda sem pdfUrl confirmado -- fila do lazy-check
     *  (App\Core\NfeStatusChecker) que reconsulta o status ate ela sair (ou dar erro/ser
     *  cancelada, que tambem para de reconsultar). */
    public static function pendingNfeCheck(): array
    {
        return Database::connection()->query(
            "SELECT id, nfe_invoice_id FROM orders
             WHERE nfe_invoice_id IS NOT NULL AND nfe_pdf_url IS NULL
               AND (nfe_status IS NULL OR nfe_status NOT IN ('CANCELLED', 'ERROR'))"
        )->fetchAll();
    }

    /** Contadores pro cabecalho da tela da fabrica -- entre os pagos: sem codigo ainda (pendente),
     *  com codigo mas nao entregue (em rota) e ja entregue. */
    public static function factoryStats(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN delivered_at IS NULL AND (tracking_code IS NULL OR tracking_code = '') THEN 1 ELSE 0 END) AS pendente_codigo,
                SUM(CASE WHEN delivered_at IS NULL AND tracking_code IS NOT NULL AND tracking_code != '' THEN 1 ELSE 0 END) AS em_rota,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS entregues
             FROM orders WHERE status = 'verificado'"
        )->fetch();

        return [
            'total' => (int) $row['total'],
            'pendente_codigo' => (int) $row['pendente_codigo'],
            'em_rota' => (int) $row['em_rota'],
            'entregues' => (int) $row['entregues'],
        ];
    }

    public static function markDelivered(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE orders SET delivered_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Marca o pedido como verificado e roda a mesma rotina de sempre: comissao em cascata
     * (Commission::createCascadeForOrder) + lancamento em Contas a Receber. Usado tanto pelo
     * botao manual "Marcar como Verificado" quanto pelo webhook do Asaas quando o cliente paga.
     * Retorna false (sem fazer nada) se houver uma aprovacao de desconto pendente pra esse pedido.
     */
    public static function markVerifiedWithCommission(int $id): bool
    {
        $order = self::find($id);
        if (!$order || $order['status'] === 'verificado') {
            return false;
        }

        if (Approval::pendingFor('order', $id)) {
            return false;
        }

        self::updateStatus($id, 'verificado');

        if ($order['seller_id']) {
            Commission::createCascadeForOrder($id, (int) $order['seller_id'], (float) $order['total_value']);
        }

        $accountId = FinancialAccount::defaultAccountId();
        if ($accountId) {
            FinancialTransaction::createForOrderReceivable($id, $accountId, (float) $order['total_value'], date('Y-m-d'));
        }

        Notifier::pedidoAprovado($order);

        return true;
    }

    public static function metrics(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): array
    {
        $sql = 'SELECT COUNT(*) AS order_count, COALESCE(SUM(total_value), 0) AS total_value,
                    COALESCE(SUM((SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id)), 0) AS products_sold
                FROM orders o
                WHERE o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        } elseif (!empty($sellerIds)) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND o.seller_id IN (' . implode(',', $names) . ')';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        $orderCount = (int) $row['order_count'];
        $totalValue = (float) $row['total_value'];

        return [
            'order_count' => $orderCount,
            'total_value' => $totalValue,
            'products_sold' => (int) $row['products_sold'],
            'ticket_medio' => $orderCount > 0 ? $totalValue / $orderCount : 0.0,
        ];
    }

    public static function costTotal(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): float
    {
        $sql = 'SELECT COALESCE(SUM(oi.quantity * p.cost_price), 0)
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        } elseif (!empty($sellerIds)) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND o.seller_id IN (' . implode(',', $names) . ')';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /** Pedidos (nao cancelados) no periodo, agrupados por vendedor -- quebra do funil de conversao. */
    public static function funnelBySeller(string $from, string $to, ?array $sellerIds = null): array
    {
        $sql = "SELECT seller_id, COUNT(*) AS order_count FROM orders
                WHERE order_date BETWEEN :from AND :to AND status != 'cancelado' AND seller_id IS NOT NULL";
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
            $result[(int) $row['seller_id']] = (int) $row['order_count'];
        }
        return $result;
    }

    public static function dailySeries(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): array
    {
        $sql = 'SELECT order_date, SUM(total_value) AS total
                FROM orders
                WHERE order_date BETWEEN :from AND :to AND status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        } elseif (!empty($sellerIds)) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND seller_id IN (' . implode(',', $names) . ')';
        }

        $sql .= ' GROUP BY order_date';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $series = [];
        foreach ($stmt->fetchAll() as $row) {
            $series[$row['order_date']] = (float) $row['total'];
        }

        return $series;
    }

    /** Resumo de compras por cliente (quantos pedidos, quantos em aberto/pagos) -- usado na
     * coluna "Pedidos" de /painel/clientes, numa unica query em vez de N+1 por cliente. */
    public static function purchaseSummaryByClientIds(array $clientIds): array
    {
        if (!$clientIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $sql = "SELECT client_id, COUNT(*) AS order_count,
                    SUM(CASE WHEN status IN ('em_andamento', 'atendido') THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status = 'verificado' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelled_count
                FROM orders
                WHERE client_id IN ({$placeholders})
                GROUP BY client_id";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($clientIds);

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['client_id']] = $row;
        }
        return $byId;
    }

    /** $sellerIds: escopo por rede (downline de Gestor/Licenciado) -- null = sem escopo (Admin). */
    public static function sellerRanking(string $from, string $to, ?array $sellerIds = null): array
    {
        $params = ['from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to];
        $scopeSql = '';
        if ($sellerIds !== null) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "rksid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $scopeSql = ' AND u.id IN (' . implode(',', $names) . ')';
        }

        $sql = 'SELECT u.id AS seller_id, u.name, u.city, u.state, u.manager_id, u.created_at, r.slug AS role_slug,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.total_value), 0) AS total_value,
                    COALESCE((SELECT SUM(c.amount) FROM commissions c WHERE c.seller_id = u.id
                        AND c.order_id IN (SELECT id FROM orders WHERE order_date BETWEEN :from2 AND :to2)), 0) AS commission_total,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_to_user_id = u.id) AS lead_count,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_to_user_id = u.id AND l.status NOT IN (\'convertido\', \'descartado\')) AS lead_open_count
                FROM users u
                JOIN roles r ON r.id = u.role_id AND r.slug = \'vendedor\'
                LEFT JOIN orders o ON o.seller_id = u.id AND o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'
                WHERE 1=1' . $scopeSql . '
                GROUP BY u.id
                ORDER BY total_value DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $orderCount = (int) $row['order_count'];
            $leadCount = (int) $row['lead_count'];
            $row['avg_ticket'] = $orderCount > 0 ? (float) $row['total_value'] / $orderCount : 0.0;
            $row['conversion_pct'] = $leadCount > 0 ? round($orderCount / $leadCount * 100, 1) : null;

            $licenciado = User::responsibleFor($row);
            $row['licenciado_name'] = $licenciado['name'] ?? null;
        }
        unset($row);

        return $rows;
    }
}
