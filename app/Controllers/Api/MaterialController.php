<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\SalesScript;
use App\Models\Testimonial;

/** Fase 76g: Materiais de Venda pro app -- mesma lista fixa de App\Controllers\MaterialController,
 *  com URLs completas (os arquivos ja sao publicos, servidos por public_html/assets) pra abrir
 *  direto no navegador do celular. */
class MaterialController
{
    private const BASE_URL = 'https://ecodiffusorebrasil.com.br';

    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Materiais.', 403);
        }

        $materials = [
            ['title' => 'Carta Patente (INPI)', 'description' => 'Patente de modelo de utilidade do Ecodiffusore, registrada no INPI (BR 202020013548-7).', 'file' => '/assets/docs/carta-patente.pdf', 'icon' => '📜'],
            ['title' => 'Certificado de registro de marca (INPI)', 'description' => 'Registro oficial da marca Ecodiffusore no INPI, processo nº 920298915.', 'file' => '/assets/docs/certificado-registro-marca.pdf', 'icon' => '🏷️'],
            ['title' => 'Laudo técnico — máquinas agrícolas', 'description' => 'Estudo do Instituto ECOTEC com redução de consumo medida em campo (John Deere, Valtra).', 'file' => '/assets/docs/laudo-maquinas-agricolas.pdf', 'icon' => '🚜'],
            ['title' => 'Manual técnico', 'description' => 'Manual completo do produto, com testes de emissão/opacidade em veículos reais.', 'file' => '/assets/docs/manual-tecnico.pdf', 'icon' => '📘'],
        ];

        $presentations = [
            ['title' => 'Apresentação para Clientes', 'description' => 'Sem valor de produto -- só o ecossistema Ecodiffusore e exemplos de economia/payback.', 'file' => '/assets/docs/apresentacao-clientes.pdf', 'icon' => '📊'],
        ];

        $calculators = [
            ['title' => 'Calculadora de Economia de Diesel', 'description' => 'Simula a economia de combustível do cliente e o payback do investimento.', 'file' => '/assets/tools/calculadora-economia-diesel.html', 'icon' => '🧮'],
            ['title' => 'Calculadora do Licenciado (sem mensalidades)', 'description' => 'Simula o retorno de virar Licenciado Ecodiffusore.', 'file' => '/assets/tools/calculadora-licenciado.html', 'icon' => '📊'],
        ];

        $withUrl = fn (array $rows) => array_map(fn ($r) => $r + ['url' => self::BASE_URL . $r['file']], $rows);

        ApiResponse::json([
            'materials' => $withUrl($materials),
            'presentations' => $withUrl($presentations),
            'calculators' => $withUrl($calculators),
            'scripts' => array_map(fn ($s) => [
                'id' => (int) $s['id'],
                'title' => $s['title'],
                'response_text' => $s['response_text'],
            ], SalesScript::all()),
            'testimonials' => array_map(fn ($t) => [
                'id' => (int) $t['id'],
                'client_name' => $t['client_name'],
                'city' => $t['city'],
                'vehicle' => $t['vehicle'],
                'testimonial_text' => $t['testimonial_text'],
            ], Testimonial::all()),
        ]);
    }
}
