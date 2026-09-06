<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\BrCity;

/** Busca de cidade real (br_cities) pro autocomplete usado em qualquer formulario do site
 *  (publico) ou do painel que capture cidade -- rota publica de proposito (sem
 *  Auth::requireLogin), reaproveitada nos dois contextos, ja que nome de cidade nao e' dado
 *  sensivel e o popup de /comprar precisa funcionar sem sessao autenticada. */
class CityController
{
    public function search(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        Response::json(['data' => BrCity::search($query)]);
    }
}
