<?php

namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'painel'): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$template}");
        }

        if ($layout === null) {
            require $viewFile;
            return;
        }

        $content = function () use ($viewFile, $data) {
            extract($data, EXTR_SKIP);
            require $viewFile;
        };

        require BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $file = BASE_PATH . '/public_html' . $path;
        $version = file_exists($file) ? filemtime($file) : time();
        return $path . '?v=' . $version;
    }

    public static function passwordToggle(string $targetId): string
    {
        return '<button type="button" class="password-toggle" data-target="' . self::e($targetId) . '" aria-label="Mostrar senha">'
            . '<svg class="icon-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 10S5 4 10 4s8.5 6 8.5 6-3.5 6-8.5 6S1.5 10 1.5 10Z"/><circle cx="10" cy="10" r="2.5"/></svg>'
            . '<svg class="icon-eye-off" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 10S5 4 10 4c1.2 0 2.3.2 3.3.6M18.5 10S15 16 10 16c-1.2 0-2.3-.2-3.3-.6"/><path d="M7.5 7.8a2.5 2.5 0 0 0 3.5 3.6"/><path d="M3 3l14 14"/></svg>'
            . '</button>';
    }
}
