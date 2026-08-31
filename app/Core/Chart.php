<?php

namespace App\Core;

class Chart
{
    /**
     * Gera um gráfico de linha em SVG (sem dependência JS): grade, eixo de valores,
     * área preenchida no período atual e linha tracejada no período anterior.
     * @param array<string,float> $current  data (Y-m-d) => valor, período atual
     * @param array<string,float> $previous data (Y-m-d) => valor, período anterior (mesma duração)
     */
    public static function dailyLine(array $current, array $previous, string $from, string $to, string $prevFrom): string
    {
        $width = 680;
        $height = 190;
        $padLeft = 58;
        $padRight = 16;
        $padTop = 14;
        $padBottom = 28;
        $usableWidth = $width - $padLeft - $padRight;
        $usableHeight = $height - $padTop - $padBottom;

        $days = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $days[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        $count = max(count($days), 1);
        $currentValues = array_values(array_map(fn ($d) => $current[$d] ?? 0.0, $days));

        $prevCursor = strtotime($prevFrom);
        $previousValues = [];
        for ($i = 0; $i < $count; $i++) {
            $d = date('Y-m-d', strtotime("+{$i} day", $prevCursor));
            $previousValues[] = $previous[$d] ?? 0.0;
        }

        $max = max(array_merge($currentValues, $previousValues, [0]));
        $niceMax = $max > 0 ? self::niceCeil($max) : 100.0;

        $xFor = fn (int $i) => $padLeft + ($count > 1 ? ($i / ($count - 1)) * $usableWidth : $usableWidth / 2);
        $yFor = fn (float $v) => $padTop + $usableHeight - ($niceMax > 0 ? ($v / $niceMax) * $usableHeight : 0);

        $pointsFor = function (array $values) use ($xFor, $yFor) {
            $points = [];
            foreach ($values as $i => $value) {
                $points[] = round($xFor($i), 1) . ',' . round($yFor($value), 1);
            }
            return implode(' ', $points);
        };

        $currentPoints = $pointsFor($currentValues);
        $previousPoints = $pointsFor($previousValues);

        // Grade horizontal + labels de valor (0, 1/3, 2/3, cheio)
        $gridSvg = '';
        $steps = 3;
        for ($s = 0; $s <= $steps; $s++) {
            $value = $niceMax * $s / $steps;
            $y = $yFor($value);
            $gridSvg .= sprintf(
                '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="chart-grid" />',
                $padLeft,
                $y,
                $width - $padRight,
                $y
            );
            $gridSvg .= sprintf(
                '<text x="%d" y="%.1f" class="chart-axis-label" text-anchor="end">%s</text>',
                $padLeft - 8,
                $y + 3,
                View::e(self::compactCurrency($value))
            );
        }

        // Labels de data no eixo X
        $labelStep = max(1, (int) ceil($count / 7));
        $xLabels = '';
        foreach ($days as $i => $d) {
            if ($i % $labelStep !== 0 && $i !== $count - 1) {
                continue;
            }
            $xLabels .= sprintf(
                '<text x="%.1f" y="%d" class="chart-axis-label" text-anchor="middle">%s</text>',
                $xFor($i),
                $height - 10,
                View::e(date('d/m', strtotime($d)))
            );
        }

        // Area preenchida sob a linha do periodo atual
        $areaPath = 'M' . $padLeft . ',' . round($yFor(0), 1) . ' L' . str_replace(' ', ' L', $currentPoints)
            . ' L' . round($xFor($count - 1), 1) . ',' . round($yFor(0), 1) . ' Z';

        $showDots = $count <= 31;
        $dots = '';
        if ($showDots) {
            foreach ($currentValues as $i => $value) {
                $dots .= sprintf(
                    '<circle cx="%.1f" cy="%.1f" r="2.6" class="chart-dot" />',
                    $xFor($i),
                    $yFor($value)
                );
            }
        }

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" role="img" aria-label="Valor de pedidos por dia">';
        $svg .= '<defs><linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0%" stop-color="#8dc63f" stop-opacity="0.28" />'
            . '<stop offset="100%" stop-color="#8dc63f" stop-opacity="0" />'
            . '</linearGradient></defs>';
        $svg .= '<style>
            .chart-grid{stroke:#eef0f5;stroke-width:1;}
            .chart-axis-label{font-size:10.5px;fill:#8a93ab;font-family:Inter,sans-serif;}
            .chart-dot{fill:#fff;stroke:#6ea62c;stroke-width:2;}
        </style>';
        $svg .= $gridSvg;
        $svg .= '<path d="' . $areaPath . '" fill="url(#chartFill)" stroke="none" />';
        if (array_filter($previousValues)) {
            $svg .= '<polyline points="' . $previousPoints . '" fill="none" stroke="#c9cfdc" stroke-width="1.75" stroke-dasharray="4 3" stroke-linejoin="round" stroke-linecap="round" />';
        }
        $svg .= '<polyline points="' . $currentPoints . '" fill="none" stroke="#6ea62c" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />';
        $svg .= $dots;
        $svg .= $xLabels;
        $svg .= '</svg>';

        return $svg;
    }

    private static function niceCeil(float $max): float
    {
        if ($max <= 0) {
            return 1.0;
        }
        $magnitude = 10 ** floor(log10($max));
        $normalized = $max / $magnitude;
        $niceNormalized = match (true) {
            $normalized <= 1 => 1,
            $normalized <= 2 => 2,
            $normalized <= 5 => 5,
            default => 10,
        };
        return $niceNormalized * $magnitude;
    }

    private static function compactCurrency(float $value): string
    {
        if ($value >= 1000) {
            return 'R$ ' . rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',') . 'k';
        }
        return 'R$ ' . number_format($value, 0, ',', '.');
    }
}
