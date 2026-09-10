<?php

namespace App\Controllers;

use App\Core\WhatsAppSync;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppInstance;

/**
 * Webhook publico e UNICO pra todas as instancias por-usuario (Fase 33) -- diferente do Asaas/
 * Mercado Pago/ClickSign (um webhook por servico), aqui uma unica URL atende N instancias porque
 * a propria Evolution manda o nome da instancia (campo "instance") em todo payload. Sem token/
 * assinatura pra validar (Evolution API nao assina webhook nem manda header de auth por padrao) --
 * a mitigacao e' so processar eventos cujo campo "instance" bate com uma linha real de
 * whatsapp_instances; um payload forjado no maximo desperdicaria uma consulta, nunca grava nada
 * pra uma instancia que nao existe.
 */
class WhatsAppWebhookController
{
    public function receive(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
        $event = $body['event'] ?? '';
        $instanceName = $body['instance'] ?? '';

        $instance = $instanceName ? WhatsAppInstance::findByName($instanceName) : null;
        if (!$instance) {
            http_response_code(200);
            exit;
        }

        if ($event === 'messages.upsert') {
            $this->handleMessage($instance, $body['data'] ?? []);
        } elseif ($event === 'connection.update') {
            $this->handleConnectionUpdate($instance, $body['data'] ?? []);
        }

        http_response_code(200);
    }

    private function handleMessage(array $instance, array $data): void
    {
        $remoteJid = $data['key']['remoteJid'] ?? null;
        if (!$remoteJid) {
            return;
        }

        $isGroup = str_ends_with($remoteJid, '@g.us');
        $name = $data['pushName'] ?? null;
        $chatId = WhatsAppChat::upsert((int) $instance['id'], $remoteJid, $name, $isGroup);

        $fromMe = !empty($data['key']['fromMe']);
        WhatsAppSync::importMessage($chatId, $data, !$fromMe);
    }

    private function handleConnectionUpdate(array $instance, array $data): void
    {
        $state = $data['state'] ?? null;
        if ($state) {
            WhatsAppInstance::updateStatus((int) $instance['id'], $state);
        }
    }
}
