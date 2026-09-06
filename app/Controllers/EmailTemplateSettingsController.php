<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Router;
use App\Core\View;
use App\Models\EmailTemplateSettings;

/**
 * Tela pro admin ajustar a identidade visual dos e-mails automaticos (App\Core\Notifier) sem
 * precisar de deploy -- logo, cor do cabecalho, cor de destaque dos botoes, texto do rodape.
 * O CONTEUDO de cada evento (titulo/corpo especifico de lead/pedido/etc.) continua fixo no codigo
 * -- e' logica de negocio, nao visual, e editar isso por aqui seria facil de quebrar sem querer.
 */
class EmailTemplateSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/email_template', [
            'user' => Auth::user(),
            'settings' => EmailTemplateSettings::current(),
            'preview' => Notifier::previewHtml(),
            'errors' => [],
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
                'preview' => Notifier::previewHtml(),
                'errors' => $errors,
            ]);
            return;
        }

        EmailTemplateSettings::update($values);

        Router::redirect('/painel/configuracoes/email?sucesso=1');
    }
}
