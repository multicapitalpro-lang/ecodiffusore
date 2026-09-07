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
    ];

    /** Eventos que so usam UM dos dois textos (o outro fica sempre null/nao editavel). */
    public const SELF_ONLY = ['follow_up_lembrete'];
    public const NETWORK_ONLY = ['vendedor_inativo', 'licenciado_pendente_aprovacao'];

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
