<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configuracao unica (1 linha so) da identidade visual dos e-mails automaticos (Notifier) --
 * logo, cor do cabecalho, cor de destaque dos botoes e texto do rodape. Pedido explicito do
 * usuario: ele precisa conseguir mexer nisso sem depender de deploy de codigo.
 */
class EmailTemplateSettings
{
    private const DEFAULTS = [
        'id' => 1,
        'logo_url' => 'https://ecodiffusorebrasil.com.br/assets/img/logo-full-white.png',
        'header_bg' => '#0e0e0e',
        'accent_color' => '#6ea62c',
        'footer_text' => 'Ecodiffusore Brasil · e-mail automático do painel, não é necessário responder.',
    ];

    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM email_template_settings WHERE id = 1')->fetch();
        return $row ? array_merge(self::DEFAULTS, $row) : self::DEFAULTS;
    }

    public static function update(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE email_template_settings SET logo_url = :logo_url, header_bg = :header_bg, accent_color = :accent_color, footer_text = :footer_text WHERE id = 1'
        );
        $stmt->execute([
            'logo_url' => $data['logo_url'],
            'header_bg' => $data['header_bg'],
            'accent_color' => $data['accent_color'],
            'footer_text' => $data['footer_text'],
        ]);
    }
}
