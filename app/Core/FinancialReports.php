<?php

namespace App\Core;

use App\Models\AsaasAnticipation;
use App\Models\Order;
use App\Models\WarrantyRequest;

class FinancialReports
{
    /** Fase 114: Admin/Gerente/Supervisor nao acompanham vendedor individual (isso e' tarefa do
     *  Licenciado, que dirige a propria equipe) -- pedido explicito do usuario pra esses 3 papeis
     *  verem "Vendas por Licença" em vez de "por Vendedor" (rotulo + agrupamento, ver
     *  vendasPorVendedor() abaixo). Licenciado/Gestor/Vendedor continuam vendo por Vendedor. O
     *  identificador interno (`vendas_por_vendedor`) NAO muda -- e' usado em report_schedules e
     *  na URL, so o texto exibido troca por papel. */
    private const LICENSE_VIEW_ROLES = ['admin', 'gerente', 'supervisor'];

    public static function catalog(?string $role = null): array
    {
        return [
            'Vendas e CRM' => [
                'vendas_por_vendedor' => in_array($role, self::LICENSE_VIEW_ROLES, true)
                    ? 'Relatório de Vendas por Licença'
                    : 'Relatório de Vendas por Vendedor',
                'garantias' => 'Relatório de Pós-venda de Instalação',
            ],
            'Caixas e Bancos' => [
                'balancete' => 'Balancete (entradas x saídas)',
                'dre' => 'DRE — Demonstrativo de Resultado',
                'fluxo_caixa' => 'Fluxo de Caixa (saldo acumulado)',
                'por_categoria' => 'Resumo de Entradas e Saídas por Categoria',
                'por_cliente' => 'Resumo de Entradas e Saídas por Cliente',
            ],
            'Contas a Pagar' => [
                'pagamentos' => 'Relatório de Pagamentos',
            ],
            'Contas a Receber' => [
                'recebimentos' => 'Relatório de Recebimentos',
            ],
            'Controle de Caixa' => [
                'controle_caixa' => 'Relatório de Controle de Caixa',
            ],
            'Comissões' => [
                'comissoes' => 'Relatório de Comissões',
            ],
            'Fiscal e Antecipações' => [
                'impostos' => 'Relatório de Impostos e Custo Real',
                'antecipacoes' => 'Relatório de Antecipações (Asaas)',
            ],
        ];
    }

    public static function title(string $type, ?string $role = null): string
    {
        foreach (self::catalog($role) as $group) {
            if (isset($group[$type])) {
                return $group[$type];
            }
        }
        return 'Relatório';
    }

    /** $sellerIds: escopo por rede (downline de Licenciado/Gestor) -- null = sem escopo (Admin, e
     *  os 2 tipos de "Fiscal e Antecipacoes" que ja sao restritos a admin/gerente nacional em
     *  ReportController, dado sensivel da operacao inteira, nao da regiao de um Licenciado). Cada
     *  metodo de relatorio que le financial_transactions aplica o mesmo filtro EXISTS por
     *  cliente/pedido ja usado em FinancialTransaction::all() -- ver scopeCondition() abaixo.
     *  O "saldo inicial" (soma de financial_accounts.initial_balance) continua global mesmo com
     *  escopo: as contas sao um caixa real compartilhado (uma conta bancaria/Asaas so pra empresa
     *  inteira), so as MOVIMENTACOES sao escopadas por rede -- mesma limitacao ja aceita em
     *  FinanceController::scopeFilters(). */
    /** Fase 117: $accountId filtra os relatorios de Caixas e Bancos/Controle de Caixa por UMA
     *  conta financeira (Caixa Principal/ASAAS/Banco Sicredi/etc) -- antes tudo vinha misturado.
     *  So os tipos que leem financial_transactions.account_id usam; Comissoes/Vendas/Fiscal/
     *  Antecipacoes ignoram (nao sao amarrados a uma conta financeira). */
    public static function generate(string $type, string $from, string $to, ?array $sellerIds = null, ?string $role = null, ?string $accountId = null): array
    {
        return match ($type) {
            'vendas_por_vendedor' => self::vendasPorVendedor($from, $to, $sellerIds, $role),
            'garantias' => self::garantiasReport($from, $to, $sellerIds),
            'balancete' => self::balancete($from, $to, $sellerIds, $accountId),
            'dre' => self::dre($from, $to, $sellerIds, $accountId),
            'fluxo_caixa' => self::fluxoCaixa($from, $to, $sellerIds, $accountId),
            'por_categoria' => self::porCategoria($from, $to, $sellerIds, $accountId),
            'por_cliente' => self::porCliente($from, $to, $sellerIds, $accountId),
            'pagamentos' => self::movimentos($from, $to, 'saida', $sellerIds, $accountId),
            'recebimentos' => self::movimentos($from, $to, 'entrada', $sellerIds, $accountId),
            'controle_caixa' => self::controleCaixa($from, $to, $sellerIds, $accountId),
            'comissoes' => self::comissoes($from, $to, $sellerIds),
            'impostos' => self::impostos($from, $to, $sellerIds),
            'antecipacoes' => self::antecipacoes($from, $to),
            default => ['kind' => 'simple', 'columns' => [], 'rows' => [], 'totals' => []],
        };
    }

    /** Clausula EXISTS que escopa financial_transactions por rede (mesmo padrao de
     *  FinancialTransaction::all() -- entra se o pedido OU o cliente vinculado pertence a alguem
     *  do escopo). $alias e' o alias da tabela financial_transactions na query (normalmente 'ft').
     *  Devolve string vazia (sem clausula) quando $sellerIds e' null -- caller so concatena. */
    private static function scopeCondition(string $alias, ?array $sellerIds, array &$params): string
    {
        if ($sellerIds === null) {
            return '';
        }

        $orderNames = [];
        $clientNames = [];
        foreach (array_values($sellerIds) as $i => $sid) {
            $orderKey = "rsid_o{$i}";
            $clientKey = "rsid_c{$i}";
            $orderNames[] = ":{$orderKey}";
            $clientNames[] = ":{$clientKey}";
            $params[$orderKey] = $sid;
            $params[$clientKey] = $sid;
        }
        $orderIn = implode(',', $orderNames);
        $clientIn = implode(',', $clientNames);

        return " AND (
            EXISTS (SELECT 1 FROM orders o2 WHERE o2.id = {$alias}.order_id AND o2.seller_id IN ({$orderIn}))
            OR EXISTS (SELECT 1 FROM clients c2 WHERE c2.id = {$alias}.client_id AND c2.seller_id IN ({$clientIn}))
        )";
    }

    /** Fase 117: mesma ideia de scopeCondition(), so que filtrando por UMA conta financeira
     *  (financial_accounts) em vez de rede -- devolve string vazia quando $accountId e' null/vazio,
     *  caller so concatena. */
    private static function accountCondition(string $alias, ?string $accountId, array &$params): string
    {
        if (empty($accountId)) {
            return '';
        }

        $params['account_id_filter'] = $accountId;
        return " AND {$alias}.account_id = :account_id_filter";
    }

    // ---- Vendas por Vendedor: mesmo motor de /painel/desempenho/vendedores (Order::sellerRanking),
    // reaproveitado aqui como relatorio exportavel -- gap real, nao existia nenhum relatorio de
    // vendas/CRM ate agora, so financeiro (Caixas/Contas/Comissoes/Fiscal). ----
    private static function vendasPorVendedor(string $from, string $to, ?array $sellerIds, ?string $role = null): array
    {
        $ranking = Order::sellerRanking($from, $to, $sellerIds);

        if (in_array($role, self::LICENSE_VIEW_ROLES, true)) {
            return self::vendasPorLicenciado($ranking);
        }

        $rows = array_map(fn ($r) => [
            $r['name'],
            (int) $r['order_count'],
            'R$ ' . number_format((float) $r['total_value'], 2, ',', '.'),
            'R$ ' . number_format((float) $r['avg_ticket'], 2, ',', '.'),
            $r['conversion_pct'] !== null ? $r['conversion_pct'] . '%' : '—',
        ], $ranking);

        $totalPedidos = array_sum(array_map(fn ($r) => (int) $r['order_count'], $ranking));

        return [
            'kind' => 'simple',
            'columns' => ['Vendedor', 'Pedidos', 'Valor Vendido', 'Ticket Médio', 'Conversão'],
            'rows' => $rows,
            'totals' => ['Total de pedidos' => (string) $totalPedidos],
        ];
    }

    /** Reagrupa o mesmo ranking (por vendedor) por Licenciado dono da rede -- soma pedidos/valor/
     *  leads de cada vendedor sob o mesmo licenciado_id (Order::sellerRanking() ja calcula isso
     *  por linha via User::responsibleFor()). Ticket medio e conversao sao recalculados a partir
     *  das somas agregadas, nao uma media das medias -- resultado matematicamente correto. */
    private static function vendasPorLicenciado(array $ranking): array
    {
        $grouped = [];
        foreach ($ranking as $r) {
            $key = $r['licenciado_id'] ?? 0;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $r['licenciado_name'] ?? 'Sem licenciado',
                    'order_count' => 0,
                    'total_value' => 0.0,
                    'lead_count' => 0,
                ];
            }
            $grouped[$key]['order_count'] += (int) $r['order_count'];
            $grouped[$key]['total_value'] += (float) $r['total_value'];
            $grouped[$key]['lead_count'] += (int) $r['lead_count'];
        }

        uasort($grouped, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        $rows = array_map(function ($g) {
            $avgTicket = $g['order_count'] > 0 ? $g['total_value'] / $g['order_count'] : 0.0;
            $conversionPct = $g['lead_count'] > 0 ? round($g['order_count'] / $g['lead_count'] * 100, 1) : null;

            return [
                $g['name'],
                $g['order_count'],
                'R$ ' . number_format($g['total_value'], 2, ',', '.'),
                'R$ ' . number_format($avgTicket, 2, ',', '.'),
                $conversionPct !== null ? $conversionPct . '%' : '—',
            ];
        }, array_values($grouped));

        $totalPedidos = array_sum(array_column($grouped, 'order_count'));

        return [
            'kind' => 'simple',
            'columns' => ['Licenciado', 'Pedidos', 'Valor Vendido', 'Ticket Médio', 'Conversão'],
            'rows' => $rows,
            'totals' => ['Total de pedidos' => (string) $totalPedidos],
        ];
    }

    // ---- Garantias: lista de solicitacoes no periodo (aberta/em_analise/aprovada/rejeitada/
    // concluida) -- outro gap real, ate agora so dava pra ver isso navegando /painel/garantias
    // tela por tela, sem relatorio exportavel/consolidado por periodo. ----
    private static function garantiasReport(string $from, string $to, ?array $sellerIds): array
    {
        $all = WarrantyRequest::forScope($sellerIds, null);
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs = strtotime($to . ' 23:59:59');
        $inPeriod = array_filter($all, function ($w) use ($fromTs, $toTs) {
            $createdTs = strtotime($w['created_at']);
            return $createdTs >= $fromTs && $createdTs <= $toTs;
        });

        $labels = ['aberta' => 'Aguardando análise', 'em_analise' => 'Em análise', 'aprovada' => 'Instalação confirmada', 'rejeitada' => 'Pendência a corrigir', 'concluida' => 'Concluída'];
        $rows = array_map(fn ($w) => [
            '#' . $w['order_id'],
            $w['client_name'],
            $w['seller_name'] ?? '—',
            $labels[$w['status']] ?? $w['status'],
            date('d/m/Y', strtotime($w['created_at'])),
        ], $inPeriod);

        $countByStatus = [];
        foreach ($inPeriod as $w) {
            $label = $labels[$w['status']] ?? $w['status'];
            $countByStatus[$label] = ($countByStatus[$label] ?? 0) + 1;
        }

        return [
            'kind' => 'simple',
            'columns' => ['Pedido', 'Cliente', 'Vendedor', 'Status', 'Enviada em'],
            'rows' => $rows,
            'totals' => array_map(fn ($v) => (string) $v, $countByStatus),
        ];
    }

    // ---- Balancete: linhas por categoria (Despesas/Receitas) em colunas semanais + Resultado ----
    private static function balancete(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $buckets = self::weeklyBuckets($from, $to);
        $saldoInicial = self::balanceBefore($from, $accountId);

        $despesasPorCategoria = [];
        $receitasPorCategoria = [];
        $totaisEntrada = array_fill(0, count($buckets), 0.0);
        $totaisSaida = array_fill(0, count($buckets), 0.0);

        foreach ($buckets as $i => $bucket) {
            $rows = self::sumByCategory($bucket['from'], $bucket['to'], $sellerIds, $accountId);
            foreach ($rows as $r) {
                $label = $r['categoria'];
                $target = $r['type'] === 'entrada' ? 'receitasPorCategoria' : 'despesasPorCategoria';
                if (!isset(${$target}[$label])) {
                    ${$target}[$label] = array_fill(0, count($buckets), 0.0);
                }
                ${$target}[$label][$i] = (float) $r['total'];
                if ($r['type'] === 'entrada') {
                    $totaisEntrada[$i] += (float) $r['total'];
                } else {
                    $totaisSaida[$i] += (float) $r['total'];
                }
            }
        }

        // Fase 119: Saldo inicial de cada BUCKET precisa ser o Saldo final do bucket ANTERIOR
        // (so o primeiro bucket usa o saldo do periodo inteiro) -- antes repetia o mesmo
        // $saldoInicial (saldo antes do periodo TODO) em toda coluna, entao a ultima semana
        // aparecia "começando" do mesmo valor da primeira mesmo depois de semanas com movimento.
        $resultadoPorBucket = [];
        foreach ($buckets as $i => $b) {
            $resultadoPorBucket[$i] = $totaisEntrada[$i] - $totaisSaida[$i];
        }
        $saldoInicialPorBucket = [];
        $saldoFinalPorBucket = [];
        $running = $saldoInicial;
        foreach ($buckets as $i => $b) {
            $saldoInicialPorBucket[$i] = $running;
            $running += $resultadoPorBucket[$i];
            $saldoFinalPorBucket[$i] = $running;
        }

        $rows = [];
        // Total override = saldoInicial (do PERIODO inteiro, nao soma dos saldos de cada semana).
        $rows[] = self::matrixRow('Saldo inicial', $saldoInicialPorBucket, true, false, $saldoInicial);

        if ($despesasPorCategoria) {
            $rows[] = self::matrixRow('Despesas', array_fill(0, count($buckets), null), true, true);
            foreach ($despesasPorCategoria as $label => $values) {
                $rows[] = self::matrixRow($label, $values);
            }
        }
        if ($receitasPorCategoria) {
            $rows[] = self::matrixRow('Receitas', array_fill(0, count($buckets), null), true, true);
            foreach ($receitasPorCategoria as $label => $values) {
                $rows[] = self::matrixRow($label, $values);
            }
        }

        $rows[] = self::matrixRow('Resultado', array_fill(0, count($buckets), null), true, true);
        $rows[] = self::matrixRow('Total de receitas', $totaisEntrada);
        $rows[] = self::matrixRow('Total de despesas', $totaisSaida);
        $rows[] = self::matrixRow('Receitas - Despesas', $resultadoPorBucket);

        // Total override = saldo final do ULTIMO bucket (o saldo final do periodo inteiro), nao
        // soma dos saldos finais de cada semana.
        $rows[] = self::matrixRow('Saldo final', $saldoFinalPorBucket, true, false, end($saldoFinalPorBucket));

        return [
            'kind' => 'matrix',
            'periods' => array_column($buckets, 'label'),
            'rows' => $rows,
        ];
    }

    // ---- DRE: estrutura contabil padrao, colunas = meses do ano de $from ----
    private static function dre(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $year = (int) date('Y', strtotime($from));
        $months = self::monthlyBucketsForYear($year);

        $byMonthCategory = [];
        foreach ($months as $i => $m) {
            $byMonthCategory[$i] = self::sumByCategory($m['from'], $m['to'], $sellerIds, $accountId);
        }

        $lines = [
            'receita_bruta' => array_fill(0, 12, 0.0),
            'deducoes' => array_fill(0, 12, 0.0),
            'custos' => array_fill(0, 12, 0.0),
            'despesas_operacionais' => array_fill(0, 12, 0.0),
            'receita_financeira' => array_fill(0, 12, 0.0),
            'despesa_financeira' => array_fill(0, 12, 0.0),
            'outras_receitas' => array_fill(0, 12, 0.0),
            'outras_despesas' => array_fill(0, 12, 0.0),
            'ir_csll' => array_fill(0, 12, 0.0),
        ];

        $deducaoCategorias = ['Devoluções de vendas', 'Descontos incondicionais'];
        $financeiraCategorias = ['Taxas bancárias', 'Taxas de cartão / gateway'];

        foreach ($byMonthCategory as $i => $rows) {
            foreach ($rows as $r) {
                $cat = $r['categoria'];
                $grupo = $r['grupo'];
                $valor = (float) $r['total'];

                if ($r['type'] === 'entrada') {
                    if ($grupo === 'Vendas' || $cat === 'Sem categoria') {
                        $lines['receita_bruta'][$i] += $valor;
                    } elseif ($grupo === 'Financeiro') {
                        $lines['receita_financeira'][$i] += $valor;
                    } elseif ($grupo === 'Outras Receitas') {
                        $lines['outras_receitas'][$i] += $valor;
                    } else {
                        $lines['receita_bruta'][$i] += $valor;
                    }
                } else {
                    if (in_array($cat, $deducaoCategorias, true)) {
                        $lines['deducoes'][$i] += $valor;
                    } elseif (in_array($cat, $financeiraCategorias, true)) {
                        $lines['despesa_financeira'][$i] += $valor;
                    } elseif ($grupo === 'Custos') {
                        $lines['custos'][$i] += $valor;
                    } elseif (in_array($grupo, ['Despesas Administrativas', 'Despesas com Pessoal', 'Despesas Comerciais'], true)) {
                        $lines['despesas_operacionais'][$i] += $valor;
                    } elseif ($grupo === 'Impostos e Taxas') {
                        $lines['ir_csll'][$i] += $valor;
                    } elseif ($grupo === 'Outras Despesas') {
                        $lines['outras_despesas'][$i] += $valor;
                    } else {
                        $lines['despesas_operacionais'][$i] += $valor;
                    }
                }
            }
        }

        $receitaLiquida = self::subtractSeries($lines['receita_bruta'], $lines['deducoes']);
        $resultadoBruto = self::subtractSeries($receitaLiquida, $lines['custos']);
        $resultadoAntesFinanceiro = self::subtractSeries($resultadoBruto, $lines['despesas_operacionais']);
        $comFinanceira = self::addSeries($resultadoAntesFinanceiro, $lines['receita_financeira']);
        $comFinanceira = self::subtractSeries($comFinanceira, $lines['despesa_financeira']);
        $comOutras = self::addSeries($comFinanceira, $lines['outras_receitas']);
        $resultadoAntesIr = self::subtractSeries($comOutras, $lines['outras_despesas']);
        $resultadoLiquido = self::subtractSeries($resultadoAntesIr, $lines['ir_csll']);

        $rows = [
            self::matrixRow('(+) Receita Operacional Bruta', $lines['receita_bruta']),
            self::matrixRow('(-) Deduções da Receita Bruta', $lines['deducoes']),
            self::matrixRow('(=) Receita Operacional Líquida', $receitaLiquida, true),
            self::matrixRow('(-) Custos das Mercadorias', $lines['custos']),
            self::matrixRow('(=) Resultado Operacional Bruto', $resultadoBruto, true),
            self::matrixRow('(-) Despesas Operacionais', $lines['despesas_operacionais']),
            self::matrixRow('(+) Receita Financeira', $lines['receita_financeira']),
            self::matrixRow('(-) Despesa Financeira', $lines['despesa_financeira']),
            self::matrixRow('(+) Outras Receitas', $lines['outras_receitas']),
            self::matrixRow('(-) Outras Despesas', $lines['outras_despesas']),
            self::matrixRow('(=) Resultado antes do IR e CSLL', $resultadoAntesIr, true),
            self::matrixRow('(-) IR e CSLL', $lines['ir_csll']),
            self::matrixRow('(=) Resultado Líquido do Exercício', $resultadoLiquido, true),
        ];

        return [
            'kind' => 'matrix',
            'periods' => array_column($months, 'label'),
            'rows' => $rows,
            'yearLabel' => 'Ano ' . $year,
        ];
    }

    private static function fluxoCaixa(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $buckets = self::weeklyBuckets($from, $to);
        $saldoInicial = self::balanceBefore($from, $accountId);

        $entradas = [];
        $saidas = [];
        foreach ($buckets as $i => $b) {
            $params = ['from' => $b['from'], 'to' => $b['to']];
            $scopeSql = self::scopeCondition('financial_transactions', $sellerIds, $params);
            $scopeSql .= self::accountCondition('financial_transactions', $accountId, $params);
            $stmt = Database::connection()->prepare(
                "SELECT type, COALESCE(SUM(amount), 0) AS total FROM financial_transactions
                 WHERE status IN ('pago','conciliado') AND is_transfer = 0
                    AND paid_date BETWEEN :from AND :to {$scopeSql} GROUP BY type"
            );
            $stmt->execute($params);
            $entradas[$i] = 0.0;
            $saidas[$i] = 0.0;
            foreach ($stmt->fetchAll() as $r) {
                if ($r['type'] === 'entrada') {
                    $entradas[$i] = (float) $r['total'];
                } else {
                    $saidas[$i] = (float) $r['total'];
                }
            }
        }

        // Fase 119: mesmo fix do balancete() -- Saldo inicial de cada bucket e' o Saldo acumulado
        // do bucket ANTERIOR, nao o mesmo saldo do periodo inteiro repetido em toda coluna.
        $saldoInicialPorBucket = [];
        $saldoAcumulado = [];
        $running = $saldoInicial;
        foreach ($buckets as $i => $b) {
            $saldoInicialPorBucket[$i] = $running;
            $running += $entradas[$i] - $saidas[$i];
            $saldoAcumulado[$i] = $running;
        }

        $rows = [
            self::matrixRow('Saldo inicial', $saldoInicialPorBucket, true, false, $saldoInicial),
            self::matrixRow('Entradas', $entradas),
            self::matrixRow('Saídas', $saidas),
            self::matrixRow('Saldo acumulado', $saldoAcumulado, true, false, end($saldoAcumulado)),
        ];

        return ['kind' => 'matrix', 'periods' => array_column($buckets, 'label'), 'rows' => $rows];
    }

    private static function porCategoria(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $rows = self::sumByCategory($from, $to, $sellerIds, $accountId);

        $entradas = array_values(array_filter($rows, fn ($r) => $r['type'] === 'entrada'));
        $saidas = array_values(array_filter($rows, fn ($r) => $r['type'] === 'saida'));

        usort($entradas, fn ($a, $b) => $b['total'] <=> $a['total']);
        usort($saidas, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'kind' => 'split',
            'left' => [
                'title' => 'Entradas',
                'rows' => array_map(fn ($r) => [$r['categoria'], self::money((float) $r['total'])], $entradas),
                'total' => self::money(array_sum(array_column($entradas, 'total'))),
            ],
            'right' => [
                'title' => 'Saídas',
                'rows' => array_map(fn ($r) => [$r['categoria'], self::money((float) $r['total'])], $saidas),
                'total' => self::money(array_sum(array_column($saidas, 'total'))),
            ],
        ];
    }

    private static function porCliente(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $scopeSql .= self::accountCondition('ft', $accountId, $params);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(cl.name, 'Sem cliente/fornecedor') AS nome, ft.type, COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.status IN ('pago','conciliado') AND ft.is_transfer = 0
                AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             GROUP BY nome, ft.type ORDER BY total DESC"
        );
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $rows = array_map(
            fn ($r) => [$r['nome'], $r['type'] === 'entrada' ? 'Entrada' : 'Saída', self::money((float) $r['total'])],
            $data
        );

        $totalEntradas = array_sum(array_map(fn ($r) => $r['type'] === 'entrada' ? (float) $r['total'] : 0, $data));
        $totalSaidas = array_sum(array_map(fn ($r) => $r['type'] === 'saida' ? (float) $r['total'] : 0, $data));

        return [
            'kind' => 'simple',
            'columns' => ['Cliente/Fornecedor', 'Tipo', 'Valor'],
            'rows' => $rows,
            'totals' => ['Total de entradas' => self::money($totalEntradas), 'Total de saídas' => self::money($totalSaidas)],
        ];
    }

    private static function movimentos(string $from, string $to, string $type, ?array $sellerIds, ?string $accountId = null): array
    {
        $params = ['type' => $type, 'from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $scopeSql .= self::accountCondition('ft', $accountId, $params);
        $stmt = Database::connection()->prepare(
            "SELECT ft.*, cl.name AS client_name FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.type = :type AND ft.status IN ('pago','conciliado') AND ft.is_transfer = 0
                AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             ORDER BY ft.paid_date"
        );
        $stmt->execute($params);

        $total = 0.0;
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $total += (float) $r['amount'];
            $rows[] = [
                date('d/m/Y', strtotime($r['paid_date'])),
                $r['client_name'] ?: '—',
                $r['description'] ?: '—',
                self::money((float) $r['amount']),
            ];
        }

        return [
            'kind' => 'simple',
            'columns' => ['Data', $type === 'saida' ? 'Fornecedor' : 'Cliente', 'Descrição', 'Valor'],
            'rows' => $rows,
            'totals' => ['Total' => self::money($total)],
        ];
    }

    private static function controleCaixa(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $scopeSql .= self::accountCondition('ft', $accountId, $params);
        $stmt = Database::connection()->prepare(
            "SELECT ft.*, fa.name AS account_name FROM financial_transactions ft
             JOIN financial_accounts fa ON fa.id = ft.account_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             ORDER BY ft.paid_date, ft.id"
        );
        $stmt->execute($params);

        // Fase 117: saldo inicial por conta filtrada -- quando accountId esta vazio, continua
        // somando TODAS as contas (mesmo comportamento global de sempre).
        $saldoInicial = self::balanceBefore($from, $accountId);
        $saldo = $saldoInicial;
        $totalEntradas = 0.0;
        $totalSaidas = 0.0;
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            if ($r['type'] === 'entrada') {
                $saldo += (float) $r['amount'];
                $totalEntradas += (float) $r['amount'];
            } else {
                $saldo -= (float) $r['amount'];
                $totalSaidas += (float) $r['amount'];
            }
            $rows[] = [
                date('d/m/Y', strtotime($r['paid_date'])),
                $r['account_name'],
                $r['description'] ?: '—',
                ($r['type'] === 'entrada' ? '+ ' : '- ') . self::money((float) $r['amount']),
                self::money($saldo),
            ];
        }

        return [
            'kind' => 'simple',
            'columns' => ['Data', 'Conta', 'Histórico', 'Valor', 'Saldo'],
            'rows' => $rows,
            'totals' => [
                'Saldo inicial' => self::money($saldoInicial),
                'Total de entradas' => self::money($totalEntradas),
                'Total de saídas' => self::money($totalSaidas),
                'Saldo final' => self::money($saldo),
            ],
        ];
    }

    private static function comissoes(string $from, string $to, ?array $sellerIds): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = '';
        if ($sellerIds !== null) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "csid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            // Escopo pelo BENEFICIARIO da comissao (nao pelo vendedor do pedido) -- um Gestor
            // precisa ver a comissao do Licenciado dele nesse relatorio, mesmo que o pedido em si
            // tenha sido vendido por um Vendedor de outro braço da mesma rede.
            $scopeSql = ' AND c.beneficiary_id IN (' . implode(',', $names) . ')';
        }
        $stmt = Database::connection()->prepare(
            "SELECT c.*, b.name AS beneficiary_name, o.order_date
             FROM commissions c
             JOIN users b ON b.id = c.beneficiary_id
             JOIN orders o ON o.id = c.order_id
             WHERE o.order_date BETWEEN :from AND :to {$scopeSql}
             ORDER BY o.order_date, c.beneficiary_id"
        );
        $stmt->execute($params);

        $roleLabels = ['licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor'];
        $total = 0.0;
        $totalPago = 0.0;
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $total += (float) $r['amount'];
            if ($r['status'] === 'pago') {
                $totalPago += (float) $r['amount'];
            }
            $rows[] = [
                date('d/m/Y', strtotime($r['order_date'])),
                $r['beneficiary_name'],
                $roleLabels[$r['role_slug']] ?? $r['role_slug'],
                '#' . $r['order_id'],
                self::money((float) $r['amount']),
                $r['status'] === 'pago' ? 'Pago' : 'Pendente',
            ];
        }

        return [
            'kind' => 'simple',
            'columns' => ['Data', 'Beneficiário', 'Papel', 'Pedido', 'Valor', 'Situação'],
            'rows' => $rows,
            'totals' => [
                'Total gerado' => self::money($total),
                'Total pago' => self::money($totalPago),
                'Total pendente' => self::money($total - $totalPago),
            ],
        ];
    }

    /** Imposto e custo real por pedido verificado no periodo (Fase 25) -- mesma tabela de precos
     *  por quantidade que ja define a comissao do Licenciado (ver App\Core\TaxReport). */
    private static function impostos(string $from, string $to, ?array $sellerIds): array
    {
        $filters = ['status' => 'verificado', 'from' => $from, 'to' => $to];
        if ($sellerIds !== null) {
            $filters['seller_ids'] = $sellerIds;
        }
        $orders = Order::all($filters);
        $report = TaxReport::forOrders($orders);

        $rows = array_map(fn ($r) => [
            '#' . $r['id'],
            date('d/m/Y', strtotime($r['order_date'])),
            $r['client_name'],
            (int) $r['total_qty'],
            self::money((float) $r['total_value']),
            self::money($r['tax']),
            self::money($r['cost']),
            self::money($r['net']),
        ], $report['rows']);

        return [
            'kind' => 'simple',
            'columns' => ['Pedido', 'Data', 'Cliente', 'Qtd.', 'Faturamento', 'Imposto', 'Custo', 'Margem líquida'],
            'rows' => $rows,
            'totals' => [
                'Faturamento' => self::money($report['totals']['revenue']),
                'Imposto' => self::money($report['totals']['tax']),
                'Custo' => self::money($report['totals']['cost']),
                'Margem líquida' => self::money($report['totals']['net']),
            ],
        ];
    }

    /** Antecipacoes feitas na Asaas no periodo (espelho local, ver App\Models\AsaasAnticipation)
     *  -- so pedidas via /painel/financeiro/antecipacoes ("Atualizar do Asaas"), nao busca ao vivo. */
    private static function antecipacoes(string $from, string $to): array
    {
        $filters = ['from' => $from, 'to' => $to];
        $rows = AsaasAnticipation::all($filters);
        $totals = AsaasAnticipation::totals($filters);

        $tableRows = array_map(fn ($r) => [
            $r['request_date'] ? date('d/m/Y', strtotime($r['request_date'])) : '—',
            $r['status'],
            self::money((float) $r['value']),
            self::money((float) $r['fee']),
            self::money((float) $r['net_value']),
        ], $rows);

        return [
            'kind' => 'simple',
            'columns' => ['Solicitada em', 'Situação', 'Valor', 'Taxa', 'Líquido'],
            'rows' => $tableRows,
            'totals' => [
                'Valor bruto (efetivadas)' => self::money($totals['value_effective']),
                'Taxa paga (efetivadas)' => self::money($totals['fee_effective']),
                'Líquido recebido (efetivadas)' => self::money($totals['net_value_effective']),
            ],
        ];
    }

    // ---- Helpers ----

    private static function sumByCategory(string $from, string $to, ?array $sellerIds, ?string $accountId = null): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $scopeSql .= self::accountCondition('ft', $accountId, $params);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(fc.name, 'Sem categoria') AS categoria,
                    COALESCE(p.name, fc.name, 'Sem categoria') AS grupo,
                    ft.type, COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             LEFT JOIN financial_categories p ON p.id = fc.parent_id
             WHERE ft.status IN ('pago','conciliado') AND ft.is_transfer = 0
                AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             GROUP BY categoria, grupo, ft.type
             HAVING total != 0"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Fase 117: $accountId restringe tanto o saldo inicial (financial_accounts.initial_balance)
     *  quanto as movimentacoes anteriores a $date pra UMA conta -- sem isso, filtrar um relatorio
     *  por conta ainda misturaria o saldo inicial de todas as contas juntas. */
    private static function balanceBefore(string $date, ?string $accountId = null): float
    {
        $initialSql = "SELECT COALESCE(SUM(fa.initial_balance), 0) FROM financial_accounts fa WHERE 1=1";
        $initialParams = [];
        if (!empty($accountId)) {
            $initialSql .= ' AND fa.id = :account_id';
            $initialParams['account_id'] = $accountId;
        }
        $stmt = Database::connection()->prepare($initialSql);
        $stmt->execute($initialParams);
        $initial = (float) $stmt->fetchColumn();

        $txSql = "SELECT COALESCE(SUM(CASE WHEN type = 'entrada' THEN amount ELSE -amount END), 0)
             FROM financial_transactions WHERE status IN ('pago','conciliado') AND paid_date < :date";
        $txParams = ['date' => $date];
        if (!empty($accountId)) {
            $txSql .= ' AND account_id = :account_id';
            $txParams['account_id'] = $accountId;
        }
        $stmt = Database::connection()->prepare($txSql);
        $stmt->execute($txParams);

        return $initial + (float) $stmt->fetchColumn();
    }

    private static function weeklyBuckets(string $from, string $to): array
    {
        $buckets = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        if ($cursor > $end) {
            return [['from' => $from, 'to' => $to, 'label' => date('d/m', $cursor)]];
        }

        while ($cursor <= $end) {
            $bucketEndTs = min(strtotime('+6 days', $cursor), $end);
            $buckets[] = [
                'from' => date('Y-m-d', $cursor),
                'to' => date('Y-m-d', $bucketEndTs),
                'label' => date('d/m', $cursor) . ' a ' . date('d/m', $bucketEndTs),
            ];
            $cursor = strtotime('+7 days', $cursor);
        }

        return $buckets;
    }

    private static function monthlyBucketsForYear(int $year): array
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $buckets = [];
        foreach ($months as $i => $label) {
            $m = $i + 1;
            $monthFrom = sprintf('%04d-%02d-01', $year, $m);
            $buckets[] = ['from' => $monthFrom, 'to' => date('Y-m-t', strtotime($monthFrom)), 'label' => $label];
        }
        return $buckets;
    }

    /** Fase 119: $totalOverride existe pra linhas de SALDO (estoque/ponto-no-tempo -- Saldo
     *  inicial/final/acumulado), onde somar os valores de cada periodo NAO FAZ SENTIDO (a coluna
     *  Total virava a soma de saldos repetidos/crescentes, um numero sem significado contabil --
     *  bug real reportado pelo usuario). Linhas de FLUXO (receitas/despesas por periodo) continuam
     *  usando a soma automatica, que e' o comportamento correto pra elas. */
    private static function matrixRow(string $label, array $values, bool $bold = false, bool $isHeader = false, ?float $totalOverride = null): array
    {
        $total = $isHeader ? null : ($totalOverride ?? array_sum(array_map(fn ($v) => $v ?? 0, $values)));
        return ['label' => $label, 'values' => $values, 'total' => $total, 'bold' => $bold, 'header' => $isHeader];
    }

    private static function addSeries(array $a, array $b): array
    {
        return array_map(fn ($x, $y) => $x + $y, $a, $b);
    }

    private static function subtractSeries(array $a, array $b): array
    {
        return array_map(fn ($x, $y) => $x - $y, $a, $b);
    }

    private static function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
