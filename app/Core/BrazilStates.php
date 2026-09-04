<?php

namespace App\Core;

/**
 * Layout de "cartograma em grade" dos 27 estados brasileiros -- cada UF numa celula de uma grade
 * fixa, na posicao relativa aproximada (Norte em cima, Nordeste a direita, Centro-Oeste no meio,
 * Sudeste abaixo do Centro-Oeste, Sul embaixo). Nao e' um mapa geografico de verdade (sem
 * fronteiras reais), mas evita o risco de desenhar um contorno impreciso do Brasil a mao --
 * e' o mesmo estilo usado em varios infograficos de "grid map" por estado.
 */
class BrazilStates
{
    public const GRID = [
        'RR' => [0, 3], 'AP' => [0, 5],
        'AM' => [1, 1], 'PA' => [1, 3],
        'AC' => [2, 0], 'RO' => [2, 2], 'TO' => [2, 3], 'MA' => [2, 4],
        'MT' => [3, 2], 'PI' => [3, 4], 'CE' => [3, 5], 'RN' => [3, 6],
        'GO' => [4, 3], 'PE' => [4, 5], 'PB' => [4, 6],
        'MS' => [5, 1], 'DF' => [5, 3], 'BA' => [5, 4], 'AL' => [5, 5], 'SE' => [5, 6],
        'SP' => [6, 2], 'MG' => [6, 3], 'ES' => [6, 4],
        'PR' => [7, 1], 'RJ' => [7, 3],
        'SC' => [8, 1],
        'RS' => [9, 1],
    ];

    public const NAMES = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
        'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
        'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    public const ROWS = 10;
    public const COLS = 7;

    /**
     * Agrupa uma lista de usuarios (precisa ter 'state' e 'city') por UF.
     * @return array<string, array{count:int, cities:string[]}>
     */
    public static function groupByState(array $users): array
    {
        $byState = [];
        foreach ($users as $u) {
            $uf = strtoupper(trim($u['state'] ?? ''));
            if ($uf === '' || !isset(self::NAMES[$uf])) {
                continue;
            }
            $byState[$uf]['count'] = ($byState[$uf]['count'] ?? 0) + 1;
            if (!empty($u['city'])) {
                $byState[$uf]['cities'][] = $u['city'] . (!empty($u['name']) ? ' (' . $u['name'] . ')' : '');
            }
        }
        return $byState;
    }
}
