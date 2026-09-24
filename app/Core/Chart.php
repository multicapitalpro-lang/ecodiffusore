<?php

namespace App\Core;

class Chart
{
    /**
     * Dados prontos pra Chart.js (ver dashboard.php + painel.js/bindDashboardChart) -- substitui o
     * antigo SVG feito a mao (linha reta, sem tooltip, ficava "achatado" esticando num card largo).
     * So preenche os buracos de dia-sem-venda com 0 e formata os rotulos; o desenho em si (curva
     * suave, tooltip, gradiente) fica todo do lado do JS.
     * @param array<string,float> $current  data (Y-m-d) => valor, período atual
     * @param array<string,float> $previous data (Y-m-d) => valor, período anterior (mesma duração)
     * @return array{labels: string[], current: float[], previous: float[]}
     */
    public static function dailySeriesData(array $current, array $previous, string $from, string $to, string $prevFrom): array
    {
        $days = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $days[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        $count = max(count($days), 1);
        $currentValues = array_values(array_map(fn ($d) => round((float) ($current[$d] ?? 0.0), 2), $days));

        $prevCursor = strtotime($prevFrom);
        $previousValues = [];
        for ($i = 0; $i < $count; $i++) {
            $d = date('Y-m-d', strtotime("+{$i} day", $prevCursor));
            $previousValues[] = round((float) ($previous[$d] ?? 0.0), 2);
        }

        return [
            'labels' => array_map(fn ($d) => date('d/m', strtotime($d)), $days),
            'current' => $currentValues,
            'previous' => $previousValues,
        ];
    }

    /**
     * Mesmo preenchimento de buracos de dailySeriesData(), so que pras 3 series de
     * Order::dailySeriesBySituation() (total/pendente/pago) num unico periodo -- grafico separado
     * do "atual vs anterior" ja existente, pra nao empilhar 6 linhas na mesma tela.
     * @param array{total: array<string,float>, pending: array<string,float>, paid: array<string,float>} $series
     * @return array{labels: string[], total: float[], pending: float[], paid: float[]}
     */
    public static function dailySituationData(array $series, string $from, string $to): array
    {
        $days = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $days[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        return [
            'labels' => array_map(fn ($d) => date('d/m', strtotime($d)), $days),
            'total' => array_values(array_map(fn ($d) => round((float) ($series['total'][$d] ?? 0.0), 2), $days)),
            'pending' => array_values(array_map(fn ($d) => round((float) ($series['pending'][$d] ?? 0.0), 2), $days)),
            'paid' => array_values(array_map(fn ($d) => round((float) ($series['paid'][$d] ?? 0.0), 2), $days)),
        ];
    }

    /**
     * Barras horizontais (sem JS) pra rankings curtos -- vendas por estado/cidade/licenciado/
     * vendedor. Diferente do dailyLine, a altura do viewBox cresce com a quantidade de itens e
     * o CSS deixa o wrapper com height:auto (ver .chart-bar-wrap) -- sem preserveAspectRatio="none"
     * aqui, entao o SVG nunca fica "achatado" esticando: escala mantendo a propria proporcao.
     * @param array<int, array{label: string, value: float}> $items ja ordenado (maior primeiro)
     */
    /** Fase 111: $valueFormatter opcional -- default continua compactCurrency() (R$), so'
     *  passado quando o chamador precisa formatar em outra moeda (ex: SimuladorController com
     *  users.secondary_currency configurado). */
    public static function bar(array $items, int $maxItems = 10, ?callable $valueFormatter = null): string
    {
        $valueFormatter ??= [self::class, 'compactCurrency'];
        $items = array_slice($items, 0, $maxItems);

        $width = 640;
        $barHeight = 26;
        $barGap = 12;
        $labelWidth = 152;
        $padRight = 78;
        $padTop = 4;
        $padBottom = 4;
        $barMaxWidth = $width - $labelWidth - $padRight;
        $count = count($items);
        $height = $count > 0
            ? $padTop + $padBottom + $count * $barHeight + max(0, $count - 1) * $barGap
            : 50;

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg chart-bar-svg" role="img" aria-label="Grafico de barras">';
        $svg .= '<style>
            .bar-label{font-size:12px;fill:#4a5170;font-family:Inter,sans-serif;}
            .bar-value{font-size:12px;fill:#4a5170;font-family:Inter,sans-serif;font-weight:600;}
        </style>';

        if (!$items) {
            $svg .= '<text x="' . ($width / 2) . '" y="28" text-anchor="middle" class="bar-label">Sem dados no período.</text></svg>';
            return $svg;
        }

        $max = max(array_map(fn ($i) => (float) $i['value'], $items));
        $max = $max > 0 ? $max : 1.0;

        foreach ($items as $i => $item) {
            $y = $padTop + $i * ($barHeight + $barGap);
            $barWidth = max(2, ((float) $item['value'] / $max) * $barMaxWidth);
            $label = mb_strlen($item['label']) > 22 ? mb_substr($item['label'], 0, 21) . '…' : $item['label'];

            $svg .= sprintf(
                '<text x="0" y="%.1f" class="bar-label" dominant-baseline="middle">%s</text>',
                $y + $barHeight / 2,
                View::e($label)
            );
            $svg .= sprintf(
                '<rect x="%d" y="%.1f" width="%.1f" height="%d" rx="4" fill="#6ea62c" />',
                $labelWidth,
                $y,
                $barWidth,
                $barHeight
            );
            $svg .= sprintf(
                '<text x="%.1f" y="%.1f" class="bar-value" dominant-baseline="middle">%s</text>',
                $labelWidth + $barWidth + 8,
                $y + $barHeight / 2,
                View::e($valueFormatter((float) $item['value']))
            );
        }

        $svg .= '</svg>';
        return $svg;
    }

    private static function compactCurrency(float $value): string
    {
        if ($value >= 1000) {
            return 'R$ ' . rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',') . 'k';
        }
        return 'R$ ' . number_format($value, 0, ',', '.');
    }
}
