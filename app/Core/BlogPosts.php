<?php

namespace App\Core;

/**
 * Registro central dos posts do Blog publico (SEO/marketing, Fase 52) -- cada post e' uma view
 * propria em app/Views/site/blog/{slug}.php, escrita a mao (decisao do usuario: sem CMS no painel
 * por enquanto, artigos entram direto no codigo). Esta classe e' a UNICA fonte de verdade da lista
 * de posts -- usada por BlogController (index/show/404) e SitemapController (pra nao esquecer de
 * incluir um post novo no sitemap.xml manualmente).
 */
class BlogPosts
{
    /**
     * Ordem = ordem de exibicao no /blog (mais novo primeiro). Cada entrada:
     * slug, title (H1/title da pagina), description (meta description + resumo na listagem),
     * publishedAt (AAAA-MM-DD, so exibicao -- nao afeta ordenacao).
     */
    public const POSTS = [
        [
            'slug' => 'ecodiffusore-e-confiavel-patente-fabricacao-garantia',
            'title' => 'Ecodiffusore é confiável? Patente, fabricação nacional e garantia',
            'description' => 'Entenda a patente registrada no INPI, a fabricação 100% brasileira e a garantia de devolução do Ecodiffusore — e como identificar o produto original.',
            'publishedAt' => '2026-09-13',
        ],
        [
            'slug' => 'como-reduzir-consumo-de-diesel-do-caminhao',
            'title' => 'Como reduzir o consumo de diesel do caminhão: 7 dicas práticas',
            'description' => 'Sete práticas comprovadas para economizar diesel no dia a dia do caminhoneiro e da transportadora, da calibragem dos pneus à tecnologia de otimização de combustão.',
            'publishedAt' => '2026-09-13',
        ],
        [
            'slug' => 'economizador-de-combustivel-vale-a-pena-payback',
            'title' => 'Economizador de combustível vale a pena? Calcule o payback',
            'description' => 'Quanto tempo leva para o investimento em um economizador de combustível se pagar? Veja o cálculo de payback e o retorno real para caminhões e frotas.',
            'publishedAt' => '2026-09-13',
        ],
    ];

    public static function find(string $slug): ?array
    {
        foreach (self::POSTS as $post) {
            if ($post['slug'] === $slug) {
                return $post;
            }
        }
        return null;
    }
}
