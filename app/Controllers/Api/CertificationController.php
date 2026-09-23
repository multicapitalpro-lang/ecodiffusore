<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Pdf;
use App\Core\SubscriptionGate;
use App\Core\View;

/** Fase 103: Certificacao do Vendedor pro app -- mesma logica de
 *  App\Controllers\CertificationController, atras do mesmo paywall ('certificacao_vendedor'). O
 *  PDF sai em binario (application/pdf) na resposta, autenticado pelo mesmo Bearer token de
 *  qualquer outra chamada da API -- o app baixa/compartilha o arquivo a partir da resposta. */
class CertificationController
{
    public function show(): void
    {
        $user = ApiAuth::requireUser();
        if ($user['role_slug'] !== 'vendedor') {
            ApiResponse::error('Só disponível pra Vendedor.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }

        ApiResponse::json([
            'certified' => !empty($user['training_completed_at']),
            'certified_at' => $user['training_completed_at'] ?? null,
        ]);
    }

    public function pdf(): void
    {
        $user = ApiAuth::requireUser();
        if ($user['role_slug'] !== 'vendedor') {
            ApiResponse::error('Só disponível pra Vendedor.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }
        if (empty($user['training_completed_at'])) {
            ApiResponse::error('Termine o treinamento primeiro pra liberar o certificado.', 422);
        }

        ob_start();
        View::render('painel/certification/pdf', [
            'sellerName' => $user['name'],
            'certifiedAt' => $user['training_completed_at'],
            'certNumber' => 'EDB-CERT-' . str_pad((string) $user['id'], 5, '0', STR_PAD_LEFT),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'certificado-vendedor-ecodiffusore.pdf', 'landscape');
    }
}
