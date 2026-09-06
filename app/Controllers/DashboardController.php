<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BrazilStates;
use App\Core\Chart;
use App\Core\DateRange;
use App\Core\ReportScheduler;
use App\Core\Roles;
use App\Core\Router;
use App\Core\TaxReport;
use App\Core\View;
use App\Models\AsaasAnticipation;
use App\Models\Client;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Quote;
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
            $data['chartSvg'] = Chart::dailyLine(
                Order::dailySeries($from, $to, $sellerId, $sellerIds),
                Order::dailySeries($prevFrom, $prevTo, $sellerId, $sellerIds),
                $from,
                $to,
                $prevFrom
            );
            $data['topProducts'] = OrderItem::topProducts($from, $to, $sellerId, $sellerIds);
            $data['goals'] = array_map(
                fn ($g) => $g + ['progress' => Goal::progress($g)],
                Goal::activeFor((int) $user['id'])
            );

            // ---- Visao geral (overview): resumo do resto do painel direto no inicio ----

            // CRM: mesmo escopo ja calculado acima pros pedidos (sellerId/sellerIds), reaproveitado
            // pra nao duplicar consulta de downlineIds/supervisedIds/nationalIds.
            $crmScope = $sellerId !== null ? ['seller_id' => $sellerId] : ($sellerIds !== null ? ['seller_ids' => $sellerIds] : []);
            $data['pedidosPendentes'] = Order::countPendingPayment($crmScope);
            $data['orcamentosPendentes'] = Quote::countPendingPayment($crmScope);

            if ($role === 'admin') {
                $leads = Lead::all();
            } else {
                $leadIds = $sellerId !== null ? [$sellerId] : ($sellerIds ?? []);
                $includeUnassigned = !in_array($role, [Roles::SELLER, 'supervisor', 'gerente'], true);
                $leads = Lead::forScope($leadIds, $includeUnassigned);
            }
            $data['leadsNovos'] = count(array_filter($leads, fn ($l) => $l['status'] === 'novo'));

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
}
