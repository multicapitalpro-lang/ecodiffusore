<?php

namespace App\Core;

/**
 * Lista curada de marcas e modelos de caminhoes/onibus comuns no Brasil, usada pro select em
 * cascata (Marca -> Modelo) no formulario de orcamento de /comprar. Nao e' exaustiva -- cobre as
 * linhas mais comuns; "Outra marca" cobre o resto.
 */
class VehicleCatalog
{
    private const CATALOG = [
        'Scania' => ['Série R', 'Série S', 'Série P', 'Série G', 'Série K (ônibus)', 'Série 4 (113/114/124/143)', 'NTG'],
        'Volvo' => ['FH', 'FM', 'FMX', 'VM', 'NH/NL (antigos)', 'B270F (ônibus)', 'B290R (ônibus)', 'B340R (ônibus)'],
        'Mercedes-Benz' => ['Actros', 'Axor', 'Atego', 'Accelo', 'OF (ônibus)', 'O500 (ônibus)', 'L/LS 1620/1932 (antigos)'],
        'DAF' => ['XF', 'CF', 'LF'],
        'Iveco' => ['Stralis', 'Tector', 'Vertis', 'Daily', 'EuroCargo'],
        'MAN / Volkswagen' => ['Constellation', 'Delivery', 'Meteor', 'Worker', 'TGX'],
        'Ford' => ['Cargo', 'F-4000', 'F-14000', 'F-16000'],
        'Agrale' => ['6000', '8500', '10000', '14000', '17000'],
        'International' => ['4700', '9200', '9800'],
        'Outra marca' => ['Não sei / outro modelo'],
    ];

    public static function all(): array
    {
        return self::CATALOG;
    }

    public static function brands(): array
    {
        return array_keys(self::CATALOG);
    }
}
