<?php

namespace App\Core;

class DateRange
{
    /** @return array{0:string,1:string} [from, to] no formato Y-m-d */
    public static function fromRequest(): array
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');

        if (!self::isValidDate($from) || !self::isValidDate($to) || $from > $to) {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
        }

        return [$from, $to];
    }

    /** @return array{0:string,1:string} período anterior, mesma duração em dias */
    public static function previousPeriod(string $from, string $to): array
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        $days = (int) round(($toTs - $fromTs) / 86400) + 1;

        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        return [$prevFrom, $prevTo];
    }

    public static function percentChange(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private static function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
