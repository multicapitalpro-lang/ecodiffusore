<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EvolutionApiClient;
use App\Core\FileUpload;
use App\Core\Response;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Core\WhatsAppSync;
use App\Models\Lead;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTag;

/** Caixa de entrada do WhatsApp pessoal (Fase 33) -- lista de conversas + thread + envio, tudo
 *  escopado a UM instancia (a do proprio usuario logado, nunca a de outra pessoa). */
class WhatsAppInboxController
{
    private const ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function index(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);

        View::render('painel/whatsapp/inbox', [
            'user' => $user,
            'instance' => $instance,
            'chats' => WhatsAppChat::forInstance((int) $instance['id']),
            'tags' => WhatsAppTag::forUser((int) $user['id']),
            'myLeads' => Lead::forUser((int) $user['id']),
            'activeChat' => null,
            'messages' => [],
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        // Lazy: so puxa o historico de mensagens da Evolution na primeira vez que a conversa e'
        // aberta (a lista de chats so trouxe a ULTIMA mensagem de cada, ver WhatsAppSync::pullChats).
        // Tambem puxa de novo (uma unica vez, self-healing) se houver midia importada antes da
        // Fase 34 sem o key gravado -- ver WhatsAppMessage::hasMediaMissingKey()/create().
        if (!WhatsAppMessage::forChat((int) $chat['id'], 1) || WhatsAppMessage::hasIncompleteMedia((int) $chat['id'])) {
            try {
                WhatsAppSync::pullMessagesForChat((int) $chat['id'], $instance['instance_name'], $chat['remote_jid']);
            } catch (\Throwable $e) {
                // Best-effort -- conversa abre vazia/sem midia antiga recuperada, usuario pode tentar de novo depois.
            }
        }

        WhatsAppChat::markRead((int) $chat['id']);

        if (Response::isAjax()) {
            View::render('painel/whatsapp/_thread', [
                'user' => $user,
                'activeChat' => WhatsAppChat::find((int) $chat['id']),
                'messages' => WhatsAppMessage::forChat((int) $chat['id']),
                'tags' => WhatsAppTag::forUser((int) $user['id']),
                'myLeads' => Lead::forUser((int) $user['id']),
            ], null);
            return;
        }

        View::render('painel/whatsapp/inbox', [
            'user' => $user,
            'instance' => $instance,
            'chats' => WhatsAppChat::forInstance((int) $instance['id']),
            'tags' => WhatsAppTag::forUser((int) $user['id']),
            'myLeads' => Lead::forUser((int) $user['id']),
            'activeChat' => WhatsAppChat::find((int) $chat['id']),
            'messages' => WhatsAppMessage::forChat((int) $chat['id']),
        ]);
    }

    /** JSON pro poll periodico do front (mensagens novas desde a ultima vez) -- reaproveita
     *  o mesmo pullMessagesForChat (idempotente) em vez de um endpoint separado so-leitura. */
    public function poll(string $id): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();

        header('Content-Type: application/json');
        if (!SubscriptionGate::hasAccess($user)) {
            echo json_encode(['messages' => []]);
            return;
        }

        $instance = WhatsAppInstance::forUser((int) $user['id']);
        if (!$instance) {
            echo json_encode(['messages' => []]);
            return;
        }

        $chat = WhatsAppChat::find((int) $id);
        if (!$chat || (int) $chat['instance_id'] !== (int) $instance['id']) {
            http_response_code(403);
            echo json_encode(['messages' => []]);
            return;
        }

        try {
            WhatsAppSync::pullMessagesForChat((int) $chat['id'], $instance['instance_name'], $chat['remote_jid']);
        } catch (\Throwable $e) {
            // Best-effort -- devolve o que ja tem localmente mesmo se a Evolution falhar agora.
        }

        echo json_encode(['messages' => WhatsAppMessage::forChat((int) $chat['id'])]);
    }

    /** Serve o arquivo de uma mensagem de midia -- usado como src/href direto no <img>/<video>/
     *  <audio>/link de download da thread (ver _thread.php). Baixa da Evolution na primeira vez
     *  (POST /chat/getBase64FromMediaMessage, ver EvolutionApiClient::fetchMediaBase64) e cacheia em
     *  storage/uploads/whatsapp/ -- toda vez depois disso serve local, sem chamar a Evolution de
     *  novo. Mesmo padrao de streaming autenticado ja usado em FinanceController::downloadAttachment
     *  (nunca serve arquivo estatico direto, sempre passa por aqui pra checar dono da conversa). */
    public function media(string $id, string $messageId): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        $message = WhatsAppMessage::find((int) $messageId);
        if (!$message || (int) $message['chat_id'] !== (int) $chat['id']) {
            http_response_code(404);
            exit;
        }

        if (empty($message['media_path'])) {
            if (empty($message['wa_key_json'])) {
                http_response_code(404);
                exit;
            }

            try {
                $client = new EvolutionApiClient($instance['instance_name']);
                $key = json_decode((string) $message['wa_key_json'], true) ?: [];
                $result = $client->fetchMediaBase64($key);
                $binary = base64_decode((string) ($result['base64'] ?? ''), true);
                if ($binary === false || $binary === '') {
                    throw new \RuntimeException('Mídia vazia devolvida pela Evolution API.');
                }
                $mimetype = $result['mimetype'] ?? $message['media_mimetype'] ?? 'application/octet-stream';
                $stored = FileUpload::storeWhatsAppMedia($binary, $mimetype);
                WhatsAppMessage::setMediaPath((int) $message['id'], $stored['stored_name'], $mimetype);
                $message['media_path'] = $stored['stored_name'];
                $message['media_mimetype'] = $mimetype;
            } catch (\Throwable $e) {
                http_response_code(502);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Não foi possível carregar essa mídia agora. Tente novamente em instantes.';
                exit;
            }
        }

        $path = FileUpload::path('whatsapp', $message['media_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . ($message['media_mimetype'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=604800');
        if ($message['message_type'] === 'documentMessage') {
            $filename = $message['media_filename'] ?: 'documento';
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        }
        readfile($path);
        exit;
    }

    public function send(string $id): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/whatsapp/conversas/{$id}?erro=1");
        }

        $text = trim($_POST['text'] ?? '');
        if ($text === '') {
            Router::redirect("/painel/whatsapp/conversas/{$id}");
        }

        try {
            $client = new EvolutionApiClient($instance['instance_name']);
            $result = $client->sendText($chat['remote_jid'], $text);
            $waId = $result['key']['id'] ?? null;

            WhatsAppMessage::create((int) $chat['id'], $waId, 'out', $user['name'], $text, 'text', date('Y-m-d H:i:s'));
            WhatsAppChat::touchLastMessage((int) $chat['id'], $text, date('Y-m-d H:i:s'), false);

            if (Response::isAjax()) {
                Response::json(['ok' => true]);
            }
        } catch (\Throwable $e) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Falha ao enviar. Confira se o WhatsApp continua conectado.']);
            }
        }

        Router::redirect("/painel/whatsapp/conversas/{$id}");
    }

    public function sync(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/whatsapp/conversas?erro=1');
        }

        try {
            WhatsAppSync::pullChats((int) $instance['id'], $instance['instance_name']);
            WhatsAppInstance::touchSync((int) $instance['id']);
            Router::redirect('/painel/whatsapp/conversas?sucesso=1');
        } catch (\Throwable $e) {
            Router::redirect('/painel/whatsapp/conversas?erro=sync');
        }
    }

    public function linkLead(string $id): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/whatsapp/conversas/{$id}?erro=1");
        }

        $leadId = !empty($_POST['lead_id']) ? (int) $_POST['lead_id'] : null;
        if ($leadId) {
            $lead = Lead::find($leadId);
            if (!$lead || (int) ($lead['assigned_to_user_id'] ?? 0) !== (int) $user['id']) {
                Router::redirect("/painel/whatsapp/conversas/{$id}?erro=1");
            }
        }

        WhatsAppChat::linkLead((int) $chat['id'], $leadId);
        Router::redirect("/painel/whatsapp/conversas/{$id}");
    }

    public function storeTag(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/whatsapp/conversas?erro=1');
        }

        $name = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#8dc63f';
        if ($name !== '') {
            WhatsAppTag::create((int) $user['id'], $name, $color);
        }

        $back = $_POST['back'] ?? '/painel/whatsapp/conversas';
        Router::redirect($back);
    }

    public function assignTag(string $id): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/whatsapp/conversas/{$id}?erro=1");
        }

        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $tags = WhatsAppTag::forUser((int) $user['id']);
        if ($tagId && in_array($tagId, array_column($tags, 'id'), true)) {
            WhatsAppTag::assignToChat((int) $chat['id'], $tagId);
        }

        Router::redirect("/painel/whatsapp/conversas/{$id}");
    }

    public function removeTag(string $id, string $tagId): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user);

        $instance = $this->requireConnectedInstance($user);
        $chat = $this->authorizeChat((int) $id, (int) $instance['id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/whatsapp/conversas/{$id}?erro=1");
        }

        WhatsAppTag::removeFromChat((int) $chat['id'], (int) $tagId);
        Router::redirect("/painel/whatsapp/conversas/{$id}");
    }

    private function requireConnectedInstance(array $user): array
    {
        $instance = WhatsAppInstance::forUser((int) $user['id']);
        if (!$instance || $instance['status'] !== 'open') {
            Router::redirect('/painel/whatsapp');
        }
        return $instance;
    }

    private function authorizeChat(int $chatId, int $instanceId): array
    {
        $chat = WhatsAppChat::find($chatId);
        if (!$chat || (int) $chat['instance_id'] !== $instanceId) {
            Router::redirect('/painel/whatsapp/conversas');
        }
        return $chat;
    }
}
