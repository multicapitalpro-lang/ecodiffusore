<?php

namespace App\Core;

class Chart
{
    /**
     * Gera um gráfico de linha simples em SVG (sem dependência JS).
     * @param array<string,float> $current  data (Y-m-d) => valor, período atual
     * @param array<string,float> $previous data (Y-m-d) => valor, período anterior (mesma duração)
     */
    public static function dailyLine(array $current, array $previous, string $from, string $to, string $prevFrom): string
    {
        $width = 680;
        $height = 220;
        $padding = 30;

        $days = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $days[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        $count = max(count($days), 1);
        $currentValues = array_map(fn ($d) => $current[$d] ?? 0.0, $days);

        $prevCursor = strtotime($prevFrom);
        $previousValues = [];
        for ($i = 0; $i < $count; $i++) {
            $d = date('Y-m-d', strtotime("+{$i} day", $prevCursor));
            $previousValues[] = $previous[$d] ?? 0.0;
        }

        $max = max(array_merge($currentValues, $previousValues, [1]));

        $pointsFor = function (array $values) use ($width, $height, $padding, $count, $max) {
            $points = [];
            $usableWidth = $width - 2 * $padding;
            $usableHeight = $height - 2 * $padding;
            foreach (array_values($values) as $i => $value) {
                $x = $padding + ($count > 1 ? ($i / ($count - 1)) * $usableWidth : $usableWidth / 2);
                $y = $height - $padding - ($max > 0 ? ($value / $max) * $usableHeight : 0);
                $points[] = round($x, 1) . ',' . round($y, 1);
            }
            return implode(' ', $points);
        };

        $currentPoints = $pointsFor($currentValues);
        $previousPoints = $previousValues ? $pointsFor($previousValues) : '';

        $labelStep = max(1, (int) ceil($count / 8));
        $labels = '';
        foreach ($days as $i => $d) {
            if ($i % $labelStep !== 0) {
                continue;
            }
            $x = $padding + ($count > 1 ? ($i / ($count - 1)) * ($width - 2 * $padding) : ($width - 2 * $padding) / 2);
            $labels .= sprintf(
                '<text x="%.1f" y="%d" class="chart-label" text-anchor="middle">%s</text>',
                $x,
                $height - 6,
                View::e(date('d/m', strtotime($d)))
            );
        }

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" role="img" aria-label="Valor de pedidos por dia">';
        $svg .= '<style>.chart-label{font-size:10px;fill:#8a93ab;font-family:Inter,sans-serif;}</style>';

        if ($previousPoints) {
            $svg .= '<polyline points="' . $previousPoints . '" fill="none" stroke="#c9cfdc" stroke-width="2" />';
        }
        $svg .= '<polyline points="' . $currentPoints . '" fill="none" stroke="#6ea62c" stroke-width="2.5" />';
        $svg .= $labels;
        $svg .= '</svg>';

        return $svg;
    }
}
