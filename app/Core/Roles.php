<?php

namespace App\Core;

/**
 * Grupos de papeis centralizados. Antes desta classe, cada controller repetia os mesmos
 * literais ['admin', 'gerente', ...] em cada Auth::requireRole() -- na segunda reorganizacao
 * de hierarquia da sessao (Fase 11), ficou claro que vale a pena centralizar aqui.
 */
class Roles
{
    /** Veem financeiro, equipe, metas, aprovacoes de desconto */
    public const MANAGEMENT = ['admin', 'gerente', 'licenciado'];

    /** Acesso geral ao painel interno (todo mundo exceto cliente) */
    public const STAFF = ['admin', 'gerente', 'licenciado', 'vendedor'];

    /** Quem efetivamente vende e aparece como "vendedor" em pedidos/orcamentos */
    public const SELLER = 'vendedor';

    /** Dono do contrato/regiao, topo da hierarquia comercial (abaixo do admin) */
    public const REGIONAL_OWNER = 'licenciado';
}
