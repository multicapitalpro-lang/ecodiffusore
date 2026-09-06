<?php

namespace App\Models;

use App\Core\Database;

/**
 * Assunto/titulo/texto de introducao/rotulo do botao de cada evento de e-mail (Notifier) --
 * editavel pelo admin em /painel/configuracoes/email, pedido explicito do usuario. A tabela de
 * dados (Cliente/Valor, ou Nome/WhatsApp/Cidade) e a URL do botao continuam fixas no codigo --
 * so o texto em prosa e' editavel aqui.
 *
 * intro_text pode conter placeholders tipo {nome} -- ver Notifier::EVENT_PLACEHOLDERS pra saber
 * quais cada evento aceita. So o CADASTRO_APROVADO usa hoje ({nome}, porque o nome do Licenciado
 * entra no meio da frase), mas o mecanismo funciona pra qualquer evento.
 */
class EmailEventTemplate
{
    public const KEYS = ['lead_roteado', 'orcamento_registrado', 'pedido_registrado', 'pedido_aprovado', 'cadastro_aprovado'];

    private const DEFAULTS = [
        'lead_roteado' => ['subject' => 'Novo lead direcionado a você', 'title' => 'Novo lead direcionado', 'intro_text' => 'Um novo lead foi direcionado pra você:', 'button_label' => 'Acessar Leads no painel'],
        'orcamento_registrado' => ['subject' => 'Orçamento registrado', 'title' => 'Orçamento registrado', 'intro_text' => 'Um orçamento foi registrado:', 'button_label' => 'Ver orçamento no painel'],
        'pedido_registrado' => ['subject' => 'Pedido registrado', 'title' => 'Pedido registrado', 'intro_text' => 'Um pedido foi registrado:', 'button_label' => 'Ver pedido no painel'],
        'pedido_aprovado' => ['subject' => 'Pedido aprovado', 'title' => 'Pedido aprovado', 'intro_text' => 'Um pedido foi aprovado (pagamento confirmado):', 'button_label' => 'Ver pedido no painel'],
        'cadastro_aprovado' => ['subject' => 'Cadastro de Licenciado aprovado', 'title' => 'Cadastro aprovado', 'intro_text' => 'O cadastro de {nome} como Licenciado Ecodiffusore Brasil foi aprovado.', 'button_label' => 'Acessar o painel'],
    ];

    /** @return array<string, array{event_key: string, subject: string, title: string, intro_text: string, button_label: string}> */
    public static function all(): array
    {
        $rows = Database::connection()->query('SELECT * FROM email_event_templates')->fetchAll();
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['event_key']] = $r;
        }

        $result = [];
        foreach (self::DEFAULTS as $key => $defaults) {
            $result[$key] = array_merge($defaults, $byKey[$key] ?? [], ['event_key' => $key]);
        }
        return $result;
    }

    public static function find(string $eventKey): array
    {
        return self::all()[$eventKey] ?? (self::DEFAULTS[$eventKey] ?? []) + ['event_key' => $eventKey];
    }

    public static function update(string $eventKey, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE email_event_templates SET subject = :subject, title = :title, intro_text = :intro_text, button_label = :button_label WHERE event_key = :key'
        );
        $stmt->execute([
            'subject' => $data['subject'],
            'title' => $data['title'],
            'intro_text' => $data['intro_text'],
            'button_label' => $data['button_label'],
            'key' => $eventKey,
        ]);
    }
}
