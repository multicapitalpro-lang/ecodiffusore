<?php

namespace App\Core;

/**
 * Grupos de papeis centralizados. Antes desta classe, cada controller repetia os mesmos
 * literais ['admin', 'gerente', ...] em cada Auth::requireRole() -- na segunda reorganizacao
 * de hierarquia da sessao (Fase 11), ficou claro que vale a pena centralizar aqui. Fase 12
 * introduziu um "Gerente" nacional (escolhido pela Ecodiffusore, cadastra Supervisores) e
 * renomeou o antigo "Gerente" regional (subordinado ao Licenciado) para "Gestor".
 */
class Roles
{
    /** Gestao REGIONAL: financeiro do licenciado, equipe, metas, aprovacao de desconto.
     * Gerente/Supervisor (papel nacional, de suporte, nao administram uma regiao) ficam de fora. */
    public const MANAGEMENT = ['admin', 'gestor', 'licenciado'];

    /** Acesso ao painel interno + CRM (Pedidos/Orcamentos/Leads/Clientes) + Comissoes.
     * Gerente/Supervisor entram aqui como VISUALIZADORES -- ver OrderController/QuoteController
     * ::assertNotViewOnly(), a permissao de escrita neles precisa de bloqueio extra. */
    public const STAFF = ['admin', 'gestor', 'licenciado', 'vendedor', 'gerente', 'supervisor'];

    /** Tela de Usuarios (cadastrar subordinado). Separado de MANAGEMENT de proposito: Gerente
     * cadastra Supervisor mas NAO deve ganhar acesso a Caixas/Contas a Pagar, que sao MANAGEMENT
     * em FinanceController. Fase 18: Supervisor entrou aqui tambem -- pode cadastrar Licenciado
     * interessado, mesmo sem ganhar acesso a nada de MANAGEMENT. */
    public const USER_MANAGEMENT = ['admin', 'gestor', 'licenciado', 'gerente', 'supervisor'];

    /** Papel nacional de suporte -- fora do split de pool, comissao paga direto pela empresa.
     * Usado pra bloquear escrita em Pedidos/Orcamentos/Leads (eles so visualizam). */
    public const NATIONAL_SUPPORT = ['gerente', 'supervisor'];

    /** Quem pode cadastrar/editar a qual Supervisor um Licenciado fica atribuido. */
    public const SUPERVISOR_ASSIGNMENT = ['admin', 'gerente'];

    /** Quem efetivamente vende e aparece como "vendedor" em pedidos/orcamentos */
    public const SELLER = 'vendedor';

    /** Dono do contrato/regiao, topo da hierarquia comercial regional (abaixo do admin) */
    public const REGIONAL_OWNER = 'licenciado';

    /** Fabrica terceirizada -- despacha os pedidos pagos. Ator completamente separado da
     *  hierarquia comercial (sem manager_id/comissao/downline), de proposito fora de STAFF/
     *  MANAGEMENT -- so ve/atualiza rastreio dos proprios pedidos verificados. */
    public const FACTORY = 'fabrica';

    /** Influenciador (Fase 56) -- divulga um link pessoal (?inf=<id>), sem entrar na cadeia de
     *  venda/atendimento (o roteamento por raio de 100km continua 100% geografico, o influenciador
     *  e' so uma etiqueta de origem). Ator separado, fora de STAFF/MANAGEMENT (mesmo tratamento de
     *  FACTORY) -- so ve o proprio painel com os leads/vendas que vieram do link dele. So o admin
     *  cadastra e so o admin edita o valor fixo de comissao por venda (users.influencer_commission_value). */
    public const INFLUENCER = 'influenciador';
}
