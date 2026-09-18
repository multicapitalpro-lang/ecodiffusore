<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\SalesScript;
use App\Models\Testimonial;

/**
 * Central de materiais de venda -- os PDFs (patente/marca/laudo/manual) ja estao publicados no
 * site publico desde a Fase 14 (usados na secao tecnica de /comprar), so nao existiam num lugar
 * facil de achar DENTRO do painel pro Vendedor mandar na hora certa sem sair procurando no site.
 * Lista fixa (nao vem de banco) -- sao sempre os mesmos 4 documentos institucionais.
 *
 * Ganhou tambem 2 secoes editaveis (Admin/Gerente mantem, todo STAFF usa): scripts de resposta
 * pra objecoes comuns e depoimentos reais de clientes -- ferramentas pro vendedor ter argumento
 * na hora certa sem precisar improvisar.
 */
class MaterialController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

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

        // Calculadoras interativas (HTML autonomo, sem PHP -- o vendedor/licenciado abre e usa na
        // hora com o cliente, sem precisar fazer login em nada, so o link).
        $calculators = [
            [
                'title' => 'Calculadora de Economia de Diesel',
                'description' => 'Simula a economia de combustível do cliente e o payback do investimento -- pra usar na conversa com o comprador.',
                'file' => '/assets/tools/calculadora-economia-diesel.html',
                'icon' => '🧮',
            ],
            [
                'title' => 'Calculadora do Licenciado (sem mensalidades)',
                'description' => 'Simula o retorno de virar Licenciado Ecodiffusore -- pra usar na conversa com quem está pensando em se tornar licenciado.',
                'file' => '/assets/tools/calculadora-licenciado.html',
                'icon' => '📊',
            ],
        ];

        View::render('painel/materials/index', [
            'user' => $user,
            'materials' => $materials,
            'calculators' => $calculators,
            'scripts' => SalesScript::all(),
            'testimonials' => Testimonial::all(),
            'canManage' => in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true),
        ]);
    }

    public function storeScript(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/materiais?erro=1');
        }

        $title = trim($_POST['title'] ?? '');
        $text = trim($_POST['response_text'] ?? '');
        if ($title === '' || $text === '') {
            Router::redirect('/painel/materiais?erro=1');
        }

        SalesScript::create($title, $text);
        Router::redirect('/painel/materiais?sucesso=1');
    }

    public function deleteScript(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/materiais?erro=1');
        }

        SalesScript::delete((int) $id);
        Router::redirect('/painel/materiais?sucesso=1');
    }

    public function storeTestimonial(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/materiais?erro=1');
        }

        $name = trim($_POST['client_name'] ?? '');
        $text = trim($_POST['testimonial_text'] ?? '');
        if ($name === '' || $text === '') {
            Router::redirect('/painel/materiais?erro=1');
        }

        Testimonial::create([
            'client_name' => $name,
            'city' => trim($_POST['city'] ?? ''),
            'vehicle' => trim($_POST['vehicle'] ?? ''),
            'testimonial_text' => $text,
        ]);
        Router::redirect('/painel/materiais?sucesso=1');
    }

    public function deleteTestimonial(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/materiais?erro=1');
        }

        Testimonial::delete((int) $id);
        Router::redirect('/painel/materiais?sucesso=1');
    }
}
