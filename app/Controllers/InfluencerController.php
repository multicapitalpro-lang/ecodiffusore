<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Roles;
use App\Core\View;
use App\Models\Commission;
use App\Models\Lead;

class InfluencerController
{
    public function dashboard(): void
    {
        Auth::requireRole([Roles::INFLUENCER]);
        $user = Auth::user();

        $leads = Lead::forInfluencer((int) $user['id']);

        $stats = [
            'leads' => count($leads),
            'orcamentos' => 0,
            'vendas' => 0,
        ];
        foreach ($leads as $l) {
            if (!empty($l['quote_id'])) {
                $stats['orcamentos']++;
            }
            if (!empty($l['order_id'])) {
                $stats['vendas']++;
            }
        }

        $commissionSummary = Commission::byBeneficiary(['beneficiary_id' => $user['id']]);
        $summary = $commissionSummary[0] ?? ['total' => 0, 'total_pago' => 0, 'total_pendente' => 0];

        $baseUrl = rtrim(Config::get('app_url', 'https://ecodiffusorebrasil.com.br'), '/');

        View::render('painel/influencer/dashboard', [
            'user' => $user,
            'leads' => $leads,
            'stats' => $stats,
            'summary' => $summary,
            'referralLink' => $baseUrl . '/comprar?inf=' . (int) $user['id'],
        ]);
    }
}
