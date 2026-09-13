<?php

namespace App\Core;

/**
 * Paginas locais de SEO (Fase 55) -- uma por estado com Licenciado ativo, mirando buscas tipo
 * "economia de diesel <estado>"/"Ecodiffusore <cidade>". Mesmo padrao de fonte unica de
 * App\Core\BlogPosts: usado por LocalController E pelo sitemap dinamico.
 */
class LocalPages
{
    public const PAGES = [
        ['slug' => 'economia-de-diesel-parana', 'stateUf' => 'PR', 'stateName' => 'Paraná'],
        ['slug' => 'economia-de-diesel-goias', 'stateUf' => 'GO', 'stateName' => 'Goiás'],
        ['slug' => 'economia-de-diesel-mato-grosso', 'stateUf' => 'MT', 'stateName' => 'Mato Grosso'],
    ];

    public static function find(string $slug): ?array
    {
        foreach (self::PAGES as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }
        return null;
    }
}
