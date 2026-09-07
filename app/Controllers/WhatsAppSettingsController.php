<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EvolutionApiClient;
use App\Core\Router;
use App\Core\View;

/** Tela pro admin conectar/desconectar o aparelho do Evolution API direto pelo painel, sem
 *  precisar escanear QR Code por fora. */
class WhatsAppSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/whatsapp', [
            'user' => Auth::user(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** JSON pro JS da tela: estado atual da conexao e, se ainda nao conectado, um QR Code novo. */
    public function status(): void
    {
        Auth::requireRole(['admin']);

        header('Content-Type: application/json');

        try {
            $client = new EvolutionApiClient();
            $state = $client->connectionState()['instance']['state'] ?? 'close';

            $payload = ['state' => $state];

            if ($state !== 'open') {
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
