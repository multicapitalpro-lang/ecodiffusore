<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BrazilStates;
use App\Core\Chart;
use App\Core\Csrf;
use App\Core\DateRange;
use App\Core\FollowUpReminder;
use App\Core\InactivityAlert;
use App\Core\NfeStatusChecker;
use App\Core\QuoteLeadReminder;
use App\Core\ReportScheduler;
use App\Core\Roles;
use App\Core\Router;
use App\Core\TaxReport;
use App\Core\View;
use App\Core\WeeklyDigest;
use App\Models\AsaasAnticipation;
use App\Models\Client;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\SellerActivity;
use App\Models\User;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!empty($user['must_change_password'])) {
            Router::redirect('/painel/trocar-senha');
        }

        if ($user['role_slug'] === 'cliente' && !$user['email_verified_at']) {
            Router::redirect('/painel/verificar-email');
        }

        if ($user['role_slug'] === Roles::FACTORY) {
            Router::redirect('/painel/fabrica');
        }

        if ($user['role_slug'] === 'licenciado' && $user['licenciado_onboarding_status'] === 'aguardando_perfil') {
            Router::redirect('/painel/licenciados/completar-perfil');
        }
        if ($user['role_slug'] === 'licenciado' && in_array($user['licenciado_onboarding_status'], ['aguardando_assinatura', 'aguardando_aprovacao', 'assinatura_recusada', 'kyc_recusado'], true)) {
            Router::redirect('/painel/licenciados/aguardando-assinatura');
        }

        $role = $user['role_slug'];
        $data = ['user' => $user];

        if (in_array($role, Roles::MANAGEMENT, true)) {
            // Sem cron nesse plano Hostinger -- "lazy check" no dashboard, a pagina mais visitada
            // por quem tem acesso a Relatorios, pra nao deixar agendamento parado sem nunca disparar.
            ReportScheduler::processDue();
            WeeklyDigest::processDue();
            FollowUpReminder::processDue();
            InactivityAlert::processDue();
            QuoteLeadReminder::processDue();
            NfeStatusChecker::processDue();
        }

        if (in_array($role, Roles::STAFF, true)) {
            [$from, $to] = DateRange::fromRequest();
            [$prevFrom, $prevTo] = DateRange::previousPeriod($from, $to);

            // Vendedor ve so as proprias vendas; gestor/licenciado veem a equipe/regiao agregada;
            // supervisor/gerente veem a rede nacional que cuidam (visualizacao); admin ve tudo
            $sellerId = null;
            $sellerIds = null;
            if ($role === Roles::SELLER) {
                $sellerId = (int) $user['id'];
            } elseif ($role === 'supervisor') {
                $sellerIds = User::supervisedIds((int) $user['id']);
            } elseif ($role === 'gerente') {
                $sellerIds = User::nationalIds((int) $user['id']);
            } elseif ($role !== 'admin') {
                $sellerIds = User::downlineIds((int) $user['id']);
            }

            $current = Order::metrics($from, $to, $sellerId, $sellerIds);
            $previous = Order::metrics($prevFrom, $prevTo, $sellerId, $sellerIds);
            $cost = Order::costTotal($from, $to, $sellerId, $sellerIds);

            $data['from'] = $from;
            $data['to'] = $to;
            $data['metrics'] = $current;
            $data['changes'] = [
                'total_value' => DateRange::percentChange($current['total_value'], $previous['total_value']),
                'order_count' => DateRange::percentChange($current['order_count'], $previous['order_count']),
                'products_sold' => DateRange::percentChange($current['products_sold'], $previous['products_sold']),
                'ticket_medio' => DateRange::percentChange($current['ticket_medio'], $previous['ticket_medio']),
            ];
            $data['grossMargin'] = $current['total_value'] - $cost;
            $data['costTotal'] = $cost;
            $data['chartDailyJson'] = json_encode(Chart::dailySeriesData(
                Order::dailySeries($from, $to, $sellerId, $sellerIds),
                Order::dailySeries($prevFrom, $prevTo, $sellerId, $sellerIds),
                $from,
                $to,
                $prevFrom
            ), JSON_UNESCAPED_UNICODE);
            $data['topProducts'] = OrderItem::topProducts($from, $to, $sellerId, $sellerIds);
            $data['goals'] = array_map(
                fn ($g) => $g + ['progress' => Goal::progress($g)],
                Goal::activeFor((int) $user['id'])
            );

            // Vendas por Vendedor: so pro painel do Licenciado (visao do proprio time). Reaproveita
            // sellerRanking() -- ja filtra role_slug='vendedor', entao mesmo $sellerIds incluindo
            // gestor(es)/o proprio licenciado (downlineIds) nao contamina o resultado.
            if ($role === Roles::REGIONAL_OWNER) {
                $vendedorItems = array_map(
                    fn ($row) => ['label' => $row['name'], 'value' => (float) $row['total_value']],
                    Order::sellerRanking($from, $to, $sellerIds)
                );
                $data['chartByVendedor'] = Chart::bar($vendedorItems, 10);
            }

            // Vendedor inativo (sem pedido/orcamento/nota de lead ha X dias): sinal pro
            // Gestor/Licenciado dar suporte antes da equipe esfriar de vez -- so pra quem de fato
            // gerencia um time de Vendedor direto.
            if (in_array($role, ['gestor', Roles::REGIONAL_OWNER], true)) {
                $ownVendedores = array_values(array_filter(
                    User::allByRole('vendedor'),
                    fn ($v) => in_array((int) $v['id'], $sellerIds ?? [], true)
                ));
                $data['vendedoresInativos'] = SellerActivity::inactiveAmong($ownVendedores);
                $data['weeklyDigestEnabled'] = !empty($user['weekly_digest_enabled']);
            }

            // ---- Visao geral (overview): resumo do resto do painel direto no inicio ----

            // CRM: mesmo escopo ja calculado acima pros pedidos (sellerId/sellerIds), reaproveitado
            // pra nao duplicar consulta de downlineIds/supervisedIds/nationalIds.
            $crmScope = $sellerId !== null ? ['seller_id' => $sellerId] : ($sellerIds !== null ? ['seller_ids' => $sellerIds] : []);

            // Cards "Pedidos pendentes"/"Pedidos pagos" (pedido do usuario, Dashboard) -- de
            // proposito SEM filtro de periodo (mesmo criterio que pedidosPendentes ja tinha antes):
            // e' um retrato operacional de agora ("quem ainda nao pagou", "quanto ja recebi no
            // total"), nao uma metrica do periodo filtrado no formulario acima.
            $situationAllTime = Order::paymentSituationSummary($crmScope);
            $data['pedidosPendentes'] = $situationAllTime['pending']['count'];
            $data['pedidosPendentesValor'] = $situationAllTime['pending']['total_value'];
            $data['pedidosPagos'] = $situationAllTime['paid']['count'];
            $data['pedidosPagosValor'] = $situationAllTime['paid']['total_value'];
            $data['orcamentosPendentes'] = Quote::countPendingPayment($crmScope);

            // Segundo grafico do Dashboard: mesma serie diaria do grafico principal, so que
            // separada em total/pendente/pago -- pedido do usuario pra ver de cara quanto do
            // faturamento do periodo ja virou dinheiro de verdade. Mesmo escopo por hierarquia
            // de tudo mais nesse bloco (sellerId/sellerIds).
            $data['chartSituacaoJson'] = json_encode(Chart::dailySituationData(
                Order::dailySeriesBySituation($from, $to, $sellerId, $sellerIds),
                $from,
                $to
            ), JSON_UNESCAPED_UNICODE);

            if ($role === 'admin') {
                $leads = Lead::all();
            } else {
                $leadIds = $sellerId !== null ? [$sellerId] : ($sellerIds ?? []);
                $includeUnassigned = !in_array($role, [Roles::SELLER, 'supervisor', 'gerente'], true);
                $leads = Lead::forScope($leadIds, $includeUnassigned);
            }
            $data['leadsNovos'] = count(array_filter($leads, fn ($l) => $l['status'] === 'novo'));

            // Follow-ups combinados (ver LeadNote) que ja chegaram na data -- mesmo "requer atencao"
            // que o resto desta secao, so que pra nao esquecer de retornar pro lead como prometido.
            $todayStr = date('Y-m-d');
            $pendingFollowUps = LeadNote::pendingFollowUps(array_column($leads, 'id'));
            $data['followUpsPendentes'] = count(array_filter($pendingFollowUps, fn ($d) => $d <= $todayStr));

            // Comissao pendente da propria pessoa (vendedor/gestor/licenciado/supervisor/gerente
            // podem todos ser beneficiario de comissao -- admin normalmente nao, fica 0).
            $myCommission = Commission::byBeneficiary(['beneficiary_id' => (int) $user['id']]);
            $data['minhaComissaoPendente'] = (float) ($myCommission[0]['total_pendente'] ?? 0);
        }

        if (in_array($role, Roles::MANAGEMENT, true)) {
            // Financeiro: mesmo escopo que FinanceController::scopeFilters() ja usa -- Licenciado
            // e Gestor veem so a propria rede, admin ve tudo.
            $financeScope = in_array($role, [Roles::REGIONAL_OWNER, 'gestor'], true)
                ? ['seller_ids' => User::downlineIds((int) $user['id'])]
                : [];

            $accounts = FinancialAccount::all();
            $data['saldoCaixa'] = array_sum(array_map(
                fn ($a) => FinancialAccount::currentBalance((int) $a['id']),
                $accounts
            ));

            $today = date('Y-m-d');
            $payables = FinancialTransaction::all(array_merge(['type' => 'saida', 'status' => 'pendente', 'exclude_transfers' => true], $financeScope));
            $receivables = FinancialTransaction::all(array_merge(['type' => 'entrada', 'status' => 'pendente', 'exclude_transfers' => true], $financeScope));

            $data['contasPagarAberto'] = array_sum(array_map(fn ($t) => FinancialTransaction::totalValue($t), $payables));
            $data['contasPagarVencidas'] = count(array_filter($payables, fn ($t) => $t['due_date'] < $today));
            $data['contasReceberAberto'] = array_sum(array_map(fn ($t) => FinancialTransaction::totalValue($t), $receivables));
        }

        if (in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true)) {
            $data['aprovacoesPendentes'] = User::pendingApprovalCount($user);

            // Fiscal/Antecipacoes: mesmo par admin+gerente (visao nacional), so dado sensivel da
            // operacao inteira. Imposto calculado no periodo do filtro (from/to ja resolvido no
            // bloco STAFF acima); antecipacao vem sempre do espelho local (nunca busca na Asaas
            // ao vivo aqui -- ver AnticipationController::sync). Nao mostra "limite disponivel pra
            // antecipar" da Asaas -- confirmado que e' um teto de risco/credito generico da conta,
            // nao dinheiro real de recebiveis prontos pra antecipar (ver AnticipationController).
            $taxOrders = Order::all(['status' => 'verificado', 'from' => $data['from'] ?? date('Y-m-01'), 'to' => $data['to'] ?? date('Y-m-t')]);
            $data['impostoPagoPeriodo'] = TaxReport::forOrders($taxOrders)['totals']['tax'];

            $anticipationTotals = AsaasAnticipation::totals();
            $data['antecipadoLiquidoTotal'] = $anticipationTotals['net_value_effective'];
            $data['antecipacaoTaxaTotal'] = $anticipationTotals['fee_effective'];
        }

        if (in_array($role, ['admin', 'gerente', 'supervisor'], true)) {
            $licenciadosAtivos = User::allByRole('licenciado');
            $data['licenciadosAtivos'] = count($licenciadosAtivos);
            $data['estadosCobertos'] = count(BrazilStates::groupByState($licenciadosAtivos));

            // Vendas por Estado/Cidade/Licenciado, separadas em pendentes e vendidos (pago) --
            // pedido do usuario pra nao misturar "quem ainda deve" com "quem ja pagou" no mesmo
            // grafico. Escopado por hierarquia (nacional pro admin, rede supervisionada pro
            // supervisor/gerente -- $sellerIds calculado mais acima, no bloco STAFF) e pelo
            // periodo filtrado (diferente dos cards "Pedidos pendentes/pagos" do topo, que sao
            // um retrato sem filtro de data). Estado/cidade sao os do VENDEDOR (users.city/state),
            // mesmo modelo geografico do resto do sistema (GeoMatch, BrazilStates).
            $regionScope = array_merge($crmScope, ['from' => $data['from'], 'to' => $data['to']]);
            $regionSummary = Order::paymentSituationSummary($regionScope);

            $pendingCharts = $this->regionCharts($regionSummary['pending']['by_seller']);
            $data['chartPendingByState'] = $pendingCharts['byState'];
            $data['chartPendingByCity'] = $pendingCharts['byCity'];
            $data['chartPendingByLicenciado'] = $pendingCharts['byLicenciado'];

            $paidCharts = $this->regionCharts($regionSummary['paid']['by_seller']);
            $data['chartPaidByState'] = $paidCharts['byState'];
            $data['chartPaidByCity'] = $paidCharts['byCity'];
            $data['chartPaidByLicenciado'] = $paidCharts['byLicenciado'];
        }

        if ($role === 'admin') {
            $data['leadCount'] = Lead::count();
        }

        if ($role === 'cliente') {
            $client = Client::findByUserId((int) $user['id']);
            $data['myClient'] = $client;

            if ($client) {
                $orders = Order::all(['client_id' => $client['id']]);
                $data['myOrders'] = array_map(function ($o) {
                    $o['payments'] = Payment::forPayable('order', (int) $o['id']);
                    return $o;
                }, $orders);
                $data['myTotalPurchased'] = array_sum(array_map(
                    fn ($o) => $o['status'] !== 'cancelado' ? (float) $o['total_value'] : 0,
                    $orders
                ));
            }
        }

        View::render('painel/dashboard', $data);
    }

    public function toggleWeeklyDigest(): void
    {
        Auth::requireRole(['gestor', Roles::REGIONAL_OWNER]);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel');
        }

        WeeklyDigest::setEnabled((int) $user['id'], !empty($_POST['enabled']));

        Router::redirect('/painel');
    }

    /** Converte um mapa seller_id => valor (Order::paymentSituationSummary()['pending'/'paid']
     *  ['by_seller']) em 3 graficos de barra (estado/cidade/licenciado do VENDEDOR) -- mesma
     *  logica que antes ficava inline num unico bloco combinado, agora reaproveitada 2x (pendente
     *  e pago) desde que a Fase de separacao por situacao de pagamento pediu os 2 quadros. */
    private function regionCharts(array $bySeller): array
    {
        $byState = [];
        $byCity = [];
        $byLicenciado = [];
        $userCache = [];
        $licenciadoNameCache = [];

        foreach ($bySeller as $sid => $value) {
            if ($value <= 0) {
                continue;
            }

            if (!array_key_exists($sid, $userCache)) {
                $userCache[$sid] = User::find($sid);
            }
            $u = $userCache[$sid];

            $uf = strtoupper(trim($u['state'] ?? ''));
            $stateKey = $uf !== '' ? $uf : 'Não informado';
            $byState[$stateKey] = ($byState[$stateKey] ?? 0) + $value;

            $city = trim($u['city'] ?? '');
            $cityLabel = ($city !== '' ? $city : 'Não informada') . ($uf !== '' ? " / {$uf}" : '');
            $byCity[$cityLabel] = ($byCity[$cityLabel] ?? 0) + $value;

            if (!array_key_exists($sid, $licenciadoNameCache)) {
                $licenciadoNameCache[$sid] = User::licenciadoNameFor($sid) ?? 'Sem licenciado';
            }
            $licName = $licenciadoNameCache[$sid];
            $byLicenciado[$licName] = ($byLicenciado[$licName] ?? 0) + $value;
        }

        arsort($byState);
        arsort($byCity);
        arsort($byLicenciado);

        $stateItems = [];
        foreach ($byState as $uf => $value) {
            $stateItems[] = ['label' => BrazilStates::NAMES[$uf] ?? $uf, 'value' => $value];
        }
        $cityItems = array_map(fn ($label, $value) => ['label' => $label, 'value' => $value], array_keys($byCity), $byCity);
        $licItems = array_map(fn ($label, $value) => ['label' => $label, 'value' => $value], array_keys($byLicenciado), $byLicenciado);

        return [
            'byState' => Chart::bar($stateItems),
            'byCity' => Chart::bar($cityItems, 8),
            'byLicenciado' => Chart::bar($licItems, 8),
        ];
    }
}
