<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AsaasClient;
use App\Core\Csrf;
use App\Core\DateRange;
use App\Core\Router;
use App\Core\View;
use App\Models\AsaasAnticipation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;

/**
 * Controle de antecipacao de recebiveis feita na Asaas (Fase 25): status, taxas e valores das
 * antecipacoes reais da conta. Espelho local (asaas_anticipations), sincronizado sob demanda --
 * nunca busca ao vivo na Asaas em toda carga de pagina (ver sync()). So admin e gerente (visao
 * nacional) tem acesso.
 *
 * Nao mostra "limite disponivel pra antecipar" (GET /anticipations/limits) -- testado ao vivo e
 * confirmado com o usuario que esse numero e' um teto de risco/credito generico que a Asaas
 * atribui a conta, nao dinheiro real de recebiveis confirmados prontos pra antecipar; exibir como
 * "disponivel" era enganoso.
 */
class AnticipationController
{
    private const ALLOWED_ROLES = ['admin', 'gerente'];

    public function index(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        [$from, $to] = DateRange::fromRequest();

        $rows = AsaasAnticipation::all(['from' => $from, 'to' => $to]);
        foreach ($rows as &$row) {
            $row['client_name'] = null;
            if ($row['payable_type'] === 'order' && $row['payable_id']) {
                $order = Order::find((int) $row['payable_id']);
                $row['client_name'] = $order['client_name'] ?? null;
            } elseif ($row['payable_type'] === 'quote' && $row['payable_id']) {
                $quote = Quote::find((int) $row['payable_id']);
                $row['client_name'] = $quote['client_name'] ?? null;
            }
        }
        unset($row);

        View::render('painel/finance/anticipations', [
            'user' => Auth::user(),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => AsaasAnticipation::totals(['from' => $from, 'to' => $to]),
        ]);
    }

    /** Busca as antecipacoes direto na Asaas e atualiza o espelho local -- unica acao desta tela
     *  que chama a API de verdade, disparada manualmente. */
    public function sync(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/antecipacoes?erro=1');
        }

        $client = new AsaasClient();

        try {
            $offset = 0;
            $imported = 0;
            do {
                $page = $client->listAnticipations($offset, 100);
                foreach ($page['data'] ?? [] as $item) {
                    $payment = !empty($item['payment']) ? Payment::findByChargeId($item['payment']) : null;

                    AsaasAnticipation::upsert([
                        'asaas_anticipation_id' => $item['id'],
                        'asaas_payment_id' => $item['payment'] ?? null,
                        'payment_id' => $payment['id'] ?? null,
                        'status' => $item['status'] ?? 'DESCONHECIDO',
                        'value' => $item['value'] ?? 0,
                        'total_value' => $item['totalValue'] ?? 0,
                        'net_value' => $item['netValue'] ?? 0,
                        'fee' => $item['fee'] ?? 0,
                        'anticipation_days' => $item['anticipationDays'] ?? null,
                        'anticipation_date' => $item['anticipationDate'] ?? null,
                        'due_date' => $item['dueDate'] ?? null,
                        'request_date' => $item['requestDate'] ?? null,
                        'denial_observation' => $item['denialObservation'] ?: null,
                    ]);
                    $imported++;
                }
                $offset += 100;
            } while (!empty($page['hasMore']));

            Router::redirect('/painel/financeiro/antecipacoes?sucesso=1&importadas=' . $imported);
        } catch (\Throwable $e) {
            Router::redirect('/painel/financeiro/antecipacoes?erro=asaas');
        }
    }
}
