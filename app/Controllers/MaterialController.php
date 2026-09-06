<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Roles;
use App\Core\View;

/**
 * Central de materiais de venda -- os PDFs (patente/marca/laudo/manual) ja estao publicados no
 * site publico desde a Fase 14 (usados na secao tecnica de /comprar), so nao existiam num lugar
 * facil de achar DENTRO do painel pro Vendedor mandar na hora certa sem sair procurando no site.
 * Lista fixa (nao vem de banco) -- sao sempre os mesmos 4 documentos institucionais.
 */
class MaterialController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);

        $materials = [
            [
                'title' => 'Carta Patente (INPI)',
                'description' => 'Patente de modelo de utilidade do Ecodiffusore, registrada no INPI (BR 202020013548-7).',
                'file' => '/assets/docs/carta-patente.pdf',
                'icon' => '📜',
            ],
            [
                'title' => 'Certificado de registro de marca (INPI)',
                'description' => 'Registro oficial da marca Ecodiffusore no INPI, processo nº 920298915.',
                'file' => '/assets/docs/certificado-registro-marca.pdf',
                'icon' => '🏷️',
            ],
            [
                'title' => 'Laudo técnico — máquinas agrícolas',
                'description' => 'Estudo do Instituto ECOTEC com redução de consumo medida em campo (John Deere, Valtra).',
                'file' => '/assets/docs/laudo-maquinas-agricolas.pdf',
                'icon' => '🚜',
            ],
            [
                'title' => 'Manual técnico',
                'description' => 'Manual completo do produto, com testes de emissão/opacidade em veículos reais.',
                'file' => '/assets/docs/manual-tecnico.pdf',
                'icon' => '📘',
            ],
        ];

        View::render('painel/materials/index', [
            'user' => Auth::user(),
            'materials' => $materials,
        ]);
    }
}
