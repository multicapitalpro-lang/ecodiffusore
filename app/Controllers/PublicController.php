<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\Lead;

class PublicController
{
    public function home(): void
    {
        View::render('site/home', [], 'site');
    }

    public function submitLead(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/?erro=csrf#contato');
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $truckBrand = trim($_POST['truck_brand'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '' || $whatsapp === '') {
            Router::redirect('/?erro=1#contato');
        }

        Lead::create([
            'name' => mb_substr($name, 0, 120),
            'whatsapp' => mb_substr($whatsapp, 0, 30),
            'city' => mb_substr($city, 0, 120),
            'truck_brand' => mb_substr($truckBrand, 0, 60),
            'message' => mb_substr($message, 0, 2000),
            'source' => 'landing_page',
        ]);

        Router::redirect('/?sucesso=1#contato');
    }
}
