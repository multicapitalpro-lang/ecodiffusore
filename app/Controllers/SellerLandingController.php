<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\BrCity;
use App\Models\Lead;
use App\Models\User;

/**
 * Link de vendas pessoal (Fase 93) -- ecodiffusorebrasil.com.br/v/{slug}. Lead capturado aqui cai
 * DIRETO pro dono do link (Licenciado/Gestor/Vendedor assinante), sem passar pelo roteamento por
 * raio de 100km do GeoMatch -- e' o vendedor distribuindo o proprio link (panfleto/QR/Instagram),
 * nao um contato organico do site que precisa ser distribuido.
 */
class SellerLandingController
{
    public function show(string $slug): void
    {
        $seller = User::findBySlug($slug);
        if (!$seller) {
            Router::redirect('/');
        }

        View::render('site/seller_landing', [
            'seller' => $seller,
            'seoTitle' => "Fale com {$seller['name']} — Ecodiffusore Brasil",
            'seoDescription' => 'Economize de 5% a 20% no diesel com o Ecodiffusore, sistema patenteado e fabricado no Brasil.',
            'seoPath' => '/v/' . $slug,
        ], 'site');
    }

    public function submitLead(string $slug): void
    {
        $seller = User::findBySlug($slug);
        if (!$seller) {
            Router::redirect('/');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/v/{$slug}?erro=csrf#contato");
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if ($name === '' || $whatsapp === '') {
            Router::redirect("/v/{$slug}?erro=1#contato");
        }
        if ($city !== '' && !BrCity::exists($city)) {
            Router::redirect("/v/{$slug}?erro=cidade#contato");
        }

        $leadId = Lead::create([
            'name' => mb_substr($name, 0, 120),
            'whatsapp' => mb_substr($whatsapp, 0, 30),
            'city' => mb_substr($city, 0, 120),
            'truck_brand' => '',
            'message' => null,
            'source' => 'link_pessoal',
        ]);
        Lead::assignTo($leadId, (int) $seller['id']);

        $sellerDigits = preg_replace('/\D/', '', $seller['whatsapp'] ?? '');
        $waUrl = $sellerDigits
            ? 'https://wa.me/55' . $sellerDigits . '?text=' . rawurlencode("Olá {$seller['name']}, vim pelo seu link e quero saber mais sobre o Ecodiffusore!")
            : null;

        Router::redirect("/v/{$slug}?sucesso=1" . ($waUrl ? '&wa=' . rawurlencode($waUrl) : '') . '#contato');
    }
}
