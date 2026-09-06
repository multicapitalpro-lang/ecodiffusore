<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Router;
use App\Core\View;
use App\Models\EmailEventTemplate;
use App\Models\EmailTemplateSettings;

/**
 * Tela pro admin ajustar os e-mails automaticos (App\Core\Notifier) sem precisar de deploy:
 * - identidade visual (logo, cores, rodape) -- update(), 1 formulario so.
 * - assunto/titulo/texto de introducao/rotulo do botao de CADA evento (lead roteado, orcamento
 *   registrado, pedido registrado, pedido aprovado, cadastro aprovado) -- updateEvent(), 1
 *   formulario por evento. A tabela de dados (Cliente/Valor etc) e a URL do botao continuam fixas
 *   no codigo -- e' estrutura, nao texto, editar isso por aqui seria facil de quebrar sem querer.
 */
class EmailTemplateSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        $previewKey = $_GET['preview'] ?? 'pedido_registrado';
        if (!in_array($previewKey, EmailEventTemplate::KEYS, true)) {
            $previewKey = 'pedido_registrado';
        }

        View::render('painel/settings/email_template', [
            'user' => Auth::user(),
            'settings' => EmailTemplateSettings::current(),
            'eventTemplates' => EmailEventTemplate::all(),
            'previewKey' => $previewKey,
            'preview' => Notifier::previewHtml($previewKey),
            'errors' => [],
            'eventErrors' => [],
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/email?erro=1');
        }

        $values = [
            'logo_url' => trim($_POST['logo_url'] ?? ''),
            'header_bg' => trim($_POST['header_bg'] ?? ''),
            'accent_color' => trim($_POST['accent_color'] ?? ''),
            'footer_text' => trim($_POST['footer_text'] ?? ''),
        ];

        $errors = [];
        if (!preg_match('/^https?:\/\/.+/', $values['logo_url'])) {
            $errors['logo_url'] = 'Informe uma URL completa (começando com http:// ou https://).';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $values['header_bg'])) {
            $errors['header_bg'] = 'Cor inválida — use o formato #RRGGBB.';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $values['accent_color'])) {
            $errors['accent_color'] = 'Cor inválida — use o formato #RRGGBB.';
        }
        if ($values['footer_text'] === '') {
            $errors['footer_text'] = 'Preencha o texto do rodapé.';
        } elseif (mb_strlen($values['footer_text']) > 500) {
            $errors['footer_text'] = 'Texto muito longo (máximo 500 caracteres).';
        }

        if ($errors) {
            View::render('painel/settings/email_template', [
                'user' => Auth::user(),
                'settings' => array_merge(EmailTemplateSettings::current(), $values),
                'eventTemplates' => EmailEventTemplate::all(),
                'previewKey' => 'pedido_registrado',
                'preview' => Notifier::previewHtml(),
                'errors' => $errors,
                'eventErrors' => [],
            ]);
            return;
        }

        EmailTemplateSettings::update($values);

        Router::redirect('/painel/configuracoes/email?sucesso=1');
    }

    public function updateEvent(string $eventKey): void
    {
        Auth::requireRole(['admin']);

        if (!in_array($eventKey, EmailEventTemplate::KEYS, true)) {
            Router::redirect('/painel/configuracoes/email?erro=1');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/email?erro=1');
        }

        $values = [
            'subject' => trim($_POST['subject'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'intro_text' => trim($_POST['intro_text'] ?? ''),
            'button_label' => trim($_POST['button_label'] ?? ''),
        ];

        $errors = [];
        if ($values['subject'] === '' || mb_strlen($values['subject']) > 150) {
            $errors['subject'] = 'Preencha o assunto (máximo 150 caracteres).';
        }
        if ($values['title'] === '' || mb_strlen($values['title']) > 150) {
            $errors['title'] = 'Preencha o título (máximo 150 caracteres).';
        }
        if ($values['intro_text'] === '' || mb_strlen($values['intro_text']) > 500) {
            $errors['intro_text'] = 'Preencha o texto de introdução (máximo 500 caracteres).';
        }
        if ($values['button_label'] === '' || mb_strlen($values['button_label']) > 80) {
            $errors['button_label'] = 'Preencha o rótulo do botão (máximo 80 caracteres).';
        }

        if ($errors) {
            $eventTemplates = EmailEventTemplate::all();
            $eventTemplates[$eventKey] = array_merge($eventTemplates[$eventKey], $values);

            View::render('painel/settings/email_template', [
                'user' => Auth::user(),
                'settings' => EmailTemplateSettings::current(),
                'eventTemplates' => $eventTemplates,
                'previewKey' => $eventKey,
                'preview' => Notifier::previewHtml($eventKey),
                'errors' => [],
                'eventErrors' => [$eventKey => $errors],
            ]);
            return;
        }

        EmailEventTemplate::update($eventKey, $values);

        Router::redirect('/painel/configuracoes/email?sucesso=1&preview=' . urlencode($eventKey));
    }
}
