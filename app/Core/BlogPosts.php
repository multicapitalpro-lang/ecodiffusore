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
            'image' => '/assets/img/beneficio-economia.jpg',
            'imageAlt' => 'Economia de combustível com o Ecodiffusore',
        ],
        [
            'slug' => 'como-reduzir-consumo-de-diesel-do-caminhao',
            'title' => 'Como reduzir o consumo de diesel do caminhão: 7 dicas práticas',
            'description' => 'Sete práticas comprovadas para economizar diesel no dia a dia do caminhoneiro e da transportadora, da calibragem dos pneus à tecnologia de otimização de combustão.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-caminhoneiros.jpg',
            'imageAlt' => 'Caminhoneiro autônomo na estrada',
        ],
        [
            'slug' => 'economizador-de-combustivel-vale-a-pena-payback',
            'title' => 'Economizador de combustível vale a pena? Calcule o payback',
            'description' => 'Quanto tempo leva para o investimento em um economizador de combustível se pagar? Veja o cálculo de payback e o retorno real para caminhões e frotas.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/beneficio-economia.jpg',
            'imageAlt' => 'Cálculo de economia de combustível',
        ],
        [
            'slug' => 'por-que-o-diesel-so-sobe-no-brasil',
            'title' => 'Por que o diesel só sobe no Brasil? Entenda os fatores por trás do preço',
            'description' => 'Câmbio, preço internacional do petróleo, tributos e paridade de importação: entenda por que o diesel fica mais caro no Brasil e como se proteger disso.',
            'publishedAt' => '2026-09-13',
            'image' => null,
        ],
        [
            'slug' => 'economia-de-combustivel-para-frotas',
            'title' => 'Economia de combustível para frotas: como reduzir o custo operacional',
            'description' => 'Diesel costuma ser o maior custo operacional de uma transportadora. Veja estratégias e tecnologia para reduzir o consumo de combustível em toda a frota.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-frotas.jpg',
            'imageAlt' => 'Frota de caminhões de transportadora',
        ],
        [
            'slug' => 'ecodiffusore-em-maquinas-agricolas',
            'title' => 'Ecodiffusore em máquinas agrícolas: economia de diesel no campo',
            'description' => 'Tratores, colheitadeiras e máquinas agrícolas a diesel também economizam combustível com o Ecodiffusore. Veja os dados do estudo técnico ECOTEC.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-agronegocio.jpg',
            'imageAlt' => 'Máquinas agrícolas a diesel no campo',
        ],
        [
            'slug' => 'geradores-a-diesel-como-reduzir-consumo',
            'title' => 'Geradores a diesel: como reduzir o consumo de combustível',
            'description' => 'Geradores a diesel rodando por muitas horas seguidas sentem o peso do combustível na conta. Veja como reduzir o consumo sem perder potência.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/beneficio-instalacao.jpg',
            'imageAlt' => 'Instalação simples em motor a diesel',
        ],
        [
            'slug' => 'arla-32-o-que-e-diferenca-para-economizador-de-combustivel',
            'title' => 'ARLA 32: o que é, para que serve e a diferença para um economizador de combustível',
            'description' => 'Entenda o que é o ARLA 32, por que motores modernos precisam dele, e qual a diferença entre ele e um sistema economizador de combustível como o Ecodiffusore.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/beneficio-emissoes.jpg',
            'imageAlt' => 'Redução de emissões em motor a diesel',
        ],
        [
            'slug' => 'diesel-s10-x-s500-qual-a-diferenca',
            'title' => 'Diesel S10 x S500: qual a diferença e qual rende mais',
            'description' => 'Entenda a diferença entre o diesel S10 e o S500, qual seu motor realmente precisa, e como isso afeta o consumo e a economia de combustível.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-caminhoneiros.jpg',
            'imageAlt' => 'Abastecimento de caminhão a diesel',
        ],
        [
            'slug' => 'direcao-economica-guia-completo-caminhoneiros',
            'title' => 'Direção econômica: guia completo para caminhoneiros economizarem diesel',
            'description' => 'Técnicas de direção econômica que reduzem o consumo de diesel na estrada, sem perder tempo de viagem nem desempenho.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-caminhoneiros.jpg',
            'imageAlt' => 'Caminhoneiro dirigindo na estrada',
        ],
        [
            'slug' => 'manutencao-preventiva-caminhoes-diesel',
            'title' => 'Manutenção preventiva de caminhões: como evitar gasto extra de diesel',
            'description' => 'Filtro sujo, injeção desregulada, pneu descalibrado: veja os itens de manutenção que mais impactam o consumo de diesel do seu caminhão.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/instalacao-passo-a-passo.jpg',
            'imageAlt' => 'Manutenção técnica de motor a diesel',
        ],
        [
            'slug' => 'esg-sustentabilidade-transporte-rodoviario',
            'title' => 'ESG e sustentabilidade no transporte rodoviário: como reduzir emissões',
            'description' => 'Como transportadoras e frotas atendem critérios ESG reduzindo emissões e consumo de diesel, com tecnologia e boas práticas de operação.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/beneficio-emissoes.jpg',
            'imageAlt' => 'Redução de emissões para transporte sustentável',
        ],
        [
            'slug' => 'linha-amarela-maquinas-pesadas-economia-diesel',
            'title' => 'Linha amarela e máquinas pesadas: como economizar diesel em obras',
            'description' => 'Escavadeiras, retroescavadeiras e tratores de esteira consomem muito diesel em obras e mineração. Veja como reduzir esse custo operacional.',
            'publishedAt' => '2026-09-13',
            'image' => '/assets/img/publico-linha-amarela.jpg',
            'imageAlt' => 'Máquina de linha amarela em obra',
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
