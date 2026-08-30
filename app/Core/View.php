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
}
