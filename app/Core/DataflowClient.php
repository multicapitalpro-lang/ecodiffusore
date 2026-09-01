<?php

namespace App\Core;

/**
 * Consulta de veiculo por placa via Dataflow -- integracao NAO feita de proposito (decisao do
 * cliente, Fase 14: "deixar superficial no momento pra ser incluso posteriormente"). Por enquanto
 * sempre retorna null, o que faz o fluxo de /comprar cair direto no formulario manual passo a passo.
 *
 * Quando a API real for integrada, este e' o unico lugar que precisa mudar.
 */
class DataflowClient
{
    /** @return array{year?: string, brand?: string, power?: string}|null */
    public function lookup(string $plate): ?array
    {
        // TODO: integrar API real da Dataflow aqui.
        return null;
    }
}
