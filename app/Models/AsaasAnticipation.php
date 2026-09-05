<?php

namespace App\Models;

use App\Core\Database;

/**
 * Espelho local das antecipacoes de recebiveis feitas na Asaas (Fase 25) -- sincronizado sob
 * demanda (AnticipationController::sync) via AsaasClient::listAnticipations(), nunca ao vivo em
 * toda carga de pagina. `payment_id` casa com `payments.asaas_charge_id` = `asaas_payment_id`
 * quando a cobranca foi gerada por este painel; pode ficar null se a cobranca nao tiver origem
 * aqui (edge case, mas guardamos os dados da Asaas mesmo assim).
 */
class AsaasAnticipation
{
    public static function all(array $filters = []): array
    {
        $sql = "SELECT a.*, p.payable_type, p.payable_id, p.amount AS payment_amount
                FROM asaas_anticipations a
                LEFT JOIN payments p ON p.id = a.payment_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND a.request_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND a.request_date <= :to';
            $params['to'] = $filters['to'];
        }

        $sql .= ' ORDER BY a.request_date DESC, a.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Totais agregados (todo o historico local, ou filtrado por periodo) -- separa DONE/SCHEDULED
     *  (antecipacao "valendo") do resto (negada/cancelada), pra nao inflar os cards com pedidos
     *  de antecipacao que nao foram efetivados. */
    public static function totals(array $filters = []): array
    {
        $rows = self::all($filters);

        $totals = [
            'count' => count($rows),
            'value_effective' => 0.0,
            'net_value_effective' => 0.0,
            'fee_effective' => 0.0,
            'count_effective' => 0,
            'count_pending' => 0,
            'count_denied' => 0,
        ];

        foreach ($rows as $r) {
            $status = strtoupper((string) $r['status']);
            if (in_array($status, ['DONE', 'SCHEDULED', 'CREDITED'], true)) {
                $totals['value_effective'] += (float) $r['value'];
                $totals['net_value_effective'] += (float) $r['net_value'];
                $totals['fee_effective'] += (float) $r['fee'];
                $totals['count_effective']++;
            } elseif (in_array($status, ['DENIED', 'CANCELLED', 'DECLINED'], true)) {
                $totals['count_denied']++;
            } else {
                $totals['count_pending']++;
            }
        }

        return $totals;
    }

    public static function upsert(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO asaas_anticipations
                (asaas_anticipation_id, asaas_payment_id, payment_id, status, value, total_value, net_value, fee,
                 anticipation_days, anticipation_date, due_date, request_date, denial_observation)
             VALUES
                (:aid, :apid, :payment_id, :status, :value, :total_value, :net_value, :fee,
                 :days, :adate, :ddate, :rdate, :denial)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), value = VALUES(value), total_value = VALUES(total_value),
                net_value = VALUES(net_value), fee = VALUES(fee), anticipation_days = VALUES(anticipation_days),
                anticipation_date = VALUES(anticipation_date), due_date = VALUES(due_date),
                denial_observation = VALUES(denial_observation), payment_id = VALUES(payment_id)'
        );
        $stmt->execute([
            'aid' => $data['asaas_anticipation_id'],
            'apid' => $data['asaas_payment_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'status' => $data['status'],
            'value' => $data['value'] ?? 0,
            'total_value' => $data['total_value'] ?? 0,
            'net_value' => $data['net_value'] ?? 0,
            'fee' => $data['fee'] ?? 0,
            'days' => $data['anticipation_days'] ?? null,
            'adate' => $data['anticipation_date'] ?? null,
            'ddate' => $data['due_date'] ?? null,
            'rdate' => $data['request_date'] ?? null,
            'denial' => $data['denial_observation'] ?? null,
        ]);
    }
}
