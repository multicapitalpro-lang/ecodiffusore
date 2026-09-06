<?php

namespace App\Core;

use App\Models\AsaasAnticipation;
use App\Models\Order;

class FinancialReports
{
    public static function catalog(): array
    {
        return [
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

    public static function title(string $type): string
    {
        foreach (self::catalog() as $group) {
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
    public static function generate(string $type, string $from, string $to, ?array $sellerIds = null): array
    {
        return match ($type) {
            'balancete' => self::balancete($from, $to, $sellerIds),
            'dre' => self::dre($from, $to, $sellerIds),
            'fluxo_caixa' => self::fluxoCaixa($from, $to, $sellerIds),
            'por_categoria' => self::porCategoria($from, $to, $sellerIds),
            'por_cliente' => self::porCliente($from, $to, $sellerIds),
            'pagamentos' => self::movimentos($from, $to, 'saida', $sellerIds),
            'recebimentos' => self::movimentos($from, $to, 'entrada', $sellerIds),
            'controle_caixa' => self::controleCaixa($from, $to, $sellerIds),
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

    // ---- Balancete: linhas por categoria (Despesas/Receitas) em colunas semanais + Resultado ----
    private static function balancete(string $from, string $to, ?array $sellerIds): array
    {
        $buckets = self::weeklyBuckets($from, $to);
        $saldoInicial = self::balanceBefore($from);

        $despesasPorCategoria = [];
        $receitasPorCategoria = [];
        $totaisEntrada = array_fill(0, count($buckets), 0.0);
        $totaisSaida = array_fill(0, count($buckets), 0.0);

        foreach ($buckets as $i => $bucket) {
            $rows = self::sumByCategory($bucket['from'], $bucket['to'], $sellerIds);
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

        $rows = [];
        $rows[] = self::matrixRow('Saldo inicial', array_fill(0, count($buckets), $saldoInicial), true);

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

        $resultadoPorBucket = [];
        $saldoAcumulado = $saldoInicial;
        foreach ($buckets as $i => $b) {
            $resultadoPorBucket[$i] = $totaisEntrada[$i] - $totaisSaida[$i];
            $saldoAcumulado += $resultadoPorBucket[$i];
        }

        $rows[] = self::matrixRow('Resultado', array_fill(0, count($buckets), null), true, true);
        $rows[] = self::matrixRow('Total de receitas', $totaisEntrada);
        $rows[] = self::matrixRow('Total de despesas', $totaisSaida);
        $rows[] = self::matrixRow('Receitas - Despesas', $resultadoPorBucket);

        $saldoFinalPorBucket = [];
        $running = $saldoInicial;
        foreach ($buckets as $i => $b) {
            $running += $resultadoPorBucket[$i];
            $saldoFinalPorBucket[$i] = $running;
        }
        $rows[] = self::matrixRow('Saldo final', $saldoFinalPorBucket, true);

        return [
            'kind' => 'matrix',
            'periods' => array_column($buckets, 'label'),
            'rows' => $rows,
        ];
    }

    // ---- DRE: estrutura contabil padrao, colunas = meses do ano de $from ----
    private static function dre(string $from, string $to, ?array $sellerIds): array
    {
        $year = (int) date('Y', strtotime($from));
        $months = self::monthlyBucketsForYear($year);

        $byMonthCategory = [];
        foreach ($months as $i => $m) {
            $byMonthCategory[$i] = self::sumByCategory($m['from'], $m['to'], $sellerIds);
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

    private static function fluxoCaixa(string $from, string $to, ?array $sellerIds): array
    {
        $buckets = self::weeklyBuckets($from, $to);
        $saldoInicial = self::balanceBefore($from);

        $entradas = [];
        $saidas = [];
        foreach ($buckets as $i => $b) {
            $params = ['from' => $b['from'], 'to' => $b['to']];
            $scopeSql = self::scopeCondition('financial_transactions', $sellerIds, $params);
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

        $saldoAcumulado = [];
        $running = $saldoInicial;
        foreach ($buckets as $i => $b) {
            $running += $entradas[$i] - $saidas[$i];
            $saldoAcumulado[$i] = $running;
        }

        $rows = [
            self::matrixRow('Saldo inicial', array_fill(0, count($buckets), $saldoInicial), true),
            self::matrixRow('Entradas', $entradas),
            self::matrixRow('Saídas', $saidas),
            self::matrixRow('Saldo acumulado', $saldoAcumulado, true),
        ];

        return ['kind' => 'matrix', 'periods' => array_column($buckets, 'label'), 'rows' => $rows];
    }

    private static function porCategoria(string $from, string $to, ?array $sellerIds): array
    {
        $rows = self::sumByCategory($from, $to, $sellerIds);

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

    private static function porCliente(string $from, string $to, ?array $sellerIds): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(cl.name, 'Sem cliente/fornecedor') AS nome, ft.type, COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.status IN ('pago','conciliado') AND ft.is_transfer = 0
                AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             GROUP BY nome, ft.type ORDER BY total DESC"
        );
        $stmt->execute($params);

        $rows = array_map(
            fn ($r) => [$r['nome'], $r['type'] === 'entrada' ? 'Entrada' : 'Saída', self::money((float) $r['total'])],
            $stmt->fetchAll()
        );

        return ['kind' => 'simple', 'columns' => ['Cliente/Fornecedor', 'Tipo', 'Valor'], 'rows' => $rows, 'totals' => []];
    }

    private static function movimentos(string $from, string $to, string $type, ?array $sellerIds): array
    {
        $params = ['type' => $type, 'from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
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

    private static function controleCaixa(string $from, string $to, ?array $sellerIds): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
        $stmt = Database::connection()->prepare(
            "SELECT ft.*, fa.name AS account_name FROM financial_transactions ft
             JOIN financial_accounts fa ON fa.id = ft.account_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to {$scopeSql}
             ORDER BY ft.paid_date, ft.id"
        );
        $stmt->execute($params);

        $saldo = self::balanceBefore($from);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $saldo += $r['type'] === 'entrada' ? (float) $r['amount'] : -(float) $r['amount'];
            $rows[] = [
                date('d/m/Y', strtotime($r['paid_date'])),
                $r['account_name'],
                $r['description'] ?: '—',
                ($r['type'] === 'entrada' ? '+ ' : '- ') . self::money((float) $r['amount']),
                self::money($saldo),
            ];
        }

        return ['kind' => 'simple', 'columns' => ['Data', 'Conta', 'Histórico', 'Valor', 'Saldo'], 'rows' => $rows, 'totals' => []];
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

    private static function sumByCategory(string $from, string $to, ?array $sellerIds): array
    {
        $params = ['from' => $from, 'to' => $to];
        $scopeSql = self::scopeCondition('ft', $sellerIds, $params);
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

    private static function balanceBefore(string $date): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(fa.initial_balance), 0) FROM financial_accounts fa"
        );
        $stmt->execute();
        $initial = (float) $stmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'entrada' THEN amount ELSE -amount END), 0)
             FROM financial_transactions WHERE status IN ('pago','conciliado') AND paid_date < :date"
        );
        $stmt->execute(['date' => $date]);

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

    private static function matrixRow(string $label, array $values, bool $bold = false, bool $isHeader = false): array
    {
        $total = $isHeader ? null : array_sum(array_map(fn ($v) => $v ?? 0, $values));
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
