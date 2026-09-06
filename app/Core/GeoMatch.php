<?php

namespace App\Core;

use App\Models\Lead;
use App\Models\User;

/**
 * Acha quem vai atender um cliente que chegou pelo site, dentro de um raio maximo da cidade
 * informada. Usa a tabela de referencia br_cities (importada 1x, ver
 * database/import_br_cities.php) e a formula de Haversine -- sem depender de nenhuma API externa
 * de geocodificacao. Se ninguem estiver no raio, devolve null (o caller cai no WhatsApp central da
 * Ecodiffusore -- o cliente nunca precisa saber que nao tinha ninguem por perto).
 *
 * Rodizio em DUAS camadas (Fase 38 -- agora pode ter mais de um Licenciado na mesma cidade):
 * 1) Entre os LICENCIADOS dentro do raio, escolhe a REDE (Licenciado + toda a downline dele) que
 *    esta ha mais tempo sem receber lead nenhum -- nao o Licenciado mais proximo.
 * 2) Dentro do Licenciado escolhido, escolhe o VENDEDOR (so os dele, sem filtrar por distancia de
 *    novo -- a area ja foi decidida pelo Licenciado) que esta ha mais tempo sem receber lead. Se
 *    ele nao tiver vendedor proprio, o Licenciado atende diretamente.
 * Cada camada usa a mesma logica de fila (ver pickLeastRecent()) -- nunca escolhe sempre o mesmo,
 * so usa distancia como desempate entre quem nunca recebeu lead ainda.
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

        // 1) Licenciados dentro do raio.
        $licenciadoCandidates = self::candidatesWithinRadius($customerCoords, User::allByRole('licenciado'));
        if (!$licenciadoCandidates) {
            return null;
        }

        // Rodizio entre REDES (nao so o Licenciado pessoalmente) -- pega a downline inteira de
        // cada candidato numa tacada so, pra nao repetir consulta por candidato.
        $downlineByLicenciado = [];
        $allNetworkIds = [];
        foreach ($licenciadoCandidates as $lic) {
            $downline = User::downlineIds($lic['id']);
            $downlineByLicenciado[$lic['id']] = $downline;
            $allNetworkIds = array_merge($allNetworkIds, $downline);
        }
        $lastAssignedPerUser = Lead::lastAssignedAt(array_values(array_unique($allNetworkIds)));

        foreach ($licenciadoCandidates as &$lic) {
            $times = array_filter(array_map(fn ($uid) => $lastAssignedPerUser[$uid] ?? null, $downlineByLicenciado[$lic['id']]));
            $lic['last_assigned_at'] = $times ? max($times) : null;
        }
        unset($lic);

        $chosenLicenciado = self::pickLeastRecent($licenciadoCandidates);

        // 2) Dentro do Licenciado escolhido, rodizio entre os PROPRIOS Vendedores -- a area ja foi
        // decidida (o Licenciado esta no raio), entao nao filtra o Vendedor por distancia de novo.
        $networkIds = $downlineByLicenciado[$chosenLicenciado['id']];
        $vendedores = array_values(array_filter(
            User::allByRole('vendedor'),
            fn ($v) => in_array((int) $v['id'], $networkIds, true) && !empty($v['whatsapp'])
        ));

        if (!$vendedores) {
            // Licenciado sem Vendedor proprio -- ele mesmo atende.
            return [
                'id' => $chosenLicenciado['id'],
                'name' => $chosenLicenciado['name'],
                'whatsapp' => $chosenLicenciado['whatsapp'],
                'distance_km' => $chosenLicenciado['distance_km'],
            ];
        }

        $vendedorIds = array_map(fn ($v) => (int) $v['id'], $vendedores);
        $lastAssignedVendedor = Lead::lastAssignedAt($vendedorIds);
        $vendedorCandidates = array_map(fn ($v) => [
            'id' => (int) $v['id'],
            'name' => $v['name'],
            'whatsapp' => $v['whatsapp'],
            // Mesma area do Licenciado escolhido -- so serve pra desempate, o Vendedor em si nao
            // tem mais peso geografico proprio nessa decisao.
            'distance_km' => $chosenLicenciado['distance_km'],
            'last_assigned_at' => $lastAssignedVendedor[(int) $v['id']] ?? null,
        ], $vendedores);

        return self::pickLeastRecent($vendedorCandidates);
    }

    /** @param array{lat: float, lng: float} $customerCoords
     *  @return array<int, array{id: int, name: string, whatsapp: string, distance_km: float}> */
    private static function candidatesWithinRadius(array $customerCoords, array $users): array
    {
        $candidates = [];

        foreach ($users as $u) {
            if (empty($u['city']) || empty($u['whatsapp'])) {
                continue;
            }

            // O usuario tem estado cadastrado (users.state) -- usa pra desambiguar cidades
            // homonimas (ex: existe mais de um "Toledo" no Brasil). O cliente so informa a cidade
            // no popup, sem estado, entao esse lado da busca continua aproximado (ver docblock).
            $coords = self::findCityCoords($u['city'], $u['state'] ?? null);
            if (!$coords) {
                continue;
            }

            $distance = self::haversine(
                (float) $customerCoords['lat'],
                (float) $customerCoords['lng'],
                (float) $coords['lat'],
                (float) $coords['lng']
            );

            if ($distance <= self::RADIUS_KM) {
                $candidates[] = [
                    'id' => (int) $u['id'],
                    'name' => $u['name'],
                    'whatsapp' => $u['whatsapp'],
                    'distance_km' => round($distance, 1),
                ];
            }
        }

        return $candidates;
    }

    /** Escolhe, entre os candidatos (cada um com 'last_assigned_at' ja calculado), quem esta ha
     *  mais tempo sem receber lead -- quem nunca recebeu vai pra frente da fila; desempate (dois
     *  nunca receberam, ou empate exato de horario) e' pelo mais proximo. */
    private static function pickLeastRecent(array $candidates): array
    {
        usort($candidates, function ($a, $b) {
            $lastA = $a['last_assigned_at'] ?? null;
            $lastB = $b['last_assigned_at'] ?? null;

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
