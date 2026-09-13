<?php

namespace App\Controllers;

use App\Core\LocalPages;
use App\Core\Router;
use App\Core\View;
use App\Models\User;

class LocalController
{
    public function parana(): void
    {
        $this->render('economia-de-diesel-parana');
    }

    public function goias(): void
    {
        $this->render('economia-de-diesel-goias');
    }

    public function matoGrosso(): void
    {
        $this->render('economia-de-diesel-mato-grosso');
    }

    private function render(string $slug): void
    {
        $page = LocalPages::find($slug);
        if (!$page) {
            Router::redirect('/');
        }

        $cities = User::activeLicensedCitiesInState($page['stateUf']);

        View::render('site/local/show', [
            'page' => $page,
            'cities' => $cities,
            'seoTitle' => 'Economia de Diesel em ' . $page['stateName'] . ' — Ecodiffusore Brasil',
            'seoDescription' => 'Ecodiffusore em ' . $page['stateName'] . ': sistema patenteado que reduz de 5% a 20% o consumo de diesel. Rede de licenciados autorizados no estado, fabricação nacional e garantia.',
            'seoPath' => '/' . $page['slug'],
        ], 'site');
    }
}
