<?php

namespace App\Models;

use App\Core\Database;

/** Textos editaveis (Admin/Gerente, ver Roles::SUPERVISOR_ASSIGNMENT) das mensagens automaticas
 *  de WhatsApp disparadas por App\Core\Notifier. Cada evento pode ter ate 2 variantes:
 *  text_self (pro ator direto -- vendedor/licenciado do evento) e text_network (pro resto da
 *  cadeia -- gestor/licenciado/supervisor/gerente/admin). Nem todo evento usa as duas (ver
 *  VARIABLES abaixo). Placeholders {chave} sao interpolados sem escapar HTML (mensagem e texto
 *  puro, nao e-mail). */
class WhatsAppEventTemplate
{
    public const KEYS = [
        'lead_roteado',
        'pedido_registrado',
        'pedido_aprovado',
        'cadastro_aprovado',
        'pedido_cancelado',
        'vendedor_inativo',
        'licenciado_pendente_aprovacao',
        'follow_up_lembrete',
        'garantia_solicitada',
        'acesso_portal_criado',
        'pedido_atualizacao_entrega',
        'cobranca_gerada',
        'orcamento_lembrete_lead',
        'garantia_estendida_lembrete_dia1',
        'garantia_estendida_lembrete_dia5',
        'garantia_estendida_lembrete_dia10',
        'garantia_estendida_lembrete_dia15',
        'licenciado_contrato_pendente',
        'liberacao_desconto_solicitada',
        'liberacao_desconto_decidida',
        'pedido_documentos_enviados',
        'pagamento_confirmado_cliente',
        'pagamento_pendente_aviso',
        'email_profissional_solicitado',
        'email_profissional_decidido',
        'vendedor_contrato_enviado',
        'vendedor_contrato_aprovado',
        'vendedor_contrato_reprovado',
    ];

    public const LABELS = [
        'lead_roteado' => 'Lead roteado',
        'pedido_registrado' => 'Novo pedido registrado',
        'pedido_aprovado' => 'Pedido aprovado/pago',
        'cadastro_aprovado' => 'Cadastro de Licenciado aprovado',
        'pedido_cancelado' => 'Pedido cancelado',
        'vendedor_inativo' => 'Alerta de vendedor inativo',
        'licenciado_pendente_aprovacao' => 'Novo Licenciado pendente de aprovação',
        'follow_up_lembrete' => 'Lembrete de follow-up',
        'garantia_solicitada' => 'Confirmação de instalação enviada',
        'acesso_portal_criado' => 'Acesso ao portal criado (cliente)',
        'pedido_atualizacao_entrega' => 'Atualização de entrega (cliente)',
        'cobranca_gerada' => 'Cobrança gerada (cliente)',
        'orcamento_lembrete_lead' => 'Lembrete de orçamento parado (lead/cliente)',
        'garantia_estendida_lembrete_dia1' => 'Pós-venda de Instalação — lembrete dia 1 (cliente)',
        'garantia_estendida_lembrete_dia5' => 'Pós-venda de Instalação — lembrete dia 5 (cliente)',
        'garantia_estendida_lembrete_dia10' => 'Pós-venda de Instalação — lembrete dia 10 (cliente)',
        'garantia_estendida_lembrete_dia15' => 'Pós-venda de Instalação — último dia (cliente)',
        'licenciado_contrato_pendente' => 'Contrato pendente de assinatura (licenciado)',
        'liberacao_desconto_solicitada' => 'Solicitação de liberação de preço',
        'liberacao_desconto_decidida' => 'Status da liberação de preço (quem pediu)',
        'pedido_documentos_enviados' => 'Cliente enviou CNH + documento do veículo',
        'pagamento_confirmado_cliente' => 'Pagamento confirmado — pedir documentos (cliente)',
        'pagamento_pendente_aviso' => 'Pagamento pendente há muito tempo (vendedor)',
        'email_profissional_solicitado' => 'Licenciado pediu e-mail profissional (admin)',
        'email_profissional_decidido' => 'Status do pedido de e-mail profissional (licenciado)',
        'vendedor_contrato_enviado' => 'Vendedor enviou contrato assinado (licenciado)',
        'vendedor_contrato_aprovado' => 'Contrato do vendedor aprovado',
        'vendedor_contrato_reprovado' => 'Contrato do vendedor reprovado',
    ];

    /** Quais placeholders {chave} cada evento aceita -- so pra exibir dica na tela, nao valida nada. */
    public const VARIABLES = [
        'lead_roteado' => ['nome', 'whatsapp', 'cidade', 'url'],
        'pedido_registrado' => ['cliente', 'produto', 'valor', 'vendedor', 'url'],
        'pedido_aprovado' => ['cliente', 'produto', 'valor', 'vendedor', 'url'],
        'cadastro_aprovado' => ['nome', 'url'],
        'pedido_cancelado' => ['cliente', 'id', 'vendedor', 'url'],
        'vendedor_inativo' => ['vendedor', 'dias', 'url'],
        'licenciado_pendente_aprovacao' => ['nome', 'cidade', 'url'],
        'follow_up_lembrete' => ['leads', 'url'],
        'garantia_solicitada' => ['cliente', 'id', 'vendedor', 'url'],
        'acesso_portal_criado' => ['nome', 'email', 'senha', 'url'],
        'pedido_atualizacao_entrega' => ['transportadora', 'codigo', 'prazo', 'url'],
        'cobranca_gerada' => ['pedido', 'produto', 'valor', 'forma', 'vencimento', 'url'],
        'orcamento_lembrete_lead' => ['nome', 'produto', 'valor', 'vendedor', 'url'],
        'garantia_estendida_lembrete_dia1' => ['cliente', 'pedido', 'dias_restantes', 'url'],
        'garantia_estendida_lembrete_dia5' => ['cliente', 'pedido', 'dias_restantes', 'url'],
        'garantia_estendida_lembrete_dia10' => ['cliente', 'pedido', 'dias_restantes', 'url'],
        'garantia_estendida_lembrete_dia15' => ['cliente', 'pedido', 'dias_restantes', 'url'],
        'licenciado_contrato_pendente' => ['nome', 'url'],
        'liberacao_desconto_solicitada' => ['vendedor', 'cliente', 'regiao', 'quantidade', 'preco_original', 'preco_solicitado', 'url'],
        'liberacao_desconto_decidida' => ['cliente', 'preco_solicitado', 'status', 'decidido_por', 'url'],
        'pedido_documentos_enviados' => ['cliente', 'pedido', 'url'],
        'pagamento_confirmado_cliente' => ['nome', 'url'],
        'pagamento_pendente_aviso' => ['cliente', 'pedido', 'url'],
        'email_profissional_solicitado' => ['licenciado', 'endereco', 'url'],
        'email_profissional_decidido' => ['endereco', 'status', 'info', 'url'],
        'vendedor_contrato_enviado' => ['vendedor', 'url'],
        'vendedor_contrato_aprovado' => ['url'],
        'vendedor_contrato_reprovado' => ['motivo', 'url'],
    ];

    /** Eventos que so usam UM dos dois textos (o outro fica sempre null/nao editavel). */
    public const SELF_ONLY = [
        'follow_up_lembrete', 'acesso_portal_criado', 'pedido_atualizacao_entrega', 'cobranca_gerada', 'orcamento_lembrete_lead',
        'garantia_estendida_lembrete_dia1', 'garantia_estendida_lembrete_dia5', 'garantia_estendida_lembrete_dia10', 'garantia_estendida_lembrete_dia15',
        'licenciado_contrato_pendente', 'liberacao_desconto_decidida', 'pagamento_confirmado_cliente',
        'pagamento_pendente_aviso', 'email_profissional_decidido', 'vendedor_contrato_aprovado', 'vendedor_contrato_reprovado',
    ];
    public const NETWORK_ONLY = ['vendedor_inativo', 'licenciado_pendente_aprovacao', 'liberacao_desconto_solicitada', 'email_profissional_solicitado', 'vendedor_contrato_enviado'];

    /** Quem de fato recebe cada variante, POR EVENTO -- o alcance da "rede" varia bastante entre
     *  eventos (lead roteado so tem o Licenciado; pedido tem a cadeia inteira), entao um rotulo
     *  generico enganava na tela. So texto de exibicao, nao afeta o despacho. */
    public const SELF_LABELS = [
        'lead_roteado' => 'Vendedor (destinatário do lead)',
        'pedido_registrado' => 'Vendedor (dono do pedido)',
        'pedido_aprovado' => 'Vendedor (dono da venda)',
        'cadastro_aprovado' => 'Licenciado (quem teve o cadastro aprovado)',
        'pedido_cancelado' => 'Vendedor (dono do pedido)',
        'follow_up_lembrete' => 'Vendedor',
        'garantia_solicitada' => 'Vendedor (dono do pedido)',
        'acesso_portal_criado' => 'Cliente (dono da conta criada)',
        'pedido_atualizacao_entrega' => 'Cliente (dono do pedido)',
        'cobranca_gerada' => 'Cliente (dono do pedido)',
        'orcamento_lembrete_lead' => 'Lead/cliente (dono do orçamento)',
        'garantia_estendida_lembrete_dia1' => 'Cliente (dono do pedido)',
        'garantia_estendida_lembrete_dia5' => 'Cliente (dono do pedido)',
        'garantia_estendida_lembrete_dia10' => 'Cliente (dono do pedido)',
        'garantia_estendida_lembrete_dia15' => 'Cliente (dono do pedido)',
        'licenciado_contrato_pendente' => 'Licenciado (dono do contrato)',
        'liberacao_desconto_decidida' => 'Vendedor/Gestor/Licenciado (quem pediu a liberação)',
        'pedido_documentos_enviados' => 'Vendedor (dono do pedido)',
        'pagamento_confirmado_cliente' => 'Cliente (dono do pedido)',
        'pagamento_pendente_aviso' => 'Vendedor (dono do pedido)',
        'email_profissional_decidido' => 'Licenciado (quem pediu o e-mail)',
        'vendedor_contrato_aprovado' => 'Vendedor (dono do contrato)',
        'vendedor_contrato_reprovado' => 'Vendedor (dono do contrato)',
    ];

    public const NETWORK_LABELS = [
        'lead_roteado' => 'Licenciado da rede',
        'pedido_registrado' => 'Resto da rede (gestor/licenciado/supervisor/gerente/admin)',
        'pedido_aprovado' => 'Resto da rede (gestor/licenciado/supervisor/gerente/admin)',
        'cadastro_aprovado' => 'Rede de suporte (supervisor/gerente/admin)',
        'pedido_cancelado' => 'Licenciado da rede',
        'vendedor_inativo' => 'Rede responsável (licenciado/supervisor/gerente/admin)',
        'licenciado_pendente_aprovacao' => 'Admin e Gerente Geral',
        'garantia_solicitada' => 'Licenciado da rede',
        'liberacao_desconto_solicitada' => 'Quem pode aprovar (Gestor/Licenciado, ou Gerente/Supervisor/Admin conforme quem pediu)',
        'pedido_documentos_enviados' => 'Licenciado da rede',
        'email_profissional_solicitado' => 'Admin',
        'vendedor_contrato_enviado' => 'Licenciado da rede',
    ];

    public static function all(): array
    {
        $rows = Database::connection()->query('SELECT * FROM whatsapp_event_templates')->fetchAll();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['event_key']] = $row;
        }
        return $byKey;
    }

    public static function find(string $eventKey): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_event_templates WHERE event_key = :k');
        $stmt->execute(['k' => $eventKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(string $eventKey, ?string $textSelf, ?string $textNetwork): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_event_templates SET text_self = :self, text_network = :network WHERE event_key = :k'
        );
        $stmt->execute(['self' => $textSelf, 'network' => $textNetwork, 'k' => $eventKey]);
    }
}
