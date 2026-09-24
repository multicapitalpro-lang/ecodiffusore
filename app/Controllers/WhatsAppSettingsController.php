<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\EvolutionApiClient;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\WhatsAppEventTemplate;
use App\Models\WhatsAppInstance;

/** Tela de WhatsApp: conexao do aparelho (so Admin, e' infra sensivel) + textos das mensagens
 *  automaticas (Admin e Gerente, ver Roles::SUPERVISOR_ASSIGNMENT). */
class WhatsAppSettingsController
{
    public function index(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        View::render('painel/settings/whatsapp', [
            'user' => Auth::user(),
            'csrfToken' => Csrf::token(),
            'templates' => WhatsAppEventTemplate::all(),
            'errors' => [],
        ]);
    }

    public function updateTemplate(string $eventKey): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        if (!in_array($eventKey, WhatsAppEventTemplate::KEYS, true)) {
            Router::redirect('/painel/configuracoes/whatsapp?erro=1');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/whatsapp?erro=1');
        }

        $textSelf = in_array($eventKey, WhatsAppEventTemplate::NETWORK_ONLY, true) ? null : trim($_POST['text_self'] ?? '');
        $textNetwork = in_array($eventKey, WhatsAppEventTemplate::SELF_ONLY, true) ? null : trim($_POST['text_network'] ?? '');

        $errors = [];
        if ($textSelf !== null && $textSelf === '') {
            $errors['text_self'] = 'Preencha o texto.';
        }
        if ($textNetwork !== null && $textNetwork === '') {
            $errors['text_network'] = 'Preencha o texto.';
        }

        if ($errors) {
            $templates = WhatsAppEventTemplate::all();
            $templates[$eventKey] = array_merge($templates[$eventKey] ?? [], ['text_self' => $textSelf, 'text_network' => $textNetwork]);

            View::render('painel/settings/whatsapp', [
                'user' => Auth::user(),
                'csrfToken' => Csrf::token(),
                'templates' => $templates,
                'errors' => [$eventKey => $errors],
            ]);
            return;
        }

        WhatsAppEventTemplate::update($eventKey, $textSelf, $textNetwork);

        Router::redirect('/painel/configuracoes/whatsapp?sucesso=1');
    }

    /** JSON pro JS da tela: estado atual da conexao e, se ainda nao conectado, um QR Code novo.
     *  Fase 106: essa e' a MESMA instancia que agora tambem roda o menu automatico (numero
     *  principal do site) -- aproveita o poll aqui (ja existia desde antes) pra manter a linha
     *  central de whatsapp_instances sincronizada e garantir que o webhook esta registrado, sem
     *  precisar de nenhuma tela nova de conexao. */
    public function status(): void
    {
        Auth::requireRole(['admin']);

        header('Content-Type: application/json');

        try {
            $client = new EvolutionApiClient();
            $state = $client->connectionState()['instance']['state'] ?? 'close';

            $payload = ['state' => $state];
            $central = WhatsAppInstance::central();

            if ($state === 'open') {
                if ($central) {
                    WhatsAppInstance::updateStatus((int) $central['id'], 'open');
                }
                // Best-effort, idempotente -- so' precisa disso rodar uma vez de verdade, mas
                // chamar de novo a cada poll nao tem custo real (mesmo padrao ja usado em
                // WhatsAppInstanceController::connect() pras instancias pessoais).
                try {
                    $webhookUrl = rtrim(Config::get('app_url'), '/') . '/webhooks/evolution';
                    $client->setWebhook($webhookUrl);
                } catch (\Throwable $e) {
                    // Best-effort.
                }
            } else {
                if ($central) {
                    WhatsAppInstance::updateStatus((int) $central['id'], $state);
                }
                $qr = $client->qrCode();
                $payload['qrcode_base64'] = $qr['base64'] ?? null;
            }

            $payload['bot_enabled'] = $central ? (bool) $central['bot_enabled'] : true;

            echo json_encode($payload);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['state' => 'erro', 'message' => $e->getMessage()]);
        }
    }

    /** Liga/desliga o menu automatico sem desconectar o aparelho -- pra quando o time quiser
     *  responder manualmente por um tempo sem o bot interferindo. */
    public function toggleBot(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/whatsapp?erro=1');
        }

        $central = WhatsAppInstance::central();
        if ($central) {
            WhatsAppInstance::setBotEnabled((int) $central['id'], empty($_POST['enabled']) ? false : true);
        }

        Router::redirect('/painel/configuracoes/whatsapp?sucesso=1');
    }

    public function disconnect(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/whatsapp?erro=1');
        }

        try {
            (new EvolutionApiClient())->logout();
        } catch (\Throwable $e) {
            // segue pro redirect mesmo se falhar -- a tela vai mostrar o estado real na proxima checagem
        }

        Router::redirect('/painel/configuracoes/whatsapp?desconectado=1');
    }
}
