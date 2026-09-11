<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ImapClient;
use App\Core\Mailer;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;

/** Caixa de entrada de atendimento@ecodiffusorebrasil.com.br direto no painel (Fase 49) -- so'
 *  leitura via IMAP (App\Core\ImapClient) + resposta via SMTP (App\Core\Mailer::sendReply()),
 *  sem sincronizar nada em banco -- o IMAP em si e' a fonte de verdade, mesmo espirito de nao
 *  duplicar dado que ja existe em outro sistema (ex: NF-e/Asaas). Gate Roles::SUPERVISOR_ASSIGNMENT
 *  (Admin + Gerente) -- decisao do usuario, mesmo grupo que ja acessa Aprovacao de Cadastros. */
class EmailInboxController
{
    public function index(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $page = max(1, (int) ($_GET['page'] ?? 1));

        try {
            $result = (new ImapClient())->listMessages($page, 25);
            $error = null;
        } catch (\Throwable $e) {
            $result = ['messages' => [], 'total' => 0];
            $error = $e->getMessage();
        }

        View::render('painel/email/index', [
            'user' => Auth::user(),
            'messages' => $result['messages'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 25,
            'error' => $error,
        ]);
    }

    public function show(string $uid): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        try {
            $message = (new ImapClient())->getMessage((int) $uid);
        } catch (\Throwable $e) {
            Router::redirect('/painel/email?erro=1');
        }

        View::render('painel/email/show', [
            'user' => Auth::user(),
            'message' => $message,
            'sucesso' => isset($_GET['sucesso']),
        ]);
    }

    public function reply(string $uid): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/email/{$uid}?erro=1");
        }

        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            Router::redirect("/painel/email/{$uid}?erro=1");
        }

        try {
            $original = (new ImapClient())->getMessage((int) $uid);
        } catch (\Throwable $e) {
            Router::redirect('/painel/email?erro=1');
        }

        $subject = str_starts_with(strtolower($original['subject']), 're:')
            ? $original['subject']
            : 'Re: ' . $original['subject'];

        $ok = Mailer::sendReply($original['from_email'], $subject, $body, $original['message_id']);

        Router::redirect("/painel/email/{$uid}?" . ($ok ? 'sucesso=1' : 'erro=2'));
    }

    public function downloadAttachment(string $uid, string $partNum): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        try {
            $att = (new ImapClient())->downloadAttachment((int) $uid, $partNum);
        } catch (\Throwable $e) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        header('Content-Type: ' . ($att['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($att['filename']) . '"');
        header('Content-Length: ' . strlen($att['content']));
        echo $att['content'];
        exit;
    }
}
