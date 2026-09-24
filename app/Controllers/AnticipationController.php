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
use App\Models\User;

/**
 * Controle de antecipacao de recebiveis feita na Asaas (Fase 25): status, taxas e valores das
 * antecipacoes reais da conta. Espelho local (asaas_anticipations) -- desde a Fase 117,
 * sincronizado automaticamente a cada carregamento da pagina (best-effort: se a Asaas estiver
 * fora do ar, so mostra o espelho local existente em vez de quebrar a tela), sem precisar mais
 * do clique manual em "Atualizar do Asaas" (que continua existindo, pra forcar uma nova busca
 * na hora). So admin e gerente (visao nacional) tem acesso.
 *
 * Nao mostra "limite disponivel pra antecipar" (GET /anticipations/limits) -- testado ao vivo e
 * confirmado com o usuario que esse numero e' um teto de risco/credito generico que a Asaas
 * atribui a conta, nao dinheiro real de recebiveis confirmados prontos pra antecipar; exibir como
 * "disponivel" era enganoso.
 */
class AnticipationController
{
    private const ALLOWED_ROLES = ['admin', 'gerente'];

    /** Fase 117: os 4 grupos que o usuario pediu pra filtrar, mapeados pros status BRUTOS que a
     *  Asaas devolve (varios status brutos caem no mesmo grupo, ex: DONE e CREDITED = concluida). */
    private const STATUS_GROUPS = [
        'andamento' => ['PENDING', 'SCHEDULED'],
        'concluida' => ['DONE', 'CREDITED'],
        'cancelada' => ['CANCELLED'],
        'rejeitada' => ['DENIED', 'DECLINED'],
    ];

    public function index(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        [$from, $to] = DateRange::fromRequest();

        // Fase 117: busca ao vivo, best-effort -- se a Asaas falhar agora (rede, chave, etc.),
        // segue so' com o que ja tinha no espelho local em vez de derrubar a pagina inteira.
        $syncError = null;
        try {
            $this->syncFromAsaas();
        } catch (\Throwable $e) {
            $syncError = $e->getMessage();
        }

        $statusGroup = $_GET['situacao'] ?? '';
        $filters = ['from' => $from, 'to' => $to];
        if (isset(self::STATUS_GROUPS[$statusGroup])) {
            $filters['statuses'] = self::STATUS_GROUPS[$statusGroup];
        }

        $rows = AsaasAnticipation::all($filters);
        foreach ($rows as &$row) {
            $row['client_name'] = null;
            $row['order_id'] = null;
            $row['licenciado_name'] = null;

            if ($row['payable_type'] === 'order' && $row['payable_id']) {
                $order = Order::find((int) $row['payable_id']);
                $row['client_name'] = $order['client_name'] ?? null;
                $row['order_id'] = (int) $row['payable_id'];

                // Pedido -> vendedor -> Licenciado dono da rede (mesmo helper ja usado em
                // Order::sellerRanking() pra resolver o Licenciado responsavel por um vendedor).
                $seller = !empty($order['seller_id']) ? User::find((int) $order['seller_id']) : null;
                if ($seller) {
                    $licenciado = User::responsibleFor($seller);
                    $row['licenciado_name'] = $licenciado['name'] ?? null;
                }
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
            'statusGroup' => $statusGroup,
            'rows' => $rows,
            'totals' => AsaasAnticipation::totals(['from' => $from, 'to' => $to]),
            'syncError' => $syncError,
        ]);
    }

    /** Forca uma nova busca na Asaas na hora -- so' precisa disso se quiser confirmar algo que
     *  acabou de acontecer sem esperar o proximo carregamento da pagina (que ja sincroniza
     *  sozinho desde a Fase 117). */
    public function sync(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/antecipacoes?erro=1');
        }

        try {
            $imported = $this->syncFromAsaas();
            Router::redirect('/painel/financeiro/antecipacoes?sucesso=1&importadas=' . $imported);
        } catch (\Throwable $e) {
            Router::redirect('/painel/financeiro/antecipacoes?erro=asaas');
        }
    }

    /** Busca as antecipacoes direto na Asaas e atualiza o espelho local -- chamada tanto pelo
     *  sync automatico de index() quanto pelo botao manual de sync(). */
    private function syncFromAsaas(): int
    {
        $client = new AsaasClient();

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

        return $imported;
    }
}
