<?php

namespace App\Core;

use App\Models\Lead;
use App\Models\User;

/**
 * Acha o Vendedor ativo pra atender um cliente que chegou pelo site, dentro de um raio maximo da
 * cidade informada. Usa a tabela de referencia br_cities (importada 1x, ver
 * database/import_br_cities.php) e a formula de Haversine -- sem depender de nenhuma API externa
 * de geocodificacao. Se ninguem estiver no raio, devolve null (o caller cai no WhatsApp central da
 * Ecodiffusore -- o cliente nunca precisa saber que nao tinha vendedor por perto).
 *
 * Distribuicao meritocratica por ordem de chegada: quando mais de um Vendedor esta dentro do raio
 * (ex: 3 vendedores na mesma cidade/regiao do Licenciado), NAO escolhe sempre o mais proximo --
 * escolhe quem esta ha mais tempo sem receber um lead (rodizio tipo fila, ver
 * Lead::lastAssignedAt()), so usando distancia como criterio de desempate quando ninguem do grupo
 * recebeu lead ainda. Isso evita que o vendedor "sorte" de ficar mais perto do centro da cidade
 * fique sempre com todos os leads da regiao.
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

        $candidates = [];

        foreach (User::allByRole('vendedor') as $vendedor) {
            if (empty($vendedor['city']) || empty($vendedor['whatsapp'])) {
                continue;
            }

            // O Vendedor tem estado cadastrado (users.state) -- usa pra desambiguar cidades
            // homonimas (ex: existe mais de um "Toledo" no Brasil). O cliente so informa a cidade
            // no popup, sem estado, entao esse lado da busca continua aproximado (ver docblock).
            $sellerCoords = self::findCityCoords($vendedor['city'], $vendedor['state'] ?? null);
            if (!$sellerCoords) {
                continue;
            }

            $distance = self::haversine(
                (float) $customerCoords['lat'],
                (float) $customerCoords['lng'],
                (float) $sellerCoords['lat'],
                (float) $sellerCoords['lng']
            );

            if ($distance <= self::RADIUS_KM) {
                $candidates[] = [
                    'id' => (int) $vendedor['id'],
                    'name' => $vendedor['name'],
                    'whatsapp' => $vendedor['whatsapp'],
                    'distance_km' => round($distance, 1),
                ];
            }
        }

        if (!$candidates) {
            return null;
        }

        $lastAssigned = Lead::lastAssignedAt(array_column($candidates, 'id'));
        usort($candidates, function ($a, $b) use ($lastAssigned) {
            $lastA = $lastAssigned[$a['id']] ?? null;
            $lastB = $lastAssigned[$b['id']] ?? null;

            // Quem nunca recebeu lead vai pra frente da fila; entre dois que nunca receberam
            // (ou empate exato de horario), desempata pelo mais proximo.
            if ($lastA === $lastB) {
                return $a['distance_km'] <=> $b['distance_km'];
            }
            if ($lastA === null) {
                return -1;
            }
            if ($lastB === null) {
                return 1;
            }
            return strcmp($lastA, $lastB);
        });

        return $candidates[0];
    }

    /** UF da primeira cidade brasileira encontrada com esse nome (mesma limitacao de ambiguidade
     *  documentada no docblock da classe -- usado so como sinal aproximado de onde ha demanda). */
    public static function stateForCity(string $cityName): ?string
    {
        $stmt = Database::connection()->prepare('SELECT uf FROM br_cities WHERE name_normalized = :name LIMIT 1');
        $stmt->execute(['name' => self::normalize($cityName)]);
        $row = $stmt->fetch();
        return $row ? $row['uf'] : null;
    }

    /** @return array{lat: float, lng: float}|null */
    private static function findCityCoords(string $cityName, ?string $state = null): ?array
    {
        $normalized = self::normalize($cityName);

        $sql = 'SELECT lat, lng FROM br_cities WHERE name_normalized = :name';
        $params = ['name' => $normalized];

        if ($state) {
            $sql .= ' AND uf = :uf';
            $params['uf'] = strtoupper($state);
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function normalize(string $s): string
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
