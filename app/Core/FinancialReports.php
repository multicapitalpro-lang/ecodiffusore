<?php

namespace App\Core;

use App\Core\Database;

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

    public static function generate(string $type, string $from, string $to): array
    {
        return match ($type) {
            'balancete' => self::balancete($from, $to),
            'dre' => self::dre($from, $to),
            'fluxo_caixa' => self::fluxoCaixa($from, $to),
            'por_categoria' => self::porCategoria($from, $to),
            'por_cliente' => self::porCliente($from, $to),
            'pagamentos' => self::movimentos($from, $to, 'saida'),
            'recebimentos' => self::movimentos($from, $to, 'entrada'),
            'controle_caixa' => self::controleCaixa($from, $to),
            default => ['columns' => [], 'rows' => [], 'totals' => []],
        };
    }

    private static function balancete(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT type, COALESCE(SUM(amount), 0) AS total FROM financial_transactions
             WHERE status IN ('pago','conciliado') AND paid_date BETWEEN :from AND :to
             GROUP BY type"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $sums = ['entrada' => 0.0, 'saida' => 0.0];
        foreach ($stmt->fetchAll() as $row) {
            $sums[$row['type']] = (float) $row['total'];
        }

        return [
            'columns' => ['Indicador', 'Valor'],
            'rows' => [
                ['Entradas', self::money($sums['entrada'])],
                ['Saídas', self::money($sums['saida'])],
                ['Saldo do período', self::money($sums['entrada'] - $sums['saida'])],
            ],
            'totals' => [],
        ];
    }

    private static function dre(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fc.name AS categoria, COALESCE(fc.parent_id, fc.id) AS grupo_id,
                    COALESCE(p.name, fc.name) AS grupo, ft.type,
                    COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             LEFT JOIN financial_categories p ON p.id = fc.parent_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to
             GROUP BY grupo, ft.type"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $receitas = 0.0;
        $custos = 0.0;
        $despesas = 0.0;
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $label = $row['grupo'] ?: 'Sem categoria';
            $valor = (float) $row['total'];
            if ($row['type'] === 'entrada') {
                $receitas += $valor;
                $rows[] = ['Receita — ' . $label, self::money($valor)];
            } elseif (stripos($label, 'custo') !== false) {
                $custos += $valor;
                $rows[] = ['Custo — ' . $label, self::money($valor)];
            } else {
                $despesas += $valor;
                $rows[] = ['Despesa — ' . $label, self::money($valor)];
            }
        }

        $resultado = $receitas - $custos - $despesas;
        $rows[] = ['— Total Receitas', self::money($receitas)];
        $rows[] = ['— Total Custos', self::money($custos)];
        $rows[] = ['— Total Despesas', self::money($despesas)];
        $rows[] = ['Resultado do período', self::money($resultado)];

        return ['columns' => ['Linha', 'Valor'], 'rows' => $rows, 'totals' => []];
    }

    private static function fluxoCaixa(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT due_date, type, SUM(amount) AS total FROM financial_transactions
             WHERE due_date BETWEEN :from AND :to GROUP BY due_date, type ORDER BY due_date"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDate[$row['due_date']][$row['type']] = (float) $row['total'];
        }

        $saldo = 0.0;
        $rows = [];
        foreach ($byDate as $date => $values) {
            $entrada = $values['entrada'] ?? 0.0;
            $saida = $values['saida'] ?? 0.0;
            $saldo += $entrada - $saida;
            $rows[] = [date('d/m/Y', strtotime($date)), self::money($entrada), self::money($saida), self::money($saldo)];
        }

        return ['columns' => ['Data', 'Entradas', 'Saídas', 'Saldo acumulado'], 'rows' => $rows, 'totals' => []];
    }

    private static function porCategoria(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(fc.name, 'Sem categoria') AS categoria, ft.type, COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to
             GROUP BY categoria, ft.type ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $rows = array_map(
            fn ($r) => [$r['categoria'], $r['type'] === 'entrada' ? 'Entrada' : 'Saída', self::money((float) $r['total'])],
            $stmt->fetchAll()
        );

        return ['columns' => ['Categoria', 'Tipo', 'Valor'], 'rows' => $rows, 'totals' => []];
    }

    private static function porCliente(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(cl.name, 'Sem cliente/fornecedor') AS nome, ft.type, COALESCE(SUM(ft.amount), 0) AS total
             FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to
             GROUP BY nome, ft.type ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $rows = array_map(
            fn ($r) => [$r['nome'], $r['type'] === 'entrada' ? 'Entrada' : 'Saída', self::money((float) $r['total'])],
            $stmt->fetchAll()
        );

        return ['columns' => ['Cliente/Fornecedor', 'Tipo', 'Valor'], 'rows' => $rows, 'totals' => []];
    }

    private static function movimentos(string $from, string $to, string $type): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ft.*, cl.name AS client_name FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.type = :type AND ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to
             ORDER BY ft.paid_date"
        );
        $stmt->execute(['type' => $type, 'from' => $from, 'to' => $to]);

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
            'columns' => ['Data', $type === 'saida' ? 'Fornecedor' : 'Cliente', 'Descrição', 'Valor'],
            'rows' => $rows,
            'totals' => ['Total' => self::money($total)],
        ];
    }

    private static function controleCaixa(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ft.*, fa.name AS account_name FROM financial_transactions ft
             JOIN financial_accounts fa ON fa.id = ft.account_id
             WHERE ft.status IN ('pago','conciliado') AND ft.paid_date BETWEEN :from AND :to
             ORDER BY ft.paid_date, ft.id"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $saldo = 0.0;
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

        return ['columns' => ['Data', 'Conta', 'Histórico', 'Valor', 'Saldo'], 'rows' => $rows, 'totals' => []];
    }

    private static function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
