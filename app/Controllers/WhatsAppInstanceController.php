<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\EvolutionApiClient;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Core\WhatsAppSync;
use App\Models\WhatsAppInstance;

/** Conexao do WhatsApp pessoal de Licenciado/Gestor/Vendedor (Fase 33) -- mesma mecanica de QR
 *  Code que o Admin ja usa (App\Controllers\WhatsAppSettingsController), so que 1 instancia por
 *  usuario em vez da instancia unica do sistema. Ferramenta inteira dentro do paywall da
 *  assinatura (SubscriptionGate::requireAccess, bloqueio total -- nao e' tela de "ver mascarado"
 *  como Relatorios/Financeiro, e' uma ferramenta ativa de comunicacao). */
class WhatsAppInstanceController
{
    private const ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function index(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = WhatsAppInstance::forUser((int) $user['id']);

        if ($instance && $instance['status'] === 'open') {
            Router::redirect('/painel/whatsapp/conversas');
        }

        View::render('painel/whatsapp/connect', [
            'user' => $user,
            'instance' => $instance,
        ]);
    }

    /** Cria a instancia (se ainda nao existir) e configura o webhook -- so depois disso a tela de
     *  status/QR (poll()) tem o que consultar. */
    public function connect(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/whatsapp?erro=1');
        }

        $instance = WhatsAppInstance::forUser((int) $user['id']);
        if (!$instance) {
            $instance = WhatsAppInstance::create((int) $user['id']);
        }

        try {
            $client = new EvolutionApiClient($instance['instance_name']);
            $client->createInstance();
            $webhookUrl = rtrim(Config::get('app_url'), '/') . '/webhooks/evolution';
            $client->setWebhook($webhookUrl);
        } catch (\Throwable $e) {
            // Best-effort -- se a instancia ja existia no servidor Evolution (reconexao), createInstance()
            // pode falhar com "ja existe", sem problema, o poll() abaixo funciona do mesmo jeito.
        }

        Router::redirect('/painel/whatsapp');
    }

    /** JSON pro JS da tela: estado atual da conexao e, se ainda nao conectado, um QR Code novo --
     *  mesmo formato de resposta que WhatsAppSettingsController::status() (admin), pra reusar o
     *  mesmo JS de polling. */
    public function status(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();

        header('Content-Type: application/json');

        // Assinatura pode ter vencido depois que a instancia ja estava conectada -- corta o poll
        // aqui tambem (nao so no connect()), sem quebrar o contrato JSON que o JS espera.
        if (!SubscriptionGate::hasAccess($user)) {
            echo json_encode(['state' => 'bloqueado']);
            return;
        }

        $instance = WhatsAppInstance::forUser((int) $user['id']);
        if (!$instance) {
            echo json_encode(['state' => 'close']);
            return;
        }

        try {
            $client = new EvolutionApiClient($instance['instance_name']);
            $state = $client->connectionState()['instance']['state'] ?? 'close';

            $payload = ['state' => $state];

            if ($state === 'open') {
                WhatsAppInstance::updateStatus((int) $instance['id'], 'open');
                // Sincroniza na hora que conecta (best-effort, nao trava a resposta se falhar --
                // o usuario ainda pode clicar "Sincronizar" manualmente na caixa de entrada).
                if (empty($instance['connected_at'])) {
                    try {
                        WhatsAppSync::pullChats((int) $instance['id'], $instance['instance_name']);
                        WhatsAppInstance::touchSync((int) $instance['id']);
                    } catch (\Throwable $e) {
                        // Best-effort.
                    }
                }
            } else {
                WhatsAppInstance::updateStatus((int) $instance['id'], $state);
                $qr = $client->qrCode();
                $payload['qrcode_base64'] = $qr['base64'] ?? null;
            }

            echo json_encode($payload);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['state' => 'erro', 'message' => $e->getMessage()]);
        }
    }

    public function disconnect(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/whatsapp?erro=1');
        }

        $instance = WhatsAppInstance::forUser((int) $user['id']);
        if ($instance) {
            try {
                (new EvolutionApiClient($instance['instance_name']))->disconnectAndDelete();
            } catch (\Throwable $e) {
                // Best-effort.
            }
            WhatsAppInstance::delete((int) $instance['id']);
        }

        Router::redirect('/painel/whatsapp?desconectado=1');
    }
}
