<?php

namespace App\Core;

use App\Models\User;

/**
 * Acha o Licenciado ativo mais proximo da cidade informada pelo cliente, dentro de um raio maximo.
 * Usa a tabela de referencia br_cities (importada 1x, ver database/import_br_cities.php) e a formula
 * de Haversine -- sem depender de nenhuma API externa de geocodificacao.
 *
 * Limitacao conhecida e aceita: o cliente so informa a cidade (sem estado) no popup de /comprar: se
 * houver mais de um municipio brasileiro com o mesmo nome em UFs diferentes, usa o primeiro que
 * encontrar em br_cities -- aproximacao, nao e' garantia de precisao perfeita.
 */
class GeoMatch
{
    private const RADIUS_KM = 100;
    private const EARTH_RADIUS_KM = 6371;

    /** @return array{id: int, name: string, whatsapp: string, distance_km: float}|null */
    public static function nearestSeller(string $customerCity): ?array
    {
        $customerCoords = self::findCityCoords($customerCity);
        if (!$customerCoords) {
            return null;
        }

        $nearest = null;
        $nearestDistance = null;

        foreach (User::allByRole('licenciado') as $licenciado) {
            if (empty($licenciado['city']) || empty($licenciado['whatsapp'])) {
                continue;
            }

            $sellerCoords = self::findCityCoords($licenciado['city']);
            if (!$sellerCoords) {
                continue;
            }

            $distance = self::haversine(
                (float) $customerCoords['lat'],
                (float) $customerCoords['lng'],
                (float) $sellerCoords['lat'],
                (float) $sellerCoords['lng']
            );

            if ($distance <= self::RADIUS_KM && ($nearestDistance === null || $distance < $nearestDistance)) {
                $nearestDistance = $distance;
                $nearest = [
                    'id' => (int) $licenciado['id'],
                    'name' => $licenciado['name'],
                    'whatsapp' => $licenciado['whatsapp'],
                    'distance_km' => round($distance, 1),
                ];
            }
        }

        return $nearest;
    }

    /** @return array{lat: float, lng: float}|null */
    private static function findCityCoords(string $cityName): ?array
    {
        $normalized = self::normalize($cityName);

        $stmt = Database::connection()->prepare(
            'SELECT lat, lng FROM br_cities WHERE name_normalized = :name LIMIT 1'
        );
        $stmt->execute(['name' => $normalized]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private static function normalize(string $s): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        return mb_strtolower(trim($transliterated !== false ? $transliterated : $s));
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
