<?php

namespace App\Models;

use App\Core\Database;
use App\Core\GeoMatch;

/** Tabela de referencia br_cities (5571 municipios do IBGE, ver database/import_br_cities.php),
 *  usada tanto pelo GeoMatch (Haversine) quanto pelo autocomplete de cidade nos formularios --
 *  garante que so entra no sistema cidade brasileira real, nunca texto livre (bairro, cidade de
 *  outro pais, erro de digitacao), o que e' o que faz o roteamento por proximidade funcionar. */
class BrCity
{
    public static function search(string $query, int $limit = 10): array
    {
        $normalized = GeoMatch::normalize($query);
        if (mb_strlen($normalized) < 2) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT name, uf FROM br_cities WHERE name_normalized LIKE :contains
             ORDER BY (name_normalized LIKE :prefix) DESC, LENGTH(name) ASC, name ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([
            'contains' => '%' . $normalized . '%',
            'prefix' => $normalized . '%',
        ]);
        return $stmt->fetchAll();
    }

    /** true se existir um municipio brasileiro com esse nome (mesma normalizacao do GeoMatch) --
     *  usado pra validar no servidor que a cidade enviada por um formulario e' real, mesmo que o
     *  autocomplete do navegador tenha sido burlado (JS desabilitado, POST direto etc). */
    public static function exists(string $cityName): bool
    {
        if (trim($cityName) === '') {
            return true;
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM br_cities WHERE name_normalized = :name LIMIT 1'
        );
        $stmt->execute(['name' => GeoMatch::normalize($cityName)]);
        return (bool) $stmt->fetchColumn();
    }
}
